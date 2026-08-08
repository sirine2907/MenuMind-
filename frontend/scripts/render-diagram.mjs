// Renders docs/architecture.mmd to docs/architecture.png with Mermaid in headless Chromium.
//   node scripts/render-diagram.mjs
import { readFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { chromium } from 'playwright'

const docs = resolve(dirname(fileURLToPath(import.meta.url)), '../../docs')
const source = await readFile(`${docs}/architecture.mmd`, 'utf8')

const html = `<!doctype html><html><body style="margin:0;background:#fff">
<pre class="mermaid">${source.replace(/</g, '&lt;')}</pre>
<script type="module">
  import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.esm.min.mjs'
  mermaid.initialize({ startOnLoad: false, theme: 'base', themeVariables: {
    primaryColor: '#fff7ed', primaryBorderColor: '#c2410c', primaryTextColor: '#1c1917',
    lineColor: '#78716c', clusterBkg: '#fafaf9', clusterBorder: '#d6d3d1', edgeLabelBackground: '#ffffff', titleColor: '#9a3412', fontFamily: 'Inter, Segoe UI, sans-serif' } })
  await mermaid.run().catch((e) => console.error('mermaid:', e.message ?? String(e)))
  document.body.dataset.done = '1'
</script></body></html>`

const browser = await chromium.launch()
const page = await browser.newPage({ deviceScaleFactor: 2, viewport: { width: 1600, height: 900 } })
page.on('console', (m) => console.log('[page]', m.text()))
page.on('pageerror', (e) => console.log('[error]', e.message))
await page.setContent(html)
await page.waitForSelector('body[data-done="1"]', { state: 'attached', timeout: 30_000 })
await page.locator('pre.mermaid svg').screenshot({ path: `${docs}/architecture.png`, omitBackground: false })
await browser.close()
console.log('✓ docs/architecture.png')
