/**
 * Phase 2 shell visual review — isolated specimens.
 */
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const BASE = process.env.APP_URL || 'http://elitehub.test';
const OUT = process.env.OUT_DIR
    || '/Users/emranalitan/Herd/emranspace/soft-command-phase2-review/specimens';

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
await page.waitForTimeout(500);

const shots = [
    ['01-full-app-shell', '.eo-app-shell', { fullPage: true }],
    ['02-mini-rail', '.eo-mini-rail'],
    ['03-context-sidebar', '.eo-context-sidebar'],
    ['04-top-command-bar', '.eo-top-command'],
    ['05-workspace-shell', '.eo-workspace-shell'],
    ['06-mission-radar', '#specimen-mission-radar'],
    ['07-event-dna', '#specimen-event-dna'],
    ['08-mission-card', '#specimen-mission-card'],
    ['09-readiness-cards', '#specimen-readiness-cards'],
    ['10-operations-card', '#specimen-operations-card'],
    ['11-commercial-card', '#specimen-commercial-card'],
    ['12-event-health-card', '#specimen-event-health-card'],
];

// Viewport overview first
await page.screenshot({ path: path.join(OUT, '00-viewport.png') });

for (const [name, selector, opts = {}] of shots) {
    if (opts.fullPage) {
        await page.screenshot({
            path: path.join(OUT, `${name}.png`),
            fullPage: true,
        });
        console.log(`captured ${name} (full page)`);
        continue;
    }

    const el = page.locator(selector).first();
    await el.scrollIntoViewIfNeeded();
    await page.waitForTimeout(150);
    await el.screenshot({ path: path.join(OUT, `${name}.png`) });
    console.log(`captured ${name}`);
}

await browser.close();
console.log(`Done → ${OUT}`);
