import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const BASE = process.env.APP_URL || 'http://elitehub.test';
const OUT = '/Users/emranalitan/Herd/emranspace/soft-command-phase3-review';

await mkdir(OUT, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
    viewport: { width: 1512, height: 982 },
    deviceScaleFactor: 2,
});
const page = await context.newPage();

page.on('pageerror', (err) => console.error('PAGEERROR', err.message));

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
if (page.url().includes('/login')) {
    await page.fill('input[name="email"]', 'emran.itan@elitebhub.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
}

async function shot(name, url) {
    const res = await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(600);
    const status = res?.status();
    const body = await page.locator('body').innerText().catch(() => '');
    if (status >= 400 || body.includes('ErrorException') || body.includes('ViewException')) {
        console.error(`FAIL ${name} status=${status}`);
        await page.screenshot({ path: path.join(OUT, `${name}-ERROR.png`), fullPage: true });
        return false;
    }
    await page.screenshot({ path: path.join(OUT, `${name}.png`) });
    console.log(`ok ${name}`);
    return true;
}

await shot('01-command-center', `${BASE}/`);
await shot('02-event-portfolio', `${BASE}/events`);
await shot('03-event-studio', `${BASE}/events/create`);

// First non-archived event for hub
const hubLink = await page.evaluate(async (base) => {
    // already on events after studio? go events
    return null;
}, BASE);

await page.goto(`${BASE}/events`, { waitUntil: 'networkidle' });
const firstHub = page.locator('a[href*="/events/"]').filter({ hasNotText: 'create' }).first();
let hubUrl = `${BASE}/events`;
try {
    // Prefer a deck/list hub link
    const href = await page.locator('a[href*="/events/"][href*="?tab="], a[href^="http"][href*="/events/"]').first().getAttribute('href');
    // Fallback: click through via API-less approach — find any /events/{id}
    const all = await page.$$eval('a[href]', (as) =>
        as.map((a) => a.getAttribute('href')).filter((h) => h && /\/events\/\d+/.test(h))
    );
    if (all[0]) hubUrl = all[0].startsWith('http') ? all[0] : `${BASE}${all[0]}`;
} catch (_) {}

await shot('04-event-hub-overview', hubUrl.includes('tab=') ? hubUrl : `${hubUrl}${hubUrl.includes('?') ? '&' : '?'}tab=overview`.replace(`${BASE}${BASE}`, BASE));

// Normalize hub URL
if (!/\/events\/\d+/.test(hubUrl)) {
    // Try tinker-free: open dashboard nearest mission
    await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
    const links = await page.$$eval('a[href]', (as) =>
        as.map((a) => a.getAttribute('href')).filter((h) => h && /\/events\/\d+/.test(h))
    );
    if (links[0]) {
        hubUrl = links[0].startsWith('http') ? links[0] : `${BASE}${links[0]}`;
        await shot('04-event-hub-overview', hubUrl);
    }
} else {
    await shot('04-event-hub-overview', hubUrl);
}

await page.screenshot({ path: path.join(OUT, '05-shell-chrome.png') });

await browser.close();
console.log('Done', OUT);
