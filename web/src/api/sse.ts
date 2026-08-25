import type { StreamEvent } from '@/types/api'

export interface StreamHandlers {
  /** 任务流：正文增量 chunk */
  onChunk?: (content: string) => void
  /** 点子聊天：delta 增量 */
  onDelta?: (content: string) => void
  /** 任务状态变化 */
  onStatus?: (status: string) => void
  /** 流结束 */
  onDone?: (data?: unknown) => void
  /** 错误 */
  onError?: (msg: string) => void
}

export interface StreamOptions {
  method?: 'GET' | 'POST'
  body?: unknown
  signal?: AbortSignal
  headers?: Record<string, string>
}

function dispatch(evt: StreamEvent, handlers: StreamHandlers) {
  switch (evt.type) {
    case 'chunk':
      handlers.onChunk?.(evt.content ?? '')
      break
    case 'delta':
      handlers.onDelta?.(evt.content ?? (evt.data as string) ?? '')
      break
    case 'status':
      handlers.onStatus?.(String(evt.message ?? evt.status ?? evt.stage ?? ''))
      break
    case 'done':
      handlers.onDone?.(evt)
      break
    case 'error':
      handlers.onError?.(evt.msg || evt.message || '流式响应出错')
      break
  }
}

/**
 * 读取 SSE 流：响应为 text/event-stream，每行 `data: {json}`
 * 使用 fetch + ReadableStream 逐行解析
 */
export async function readStream(
  url: string,
  opts: StreamOptions,
  handlers: StreamHandlers,
): Promise<void> {
  const { method = 'GET', body, signal, headers } = opts
  const init: RequestInit = {
    method,
    credentials: 'same-origin',
    signal,
    headers: {
      Accept: 'text/event-stream',
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...headers,
    },
  }
  if (body !== undefined) init.body = JSON.stringify(body)

  let res: Response
  try {
    res = await fetch(url, init)
  } catch (e) {
    if ((e as Error)?.name === 'AbortError') return
    handlers.onError?.('网络错误，无法连接流')
    return
  }

  if (res.status === 401) {
    if (window.location.pathname.startsWith('/admin')) {
      window.location.href = '/admin/login'
    }
    handlers.onError?.('未登录或登录已过期')
    return
  }
  if (!res.ok || !res.body) {
    handlers.onError?.(`HTTP ${res.status}`)
    return
  }

  const reader = res.body.getReader()
  const decoder = new TextDecoder()
  let buf = ''

  try {
    for (;;) {
      const { done, value } = await reader.read()
      if (done) break
      buf += decoder.decode(value, { stream: true })
      let idx: number
      while ((idx = buf.indexOf('\n')) >= 0) {
        const line = buf.slice(0, idx).trim()
        buf = buf.slice(idx + 1)
        if (!line || !line.startsWith('data:')) continue
        const payload = line.slice(5).trim()
        if (!payload) continue
        try {
          const evt = JSON.parse(payload) as StreamEvent
          if (!evt || typeof evt.type !== 'string') continue
          dispatch(evt, handlers)
        } catch {
          // 非 JSON 行忽略
        }
      }
    }
  } catch (e) {
    if ((e as Error)?.name !== 'AbortError') {
      handlers.onError?.((e as Error)?.message || '流读取失败')
    }
  } finally {
    reader.releaseLock()
  }
}
