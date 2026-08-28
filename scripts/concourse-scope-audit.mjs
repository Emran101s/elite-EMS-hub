/**
 * Concourse scope audit.
 *
 * Every --cx-* design token is defined on .cx-canvas. A component that uses
 * cx-* classes but renders outside a .cx-canvas ancestor gets the classes
 * with none of the values — navy text on a navy ground, unfilled pills,
 * invisible badges. Nothing else catches this: the classes are present, the
 * bindings match, and the whole PHP suite passes on visibly broken output.
 *
 * This walks real pages in a headless browser and reports any element
 * carrying a cx-* class that fails to resolve --cx-accent.
 *
 * Auth: pass an ENCRYPTED session cookie value (Laravel encrypts cookies, so
 * a raw session id will not work):
 *
 *   ENC=$(php artisan tinker --execute='
 *     $id="<a sessions.id row>"; $n=config("session.cookie");
 *     echo app("encrypter")->encrypt(
 *       Illuminate\Cookie\CookieValuePrefix::create($n, app("encrypter")->getKey()).$id, false);
 *   ' | tail -1)
 *   node scripts/concourse-scope-audit.mjs "$ENC" "http://localhost:8912/events/7?tab=overview" ...
 *
 * Quote each URL separately — unquoted they collapse into one argument and
 * the run reports a meaningless pass.
 */
import puppeteer from 'puppeteer';
const ENC = process.argv[2];
const urls = process.argv.slice(3);
const browser = await puppeteer.launch({
  executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  headless: 'new', args: ['--no-sandbox','--disable-gpu'] });
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 950 });
await page.setCookie({ name:'elite-business-hub-session', value:ENC, domain:'localhost', path:'/', httpOnly:true });

let bad = 0;
for (const u of urls) {
  await page.goto(u, { waitUntil:'networkidle2', timeout:30000 });
  await new Promise(r=>setTimeout(r,700));
  const res = await page.evaluate(() => {
    // Every element carrying a cx-* class must resolve --cx-accent.
    // If it doesn't, it is outside .cx-canvas and all its colours are dead.
    const out = [];
    for (const el of document.querySelectorAll('[class*="cx-"]')) {
      const cls = [...el.classList].filter(c => c.startsWith('cx-'));
      if (!cls.length) continue;
      const v = getComputedStyle(el).getPropertyValue('--cx-accent').trim();
      if (!v) out.push({ cls: cls.join(' '), tag: el.tagName.toLowerCase() });
    }
    // de-dupe
    const seen = new Set(); const uniq = [];
    for (const o of out) { const k = o.cls; if (!seen.has(k)) { seen.add(k); uniq.push(o); } }
    return uniq;
  });
  const tab = new URL(u).search || '/';
  if (res.length) { bad += res.length; console.log(`BROKEN ${tab}`); res.slice(0,6).forEach(r=>console.log(`    ${r.tag}.${r.cls}`)); }
  else console.log(`ok     ${tab}`);
}
console.log(bad ? `\n${bad} unscoped cx-* element groups found` : '\nall cx-* elements resolve their tokens');
await browser.close();
