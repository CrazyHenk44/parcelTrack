import puppeteer from "puppeteer";

const trackingNumber = process.argv[2];
if (!trackingNumber) {
  console.error("Missing tracking number argument");
  process.exit(1);
}

const browser = await puppeteer.launch({
  headless: true,
  executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium',
  args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"]
});

const page = await browser.newPage();
await page.goto("https://www.gofoexpress.nl/track/", { waitUntil: "domcontentloaded" });

// vervanging voor page.waitForTimeout(5000)
await new Promise(r => setTimeout(r, 5000));

const result = await page.evaluate(async (tn) => {
  const res = await fetch("https://www.gofoexpress.nl/open-api/official/track/queryTrackV2", {
    method: "POST",
    headers: { "Content-Type": "application/json", lang: "en" },
    body: JSON.stringify({ numberList: [tn], year: new Date().getFullYear() }),
  });
  return await res.json();
}, trackingNumber);

console.log(JSON.stringify(result));
await browser.close();
