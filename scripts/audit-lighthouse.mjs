#!/usr/bin/env node
// Runs a Lighthouse audit against a set of local URLs and saves JSON + HTML
// reports under storage/audits/lighthouse/. Uses the system Google Chrome
// directly (not @lhci/cli's own launcher) — proven to work headless on macOS
// here; `lhci`'s launcher hit a sandbox-related Chrome connection failure.
//
// Usage:
//   npm run audit:lighthouse
//   npm run audit:lighthouse -- http://localhost:8912/some/other/page

import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outDir = path.join(root, 'storage', 'audits', 'lighthouse');
mkdirSync(outDir, { recursive: true });

const defaultUrls = [
    'http://localhost:8912/login',
    'http://localhost:8912',
];

const urls = process.argv.slice(2).length ? process.argv.slice(2) : defaultUrls;

const chromeCandidates = [
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    process.env.CHROME_PATH,
].filter(Boolean);

const chromePath = chromeCandidates.find((p) => existsSync(p));

if (!chromePath) {
    console.error('No Chrome binary found. Set CHROME_PATH to a Chrome/Chromium executable and retry.');
    process.exit(1);
}

for (const url of urls) {
    const slug = url.replace(/^https?:\/\//, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '');
    const outPath = path.join(outDir, `${slug}.json`);

    console.log(`Auditing ${url} → ${path.relative(root, outPath)}`);

    execFileSync('npx', [
        'lighthouse', url,
        '--output=json',
        `--output-path=${outPath}`,
        '--chrome-flags=--headless=new --no-sandbox --disable-gpu',
        '--quiet',
    ], {
        cwd: root,
        stdio: 'inherit',
        env: { ...process.env, CHROME_PATH: chromePath },
    });

    const report = JSON.parse(await import('node:fs').then((fs) => fs.readFileSync(outPath, 'utf8')));
    const scores = Object.entries(report.categories)
        .map(([key, cat]) => `${key}: ${Math.round(cat.score * 100)}%`)
        .join('  ·  ');
    console.log(`  ${scores}\n`);
}
