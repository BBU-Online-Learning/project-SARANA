const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const source = fs.readFileSync('public/js/workspace.js', 'utf8');
const pageHtml = (title, content, bodyClass = '') => `<!doctype html><html><head><title>${title}</title></head>
<body class="application-shell ${bodyClass}">
<div id="workspace-navigation"><nav class="workspace-nav">
<a data-workspace-nav href="/classes">Classes</a><a data-workspace-nav href="/calendar">Calendar</a>
<a data-workspace-nav href="/slow">Slow</a><a data-workspace-nav href="/meeting-room">Meeting</a>
<a data-workspace-nav href="/inertia">React page</a>
</nav></div>
<button data-shell-toggle></button><button data-shell-collapse><span data-shell-collapse-label></span><i></i></button>
<button class="workspace-backdrop" data-shell-close hidden></button>
<div class="workspace-header-context">${title}</div><div id="workspace-navigation-status"></div>
<main id="main-content" tabindex="-1">${content}</main>
<nav class="workspace-mobile-nav"></nav><script src="/workspace.js" defer></script></body></html>`;

(async () => {
    const browser = await chromium.launch({ headless: true, channel: process.env.CALL_TEST_BROWSER || 'chrome' });
    try {
        const page = await browser.newPage();
        await page.route('http://workspace.test/**', async (route) => {
            const url = new URL(route.request().url());
            if (url.pathname === '/workspace.js') {
                await route.fulfill({ status: 200, contentType: 'text/javascript', body: source });
                return;
            }
            if (url.pathname === '/slow') await new Promise((resolve) => setTimeout(resolve, 500));
            const content = {
                '/home': '<h1>Home page</h1>',
                '/classes': `<h1>${url.searchParams.get('search') || 'All classes'}</h1>
                    <form method="GET" action="/classes" data-workspace-nav-form>
                    <input name="search" value="${url.searchParams.get('search') || ''}"><button>Search</button></form>`,
                '/calendar': '<h1>Calendar page</h1><script>window.calendarLoadedNormally = true</script>',
                '/slow': '<h1>Stale page</h1>',
                '/meeting-room': '<h1>Meeting room</h1><script>window.fullPageScriptRan = true</script>',
                '/inertia': '<div id="app"><h1>React page</h1></div><script>window.inertiaPageLoadedNormally = true</script>',
            }[url.pathname];
            await route.fulfill({ status: content ? 200 : 404, contentType: 'text/html',
                body: pageHtml(url.pathname, content || 'Not found', url.pathname === '/meeting-room' ? 'workspace-full-page' : '') });
        });

        await page.goto('http://workspace.test/home');
        await page.getByRole('link', { name: 'Classes' }).click();
        await page.getByRole('heading', { name: 'All classes' }).waitFor();
        await page.locator('input[name="search"]').fill('Algebra');
        await page.getByRole('button', { name: 'Search' }).click();
        await page.getByRole('heading', { name: 'Algebra' }).waitFor();
        assert.equal(new URL(page.url()).searchParams.get('search'), 'Algebra');

        await page.goBack();
        await page.getByRole('heading', { name: 'All classes' }).waitFor();
        await page.goBack();
        await page.getByRole('heading', { name: 'Home page' }).waitFor();

        await page.getByRole('link', { name: 'Slow' }).click();
        await page.getByRole('link', { name: 'Calendar' }).click();
        await page.getByRole('heading', { name: 'Calendar page' }).waitFor();
        await page.waitForTimeout(650);
        assert.equal(await page.locator('#main-content h1').textContent(), 'Calendar page');

        await page.getByRole('link', { name: 'Meeting' }).click();
        await page.getByRole('heading', { name: 'Meeting room' }).waitFor();
        assert.equal(await page.evaluate(() => window.fullPageScriptRan), true);
        await page.getByRole('link', { name: 'Calendar' }).click();
        await page.getByRole('heading', { name: 'Calendar page' }).waitFor();
        assert.equal(await page.evaluate(() => window.calendarLoadedNormally), true);
        await page.getByRole('link', { name: 'React page' }).click();
        await page.getByRole('heading', { name: 'React page' }).waitFor();
        assert.equal(await page.evaluate(() => window.inertiaPageLoadedNormally), true);
        await page.getByRole('link', { name: 'Calendar' }).click();
        await page.getByRole('heading', { name: 'Calendar page' }).waitFor();
        assert.equal(await page.evaluate(() => window.calendarLoadedNormally), true);
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });
