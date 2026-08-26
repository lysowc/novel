import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchHome } from '@/api'

/**
 * 站点信息（站点名来自系统配置，整站缓存一次）
 */
export const useSiteStore = defineStore('site', () => {
  const name = ref('拾光小说')
  let loaded = false

  async function load() {
    if (loaded) return
    try {
      const data = await fetchHome()
      if (data.site_name) {
        name.value = String(data.site_name)
      }
      loaded = true
    } catch {
      // 请求失败保持默认名
    }
  }

  return { name, load }
})
