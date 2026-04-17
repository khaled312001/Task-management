const express = require('express');
const cors = require('cors');
const qrcode = require('qrcode');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, delay } = require('@whiskeysockets/baileys');
const pino = require('pino');

const app = express();
app.use(cors());
app.use(express.json());

const sessions = new Map();
const AUTH_DIR = __dirname + '/auth_sessions';

async function startSession(sessionId) {
    if (sessions.has(sessionId) && sessions.get(sessionId).connected) {
        return sessions.get(sessionId);
    }

    const sessionData = {
        socket: null,
        qr: null,
        connected: false,
        phone: null,
    };
    sessions.set(sessionId, sessionData);

    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR + '/' + sessionId);

    const sock = makeWASocket({
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        browser: ['Barmagly Tasks', 'Chrome', '120.0'],
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr: qrCode } = update;

        if (qrCode) {
            try {
                sessionData.qr = await qrcode.toDataURL(qrCode);
                sessionData.connected = false;
                console.log(`[${sessionId}] QR Code generated`);
            } catch (e) {
                console.error('QR error:', e.message);
            }
        }

        if (connection === 'open') {
            sessionData.connected = true;
            sessionData.qr = null;
            sessionData.phone = sock.user?.id?.split(':')[0] || sock.user?.id?.split('@')[0] || null;
            console.log(`[${sessionId}] Connected! Phone: ${sessionData.phone}`);
        }

        if (connection === 'close') {
            const reason = lastDisconnect?.error?.output?.statusCode;
            console.log(`[${sessionId}] Disconnected. Reason: ${reason}`);

            if (reason !== DisconnectReason.loggedOut) {
                // Reconnect
                console.log(`[${sessionId}] Reconnecting...`);
                sessions.delete(sessionId);
                await delay(3000);
                startSession(sessionId);
            } else {
                sessionData.connected = false;
                sessionData.qr = null;
                sessionData.phone = null;
                sessions.delete(sessionId);
                // Clean auth
                const fs = require('fs');
                try { fs.rmSync(AUTH_DIR + '/' + sessionId, { recursive: true, force: true }); } catch(e) {}
            }
        }
    });

    sessionData.socket = sock;
    return sessionData;
}

// Start / connect session
app.post('/session/start', async (req, res) => {
    const { sessionId } = req.body;
    if (!sessionId) return res.json({ error: 'sessionId required' });

    try {
        const session = await startSession(sessionId);
        // Wait for QR to generate
        await delay(4000);
        const s = sessions.get(sessionId);
        res.json({
            success: true,
            qr: s?.qr || null,
            connected: s?.connected || false,
            phone: s?.phone || null,
        });
    } catch (e) {
        console.error('Start error:', e.message);
        res.json({ success: false, error: e.message });
    }
});

// Get QR
app.get('/session/qr/:sessionId', (req, res) => {
    const session = sessions.get(req.params.sessionId);
    if (!session) return res.json({ qr: null, connected: false });
    res.json({ qr: session.qr, connected: session.connected, phone: session.phone });
});

// Get status
app.get('/session/status/:sessionId', (req, res) => {
    const session = sessions.get(req.params.sessionId);
    if (!session) return res.json({ connected: false });
    res.json({ connected: session.connected, phone: session.phone });
});

// Stop session
app.post('/session/stop', async (req, res) => {
    const { sessionId } = req.body;
    const session = sessions.get(sessionId);
    if (session?.socket) {
        try { await session.socket.logout(); } catch (e) {}
        try { session.socket.end(); } catch (e) {}
    }
    sessions.delete(sessionId);
    res.json({ success: true });
});

// Send message
app.post('/message/send', async (req, res) => {
    const { sessionId, phone, message } = req.body;
    const session = sessions.get(sessionId);

    if (!session || !session.connected) {
        return res.json({ success: false, error: 'Session not connected' });
    }

    try {
        let formattedPhone = phone.replace(/[^0-9]/g, '');
        if (!formattedPhone.endsWith('@s.whatsapp.net')) {
            formattedPhone = formattedPhone + '@s.whatsapp.net';
        }

        const result = await session.socket.sendMessage(formattedPhone, { text: message });
        res.json({ success: true, messageId: result?.key?.id });
    } catch (e) {
        res.json({ success: false, error: e.message });
    }
});

// Health check
app.get('/health', (req, res) => {
    const activeSessions = [];
    for (const [id, s] of sessions) {
        activeSessions.push({ id, connected: s.connected, phone: s.phone });
    }
    res.json({ status: 'ok', sessions: activeSessions });
});

const PORT = process.env.WA_PORT || 3001;
app.listen(PORT, () => {
    console.log(`WhatsApp Baileys Service running on port ${PORT}`);
});
