// 通用格式化工具

/** 数字格式化：12000 -> 1.2万 */
export function formatNumber(n: number | undefined | null): string {
  const v = Number(n ?? 0)
  if (v >= 100000000) return (v / 100000000).toFixed(1).replace(/\.0$/, '') + '亿'
  if (v >= 10000) return (v / 10000).toFixed(1).replace(/\.0$/, '') + '万'
  return String(v)
}

/** 日期格式化 */
export function formatDate(
  s: string | undefined | null,
  withTime = false,
): string {
  if (!s) return '—'
  const d = new Date(s)
  if (Number.isNaN(d.getTime())) return s
  const pad = (x: number) => String(x).padStart(2, '0')
  const date = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
  if (!withTime) return date
  return `${date} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** 相对时间：刚刚 / N分钟前 / N小时前 / N天前 / 日期 */
export function formatRelative(s: string | undefined | null): string {
  if (!s) return '—'
  const d = new Date(s)
  if (Number.isNaN(d.getTime())) return s
  const diff = Date.now() - d.getTime()
  const min = 60 * 1000
  const hour = 60 * min
  const day = 24 * hour
  if (diff < min) return '刚刚'
  if (diff < hour) return `${Math.floor(diff / min)} 分钟前`
  if (diff < day) return `${Math.floor(diff / hour)} 小时前`
  if (diff < 7 * day) return `${Math.floor(diff / day)} 天前`
  return formatDate(s)
}

/** 时长（毫秒）-> 可读 */
export function formatDuration(ms: number | undefined | null): string {
  const v = Number(ms ?? 0)
  if (v < 1000) return `${v}ms`
  if (v < 60 * 1000) return `${(v / 1000).toFixed(1)}s`
  return `${Math.floor(v / 60000)}m ${Math.floor((v % 60000) / 1000)}s`
}

/** 字符串哈希（用于封面渐变取色） */
export function hashCode(s: string): number {
  let h = 0
  for (let i = 0; i < s.length; i++) {
    h = (h << 5) - h + s.charCodeAt(i)
    h |= 0
  }
  return Math.abs(h)
}

/** 封面渐变色板（8 套黑白灰调，与整体单色设计一致） */
export const COVER_PALETTES: [string, string][] = [
  ['#09090b', '#3f3f46'],
  ['#18181b', '#71717a'],
  ['#27272a', '#a1a1aa'],
  ['#3f3f46', '#d4d4d8'],
  ['#0a0a0a', '#52525b'],
  ['#52525b', '#18181b'],
  ['#71717a', '#27272a'],
  ['#a1a1aa', '#3f3f46'],
]

/** 渐变角度（随哈希变化，增加区分度） */
const COVER_ANGLES = [135, 155, 120, 160, 145, 125, 140, 115]

/** 根据标题哈希取渐变色板 */
export function coverGradient(title: string): string {
  const h = Math.abs(hashCode(title || 'novel'))
  const [from, to] = COVER_PALETTES[h % COVER_PALETTES.length]
  const angle = COVER_ANGLES[h % COVER_ANGLES.length]
  return `linear-gradient(${angle}deg, ${from} 0%, ${to} 100%)`
}

/** 章节字数估算（中文按字符数） */
export function countWords(text: string): number {
  if (!text) return 0
  return text.replace(/\s/g, '').length
}

/** outline JSON 解析（容错） */
export function parseOutline(raw: string | null | undefined): { volumes: OutlineVolumeLike[] } {
  if (!raw) return { volumes: [] }
  try {
    const obj = JSON.parse(raw)
    if (obj && Array.isArray(obj.volumes)) return obj
    return { volumes: [] }
  } catch {
    return { volumes: [] }
  }
}

interface OutlineVolumeLike {
  title: string
  chapters: { no: number; title: string; summary: string }[]
}

/** 全角/半角统一（搜索用） */
export function normalizeText(s: string): string {
  return s
    .trim()
    .toLowerCase()
    .replace(/[Ａ-Ｚａ-ｚ０-９]/g, (c) =>
      String.fromCharCode(c.charCodeAt(0) - 0xfee0),
    )
}
