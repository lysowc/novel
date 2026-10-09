#!/usr/bin/env bash
# AI 小说系统 - 全链路验收脚本
# 用法: bash test/acceptance.sh [BASE_URL]
set -u

BASE="${1:-http://127.0.0.1:8787}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin123}"
COOKIE=$(mktemp)
PASS=0; FAIL=0

step() { echo; echo "━━━ $1 ━━━"; }
ok()   { PASS=$((PASS+1)); echo "  ✅ $1"; }
bad()  { FAIL=$((FAIL+1)); echo "  ❌ $1"; }

check() { # check <描述> <实际> <期望>
  if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (期望 $3, 实际 $2)"; fi
}

step "0. 服务健康检查"
curl -s -o /dev/null -w "%{http_code}" "$BASE/api/home" | grep -q 200 && ok "后端在线" || { bad "后端不可达"; echo "结果：失败"; exit 1; }

step "1. 登录"
R=$(curl -s -c "$COOKIE" -X POST "$BASE/api/admin/login" -H 'Content-Type: application/json' -d "{\"username\":\"$ADMIN_USER\",\"password\":\"$ADMIN_PASS\"}")
echo "$R" | grep -q '"code":0' && ok "登录成功" || { bad "登录失败: $R"; exit 1; }
curl -s -b "$COOKIE" "$BASE/api/admin/me" | grep -q '"code":0' && ok "me 接口" || bad "me 接口"

step "2. 前台 API"
curl -s "$BASE/api/home" | grep -q '"code":0' && ok "首页" || bad "首页"
curl -s "$BASE/api/categories" | grep -q '"code":0' && ok "分类" || bad "分类"
curl -s "$BASE/api/novels?page=1" | grep -q '"code":0' && ok "小说列表" || bad "小说列表"

step "3. 后台 CRUD"
# 创建分类
R=$(curl -s -b "$COOKIE" -X POST "$BASE/api/admin/categories" -H 'Content-Type: application/json' -d '{"name":"验收测试分类","sort":99}')
CAT_ID=$(echo "$R" | python3 -c "import json,sys; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)
[ -n "$CAT_ID" ] && ok "创建分类 #$CAT_ID" || bad "创建分类"
# 创建小说
R=$(curl -s -b "$COOKIE" -X POST "$BASE/api/admin/novels" -H 'Content-Type: application/json' -d "{\"title\":\"验收测试小说\",\"category_id\":$CAT_ID,\"description\":\"验收用\"}")
NOVEL_ID=$(echo "$R" | python3 -c "import json,sys; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)
[ -n "$NOVEL_ID" ] && ok "创建小说 #$NOVEL_ID" || bad "创建小说"

step "4. 无鉴权拦截"
CODE=$(curl -s "$BASE/api/admin/dashboard" | python3 -c "import json,sys; print(json.load(sys.stdin).get('code'))" 2>/dev/null)
check "未登录访问后台返回 401" "$CODE" "401"

# AI 链路（需要已配置 Provider/Model，否则跳过）
HAS_AI=$(curl -s -b "$COOKIE" "$BASE/api/admin/ai/providers" | python3 -c "
import json,sys
try:
    d=json.load(sys.stdin)['data']; print(1 if d and any(p.get('status') for p in d) else 0)
except: print(0)" 2>/dev/null)

if [ "$HAS_AI" = "1" ] && [ -n "$NOVEL_ID" ]; then
  step "5. AI 任务链路"
  # 生成设定
  R=$(curl -s -b "$COOKIE" -X POST "$BASE/api/admin/ai/tasks" -H 'Content-Type: application/json' -d "{\"task_type\":\"generate_setting\",\"novel_id\":$NOVEL_ID}")
  TASK_ID=$(echo "$R" | python3 -c "import json,sys; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)
  [ -n "$TASK_ID" ] && ok "创建生成设定任务 #$TASK_ID" || bad "创建任务"
  for i in $(seq 1 30); do
    S=$(curl -s -b "$COOKIE" "$BASE/api/admin/ai/tasks/$TASK_ID" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['status'])" 2>/dev/null)
    [ "$S" = "success" ] || [ "$S" = "failed" ] && break
    sleep 2
  done
  check "设定任务完成" "$S" "success"

  # 互斥验证（确定性）：插入一条 running 任务，再创建应被拒
  MYSQL_CMD="mysql -h127.0.0.1 -uroot -proot ai_novel -N -e"
  $MYSQL_CMD "INSERT INTO ai_task (task_type, ref_id, ref_type, params, status, error_message, created_at, updated_at) VALUES ('generate_outline', $NOVEL_ID, 'novel', '{}', 'running', '__mutex_test__', NOW(), NOW())" 2>/dev/null
  R2=$(curl -s -b "$COOKIE" -X POST "$BASE/api/admin/ai/tasks" -H 'Content-Type: application/json' -d "{\"task_type\":\"generate_outline\",\"novel_id\":$NOVEL_ID}")
  echo "$R2" | grep -q '进行中' && ok "任务互斥拒绝" || bad "任务互斥逻辑"
  $MYSQL_CMD "DELETE FROM ai_task WHERE ref_id=$NOVEL_ID AND error_message='__mutex_test__'" 2>/dev/null

  # 生成大纲
  R=$(curl -s -b "$COOKIE" -X POST "$BASE/api/admin/ai/tasks" -H 'Content-Type: application/json' -d "{\"task_type\":\"generate_outline\",\"novel_id\":$NOVEL_ID}")
  TASK_ID=$(echo "$R" | python3 -c "import json,sys; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)
  for i in $(seq 1 60); do
    S=$(curl -s -b "$COOKIE" "$BASE/api/admin/ai/tasks/$TASK_ID" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['status'])" 2>/dev/null)
    [ "$S" = "success" ] || [ "$S" = "failed" ] && break
    sleep 2
  done
  check "大纲任务完成" "$S" "success"

  # 生成第 1 章（SSE 订阅 + 流式）
  R=$(curl -s -b "$COOKIE" -X POST "$BASE/api/admin/ai/tasks" -H 'Content-Type: application/json' -d "{\"task_type\":\"generate_chapter\",\"novel_id\":$NOVEL_ID,\"params\":{\"chapter_no\":1}}")
  TASK_ID=$(echo "$R" | python3 -c "import json,sys; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)
  SSE_FILE=$(mktemp)
  curl -sN --max-time 120 -b "$COOKIE" "$BASE/api/admin/ai/tasks/$TASK_ID/stream" > "$SSE_FILE" &
  SSE_PID=$!
  wait $SSE_PID
  CHUNKS=$(grep -c '"type":"chunk"' "$SSE_FILE")
  DONE=$(grep -c '"type":"done"' "$SSE_FILE")
  [ "$CHUNKS" -gt 0 ] && ok "章节流式输出（$CHUNKS 个 chunk）" || bad "无流式输出"
  [ "$DONE" -ge 1 ] && ok "收到 done 事件" || bad "缺少 done 事件"
  grep -q '"type":"status"' "$SSE_FILE" && ok "收到阶段状态事件" || bad "缺少状态事件"

  # 验证章节/摘要/记忆
  CH=$(curl -s -b "$COOKIE" "$BASE/api/admin/novels/$NOVEL_ID/chapters" | python3 -c "
import json,sys
d=json.load(sys.stdin)['data']
print(len(d), (d[0].get('summary') or '') != '' if d else '')" 2>/dev/null)
  MEM=$(curl -s -b "$COOKIE" "$BASE/api/admin/novels/$NOVEL_ID/memory" | python3 -c "
import json,sys
try:
    d=json.loads(json.load(sys.stdin)['data']['content']); print('v2' if d.get('schema')=='v2' else 'json')
except: print('bad')" 2>/dev/null)
  check "章节含摘要" "$(echo $CH | cut -d' ' -f2)" "True"
  check "小说记忆为结构化 v2 JSON" "$MEM" "v2"

  # 一致性审校
  R=$(curl -s -b "$COOKIE" -X POST "$BASE/api/admin/novels/$NOVEL_ID/consistency")
  CTASK=$(echo "$R" | python3 -c "import json,sys; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)
  [ -n "$CTASK" ] && ok "创建审校任务 #$CTASK" || bad "创建审校任务"
  for i in $(seq 1 30); do
    S=$(curl -s -b "$COOKIE" "$BASE/api/admin/ai/tasks/$CTASK" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['status'])" 2>/dev/null)
    [ "$S" = "success" ] || [ "$S" = "failed" ] && break
    sleep 2
  done
  check "审校任务完成" "$S" "success"
  R=$(curl -s -b "$COOKIE" "$BASE/api/admin/novels/$NOVEL_ID/consistency")
  N_ISSUES=$(echo "$R" | python3 -c "
import json,sys
try:
    lst=json.load(sys.stdin)['data']
    print(len(lst[0].get('report',{}).get('issues',[])) if lst else -1)
except: print(-1)" 2>/dev/null)
  [ "$N_ISSUES" -gt 0 ] 2>/dev/null && ok "审校报告含 $N_ISSUES 个问题" || bad "审校报告读取"

  step "6. 前台阅读（发布后）"
  curl -s -b "$COOKIE" -X PUT "$BASE/api/admin/novels/$NOVEL_ID" -H 'Content-Type: application/json' -d '{"status":"published","is_public":1}' > /dev/null
  curl -s "$BASE/api/novels/$NOVEL_ID" | grep -q '"code":0' && ok "小说详情" || bad "小说详情"
  curl -s "$BASE/api/novels/$NOVEL_ID/chapters/1" | grep -q '"code":0' && ok "章节阅读" || bad "章节阅读"

  step "7. AI 日志"
  N=$(curl -s -b "$COOKIE" "$BASE/api/admin/ai/logs?page_size=1" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['total'])" 2>/dev/null)
  [ "$N" -gt 0 ] 2>/dev/null && ok "日志记录 $N 条" || bad "无日志记录"
else
  echo "  ⏭ 未配置 AI Provider，跳过 AI 链路（后台配置 Provider/Model 后重跑）"
fi

step "清理测试数据"
curl -s -b "$COOKIE" -X DELETE "$BASE/api/admin/novels/$NOVEL_ID" > /dev/null && ok "删除测试小说"
[ -n "${CAT_ID:-}" ] && curl -s -b "$COOKIE" -X DELETE "$BASE/api/admin/categories/$CAT_ID" > /dev/null && ok "删除测试分类"

echo
echo "════════════════════════════"
echo "通过 $PASS 项 / 失败 $FAIL 项"
[ "$FAIL" = "0" ] && echo "🎉 验收通过" || echo "⚠️ 存在失败项"
rm -f "$COOKIE"
exit $FAIL
