const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const assert = require("node:assert/strict");
const { createServer } = require("node:http");
const fs = require("node:fs");
const path = require("node:path");

const manifest = JSON.parse(fs.readFileSync("public/build/manifest.json", "utf8"));
const entry = manifest["resources/js/meeting-room.jsx"];
const buildDirectory = path.resolve("public/build/assets");
const html = (canManage, waitingUrl = '', joinRequestsUrl = '') => `<!doctype html><html><head><meta name="csrf-token" content="test"></head>
<body class="learning-workspace meeting-room-page"><div id="meeting-react-root"
data-meeting-title="Biology review" data-class-name="Science 1" data-start-label="Tue, Sep 29 · 9:00 AM"
data-back-url="/meetings/1" data-credentials-url="/credentials" data-can-manage="${canManage}"
data-waiting-room-url="${waitingUrl}" data-join-requests-url="${joinRequestsUrl}"
data-end-at="${new Date(Date.now() + 3600000).toISOString()}"></div>
<script type="module" src="/build/${entry.file}"></script></body></html>`;

let joinStatus = null;
let credentialRequests = 0;
const server = createServer((request, response) => {
    if (request.url === "/credentials") {
        credentialRequests += 1;
        response.writeHead(403, { "Content-Type": "application/json" });
        response.end("{}");
        return;
    }
    if (request.url === "/waiting") {
        if (request.method === "POST") joinStatus = "pending";
        if (request.method === "DELETE") joinStatus = "cancelled";
        response.writeHead(request.method === "POST" ? 201 : 200, { "Content-Type": "application/json" });
        response.end(JSON.stringify({ request: joinStatus ? { reference: "request-1", status: joinStatus, can_enter: joinStatus === "admitted" } : null, meeting_open: true, can_manage: false }));
        return;
    }
    if (request.url === "/join-requests" && request.method === "GET") {
        response.writeHead(200, { "Content-Type": "application/json" });
        response.end(JSON.stringify({ requests: joinStatus === "pending" ? [{ reference: "request-1", display_name: "Student Five", requested_at: new Date().toISOString() }] : [] }));
        return;
    }
    if (request.url === "/join-requests/request-1" && request.method === "PATCH") {
        joinStatus = "admitted";
        response.writeHead(200, { "Content-Type": "application/json" });
        response.end(JSON.stringify({ request: { reference: "request-1", status: joinStatus, can_enter: true } }));
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
    response.end(request.url === "/student" ? html('false', '/waiting') : request.url === "/host" ? html('true', '', '/join-requests') : html('true'));
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

        const hostPage = await browser.newPage();
        const studentPage = await browser.newPage();
        hostPage.on("pageerror", (error) => errors.push(error.message));
        studentPage.on("pageerror", (error) => errors.push(error.message));
        await hostPage.goto(`http://127.0.0.1:${server.address().port}/host`);
        await studentPage.goto(`http://127.0.0.1:${server.address().port}/student`);
        await studentPage.getByText("Request entry to join this class meeting.").waitFor();
        await studentPage.locator("#meeting-request-entry").click();
        await studentPage.getByText("Waiting for your teacher to admit you.").waitFor();
        assert.equal(await studentPage.locator("#meeting-connect").isDisabled(), true);
        await hostPage.locator("#meeting-waiting-top-count").getByText("1").waitFor({ timeout: 5000 });
        await hostPage.locator("#meeting-details-toggle").click();
        await hostPage.locator("#meeting-waiting-requests").getByText("Student Five").waitFor();
        await hostPage.locator("#meeting-waiting-requests").getByRole("button", { name: "Admit" }).click();
        await studentPage.getByText("Your teacher admitted you. Select Connect to meeting.").waitFor({ timeout: 5000 });
        assert.equal(await studentPage.locator("#meeting-connect").isEnabled(), true);
        assert.equal(credentialRequests, 1, "waiting students must not request LiveKit credentials");
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
        server.close();
    }
})().catch((error) => { console.error(error); server.close(); process.exitCode = 1; });
