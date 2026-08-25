import { defineStore } from 'pinia'
import { fetchMe, login as apiLogin, logout as apiLogout } from '@/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    username: '' as string,
    checked: false,
  }),
  getters: {
    isLoggedIn: (s) => !!s.username,
  },
  actions: {
    async fetchMe() {
      try {
        const res = await fetchMe()
        this.username = res.username
      } catch {
        this.username = ''
      } finally {
        this.checked = true
      }
    },
    async login(username: string, password: string) {
      const res = await apiLogin(username, password)
      this.username = res.username
      this.checked = true
    },
    async logout() {
      try {
        await apiLogout()
      } catch {
        // 忽略
      }
      this.username = ''
    },
  },
})
