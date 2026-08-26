import { chromium } from 'playwright-core'
const exe = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
const BASE = 'http://127.0.0.1:8787'
const browser = await chromium.launch({ executablePath: exe, headless: true })
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
await page.goto(`${BASE}/`, { waitUntil: 'networkidle' })
await page.waitForTimeout(500)
const navText = await page.evaluate(() => document.querySelector('header a span.text-lg')?.textContent ?? '')
console.log('导航站点名:', navText)
// 搜索
await page.fill('input[placeholder="搜索小说…"]', '测试')
await page.keyboard.press('Enter')
await page.waitForTimeout(1500)
console.log('跳转后 URL:', page.url())
const title = await page.evaluate(() => document.querySelector('main h1')?.textContent ?? '')
console.log('页面标题:', title)
const count = await page.evaluate(() => document.querySelectorAll('main a[href*="/novel/"]').length)
console.log('结果卡片数:', count)
await browser.close()
