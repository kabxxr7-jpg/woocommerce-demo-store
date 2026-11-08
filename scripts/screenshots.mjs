#!/usr/bin/env node
// Light-mode portfolio screenshots (2x) of the running store.  node scripts/screenshots.mjs [names...]
import { chromium } from "playwright-core";
const BASE = (process.env.BASE || "http://127.0.0.1:8081").replace(/\/$/, "");
const OUT = "portfolio";
const only = process.argv.slice(2);
const b = await chromium.launch({ channel: "chrome" });
const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: "light" });
await ctx.addInitScript(() => { try { localStorage.setItem("pp_consent", "denied"); } catch (e) {} }); // no banner in shots
await ctx.route(/googletagmanager|google-analytics/, (r) => r.fulfill({ status: 204, body: "" }));
const p = await ctx.newPage();
const shot = async (name, fn) => {
  if (only.length && !only.includes(name)) return;
  await fn();
  await p.waitForTimeout(800);
  await p.screenshot({ path: `${OUT}/${name}.png` });
  console.log("saved", name);
};
await shot("01-home", () => p.goto(BASE + "/", { waitUntil: "networkidle" }));
await shot("02-shop", async () => { await p.goto(BASE + "/shop/", { waitUntil: "networkidle" }); await p.evaluate(() => window.scrollTo(0, 260)); });
await shot("03-product", () => p.goto(BASE + "/product/raspberry-garden-teapot/", { waitUntil: "networkidle" }));
await shot("04-checkout", async () => {
  await p.goto(BASE + "/product/raspberry-garden-teapot/", { waitUntil: "load" });
  await p.click("form.cart button[type=submit], button.single_add_to_cart_button");
  await p.waitForLoadState("load");
  await p.goto(BASE + "/checkout/", { waitUntil: "networkidle" });
  await p.fill("#email", "ada@example.com"); await p.fill("#shipping-first_name", "Ada"); await p.fill("#shipping-last_name", "Example");
  await p.fill("#shipping-address_1", "1 Test Street"); await p.fill("#shipping-city", "Springfield"); await p.fill("#shipping-postcode", "97477");
  await p.waitForLoadState("networkidle"); await p.evaluate(() => window.scrollTo(0, 0));
});
await shot("05-about-speed", async () => { await p.goto(BASE + "/about/", { waitUntil: "networkidle" }); await p.evaluate(() => document.getElementById("speed")?.scrollIntoView()); await p.evaluate(() => window.scrollBy(0, -90)); });
if (!only.length || only.includes("06-phone")) {
  const m = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, colorScheme: "light", isMobile: true, hasTouch: true });
  await m.addInitScript(() => { try { localStorage.setItem("pp_consent", "denied"); } catch (e) {} });
  const mp = await m.newPage(); await mp.goto(BASE + "/", { waitUntil: "networkidle" }); await mp.waitForTimeout(800);
  await mp.screenshot({ path: `${OUT}/06-phone-home.png` }); console.log("saved 06-phone-home");
}
await b.close();
