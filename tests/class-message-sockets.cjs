const { createHmac } = require('node:crypto');
const assert = require('node:assert/strict');
const WebSocket = require('ws');
const topics = JSON.parse(process.env.CLASS_TEST_TOPICS);
const sockets = [];
function connect(index) {
    const ws = new WebSocket(
        `ws://127.0.0.1:${process.env.CLASS_TEST_PORT}/app/${process.env.CLASS_TEST_KEY}?protocol=7&client=js&version=8.4.0&flash=false`,
        { origin: 'http://127.0.0.1' },
    );
    sockets[index] = ws;
    ws.addEventListener('message', event => {
        const packet = JSON.parse(event.data);
        if (packet.event === 'pusher:connection_established') {
            const { socket_id } = JSON.parse(packet.data);
            const signature = createHmac('sha256', process.env.CLASS_TEST_SECRET).update(`${socket_id}:${topics[index]}`).digest('hex');
            ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: topics[index], auth: `${process.env.CLASS_TEST_KEY}:${signature}` } }));
        } else if (packet.event === 'pusher_internal:subscription_succeeded') {
            console.log(`ready:${index}`);
        } else if (packet.event === 'school-class.messages.changed') {
            assert.deepEqual(JSON.parse(packet.data), []);
            console.log(`changed:${index}`);
        } else if (packet.event === 'pusher:error') {
            throw Error(`Reverb rejected the test subscription: ${JSON.stringify(packet.data)}`);
        }
    });
    ws.addEventListener('error', () => { console.error('Test websocket failed'); process.exitCode = 1; });
}
topics.forEach((_topic, index) => connect(index));
process.stdin.setEncoding('utf8');
process.stdin.on('data', text => {
    if (text.includes('reconnect')) { sockets[1].close(); connect(1); }
    if (text.includes('stop')) { sockets.forEach(socket => socket.close()); process.exit(0); }
});
setTimeout(() => { console.error('Test websocket deadline exceeded'); process.exit(1); }, 20000).unref();
