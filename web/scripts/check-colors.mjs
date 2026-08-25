// 配色验证：读取关键元素计算样式，确认黑白单色
// 用法: node scripts/check-colors.mjs
import { chromium } from 'playwright-core'

const exe = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
const BASE = 'http://127.0.0.1:8787'

const browser = await chromium.launch({ executablePath: exe, headless: true })

async function checkScheme(scheme) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: scheme })
  const page = await ctx.newPage()

  // 前台首页
  await page.goto(`${BASE}/`, { waitUntil: 'networkidle' })
  const home = await page.evaluate(() => {
    const body = getComputedStyle(document.body)
    const cs = getComputedStyle(document.documentElement)
    // 主按钮（继续阅读卡片不是按钮；取 hero 区或导航里的元素）
    const anyButton = document.querySelector('a, button')
    const link = anyButton ? getComputedStyle(anyButton) : null
    return {
      bodyBg: body.backgroundColor,
      bodyColor: body.color,
      tokenPrimary: cs.getPropertyValue('--primary').trim(),
      tokenBg: cs.getPropertyValue('--background').trim(),
      linkColor: link?.color ?? null,
    }
  })
  console.log(`[${scheme}] 首页 body背景=${home.bodyBg} 文字=${home.bodyColor}`)
  console.log(`[${scheme}] 首页 --primary=${home.tokenPrimary} --background=${home.tokenBg}`)

  // 登录页主按钮
  await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' })
  const login = await page.evaluate(() => {
    const btn = document.querySelector('button[type="submit"]')
    if (!btn) return null
    const s = getComputedStyle(btn)
    return { bg: s.backgroundColor, color: s.color, border: s.borderColor }
  })
  console.log(`[${scheme}] 登录按钮 背景=${login?.bg} 文字=${login?.color}`)
  await ctx.close()
}

await checkScheme('light')
await checkScheme('dark')
await browser.close()
