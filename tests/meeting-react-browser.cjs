const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const assert = require("node:assert/strict");
const { createServer } = require("node:http");
const fs = require("node:fs");
const path = require("node:path");

const manifest = JSON.parse(fs.readFileSync("public/build/manifest.json", "utf8"));
const entry = manifest["resources/js/meeting-room.jsx"];
const buildDirectory = path.resolve("public/build/assets");
const html = `<!doctype html><html><head><meta name="csrf-token" content="test"></head>
<body class="learning-workspace meeting-room-page"><div id="meeting-react-root"
data-meeting-title="Biology review" data-class-name="Science 1" data-start-label="Tue, Sep 29 · 9:00 AM"
data-back-url="/meetings/1" data-credentials-url="/credentials" data-end-at="${new Date(Date.now() + 3600000).toISOString()}"></div>
<script type="module" src="/build/${entry.file}"></script></body></html>`;

const server = createServer((request, response) => {
    if (request.url === "/credentials") {
        response.writeHead(403, { "Content-Type": "application/json" });
        response.end("{}");
        return;
    }
    if (request.url.startsWith("/build/assets/")) {
        const asset = path.resolve("public", `.${request.url}`);
        if (!asset.startsWith(`${buildDirectory}${path.sep}`) || !fs.existsSync(asset)) {
            response.writeHead(404);
            response.end();
            return;
        }
        response.writeHead(200, { "Content-Type": "text/javascript" });
        response.end(fs.readFileSync(asset));
        return;
    }
    response.writeHead(200, { "Content-Type": "text/html" });
    response.end(html);
});

(async () => {
    await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
    const browser = await chromium.launch({ headless: true, channel: process.env.CALL_TEST_BROWSER || "chrome" });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on("pageerror", (error) => errors.push(error.message));
        await page.goto(`http://127.0.0.1:${server.address().port}/`);
        await page.locator("#meeting-connect").waitFor();
        assert.equal(await page.locator(".meeting-page-title strong").textContent(), "Biology review");
        assert.equal(await page.locator("#meeting-fullscreen").count(), 1);
        assert.equal(await page.locator("#meeting-chat-toggle").count(), 1);
        assert.equal(await page.locator("#meeting-hand").getAttribute("aria-pressed"), "false");
        await page.locator("#meeting-connect").click();
        await page.getByText("Meeting access is unavailable. Check the schedule and your class membership.").waitFor();
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
        server.close();
    }
})().catch((error) => { console.error(error); server.close(); process.exitCode = 1; });
