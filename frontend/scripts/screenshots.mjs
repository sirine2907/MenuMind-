// Captures the README screenshots against a running MenuMind stack.
//
//   APP_URL=http://localhost:3000 API_URL=http://localhost:8080 node scripts/screenshots.mjs
//
// Works with any LLM provider; with LLM_PROVIDER=mock the answers are deterministic.
import { mkdir } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { chromium } from 'playwright'

const APP_URL = process.env.APP_URL ?? 'http://localhost:3000'
const API_URL = process.env.API_URL ?? 'http://localhost:8080'
const OUT = resolve(dirname(fileURLToPath(import.meta.url)), '../../docs/screenshots')
const QUESTION = "I'm allergic to peanuts, what vegetarian dishes can I order under 25 TND?"

await mkdir(OUT, { recursive: true })

const browser = await chromium.launch()
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' })
const page = await context.newPage()
const shot = async (name) => {
  await page.waitForTimeout(400) // let transitions settle
  await page.screenshot({ path: `${OUT}/${name}.png` })
  console.log(`✓ ${name}.png`)
}

async function login(email) {
  await page.goto(`${APP_URL}/login`)
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Log in' }).click()
  await page.waitForURL('**/menu')
}

async function ask(message, waitFor) {
  await page.getByLabel('Message').fill(message)
  await page.getByRole('button', { name: 'Send' }).click()
  await page.getByText(waitFor).last().waitFor({ timeout: 180_000 })
}

// 1. Login
await page.goto(`${APP_URL}/login`)
await page.getByRole('button', { name: 'Log in' }).waitFor()
await shot('00-login')

// 2. Menu
await login('demo@menumind.tn')
await page.locator('article').first().waitFor()
await shot('01-menu')

// 3. Chat: the showcase question, with tool steps and citations
await page.goto(`${APP_URL}/chat`)
await ask(QUESTION, 'Sources')
await page.locator('main .overflow-y-auto').first().evaluate((el) => el.scrollTo(0, 0))
await shot('02-chat')

// 4. Order through the assistant, then confirm
await ask("I'd like 2 Ojja Légumes and a Citronnade", 'Shall I place this order?')
await ask('Yes please', 'has been placed')
await shot('03-order-confirmation')

await page.goto(`${APP_URL}/orders`)
await page.getByText('Order #').first().waitFor()
await shot('04-orders')

// 5. Admin back office
await page.getByLabel('Log out').click()
await login('admin@menumind.tn')
await page.goto(`${APP_URL}/admin`)
await page.getByText('Search engine').waitFor()
await page.getByText('Dishes (').waitFor()
await shot('05-admin')

// 6. Dark mode
await page.getByLabel('Switch to dark mode').click()
await page.goto(`${APP_URL}/menu`)
await page.locator('article').first().waitFor()
await shot('06-menu-dark')
await page.getByLabel('Switch to light mode').click()

// 7. Swagger UI
await page.goto(`${API_URL}/api/documentation`)
await page.locator('.opblock').first().waitFor({ timeout: 60_000 })
await shot('07-swagger')

await browser.close()
