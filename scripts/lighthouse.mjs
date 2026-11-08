#!/usr/bin/env node
// Lighthouse, 3 runs per page and form factor, medians reported.
//   node scripts/lighthouse.mjs before          # label of the state being measured
//   LH_BASE=https://shop.kabiirlabs.com node scripts/lighthouse.mjs after-public
// Needs Google Chrome. Uses the lighthouse npm package via npx (version pinned below).
import { execFileSync } from "node:child_process";
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { join } from "node:path";

const LH = "lighthouse@13.5.0";
const label = process.argv[2];
if (!label) { console.error("usage: node scripts/lighthouse.mjs <label>"); process.exit(2); }
const base = (process.env.LH_BASE || "http://127.0.0.1:8081").replace(/\/$/, "");
const runs = Number(process.env.LH_RUNS || 3);
const pages = [["home", "/"], ["shop", "/shop/"], ["product", "/product/raspberry-garden-teapot/"]];
const out = join("reports", "lighthouse", label);
mkdirSync(out, { recursive: true });

const median = (xs) => { const s = [...xs].sort((a, b) => a - b); const m = Math.floor(s.length / 2); return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2; };
const summary = { label, base, runs, lighthouse: LH, date: new Date().toISOString(), results: {} };

for (const ff of ["mobile", "desktop"]) {
  for (const [name, path] of pages) {
    const rows = [];
    for (let i = 1; i <= runs; i++) {
      const file = join(out, `${name}-${ff}-${i}.json`);
      const args = ["-y", LH, base + path, "--quiet", "--output=json", `--output-path=${file}`,
        "--only-categories=performance,accessibility,best-practices,seo",
        "--chrome-flags=--headless=new --no-first-run --no-default-browser-check"];
      if (ff === "desktop") args.push("--preset=desktop");
      // Lighthouse sometimes fails to record a trace (NO_NAVSTART); such a run is repeated, up to 3 times.
      for (let attempt = 1; ; attempt++) {
        try { execFileSync("npx", args, { stdio: ["ignore", "ignore", "inherit"] }); break; }
        catch (e) { if (attempt >= 3) throw e; console.log(`  retrying ${name} ${ff} run ${i} (attempt ${attempt} failed)`); }
      }
      const r = JSON.parse(readFileSync(file, "utf8"));
      const a = r.audits;
      rows.push({
        performance: Math.round(r.categories.performance.score * 100),
        accessibility: Math.round(r.categories.accessibility.score * 100),
        bestPractices: Math.round(r.categories["best-practices"].score * 100),
        seo: Math.round(r.categories.seo.score * 100),
        fcp: a["first-contentful-paint"].numericValue,
        lcp: a["largest-contentful-paint"].numericValue,
        tbt: a["total-blocking-time"].numericValue,
        cls: a["cumulative-layout-shift"].numericValue,
        si: a["speed-index"].numericValue,
        ttfb: a["server-response-time"]?.numericValue ?? null,
        bytes: a["total-byte-weight"].numericValue,
        requests: a["network-requests"]?.details?.items?.length ?? null,
      });
      // keep the JSON small: drop the screenshots and full-page image
      delete r.audits["full-page-screenshot"]; delete r.audits["screenshot-thumbnails"]; delete r.audits["final-screenshot"];
      writeFileSync(file, JSON.stringify(r));
      process.stdout.write(`${label} ${ff} ${name} run ${i}: perf ${rows.at(-1).performance}, LCP ${(rows.at(-1).lcp / 1000).toFixed(2)} s\n`);
    }
    const med = {};
    for (const k of Object.keys(rows[0])) med[k] = rows[0][k] === null ? null : median(rows.map((r) => r[k]));
    summary.results[`${name}-${ff}`] = { runs: rows, median: med };
  }
}
writeFileSync(join(out, "summary.json"), JSON.stringify(summary, null, 2));
console.log(`summary written to ${join(out, "summary.json")}`);
