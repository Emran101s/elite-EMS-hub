import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const BASE = process.env.APP_URL || 'http://elitehub.test';
const OUT = '/Users/emranalitan/Herd/emranspace/soft-command-phase4-review';

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
    await page.waitForTimeout(700);
    const status = res?.status();
    const body = await page.locator('body').innerText().catch(() => '');
    if (status >= 400 || body.includes('ErrorException') || body.includes('ViewException') || body.includes('Undefined variable')) {
        console.error(`FAIL ${name} status=${status}`);
        console.error(body.slice(0, 500));
        await page.screenshot({ path: path.join(OUT, `${name}-ERROR.png`), fullPage: true });
        return false;
    }
    await page.screenshot({ path: path.join(OUT, `${name}.png`) });
    console.log(`ok ${name}`);
    return true;
}

await shot('01-crm-pipeline', `${BASE}/crm`);
await shot('02-clients', `${BASE}/settings/clients`);
await shot('03-proposals', `${BASE}/proposals`);
await shot('04-contracts', `${BASE}/contracts`);
await shot('05-finance', `${BASE}/finance`);
await shot('06-invoices', `${BASE}/invoices`);
await shot('07-payments', `${BASE}/payments`);

await browser.close();
console.log('Done', OUT);
