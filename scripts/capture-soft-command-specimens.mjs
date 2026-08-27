/**
 * Capture Phase 1 Soft Command visual review specimens.
 * Usage: node scripts/capture-soft-command-specimens.mjs
 */
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const BASE = process.env.APP_URL || 'http://elitehub.test';
const OUT = process.env.OUT_DIR
    || '/Users/emranalitan/Herd/emranspace/soft-command-phase1-review/specimens';

const specimens = [
    ['01-typography', '#specimen-01-typography'],
    ['02-color', '#specimen-02-color'],
    ['03-buttons', '#specimen-03-buttons'],
    ['04-status-pills', '#specimen-04-status-pills'],
    ['05-metric-pills', '#specimen-05-metric-pills'],
    ['06-soft-cards', '#specimen-06-soft-cards'],
    ['07-selected-dark-card', '#specimen-07-selected-dark'],
    ['08-queue-list', '#specimen-08-queue-list'],
    ['09-detail-panel', '#specimen-09-detail-panel'],
    ['10-action-panel', '#specimen-10-action-panel'],
    ['11-smart-table', '#specimen-11-smart-table'],
    ['12-filter-bar', '#specimen-12-filter-bar'],
    ['13-form-fields', '#specimen-13-form-fields'],
    ['14-readiness-cards', '#specimen-14-readiness'],
    ['15-module-cards', '#specimen-15-module-cards'],
    ['16-empty-state', '#specimen-16-empty-state'],
    ['17-alert-card', '#specimen-17-alert-card'],
];

await mkdir(OUT, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
    viewport: { width: 1440, height: 1100 },
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

await page.goto(`${BASE}/design/soft-command`, { waitUntil: 'networkidle' });
await page.waitForSelector('#specimen-01-typography');

// Full board + MDA composition
await page.screenshot({
    path: path.join(OUT, '00-full-review-board.png'),
    fullPage: true,
});

const mda = page.locator('#mda');
await mda.scrollIntoViewIfNeeded();
await mda.screenshot({ path: path.join(OUT, '18-mda-composition.png') });

for (const [name, selector] of specimens) {
    const el = page.locator(selector);
    await el.scrollIntoViewIfNeeded();
    await page.waitForTimeout(120);
    await el.screenshot({
        path: path.join(OUT, `${name}.png`),
    });
    console.log(`captured ${name}`);
}

await browser.close();
console.log(`Done → ${OUT}`);
