// In-memory transport for synthetic UI previews; never included by application views.
window.axios = {
    async get(url, config = {}) {
        const match = url.match(/^\/chat\/rooms\/(\d+)(.*)$/);
        if (match) {
            const room = window.previewRooms[match[1]];
            if (!room) throw new Error('Unknown preview conversation');
            if (match[2] === '/messages/search') {
                const page = new DOMParser().parseFromString(room.html, 'text/html');
                const keyword = String(config.params?.keyword || '').toLowerCase();
                const results = [...page.querySelectorAll('.message-item')].filter(message => message.textContent.toLowerCase().includes(keyword))
                    .map(message => ({ message_id: Number(message.dataset.messageId), snippet: message.querySelector('.teams-message-bubble')?.textContent.trim() || '' }));
                return { data: { results } };
            }
            if (match[2] === '/reads') return { data: { readers: [] } };
            return { data: { ...room, type: room.room_type } };
        }
        if (url === '/chat/calls/current') return { data: { call: null } };
        throw new Error('This action is not available in the synthetic preview.');
    },
    async post(url) {
        if (url === '/chat/presence/ping' || url.endsWith('/read') || url.endsWith('/typing')) return { data: {} };
        throw new Error('Preview only: no messages, calls or groups are created.');
    },
};
