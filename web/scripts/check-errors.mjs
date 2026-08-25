import { chromium } from 'playwright-core'
const exe = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
const BASE = 'http://127.0.0.1:8787'
const browser = await chromium.launch({ executablePath: exe, headless: true })
const ctx = await browser.newContext()
const page = await ctx.newPage()
const errors = []
page.on('pageerror', e => errors.push('pageerror: ' + e.message))
page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()) })
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' })
await page.fill('#username', 'admin')
await page.fill('#password', 'admin123')
await page.click('button[type="submit"]')
await page.waitForURL('**/admin', { timeout: 15000 })
await page.waitForTimeout(1500)
for (const path of ['/admin', '/admin/novels/2', '/admin/ideas', '/admin/ai/config', '/admin/ai/prompts', '/admin/ai/logs', '/admin/settings']) {
  await page.goto(BASE + path, { waitUntil: 'networkidle' })
  await page.waitForTimeout(600)
}
await page.goto(`${BASE}/`, { waitUntil: 'networkidle' })
await page.goto(`${BASE}/read/2/1`, { waitUntil: 'networkidle' })
await page.waitForTimeout(600)
console.log(errors.length === 0 ? '✅ 全部页面无 JS 错误' : '❌ 有错误:\n' + errors.join('\n'))
await browser.close()
