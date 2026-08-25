import { defineStore } from 'pinia'

export type ReaderWidth = 'narrow' | 'medium' | 'wide'

interface ReaderSettings {
  fontSize: number
  width: ReaderWidth
}

export interface ReadingProgress {
  no: number
  percent: number
  scrollTop: number
  updatedAt: number
}

const SETTINGS_KEY = 'reader-settings'
const PROGRESS_KEY = 'reader-progress'

function loadSettings(): ReaderSettings {
  try {
    const raw = localStorage.getItem(SETTINGS_KEY)
    if (raw) {
      const s = JSON.parse(raw) as Partial<ReaderSettings>
      return {
        fontSize: Math.min(24, Math.max(14, Number(s.fontSize) || 18)),
        width: ['narrow', 'medium', 'wide'].includes(s.width as string)
          ? (s.width as ReaderWidth)
          : 'medium',
      }
    }
  } catch {
    // ignore
  }
  return { fontSize: 18, width: 'medium' }
}

function loadProgress(): Record<number, ReadingProgress> {
  try {
    const raw = localStorage.getItem(PROGRESS_KEY)
    if (raw) return JSON.parse(raw) as Record<number, ReadingProgress>
  } catch {
    // ignore
  }
  return {}
}

export const WIDTH_STYLES: Record<ReaderWidth, string> = {
  narrow: 'max-w-2xl',
  medium: 'max-w-3xl',
  wide: 'max-w-4xl',
}

export const useReaderStore = defineStore('reader', {
  state: () => ({
    fontSize: 18,
    width: 'medium' as ReaderWidth,
    progress: {} as Record<number, ReadingProgress>,
    settingsLoaded: false,
  }),
  actions: {
    init() {
      if (this.settingsLoaded) return
      const s = loadSettings()
      this.fontSize = s.fontSize
      this.width = s.width
      this.progress = loadProgress()
      this.settingsLoaded = true
    },
    setFontSize(size: number) {
      this.fontSize = Math.min(24, Math.max(14, size))
      this.persistSettings()
    },
    setWidth(w: ReaderWidth) {
      this.width = w
      this.persistSettings()
    },
    persistSettings() {
      localStorage.setItem(
        SETTINGS_KEY,
        JSON.stringify({ fontSize: this.fontSize, width: this.width }),
      )
    },
    /** 保存阅读进度（节流由调用方处理） */
    saveProgress(novelId: number, p: ReadingProgress) {
      this.progress[novelId] = p
      localStorage.setItem(PROGRESS_KEY, JSON.stringify(this.progress))
    },
    getProgress(novelId: number): ReadingProgress | undefined {
      return this.progress[novelId]
    },
    clearProgress(novelId: number) {
      delete this.progress[novelId]
      localStorage.setItem(PROGRESS_KEY, JSON.stringify(this.progress))
    },
  },
})
