const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const assert = require("node:assert/strict");
const { createServer } = require("node:http");
const fs = require("node:fs");
const path = require("node:path");

const manifest = JSON.parse(fs.readFileSync("public/build/manifest.json", "utf8"));
const entry = manifest["resources/js/inertia.jsx"];
const buildDirectory = path.resolve("public/build/assets");

function meeting(id, group, roomUrl = null) {
    return {
        id, title: `Biology meeting ${id}`, status: "scheduled", rescheduled: false,
        day: "29", month: "Sep", time: "Tue, Sep 29, 2026 · 9:00 AM–10:00 AM Asia/Bangkok",
        host: "Teacher One", repeat: null, group, detailsUrl: `/details/${id}`, roomUrl,
    };
}

function pageData(page) {
    return {
        component: "Meetings/Index", url: page === 1 ? "/meetings" : "/meetings?page=2", version: "test",
        props: {
            errors: {}, title: "Meetings · Biology 10A",
            schoolClass: { name: "Biology 10A", archived: false, academicYear: "2026", overviewUrl: "/classes/1" },
            classesUrl: "/classes", sections: [{ label: "Overview", url: "/classes/1" }, { label: "Meetings", url: "/meetings" }],
            scheduleUrl: "/meetings/create",
            meetings: page === 1 ? [meeting(1, "ready", "/room/1"), meeting(2, "upcoming")] : [meeting(3, "earlier")],
            pagination: { page, lastPage: 2, previousUrl: page === 2 ? "/meetings" : null, nextUrl: page === 1 ? "/meetings?page=2" : null },
        },
    };
}

const server = createServer((request, response) => {
    if (request.url?.startsWith("/build/assets/")) {
        const asset = path.resolve("public", `.${request.url}`);
        if (!asset.startsWith(`${buildDirectory}${path.sep}`) || !fs.existsSync(asset)) {
            response.writeHead(404).end();
            return;
        }
        response.writeHead(200, { "Content-Type": "text/javascript" }).end(fs.readFileSync(asset));
        return;
    }
    const data = pageData(request.url?.includes("page=2") ? 2 : 1);
    if (request.headers["x-inertia"]) {
        response.writeHead(200, { "Content-Type": "application/json", "X-Inertia": "true", Vary: "X-Inertia" }).end(JSON.stringify(data));
        return;
    }
    const safeData = JSON.stringify(data).replaceAll("<", "\\u003c");
    response.writeHead(200, { "Content-Type": "text/html" }).end(`<!doctype html><html><head><title>Meetings</title></head><body>
        <main id="main-content"><script data-page="app" type="application/json">${safeData}</script><div id="app"></div></main>
        <script type="module" src="/build/${entry.file}"></script></body></html>`);
});

(async () => {
    await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
    const browser = await chromium.launch({ headless: true, channel: process.env.CALL_TEST_BROWSER || "chrome" });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on("pageerror", (error) => errors.push(error.message));
        await page.goto(`http://127.0.0.1:${server.address().port}/meetings`);
        await page.getByRole("heading", { name: "Class meetings" }).waitFor();
        assert.equal(await page.getByRole("link", { name: "Join meeting" }).count(), 1);
        assert.equal(await page.getByRole("link", { name: "Schedule meeting" }).count(), 1);
        assert.equal(await page.getByRole("heading", { name: "Ready to join" }).count(), 1);
        assert.equal(await page.getByRole("heading", { name: "Coming up" }).count(), 1);
        await page.getByRole("link", { name: "Next" }).click();
        await page.getByRole("heading", { name: "Earlier meetings" }).waitFor();
        assert.equal(await page.getByText("Page 2 of 2").count(), 1);
        assert.equal(await page.getByRole("link", { name: "Join meeting" }).count(), 0);
        assert.equal(await page.locator("#app").count(), 1);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
        server.close();
    }
})().catch((error) => { console.error(error); server.close(); process.exitCode = 1; });
