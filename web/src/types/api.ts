// 与后端契约一致的数据类型定义

export interface ApiResponse<T = unknown> {
  code: number
  msg: string
  data: T
}

export interface PageResult<T> {
  list: T[]
  total: number
  page: number
  page_size: number
}

export interface Category {
  id: number
  name: string
  description: string
  sort: number
  status: number
  novel_count?: number
  created_at: string
  updated_at: string
}

export type NovelStatus = 'draft' | 'published' | 'finished'

export interface Novel {
  id: number
  category_id: number
  title: string
  cover: string
  description: string
  tags: string // 逗号分隔
  status: NovelStatus
  is_public: 0 | 1
  word_count: number
  chapter_count: number
  category_name?: string
  created_at: string
  updated_at: string
}

export interface NovelDetail extends Novel {
  first_no: number
}

export interface ChapterListItem {
  chapter_no: number
  title: string
  word_count: number
  updated_at: string
}

export interface Chapter {
  id: number
  novel_id: number
  chapter_no: number
  title: string
  summary: string
  content: string
  word_count: number
  status: number
  created_at: string
  updated_at: string
}

export interface ChapterRead {
  chapter: Chapter
  prev_no: number | null
  next_no: number | null
}

export type IdeaStatus = 'unused' | 'used'

export interface Idea {
  id: number
  category_id: number
  title: string
  content: string
  status: IdeaStatus
  category_name?: string
  created_at: string
  updated_at: string
}

export type IdeaRole = 'user' | 'assistant'

export interface IdeaMessage {
  id: number
  idea_id: number
  role: IdeaRole
  content: string
  created_at: string
}

export interface AiProvider {
  id: number
  name: string
  base_url: string
  api_key: string // 回显时打码
  status: number
  is_default: number
  created_at: string
}

export interface AiModel {
  id: number
  provider_id: number
  provider_name?: string
  name: string
  display_name: string
  max_tokens: number
  temperature: number
  status: number
  is_default: number
}

export type PromptType =
  | 'idea_chat'
  | 'novel_setting'
  | 'outline'
  | 'chapter_generate'
  | 'chapter_summary'
  | 'memory_update'
  | 'chapter_continue'

export interface Prompt {
  id: number
  type: PromptType
  name: string
  description: string
  content: string
  updated_at: string
}

export type TaskStatus = 'pending' | 'running' | 'success' | 'failed'

export type TaskType =
  | 'generate_setting'
  | 'generate_outline'
  | 'generate_chapter'
  | 'continue_chapter'
  | 'regenerate_chapter'
  | 'generate_summary'
  | 'update_memory'

export interface AiTask {
  id: number
  task_type: TaskType
  ref_id: number
  ref_type?: string
  status: TaskStatus
  error_message: string
  created_at: string
  updated_at: string
}

export interface AiLog {
  id: number
  provider: string
  model: string
  task_type: string
  prompt_tokens: number
  completion_tokens: number
  total_tokens: number
  duration: number // 毫秒
  status: number
  error_message: string
  created_at: string
}

export interface NovelSetting {
  id: number
  novel_id: number
  world_view: string
  characters: string
  factions: string
  conflicts: string
  main_plot: string
  style: string
}

// Outline 是 novel 上的 JSON 字符串
export interface OutlineChapter {
  no: number
  title: string
  summary: string
}

export interface OutlineVolume {
  title: string
  chapters: OutlineChapter[]
}

export interface Outline {
  volumes: OutlineVolume[]
}

export interface Memory {
  id: number
  novel_id: number
  content: string // JSON 字符串
  updated_at: string
}

export interface Dashboard {
  novel_count: number
  chapter_count: number
  total_words: number
  idea_count: number
  running_tasks: number
  today_chapters: number
  recent_tasks: AiTask[]
  recent_logs: AiLog[]
}

export interface HomeData {
  recent_updates: Novel[]
  categories: { id: number; name: string; novel_count: number }[]
}

export interface SystemConfig {
  [key: string]: string | number
}

// SSE 事件
export type StreamEventType = 'chunk' | 'delta' | 'status' | 'done' | 'error'

export interface StreamEvent {
  type: StreamEventType
  content?: string
  msg?: string
  /** 任务状态事件：status(pending/running/success/failed) / stage / message */
  status?: string
  stage?: string
  message?: string
  error_message?: string
  data?: unknown
}

export interface LoginResult {
  username: string
}

export interface CreateNovelResult {
  novel_id: number
}
