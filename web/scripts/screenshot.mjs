// 视觉验证脚本：用系统 Chrome 截取关键页面（黑白配色检查用）
// 用法: node scripts/screenshot.mjs [输出目录]
import { chromium } from 'playwright-core'
import { mkdirSync } from 'node:fs'

const outDir = process.argv[2] || '/tmp/webman-shots'
mkdirSync(outDir, { recursive: true })

const exe = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
const BASE = 'http://127.0.0.1:8787'
const viewport = { width: 1440, height: 900 }

const browser = await chromium.launch({ executablePath: exe, headless: true })

// ---- 前台（亮色） ----
const ctxLight = await browser.newContext({ viewport, colorScheme: 'light' })
const p1 = await ctxLight.newPage()
await p1.goto(`${BASE}/`, { waitUntil: 'networkidle' })
await p1.screenshot({ path: `${outDir}/home-light.png` })
await p1.goto(`${BASE}/read/2/1`, { waitUntil: 'networkidle' })
await p1.screenshot({ path: `${outDir}/reader-light.png` })
await ctxLight.close()

// ---- 前台（暗色） ----
const ctxDark = await browser.newContext({ viewport, colorScheme: 'dark' })
const p2 = await ctxDark.newPage()
await p2.goto(`${BASE}/`, { waitUntil: 'networkidle' })
await p2.screenshot({ path: `${outDir}/home-dark.png` })
await p2.goto(`${BASE}/read/2/1`, { waitUntil: 'networkidle' })
await p2.screenshot({ path: `${outDir}/reader-dark.png` })
await ctxDark.close()

// ---- 后台（登录后，亮色） ----
const ctxAdmin = await browser.newContext({ viewport, colorScheme: 'light' })
const p3 = await ctxAdmin.newPage()
await p3.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' })
await p3.screenshot({ path: `${outDir}/login-light.png` })
await p3.fill('#username', 'admin')
await p3.fill('#password', 'admin123')
await p3.click('button[type="submit"]')
await p3.waitForURL('**/admin', { timeout: 15000 })
await p3.waitForTimeout(1200)
await p3.screenshot({ path: `${outDir}/dashboard-light.png` })
await p3.goto(`${BASE}/admin/novels/2`, { waitUntil: 'networkidle' })
await p3.waitForTimeout(800)
await p3.screenshot({ path: `${outDir}/novel-admin-light.png` })
await p3.goto(`${BASE}/admin/ideas`, { waitUntil: 'networkidle' })
await p3.waitForTimeout(800)
await p3.screenshot({ path: `${outDir}/ideas-light.png` })
await ctxAdmin.close()

// ---- 后台（暗色） ----
const ctxAdminDark = await browser.newContext({ viewport, colorScheme: 'dark' })
const p4 = await ctxAdminDark.newPage()
await p4.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' })
await p4.fill('#username', 'admin')
await p4.fill('#password', 'admin123')
await p4.click('button[type="submit"]')
await p4.waitForURL('**/admin', { timeout: 15000 })
await p4.waitForTimeout(1200)
await p4.screenshot({ path: `${outDir}/dashboard-dark.png` })
await ctxAdminDark.close()

await browser.close()
console.log('截图完成:', outDir)
