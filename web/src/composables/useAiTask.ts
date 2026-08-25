import { ref } from 'vue'
import { toast } from 'vue-sonner'
import { createAiTask, fetchTask } from '@/api'
import type { AiTask, TaskType } from '@/types/api'

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms))

/**
 * 创建任务 + 轮询状态（用于设定/大纲/记忆等无流式输出的任务）
 */
export function usePollingTask() {
  const running = ref(false)

  async function run(opts: {
    taskType: TaskType
    novelId: number
    params?: Record<string, unknown>
    onSuccess?: (task: AiTask) => void
  }): Promise<AiTask | null> {
    running.value = true
    try {
      const task = await createAiTask({
        task_type: opts.taskType,
        novel_id: opts.novelId,
        params: opts.params,
      })
      toast.info('AI 任务已创建，正在处理…')
      const deadline = Date.now() + 10 * 60 * 1000
      while (Date.now() < deadline) {
        await sleep(2000)
        const t = await fetchTask(task.id)
        if (t.status === 'success') {
          toast.success('AI 任务完成')
          opts.onSuccess?.(t)
          return t
        }
        if (t.status === 'failed') {
          toast.error(t.error_message || 'AI 任务失败，可重试')
          return t
        }
      }
      toast.error('任务处理超时')
      return null
    } catch (e) {
      toast.error((e as Error).message || '创建任务失败')
      return null
    } finally {
      running.value = false
    }
  }

  return { running, run }
}
