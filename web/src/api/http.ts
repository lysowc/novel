import { toast } from 'vue-sonner'
import type { ApiResponse } from '@/types/api'

export class ApiError extends Error {
  code: number
  status: number
  constructor(status: number, code: number, msg: string) {
    super(msg)
    this.name = 'ApiError'
    this.status = status
    this.code = code
  }
}

const BASE = '/api'

interface RequestOptions {
  method?: string
  body?: unknown
  headers?: Record<string, string>
  /** 是否提示错误（默认 true） */
  silent?: boolean
}

/** 401 统一跳转后台登录 */
function handleUnauthorized() {
  const path = window.location.pathname
  if (path.startsWith('/admin') && !path.startsWith('/admin/login')) {
    const redirect = encodeURIComponent(path + window.location.search)
    window.location.href = `/admin/login?redirect=${redirect}`
  }
}

async function request<T>(path: string, opts: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, headers, silent = false } = opts
  const init: RequestInit = {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...headers,
    },
  }
  if (body !== undefined) init.body = JSON.stringify(body)

  let res: Response
  try {
    res = await fetch(BASE + path, init)
  } catch (e) {
    if (!silent) toast.error('网络错误，无法连接服务器')
    throw new ApiError(0, -1, '网络错误，无法连接服务器')
  }

  if (res.status === 401) {
    handleUnauthorized()
    if (!silent) toast.error('未登录或登录已过期')
    throw new ApiError(401, 401, '未登录或登录已过期')
  }

  let json: ApiResponse<T>
  try {
    json = (await res.json()) as ApiResponse<T>
  } catch {
    const err = new ApiError(res.status, -1, `响应解析失败 (HTTP ${res.status})`)
    if (!silent) toast.error(err.message)
    throw err
  }

  if (json.code !== 0) {
    const err = new ApiError(res.status, json.code, json.msg || '请求失败')
    if (!silent) toast.error(err.message)
    throw err
  }
  return json.data
}

export const http = {
  get<T>(path: string, opts?: RequestOptions) {
    return request<T>(path, { ...opts, method: 'GET' })
  },
  post<T>(path: string, body?: unknown, opts?: RequestOptions) {
    return request<T>(path, { ...opts, method: 'POST', body })
  },
  put<T>(path: string, body?: unknown, opts?: RequestOptions) {
    return request<T>(path, { ...opts, method: 'PUT', body })
  },
  del<T>(path: string, opts?: RequestOptions) {
    return request<T>(path, { ...opts, method: 'DELETE' })
  },
}

/** 带查询参数拼装 */
export function withQuery(path: string, params?: Record<string, unknown>): string {
  if (!params) return path
  const qs = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(
      ([k, v]) =>
        `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`,
    )
    .join('&')
  return qs ? `${path}?${qs}` : path
}
