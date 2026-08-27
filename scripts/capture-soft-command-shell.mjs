import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const BASE = process.env.APP_URL || 'http://elitehub.test';
const OUT = process.env.OUT_DIR
    || '/Users/emranalitan/Herd/emranspace/soft-command-phase2-review';

await mkdir(OUT, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
    viewport: { width: 1512, height: 982 },
    deviceScaleFactor: 2,
});
const page = await context.newPage();

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
if (page.url().includes('/login')) {
    await page.fill('input[name="email"]', 'emran.itan@elitebhub.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
}

await page.goto(`${BASE}/design/soft-command-shell`, { waitUntil: 'networkidle' });
await page.waitForSelector('.eo-app-shell');
await page.waitForTimeout(400);

await page.screenshot({ path: path.join(OUT, '01-app-shell-full.png'), fullPage: true });
await page.screenshot({ path: path.join(OUT, '02-app-shell-viewport.png') });

await page.locator('.eo-mini-rail').screenshot({ path: path.join(OUT, '03-mini-rail.png') });
await page.locator('.eo-context-sidebar').screenshot({ path: path.join(OUT, '04-context-sidebar.png') });
await page.locator('.eo-top-command').screenshot({ path: path.join(OUT, '05-top-command-bar.png') });
await page.locator('.eo-workspace-shell').screenshot({ path: path.join(OUT, '06-workspace-shell.png') });
await page.locator('.eo-radar').first().screenshot({ path: path.join(OUT, '07-mission-radar.png') });

await page.goto(`${BASE}/design/soft-command`, { waitUntil: 'networkidle' });
await page.waitForSelector('#event-dna');
await page.locator('#event-dna').screenshot({ path: path.join(OUT, '08-event-dna-domain-cards.png') });

await browser.close();
console.log(`Done → ${OUT}`);
