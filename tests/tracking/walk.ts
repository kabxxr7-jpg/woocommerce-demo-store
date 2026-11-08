// End-to-end tracking test on the running store: walks the real WooCommerce funnel in headless Chrome,
// records every dataLayer push, then checks the GA4 ecommerce events with the tracking-kit validator.
//   npx tsx tests/tracking/walk.ts                 (store on http://127.0.0.1:8081)
//   BASE=https://shop.kabiirlabs.com npx tsx tests/tracking/walk.ts
// Requests to Google hosts are answered locally with an empty response, so a test run never reaches GA4.
import { chromium, type Page } from "playwright-core";
import type { Capture, Push } from "./types.js";
import { ecommerceEvents, summarize, validate } from "./validator.js";

const BASE = (process.env.BASE || "http://127.0.0.1:8081").replace(/\/$/, "");
const TEST_EMAIL = "ada.example@example.com";
const TEST_LAST = "Examplesson";

const RECORDER = `(function(){function rec(k,v){var j;try{j=JSON.stringify(v===undefined?null:v)}catch(e){j='{"unserializable":true}'}try{window.__rec(JSON.stringify({kind:k,page:location.href,json:j}))}catch(e){}}
function isArgs(v){return Object.prototype.toString.call(v)==="[object Arguments]"}
function item(i){isArgs(i)||Array.isArray(i)?rec("command",Array.prototype.slice.call(i)):rec("object",i)}
function patch(a){if(!Array.isArray(a)||a.__p)return a;Object.defineProperty(a,"__p",{value:true});a.forEach(item);var o=a.push;a.push=function(){for(var i=0;i<arguments.length;i++)item(arguments[i]);return o.apply(this,arguments)};return a}
var cur=patch([]);Object.defineProperty(window,"dataLayer",{configurable:true,get:function(){return cur},set:function(v){cur=patch(v)}})})();`;

async function main() {
  const browser = await chromium.launch({ channel: process.env.PW_CHANNEL || "chrome", headless: true });
  // Live runs use the normal Chrome user agent: GA4 drops hits from "HeadlessChrome" as bot traffic.
  const ua = (await browser.newPage().then(async (p) => { const u = await p.evaluate(() => navigator.userAgent); await p.close(); return u; })).replace("HeadlessChrome", "Chrome");
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, ...(process.env.LIVE === "1" ? { userAgent: ua } : {}) });
  const capture: Capture = { pushes: [], hits: [], scripts: [], blocked: [] };
  let seq = 0;
  await context.exposeBinding("__rec", (_s, payload: string) => {
    const p = JSON.parse(payload);
    capture.pushes.push({ seq: ++seq, page: p.page, kind: p.kind, data: JSON.parse(p.json) } as Push);
  });
  await context.addInitScript(RECORDER);
  // LIVE=1: let the real Google Tag Manager and GA4 requests through (marked for GA4 DebugView with ?pp_ga_debug=1)
  // and record every GA4 hit. Default: answer them locally so a test run never reaches GA4.
  const LIVE = process.env.LIVE === "1";
  const ga4Hits: { en: string; dbg: boolean; tid: string }[] = [];
  if (LIVE) {
    context.on("request", (r) => {
      const u = new URL(r.url());
      if (!/\/g\/collect$/.test(u.pathname)) return;
      const lines = [u.search.slice(1), ...(r.postData() || "").split("\r\n")].filter(Boolean);
      for (const line of lines.length > 1 ? lines.slice(1).map((l) => u.search.slice(1) + "&" + l) : lines) {
        const q = new URLSearchParams(line);
        ga4Hits.push({ en: q.get("en") || "", dbg: q.get("_dbg") === "1" || q.get("ep.debug_mode") === "true", tid: q.get("tid") || "" });
      }
    });
  } else {
    await context.route(/google-analytics\.com|googletagmanager\.com|analytics\.google\.com|doubleclick\.net/, (r) =>
      r.fulfill({ status: 204, body: "" }),
    );
  }
  const page = await context.newPage();
  if (process.env.TRACE) page.on("request", (r) => /wc\/store\/v1\/(checkout|batch|cart\/update-customer)/.test(r.url()) && console.error(`\n  > ${r.method()} ${r.url()}`));
  page.on("pageerror", (e) => console.error(`\n  page error: ${e.message}`));
  page.on("response", async (r) => {
    if (r.status() >= 400 && !/google|doubleclick/.test(r.url())) {
      const body = await r.text().catch(() => "");
      console.error(`\n  HTTP ${r.status()} ${r.request().method()} ${r.url()} ${body.slice(0, 300)}`);
    }
  });
  const step = async (label: string, fn: () => Promise<unknown>) => {
    process.stdout.write(`- ${label} ... `);
    try {
      await fn();
    } catch (e) {
      const shot = process.env.FAIL_SHOT || "tracking-failure.png";
      await page.screenshot({ path: shot, fullPage: true }).catch(() => {});
      console.log(`failed (screenshot: ${shot})`);
      throw e;
    }
    await page.waitForTimeout(LIVE ? 3500 : 800); // let queued events drain (and, live, let GA4 send its batched hits)
    console.log("ok");
  };

  await step("home page, accept analytics", async () => {
    await page.goto(BASE + (LIVE ? "/?pp_ga_debug=1" : "/"), { waitUntil: "load" });
    await page.getByRole("button", { name: "Accept analytics" }).click();
  });
  await step("shop page (view_item_list)", () => page.goto(BASE + "/shop/", { waitUntil: "load" }));
  await step("product page (view_item)", () => page.goto(BASE + "/product/raspberry-garden-teapot/", { waitUntil: "load" }));
  await step("add 2 from the product page (add_to_cart via form)", async () => {
    await page.locator("input.qty").first().fill("2");
    await Promise.all([
      page.waitForResponse((r) => r.url().includes("/pp/v1/dl-queue"), { timeout: 15000 }).catch(() => {}),
      page.locator("button.single_add_to_cart_button, .wc-block-add-to-cart-form button[type=submit]").first().click(),
    ]);
  });
  await step("back to the shop, add another product from the grid (add_to_cart via Store API)", async () => {
    await page.goto(BASE + "/shop/", { waitUntil: "load" });
    const btn = page.locator(".wp-block-button.wc-block-components-product-button button, .add_to_cart_button").first();
    await btn.click();
    await page.waitForResponse((r) => r.url().includes("/pp/v1/dl-queue"), { timeout: 10000 }).catch(() => {});
  });
  await step("cart (view_cart)", () => page.goto(BASE + "/cart/", { waitUntil: "networkidle" }));
  await step("checkout (begin_checkout), fill test details, place order", async () => {
    await page.goto(BASE + "/checkout/", { waitUntil: "networkidle" });
    await fillCheckout(page);
    // The block checkout ignores a click while it is still saving the address (it pushes it to the server
    // after each field). Wait for that, then click; try again if the click landed during a save.
    const button = page.locator(".wc-block-components-checkout-place-order-button, #place_order").first();
    for (let attempt = 1; attempt <= 3 && !/order-received/.test(page.url()); attempt++) {
      await page.waitForLoadState("networkidle");
      await button.click();
      await page.waitForURL(/order-received/, { timeout: 15000 }).catch(() => {});
    }
    if (!/order-received/.test(page.url())) throw new Error("order was not placed after 3 clicks");
    await page.waitForLoadState("load");
  });
  await step("reload the thank-you page (purchase must not repeat)", () => page.reload({ waitUntil: "load" }));
  if (LIVE) await page.waitForTimeout(4000); // let the last hits leave
  await browser.close();
  if (LIVE) {
    console.log(`\nGA4 hits sent: ${ga4Hits.length} (measurement ids: ${[...new Set(ga4Hits.map((h) => h.tid))].join(", ")}; debug flag on ${ga4Hits.filter((h) => h.dbg).length})`);
    console.log("GA4 events sent: " + ga4Hits.map((h) => h.en).join(", "));
  }

  const events = ecommerceEvents(capture);
  console.log("\nEvents pushed: " + events.map((e) => e.name).join(", "));
  const results = validate(capture, { expectHits: false, requireFunnel: false, piiValues: [TEST_EMAIL, TEST_LAST] });
  for (const r of results) console.log(`${r.status.toUpperCase().padEnd(4)} ${r.title}${r.detail ? " - " + r.detail : ""}`);
  const names = new Set(events.map((e) => e.name));
  const must = ["view_item_list", "view_item", "add_to_cart", "view_cart", "begin_checkout", "purchase"];
  const missing = must.filter((m) => !names.has(m));
  const purchases = events.filter((e) => e.name === "purchase").length;
  const addToCart = events.filter((e) => e.name === "add_to_cart").length;
  const extra: string[] = [];
  if (missing.length) extra.push("missing events: " + missing.join(", "));
  if (purchases !== 1) extra.push(`purchase pushed ${purchases} times (expected exactly 1, including after reload)`);
  if (addToCart !== 2) extra.push(`add_to_cart pushed ${addToCart} times (expected 2: grid button and product form)`);
  const s = summarize(results);
  console.log(`\nvalidator: ${s.pass} pass, ${s.fail} fail, ${s.skip} skip`);
  for (const e of extra) console.log("FAIL " + e);
  if (process.env.DUMP) console.log(JSON.stringify(capture.pushes, null, 1));
  process.exit(s.fail + extra.length ? 1 : 0);
}

async function fillCheckout(page: Page) {
  const fill = async (sel: string, v: string) => {
    const el = page.locator(sel).first();
    if (await el.count()) await el.fill(v);
  };
  await fill("#email, input[name=email], #billing_email", TEST_EMAIL);
  await fill("#shipping-first_name, #billing-first_name, #billing_first_name", "Ada");
  await fill("#shipping-last_name, #billing-last_name, #billing_last_name", TEST_LAST);
  await fill("#shipping-address_1, #billing-address_1, #billing_address_1", "1 Test Street");
  await fill("#shipping-city, #billing-city, #billing_city", "Springfield");
  const state = page.locator("#shipping-state, #billing-state").first();
  if (await state.count()) {
    const tag = await state.evaluate((e) => e.tagName);
    tag === "SELECT" ? await state.selectOption("OR") : await state.fill("OR");
  }
  await fill("#shipping-postcode, #billing-postcode, #billing_postcode", "97477");
  await page.waitForTimeout(1500); // address validation and shipping rates refresh
}

main().catch((e) => {
  console.error(e);
  process.exit(3);
});
