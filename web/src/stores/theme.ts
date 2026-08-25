import { defineStore } from 'pinia'

export type ThemeMode = 'light' | 'dark' | 'system'

const KEY = 'novel-theme'

function getSystemDark(): boolean {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
}

function resolveDark(mode: ThemeMode): boolean {
  if (mode === 'system') return getSystemDark()
  return mode === 'dark'
}

export const useThemeStore = defineStore('theme', {
  state: () => ({
    mode: (localStorage.getItem(KEY) as ThemeMode) || 'system',
  }),
  getters: {
    isDark(state): boolean {
      return resolveDark(state.mode)
    },
  },
  actions: {
    apply() {
      const dark = resolveDark(this.mode)
      document.documentElement.classList.toggle('dark', dark)
      document.documentElement.style.colorScheme = dark ? 'dark' : 'light'
      localStorage.setItem(KEY, this.mode)
    },
    setMode(mode: ThemeMode) {
      this.mode = mode
      this.apply()
    },
    toggle() {
      this.setMode(this.isDark ? 'light' : 'dark')
    },
    init() {
      this.apply()
      // 跟随系统变化
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (this.mode === 'system') this.apply()
      })
    },
  },
})
