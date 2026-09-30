const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const assert = require("node:assert/strict");
const { createServer } = require("node:http");
const fs = require("node:fs");
const path = require("node:path");

const manifest = JSON.parse(fs.readFileSync("public/build/manifest.json", "utf8"));
const buildDirectory = path.resolve("public/build/assets");
const entry = manifest["resources/js/inertia.jsx"];
let submitted = null;

function pageData() {
    return {
        component: "ReportingPeriods/Index", url: "/academics/reporting-periods", version: "test",
        props: {
            errors: {}, title: "Reporting periods", success: null,
            years: [
                { id: 1, name: "2026", startsOn: "2026-01-01", endsOn: "2026-12-31" },
                { id: 2, name: "2027", startsOn: "2027-01-01", endsOn: "2027-12-31" },
            ],
            periods: [
                { id: 3, academicYearId: 1, academicYearName: "2026", parentId: null, name: "Semester 1", code: "S1", sequence: 1, startsOn: "2026-01-01", endsOn: "2026-06-30", status: "draft" },
                { id: 4, academicYearId: 2, academicYearName: "2027", parentId: null, name: "Next year", code: "NEXT", sequence: 1, startsOn: "2027-01-01", endsOn: "2027-06-30", status: "open" },
            ],
            urls: { index: "/academics/reporting-periods", store: "/academics/reporting-periods", academics: "/academics" },
        },
    };
}

const server = createServer(async (request, response) => {
    if (request.url?.startsWith("/build/assets/")) {
        const asset = path.resolve("public", `.${request.url}`);
        if (!asset.startsWith(`${buildDirectory}${path.sep}`) || !fs.existsSync(asset)) {
            response.writeHead(404).end();
            return;
        }
        response.writeHead(200, { "Content-Type": "text/javascript" }).end(fs.readFileSync(asset));
        return;
    }
    if (request.method !== "GET") {
        let body = "";
        for await (const chunk of request) body += chunk;
        submitted = { method: request.method, url: request.url, body: JSON.parse(body) };
        response.writeHead(303, { Location: "/academics/reporting-periods" }).end();
        return;
    }
    const data = pageData();
    if (request.headers["x-inertia"]) {
        response.writeHead(200, { "Content-Type": "application/json", "X-Inertia": "true", Vary: "X-Inertia" }).end(JSON.stringify(data));
        return;
    }
    const safeData = JSON.stringify(data).replaceAll("<", "\\u003c");
    response.writeHead(200, { "Content-Type": "text/html" }).end(`<!doctype html><html><head><meta name="csrf-token" content="test"><title>Reporting periods</title></head><body>
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
        await page.goto(`http://127.0.0.1:${server.address().port}/academics/reporting-periods`);
        await page.getByRole("heading", { name: "Reporting periods" }).waitFor();
        assert.equal(await page.getByRole("article").count(), 1);
        await page.getByLabel("Show academic year").selectOption("2");
        await page.getByText("Next year").waitFor();
        assert.equal(await page.getByRole("article").count(), 1);
        await page.getByLabel("Show academic year").selectOption("1");
        await page.getByRole("button", { name: "Edit" }).click();
        assert.equal(await page.getByLabel("Name", { exact: true }).inputValue(), "Semester 1");
        assert.equal(await page.getByLabel("Academic year", { exact: true }).inputValue(), "1");
        await page.getByLabel("Name", { exact: true }).fill("First semester");
        await page.getByRole("button", { name: "Save changes" }).click();
        await page.waitForFunction(() => document.querySelector("#period-name")?.value === "");
        assert.equal(submitted?.method, "PATCH");
        assert.equal(submitted?.url, "/academics/reporting-periods/3");
        assert.equal(submitted?.body.name, "First semester");
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
        server.close();
    }
})().catch((error) => { console.error(error); server.close(); process.exitCode = 1; });
