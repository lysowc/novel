import { chromium } from 'playwright-core'
const exe = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
const BASE = 'http://127.0.0.1:8787'
const browser = await chromium.launch({ executablePath: exe, headless: true })
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' })
await page.fill('#username', 'admin')
await page.fill('#password', 'admin123')
await page.click('button[type="submit"]')
await page.waitForURL('**/admin', { timeout: 15000 })
await page.goto(`${BASE}/admin/ideas/1/chat`, { waitUntil: 'networkidle' })
await page.waitForTimeout(1000)
const result = await page.evaluate(() => {
  const ta = document.querySelector('textarea')
  if (!ta) return { found: false }
  const r = ta.getBoundingClientRect()
  const visible = r.top >= 0 && r.bottom <= window.innerHeight
  const sendBtn = Array.from(document.querySelectorAll('button')).find(b => b.textContent?.includes('发送'))
  return { found: true, visible, top: Math.round(r.top), bottom: Math.round(r.bottom), vh: window.innerHeight, sendBtnVisible: sendBtn ? sendBtn.getBoundingClientRect().bottom <= window.innerHeight : false }
})
console.log('输入框:', JSON.stringify(result))
// 外层顶栏是否隐藏（bare 模式）
const headerCount = await page.evaluate(() => document.querySelectorAll('header').length)
console.log('header 数量（应为 1）:', headerCount)
await browser.close()
