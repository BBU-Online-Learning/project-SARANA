(() => {
    'use strict';

    function setBusy(panel, busy) {
        panel?.classList.toggle('is-busy', busy);
        panel?.setAttribute('aria-busy', String(busy));
    }

    function replacePanel(panel, html, message) {
        const next = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-group-invite-panel]');
        if (!next) return;
        panel.replaceWith(next);
        const status = next.querySelector('[data-group-invite-status]');
        if (status) status.textContent = message || '';
    }

    async function submitInviteForm(form) {
        const panel = form.closest('[data-group-invite-panel]');
        setBusy(panel, true);
        try {
            const response = await axios.request({
                url: form.action,
                method: form.querySelector('[name="_method"]')?.value || form.method || 'POST',
                headers: { Accept: 'application/json' },
            });
            replacePanel(panel, response.data.html, response.data.message);
        } catch (error) {
            setBusy(panel, false);
            const status = panel?.querySelector('[data-group-invite-status]');
            if (status) status.textContent = error.response?.data?.message || 'The invitation could not be updated. Try again.';
        }
    }

    async function copyInvite(button) {
        const panel = button.closest('[data-group-invite-panel]');
        const input = panel?.querySelector('[data-group-invite-url]');
        if (!input) return;
        try {
            await navigator.clipboard.writeText(input.value);
        } catch {
            input.select();
            document.execCommand('copy');
            input.setSelectionRange(0, 0);
        }
        const status = panel.querySelector('[data-group-invite-status]');
        if (status) status.textContent = 'Invitation link copied.';
    }

    async function shareInvite(button) {
        const panel = button.closest('[data-group-invite-panel]');
        const url = panel?.querySelector('[data-group-invite-url]')?.value;
        if (!url) return;
        if (navigator.share) {
            try {
                await navigator.share({ title: button.dataset.shareTitle || 'Join group chat', url });
                return;
            } catch (error) {
                if (error.name === 'AbortError') return;
            }
        }
        await copyInvite(button);
    }

    async function downloadInviteQr(button) {
        const panel = button.closest('[data-group-invite-panel]');
        const qrImage = panel?.querySelector('.group-invite-qr img');
        const status = panel?.querySelector('[data-group-invite-status]');
        if (!qrImage) return;

        try {
            const image = new Image();
            await new Promise((resolve, reject) => {
                image.onload = resolve;
                image.onerror = reject;
                image.src = qrImage.src;
            });

            const canvas = document.createElement('canvas');
            canvas.width = 640;
            canvas.height = 640;
            const context = canvas.getContext('2d');
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise((resolve, reject) => {
                canvas.toBlob((result) => result ? resolve(result) : reject(new Error('PNG conversion failed.')), 'image/png');
            });
            const downloadUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = button.dataset.downloadName || 'group-invite.png';
            document.body.append(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
            if (status) status.textContent = 'QR code downloaded as PNG.';
        } catch {
            if (status) status.textContent = 'The QR code could not be downloaded. Try again.';
        }
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-group-invite-create], [data-group-invite-revoke]');
        if (!form) return;
        event.preventDefault();
        if (form.matches('[data-group-invite-revoke]')) {
            const confirmed = await window.AppConfirm?.ask({
                title: 'Disable invitation?',
                message: 'People will no longer be able to join with this link or QR code.',
                confirmLabel: 'Disable link',
                tone: 'danger',
            });
            if (!confirmed) return;
        }
        await submitInviteForm(form);
    });

    document.addEventListener('click', (event) => {
        const copy = event.target.closest('[data-copy-group-invite]');
        if (copy) copyInvite(copy);
        const share = event.target.closest('[data-share-group-invite]');
        if (share) shareInvite(share);
        const download = event.target.closest('[data-download-group-invite-qr]');
        if (download) downloadInviteQr(download);
    });
})();
