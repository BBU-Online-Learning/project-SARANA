const { createHmac } = require('node:crypto');
const topics = JSON.parse(process.env.GROUP_TEST_TOPICS);
const sockets = [];
function connect(index) {
    const topic = topics[index];
    const ws = new WebSocket(`ws://127.0.0.1:${process.env.GROUP_TEST_PORT}/app/${process.env.GROUP_TEST_KEY}?protocol=7&client=js&version=8.4.0&flash=false`);
    sockets[index] = ws;
    ws.addEventListener('message', event => {
        const packet = JSON.parse(event.data);
        if (packet.event === 'pusher:connection_established') {
            const { socket_id } = JSON.parse(packet.data);
            // Test-only signing simulates a legacy socket authorized before deployment/removal.
            const channelData = topic.startsWith('presence-') ? JSON.stringify({ user_id: 'legacy-test', user_info: {} }) : null;
            const signature = createHmac('sha256', process.env.GROUP_TEST_SECRET)
                .update(`${socket_id}:${topic}${channelData ? ':' + channelData : ''}`).digest('hex');
            ws.send(JSON.stringify({ event: 'pusher:subscribe', data: {
                channel: topic, auth: `${process.env.GROUP_TEST_KEY}:${signature}`,
                ...(channelData ? { channel_data: channelData } : {}),
            } }));
        } else if (packet.event === 'pusher_internal:subscription_succeeded') {
            console.log(`ready:${index}`);
        } else if (['message.updated', 'sidebar.updated'].includes(packet.event)) {
            console.log(`${packet.event}:${index}`);
        } else if (packet.event === 'pusher:error') {
            throw Error('Reverb rejected test subscription');
        }
    });
    ws.addEventListener('error', () => { console.error('Test websocket failed'); process.exitCode = 1; });
}
topics.forEach((_topic, index) => connect(index));
process.stdin.setEncoding('utf8');
process.stdin.on('data', text => {
    if (text.includes('reconnect')) { sockets[1].close(); connect(1); }
    if (text.includes('stop')) { sockets.forEach(socket => socket.close()); process.exit(process.exitCode || 0); }
});
setTimeout(() => { console.error('Test websocket deadline exceeded'); process.exit(1); }, 20000).unref();
