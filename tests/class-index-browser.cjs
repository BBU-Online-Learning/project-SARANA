const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const assert = require("node:assert/strict");
const { createServer } = require("node:http");
const fs = require("node:fs");
const path = require("node:path");

const manifest = JSON.parse(fs.readFileSync("public/build/manifest.json", "utf8"));
const entry = manifest["resources/js/inertia.jsx"];
const buildDirectory = path.resolve("public/build/assets");
let partialRequests = 0;

function schoolClass(id, archived) {
    return {
        id, name: archived ? "History archive" : "Biology class", description: archived ? "Last year" : "Current lessons",
        archived, memberCount: 6, channelCount: 4, creatorName: "Teacher One", joinCode: "TESTCODE",
        canManage: true, showUrl: `/classes/${id}`,
    };
}

function pageData(url) {
    const parsed = new URL(url, "http://localhost");
    const role = parsed.pathname === "/student" ? "student" : parsed.pathname === "/admin" ? "admin" : "teacher";
    const status = parsed.searchParams.get("status") || "all";
    const search = parsed.searchParams.get("search") || "";
    const classes = [schoolClass(1, false), schoolClass(2, true)]
        .filter((item) => (status === "all" || (status === "archived") === item.archived)
            && (!search || `${item.name} ${item.description}`.toLowerCase().includes(search.toLowerCase())));
    const isAdministrator = role === "admin";
    return {
        component: "Classes/Index", url: parsed.pathname + parsed.search, version: "test",
        props: {
            errors: {}, title: "Classes", role, isAdministrator, canCreateClass: role !== "student",
            introduction: "Open your classes and start learning.",
            summary: { total: 2, active: 1, archived: 1 }, filters: { search, status },
            urls: { index: parsed.pathname, active: `${parsed.pathname}?status=active`, archived: `${parsed.pathname}?status=archived`, create: "/classes", join: "/classes/join", academics: isAdministrator ? "/academics" : null },
            oldInput: { name: "", description: "", ownerId: "", joinCode: "" },
            eligibleTeachers: isAdministrator ? [{ id: 9, name: "Teacher One" }] : [],
            classes,
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
    const data = pageData(request.url);
    if (request.headers["x-inertia"]) {
        if (request.headers["x-inertia-partial-data"]?.includes("classes")) partialRequests += 1;
        response.writeHead(200, { "Content-Type": "application/json", "X-Inertia": "true", Vary: "X-Inertia" }).end(JSON.stringify(data));
        return;
    }
    const safeData = JSON.stringify(data).replaceAll("<", "\\u003c");
    response.writeHead(200, { "Content-Type": "text/html" }).end(`<!doctype html><html><head><meta name="csrf-token" content="test"><title>Classes</title></head><body>
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
        await page.goto(`http://127.0.0.1:${server.address().port}/classes`);
        await page.getByRole("heading", { name: "My Classes" }).waitFor();
        assert.equal(await page.locator("#create-class").count(), 1);
        assert.equal(await page.locator("#join-class").count(), 1);
        assert.equal(await page.locator(".learning-class-card").count(), 2);
        await page.evaluate(() => { window.__pageMarker = 42; });
        await page.getByRole("link", { name: /Archived classes/ }).click();
        await page.getByRole("heading", { name: "History archive" }).waitFor();
        assert.equal(await page.locator(".learning-class-card").count(), 1);
        assert.equal(await page.evaluate(() => window.__pageMarker), 42, "status filtering must keep the page mounted");
        await page.getByRole("searchbox", { name: "Search classes" }).fill("missing");
        await page.getByRole("button", { name: "Apply filters" }).click();
        await page.getByText("No classes match these filters.", { exact: false }).waitFor();
        assert.ok(partialRequests >= 2, "filtering must use Inertia partial updates");

        await page.goto(`http://127.0.0.1:${server.address().port}/student`);
        await page.getByRole("heading", { name: "My Classes" }).waitFor();
        assert.equal(await page.locator("#create-class").count(), 0);
        assert.equal(await page.locator("#join-class").count(), 1);
        await page.goto(`http://127.0.0.1:${server.address().port}/admin`);
        await page.getByRole("heading", { name: "Class administration" }).waitFor();
        assert.equal(await page.locator("#enrollment-owner option").count(), 2);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
        server.close();
    }
})().catch((error) => { console.error(error); server.close(); process.exitCode = 1; });
