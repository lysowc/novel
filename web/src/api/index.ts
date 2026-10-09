// API 统一入口：每个函数按 USE_MOCK 切换 mock / 真实请求
// 真实请求路径与后端契约一一对应，联调时仅需关闭 mock
import type {
  AiLog, AiModel, AiProvider, AiTask, Category, Chapter, ChapterListItem,
  ChapterRead, ConsistencyReport, Dashboard, HomeData, Idea, IdeaMessage,
  LoginResult, Memory, Novel, NovelDetail, NovelSetting, PageResult, Prompt,
  SystemConfig, TaskType,
} from '@/types/api'
import { http, withQuery } from './http'
import { readStream, type StreamHandlers } from './sse'
import { mockApi, mockStreamText, USE_MOCK } from './mock'

export { USE_MOCK, MOCK_USERNAME, MOCK_PASSWORD } from './mock'

// ============ 前台 ============

export function fetchHome() {
  if (USE_MOCK) return mockApi.home()
  return http.get<HomeData>('/home')
}

export function fetchCategories() {
  if (USE_MOCK) return mockApi.categories()
  return http.get<{ list: { id: number; name: string; novel_count: number }[] }>('/categories')
}

export function fetchNovels(params: { category_id?: number | string; keyword?: string; page?: number; page_size?: number }) {
  if (USE_MOCK) return mockApi.novelList(params)
  return http.get<PageResult<Novel>>(withQuery('/novels', params))
}

export function fetchNovelDetail(id: number | string) {
  if (USE_MOCK) return mockApi.novelDetail(Number(id))
  return http.get<NovelDetail>(`/novels/${id}`)
}

export function fetchNovelChapters(id: number | string) {
  if (USE_MOCK) return mockApi.novelChapters(Number(id))
  return http.get<{ list: ChapterListItem[]; total: number }>(`/novels/${id}/chapters`)
}

export function fetchChapterRead(id: number | string, no: number | string) {
  if (USE_MOCK) return mockApi.chapterRead(Number(id), Number(no))
  return http.get<ChapterRead>(`/novels/${id}/chapters/${no}`)
}

// ============ 认证 ============

export function login(username: string, password: string) {
  if (USE_MOCK) return mockApi.login(username, password)
  return http.post<LoginResult>('/admin/login', { username, password })
}

export function logout() {
  if (USE_MOCK) return Promise.resolve()
  return http.post<void>('/admin/logout')
}

export function fetchMe() {
  if (USE_MOCK) return mockApi.me()
  return http.get<LoginResult>('/admin/me')
}

export function changePassword(old_password: string, new_password: string) {
  if (USE_MOCK) return Promise.resolve()
  return http.put<void>('/admin/password', { old_password, new_password })
}

// ============ 仪表盘 ============

export function fetchDashboard() {
  if (USE_MOCK) return mockApi.dashboard()
  return http.get<Dashboard>('/admin/dashboard')
}

// ============ 分类 ============

export function fetchAdminCategories() {
  if (USE_MOCK) return mockApi.categoryList()
  return http.get<PageResult<Category>>('/admin/categories')
}

export function createCategory(body: Partial<Category>) {
  if (USE_MOCK) return mockApi.createCategory(body)
  return http.post<Category>('/admin/categories', body)
}

export function updateCategory(id: number | string, body: Partial<Category>) {
  if (USE_MOCK) return mockApi.updateCategory(Number(id), body)
  return http.put<Category>(`/admin/categories/${id}`, body)
}

export function deleteCategory(id: number | string) {
  if (USE_MOCK) return mockApi.deleteCategory(Number(id))
  return http.del<void>(`/admin/categories/${id}`)
}

// ============ 小说 ============

export function fetchAdminNovels(params: { page?: number; page_size?: number; keyword?: string; category_id?: number | string; status?: string }) {
  if (USE_MOCK) return mockApi.adminNovelList(params)
  return http.get<PageResult<Novel>>(withQuery('/admin/novels', params))
}

export function createNovel(body: Partial<Novel>) {
  if (USE_MOCK) return mockApi.createNovel(body)
  return http.post<Novel>('/admin/novels', body)
}

export function fetchAdminNovel(id: number | string) {
  if (USE_MOCK) return mockApi.adminNovelDetail(Number(id))
  return http.get<NovelDetail>(`/admin/novels/${id}`)
}

export function updateNovel(id: number | string, body: Partial<Novel>) {
  if (USE_MOCK) return mockApi.updateNovel(Number(id), body)
  return http.put<Novel>(`/admin/novels/${id}`, body)
}

export function deleteNovel(id: number | string) {
  if (USE_MOCK) return mockApi.deleteNovel(Number(id))
  return http.del<void>(`/admin/novels/${id}`)
}

// ============ 章节 ============

export function fetchAdminChapters(id: number | string) {
  if (USE_MOCK) return mockApi.adminChapters(Number(id))
  return http.get<Chapter[]>(`/admin/novels/${id}/chapters`)
}

export function fetchChapter(id: number | string) {
  return http.get<Chapter>(`/admin/chapters/${id}`)
}

export function createChapter(id: number | string, body: { title: string; content: string; summary?: string }) {
  if (USE_MOCK) return mockApi.createChapter(Number(id), body)
  return http.post<Chapter>(`/admin/novels/${id}/chapters`, body)
}

export function updateChapter(id: number | string, body: Partial<Chapter>) {
  if (USE_MOCK) return mockApi.updateChapter(Number(id), body)
  return http.put<Chapter>(`/admin/chapters/${id}`, body)
}

export function deleteChapter(id: number | string) {
  if (USE_MOCK) return mockApi.deleteChapter(Number(id))
  return http.del<void>(`/admin/chapters/${id}`)
}

// ============ 设定 / 大纲 / 记忆 ============

export function fetchNovelSetting(id: number | string) {
  if (USE_MOCK) return mockApi.getSetting(Number(id))
  return http.get<NovelSetting>(`/admin/novels/${id}/setting`)
}

export function saveNovelSetting(id: number | string, body: Partial<NovelSetting>) {
  if (USE_MOCK) return mockApi.saveSetting(Number(id), body)
  return http.put<NovelSetting>(`/admin/novels/${id}/setting`, body)
}

export function fetchNovelOutline(id: number | string) {
  if (USE_MOCK) return mockApi.getOutline(Number(id))
  return http.get<{ outline: string }>(`/admin/novels/${id}/outline`)
}

export function saveNovelOutline(id: number | string, outline: string) {
  if (USE_MOCK) return mockApi.saveOutline(Number(id), outline)
  return http.put<void>(`/admin/novels/${id}/outline`, { outline })
}

export function fetchNovelMemory(id: number | string) {
  if (USE_MOCK) return mockApi.getMemory(Number(id))
  return http.get<Memory>(`/admin/novels/${id}/memory`)
}

export function saveNovelMemory(id: number | string, content: string) {
  if (USE_MOCK) return mockApi.saveMemory(Number(id), content)
  return http.put<Memory>(`/admin/novels/${id}/memory`, { content })
}

// ============ 一致性审校 ============

export function fetchConsistencyReports(id: number | string) {
  if (USE_MOCK) return mockApi.consistencyReports(Number(id))
  return http.get<ConsistencyReport[]>(`/admin/novels/${id}/consistency`)
}

export function runConsistencyCheck(id: number | string) {
  if (USE_MOCK) return mockApi.runConsistency(Number(id))
  return http.post<AiTask>(`/admin/novels/${id}/consistency`)
}

// ============ 点子 ============

export function fetchIdeas(params: { page?: number; page_size?: number; category_id?: number | string; status?: string }) {
  if (USE_MOCK) return mockApi.ideaList(params)
  return http.get<PageResult<Idea>>(withQuery('/admin/ideas', params))
}

export function createIdea(body: Partial<Idea>) {
  if (USE_MOCK) return mockApi.createIdea(body)
  return http.post<Idea>('/admin/ideas', body)
}

export function updateIdea(id: number | string, body: Partial<Idea>) {
  if (USE_MOCK) return mockApi.updateIdea(Number(id), body)
  return http.put<Idea>(`/admin/ideas/${id}`, body)
}

export function deleteIdea(id: number | string) {
  if (USE_MOCK) return mockApi.deleteIdea(Number(id))
  return http.del<void>(`/admin/ideas/${id}`)
}

export function fetchIdeaMessages(id: number | string) {
  if (USE_MOCK) return mockApi.ideaMessages(Number(id))
  return http.get<IdeaMessage[]>(`/admin/ideas/${id}/messages`)
}

export function saveIdea(id: number | string, body: { title?: string; content?: string }) {
  if (USE_MOCK) return mockApi.saveIdea(Number(id), body)
  return http.post<Idea>(`/admin/ideas/${id}/save`, body)
}

export function createNovelFromIdea(id: number | string) {
  if (USE_MOCK) return mockApi.createNovelFromIdea(Number(id))
  return http.post<{ novel_id: number }>(`/admin/ideas/${id}/create-novel`)
}

// 点子聊天（SSE 流式）
export async function streamIdeaChat(
  id: number | string,
  message: string,
  handlers: StreamHandlers,
  signal?: AbortSignal,
) {
  if (USE_MOCK) {
    await mockStreamText('好的，这个想法非常有意思！我们可以从这几个角度展开：\n\n1. 主角的独特之处：他并非传统意义上的强者，而是凭借智慧与观察力一步步接近真相。\n\n2. 世界的规则：这个世界的规则与我们的常识略有不同，这正是故事张力的来源。\n\n3. 冲突的核心：真正推动剧情的不只是事件本身，而是人物之间的信念碰撞。\n\n如果你想继续深入某个方向，随时告诉我！', handlers, signal)
    return
  }
  await readStream(`/api/admin/ideas/${id}/chat`, { method: 'POST', body: { message }, signal }, handlers)
}

// ============ AI 配置 ============

export function fetchProviders() {
  if (USE_MOCK) return mockApi.providerList()
  return http.get<AiProvider[]>('/admin/ai/providers')
}

export function createProvider(body: Partial<AiProvider>) {
  if (USE_MOCK) return mockApi.createProvider(body)
  return http.post<AiProvider>('/admin/ai/providers', body)
}

export function updateProvider(id: number | string, body: Partial<AiProvider>) {
  if (USE_MOCK) return mockApi.updateProvider(Number(id), body)
  return http.put<AiProvider>(`/admin/ai/providers/${id}`, body)
}

export function deleteProvider(id: number | string) {
  if (USE_MOCK) return mockApi.deleteProvider(Number(id))
  return http.del<void>(`/admin/ai/providers/${id}`)
}

export function setProviderDefault(id: number | string) {
  if (USE_MOCK) return mockApi.setProviderDefault(Number(id))
  return http.post<void>(`/admin/ai/providers/${id}/default`)
}

export function fetchModels() {
  if (USE_MOCK) return mockApi.modelList()
  return http.get<AiModel[]>('/admin/ai/models')
}

export function createModel(body: Partial<AiModel>) {
  if (USE_MOCK) return mockApi.createModel(body)
  return http.post<AiModel>('/admin/ai/models', body)
}

export function updateModel(id: number | string, body: Partial<AiModel>) {
  if (USE_MOCK) return mockApi.updateModel(Number(id), body)
  return http.put<AiModel>(`/admin/ai/models/${id}`, body)
}

export function deleteModel(id: number | string) {
  if (USE_MOCK) return mockApi.deleteModel(Number(id))
  return http.del<void>(`/admin/ai/models/${id}`)
}

export function setModelDefault(id: number | string) {
  if (USE_MOCK) return mockApi.setModelDefault(Number(id))
  return http.post<void>(`/admin/ai/models/${id}/default`)
}

// ============ Prompt ============

export function fetchPrompts() {
  if (USE_MOCK) return mockApi.promptList()
  return http.get<Prompt[]>('/admin/prompts')
}

export function updatePrompt(id: number | string, content: string) {
  if (USE_MOCK) return mockApi.updatePrompt(Number(id), content)
  return http.put<Prompt>(`/admin/prompts/${id}`, { content })
}

// ============ 任务 ============

export function fetchTasks(params: { novel_id?: number | string; page?: number; page_size?: number }) {
  if (USE_MOCK) return mockApi.taskList(params)
  return http.get<PageResult<AiTask>>(withQuery('/admin/ai/tasks', params))
}

export function createAiTask(body: { task_type: TaskType; novel_id?: number; params?: Record<string, unknown> }) {
  if (USE_MOCK) return mockApi.createTask(body)
  return http.post<AiTask>('/admin/ai/tasks', body)
}

export function fetchTask(id: number | string) {
  if (USE_MOCK) return mockApi.taskDetail(Number(id))
  return http.get<AiTask>(`/admin/ai/tasks/${id}`)
}

export function retryTask(id: number | string) {
  if (USE_MOCK) return mockApi.retryTask(Number(id))
  return http.post<AiTask>(`/admin/ai/tasks/${id}/retry`)
}

// 任务流（SSE）：先建任务拿 task_id，再订阅
export async function streamAiTask(
  id: number | string,
  handlers: StreamHandlers,
  signal?: AbortSignal,
) {
  if (USE_MOCK) {
    await mockStreamText(MOCK_STREAM_TEXT, handlers, signal)
    return
  }
  await readStream(`/api/admin/ai/tasks/${id}/stream`, { method: 'GET', signal }, handlers)
}

const MOCK_STREAM_TEXT = `夜色如墨，雁门关的城头挂着一盏孤灯。

沈砚站在城楼上，望着关外黑沉沉的旷野。风从北境吹来，裹着雪的腥气。赵铁站在他身后，沉默地抽着旱烟。

"赵叔。"沈砚忽然开口，"你说，我爹当年站在这里的时候，看到的是什么？"

赵铁吐出一口烟："他看到的，是北境的狼烟。"

"狼烟？"

"北境人从不打无准备的仗。"赵铁把烟杆在靴底磕了磕，"他们集结了十年，等的就是一个机会。你爹当年死守雁门关，等的也是这个机会——让他们以为，关内已经空了。"

沈砚沉默片刻，忽然笑了："所以，我爹不是在守关。他是在诱敌深入。"

赵铁没接话，但嘴角的疤动了动。

城外的雪越下越大。沈砚忽然听到，风里传来一阵若有若无的号角声。他的瞳孔骤然收缩。

"来了。"

他转身，衣袍在风里猎猎作响。城墙下，十万边军已经列阵完毕，刀枪如林，火光冲天。

这一夜，雁门关外，狼烟四起。

而那个十年前被逐出山门的少年，终于站到了他父亲曾经站过的地方。`

// ============ 日志 ============

export function fetchLogs(params: { page?: number; page_size?: number }) {
  if (USE_MOCK) return mockApi.logList(params)
  return http.get<PageResult<AiLog>>(withQuery('/admin/ai/logs', params))
}

export function clearLogs() {
  if (USE_MOCK) return mockApi.clearLogs()
  return http.del<void>('/admin/ai/logs')
}

// ============ 系统配置 ============

export function fetchConfig() {
  if (USE_MOCK) return mockApi.getConfig()
  return http.get<SystemConfig>('/admin/config')
}

export function saveConfig(body: SystemConfig) {
  if (USE_MOCK) return mockApi.saveConfig(body)
  return http.put<SystemConfig>('/admin/config', body)
}
