const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const source = fs.readFileSync("public/js/settings.js", "utf8");

async function check(initialPermission, resultPermission) {
    const button = { hidden: false };
    const status = { textContent: "" };
    const previews = [];
    const previewButtons = [
        { dataset: { previewSound: "message", toneSelect: "setting-message-tone" } },
        { dataset: { previewSound: "call", toneSelect: "setting-call-tone" } },
    ];
    const Notification = {
        permission: initialPermission,
        async requestPermission() { this.permission = resultPermission; return resultPermission; },
    };
    const document = {
        addEventListener(name, callback) { this[name] = callback; },
        getElementById(id) {
            if (id === "settings-browser-permission") return button;
            if (id === "settings-browser-permission-status") return status;
            return { value: id === "setting-message-tone" ? "chime" : "bright" };
        },
    };
    const window = {
        Notification,
        MessageSounds: { preview(tone) { previews.push(["message", tone]); } },
        CallSounds: { preview(tone) { previews.push(["call", tone]); } },
    };
    vm.runInNewContext(source, { document, window, Notification });
    previewButtons.forEach(preview => document.click({ target: { closest(selector) { return selector === "[data-preview-sound]" ? preview : null; } } }));
    assert.deepEqual(previews, [["message", "chime"], ["call", "bright"]]);
    if (initialPermission === "default") {
        assert.equal(button.hidden, false);
        await document.click({ target: { closest(selector) { return selector === "#settings-browser-permission" ? button : null; } } });
        assert.equal(button.hidden, true);
    } else {
        assert.equal(button.hidden, true);
    }
    window.SettingsPage.refresh();
    return status.textContent;
}

(async () => {
    assert.match(await check("default", "granted"), /enabled/);
    assert.match(await check("default", "denied"), /blocked/);
    assert.match(await check("granted", "granted"), /enabled/);
    console.log("Settings: browser permission controls passed.");
})().catch(error => { console.error(error); process.exitCode = 1; });
