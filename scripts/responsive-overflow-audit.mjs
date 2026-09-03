/**
 * Responsive overflow audit.
 *
 * Nothing in the PHP suite tests viewport width, and none of this is visible
 * in a desktop browser: six pages scrolled sideways on a phone and the whole
 * suite stayed green through all of it.
 *
 * One family of cause — a box that cannot shrink below its content — in four
 * spellings, all of which shipped at some point:
 *
 *   1. a Tailwind grid/flex child without `min-w-0` (min-width defaults to
 *      auto, so a column holding `truncate` content grows the track)
 *   2. a bare `1fr` track, which is minmax(AUTO,1fr) — concourse.css had this
 *      in phone overrides one line below a correct `minmax(0,1fr)`
 *   3. a grid that declares columns only at a breakpoint, leaving an implicit
 *      auto track at every narrower width
 *   4. a flex group that cannot wrap, or a scroller not allowed to be
 *      narrower than its contents (overflow-x-auto alone does not scroll)
 *
 * The tell for 1–3 is `truncate`/`white-space: nowrap`: min-content becomes
 * the WHOLE unbroken string, so the track grows instead of ellipsising —
 * which also means text that appears truncated may never truncate at all.
 *
 * Usage — same encrypted-cookie auth as concourse-scope-audit.mjs, because
 * Laravel encrypts session cookies and a raw session id will not work:
 *
 *   ENC=$(php artisan tinker --execute='
 *     $id="<a sessions.id row>"; $n=config("session.cookie");
 *     echo app("encrypter")->encrypt(
 *       Illuminate\Cookie\CookieValuePrefix::create($n, app("encrypter")->getKey()).$id, false);
 *   ' | tail -1)
 *   node scripts/responsive-overflow-audit.mjs "$ENC"                  # default routes
 *   node scripts/responsive-overflow-audit.mjs "$ENC" /finance /events # or your own
 *
 * Exits non-zero when any page overflows, so it can gate a branch.
 */
import puppeteer from 'puppeteer';

const ENC = process.argv[2];
if (!ENC) {
  console.error('Usage: node scripts/responsive-overflow-audit.mjs <encrypted-session-cookie> [routes...]');
  process.exit(2);
}

// The audit is only as good as this list. It reported "all clean" twice while
// being true only of the routes named at the time; widening it from 12 to 20
// found two more real overflows immediately. Add a route when you add a page.
const DEFAULT_ROUTES = [
  '/', '/events', '/tasks', '/crm', '/finance', '/suppliers', '/venues',
  '/team', '/reports', '/ai-assistant', '/settings', '/projects',
  '/invoices', '/payments',
  '/events/7?tab=overview', '/events/7?tab=budget', '/events/7?tab=agenda',
  '/events/7?tab=tasks', '/events/7?tab=risks', '/events/7?tab=contract',
];

const BASE = process.env.AUDIT_BASE_URL || 'http://localhost:8912';
const routes = process.argv.length > 3 ? process.argv.slice(3) : DEFAULT_ROUTES;
const WIDTHS = [1440, 375];

const browser = await puppeteer.launch({
  executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  headless: 'new', args: ['--no-sandbox', '--disable-gpu'],
});
const page = await browser.newPage();
await page.setCookie({ name: 'elite-business-hub-session', value: ENC, domain: new URL(BASE).hostname, path: '/', httpOnly: true });

let failures = 0;

for (const width of WIDTHS) {
  await page.setViewport({ width, height: 900 });
  const bad = [];

  for (const route of routes) {
    let res;
    try {
      res = await page.goto(BASE + route, { waitUntil: 'networkidle2', timeout: 40000 });
    } catch {
      // A route that will not load is a failure worth reporting, never a
      // silent skip — an audit that treats absence of a result as a pass is
      // the thing this file exists to avoid.
      bad.push({ route, note: 'did not load' });
      continue;
    }
    if (res && res.status() >= 400) { bad.push({ route, note: `HTTP ${res.status()}` }); continue; }

    await new Promise(r => setTimeout(r, 450));

    // A page that bounced to login renders a short, narrow form that fits
    // every viewport — so an expired session turns this whole audit into a
    // row of meaningless "ok"s. It has already happened once. Treat it as a
    // failure of the run, not a pass for the route.
    const authed = await page.evaluate(() =>
      !document.querySelector('input[type="password"]') && !/Sign in to your command center/i.test(document.body.innerText));

    if (!authed) {
      bad.push({ route, note: 'NOT AUTHENTICATED — session cookie expired or wrong; nothing was measured' });
      continue;
    }

    const result = await page.evaluate(() => {
      const vw = window.innerWidth;
      // An element already inside an overflow-x ancestor is scrolling on
      // purpose (day strips, tables). Reporting those buries the real one.
      const inScroller = el => {
        let p = el.parentElement;
        while (p && p !== document.body) {
          const ox = getComputedStyle(p).overflowX;
          if (ox === 'auto' || ox === 'scroll' || ox === 'hidden') return true;
          p = p.parentElement;
        }
        return false;
      };
      const culprits = [];
      for (const el of document.querySelectorAll('*')) {
        const b = el.getBoundingClientRect();
        if (b.width <= vw || b.height === 0 || inScroller(el)) continue;
        culprits.push(`${el.tagName.toLowerCase()}.${(el.className || '').toString().trim().slice(0, 60)} (${Math.round(b.width)}px)`);
      }
      return { scrollWidth: document.body.scrollWidth, vw, culprits: culprits.slice(0, 3) };
    });

    if (result.scrollWidth > result.vw + 2) {
      bad.push({ route, note: `${result.scrollWidth}px in a ${result.vw}px viewport`, culprits: result.culprits });
    }
  }

  if (bad.length === 0) {
    console.log(`ok     ${width}px — all ${routes.length} routes fit`);
  } else {
    failures += bad.length;
    console.log(`FAILED ${width}px — ${bad.length} of ${routes.length}`);
    for (const b of bad) {
      console.log(`    ${b.route}: ${b.note}`);
      (b.culprits || []).forEach(c => console.log(`        ${c}`));
    }
  }
}

await browser.close();
console.log(failures ? `\n${failures} overflowing route(s)` : '\nno route scrolls sideways at any tested width');
process.exit(failures ? 1 : 0);
