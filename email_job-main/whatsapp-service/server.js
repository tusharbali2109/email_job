/**
 * WhatsApp Automation Service for ReachOut
 * Runs locally on port 3001 — do NOT deploy this to cloud servers.
 *
 * Usage:
 *   cd whatsapp-service
 *   npm install
 *   node server.js
 */

const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const express = require('express');
const cors    = require('cors');
const QRCode  = require('qrcode');
const path    = require('path');
const fs      = require('fs');

const app  = express();
const PORT = 3001;

app.use(cors({ origin: '*' }));
app.use(express.json({ limit: '100mb' }));

/* ─── state ─── */
let waClient    = null;
let clientState = 'disconnected'; // disconnected | initializing | qr | ready
let currentQR   = null;

let sendQueue = [];   // [{id, name, phone, company, status, error}]
let isSending = false;
let isPaused  = false;
let isStopped = false;
let stats     = { total: 0, sent: 0, failed: 0, remaining: 0, current: null };
let sendLogs  = [];
let templateBody    = '';
let candidateMobile = '';
let candidateEmail  = '';
let resumePath      = '';

/* ─── helpers ─── */
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

function cleanPhone(raw) {
    let d = String(raw || '').replace(/\D/g, '');
    if (!d) return null;
    // strip leading zeros
    d = d.replace(/^0+/, '');
    // If 10 digits assume Indian number, prepend 91
    if (d.length === 10) d = '91' + d;
    return d + '@c.us';
}

function stripHtml(html) {
    return (html || '')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n\n')
        .replace(/<\/div>/gi, '\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function renderTemplate(tmpl, vars) {
    return tmpl
        .replace(/\{\{name\}\}/gi,    vars.name    || '')
        .replace(/\{\{company\}\}/gi, vars.company || '')
        .replace(/\{\{mobile\}\}/gi,  vars.mobile  || '')
        .replace(/\{\{email\}\}/gi,   vars.email   || '');
}

/* ─── WhatsApp client factory ─── */
function createClient() {
    return new Client({
        authStrategy: new LocalAuth({
            dataPath: path.join(__dirname, '.wwebjs_auth'),
        }),
        puppeteer: {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
            ],
        },
        webVersionCache: {
            type: 'remote',
            remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.2412.54.html',
        },
    });
}

/* ─── routes ─── */

// Initialize / reconnect WhatsApp session
app.post('/init', (req, res) => {
    if (clientState === 'ready') {
        return res.json({ success: true, state: 'ready' });
    }
    if (clientState === 'initializing' || clientState === 'qr') {
        return res.json({ success: true, state: clientState });
    }

    clientState = 'initializing';
    currentQR   = null;

    waClient = createClient();

    waClient.on('qr', async (qr) => {
        try {
            currentQR   = await QRCode.toDataURL(qr);
            clientState = 'qr';
        } catch (e) {
            console.error('QR generation failed:', e.message);
        }
    });

    waClient.on('authenticated', () => {
        console.log('[WA] Authenticated');
        currentQR = null;
    });

    waClient.on('ready', () => {
        clientState = 'ready';
        currentQR   = null;
        console.log('[WA] Client ready!');
    });

    waClient.on('auth_failure', (msg) => {
        clientState = 'disconnected';
        waClient    = null;
        console.error('[WA] Auth failure:', msg);
    });

    waClient.on('disconnected', (reason) => {
        clientState = 'disconnected';
        waClient    = null;
        console.log('[WA] Disconnected:', reason);
    });

    waClient.initialize().catch(err => {
        clientState = 'disconnected';
        console.error('[WA] Init error:', err.message);
    });

    res.json({ success: true, state: clientState });
});

// Disconnect / destroy session
app.post('/disconnect', async (req, res) => {
    if (waClient) {
        try { await waClient.destroy(); } catch (_) {}
        waClient = null;
    }
    clientState = 'disconnected';
    currentQR   = null;
    res.json({ success: true });
});

// Current status
app.get('/status', (req, res) => {
    res.json({ state: clientState, hasQR: !!currentQR });
});

// QR code as base64 data URL
app.get('/qr', (req, res) => {
    res.json({ qr: currentQR });
});

// Queue bulk send
app.post('/send', (req, res) => {
    if (clientState !== 'ready') {
        return res.status(400).json({ success: false, error: 'WhatsApp not connected' });
    }
    if (isSending) {
        return res.status(400).json({ success: false, error: 'Already sending' });
    }

    const { contacts, template, resume, candidateMobile: cm, candidateEmail: ce } = req.body;

    if (!contacts || !contacts.length) {
        return res.status(400).json({ success: false, error: 'No contacts provided' });
    }

    templateBody    = template   || '';
    resumePath      = resume     || '';
    candidateMobile = cm         || '';
    candidateEmail  = ce         || '';

    sendQueue = contacts.map(c => ({ ...c, status: 'pending', error: null }));
    stats     = { total: contacts.length, sent: 0, failed: 0, remaining: contacts.length, current: null };
    sendLogs  = [];
    isSending = true;
    isPaused  = false;
    isStopped = false;

    res.json({ success: true, total: contacts.length });

    // Process asynchronously
    processSendQueue().catch(err => console.error('[SEND] Queue error:', err));
});

// Pause / Resume / Stop
app.post('/control', (req, res) => {
    const { action } = req.body;
    if (action === 'pause')  isPaused = true;
    if (action === 'resume') isPaused = false;
    if (action === 'stop')   { isStopped = true; isSending = false; }
    res.json({ success: true, action, isPaused, isStopped });
});

// Live progress
app.get('/progress', (req, res) => {
    res.json({ ...stats, isPaused, isSending, isStopped });
});

// All logs
app.get('/logs', (req, res) => {
    res.json(sendLogs);
});

// Retry failed messages
app.post('/retry', (req, res) => {
    if (isSending) return res.status(400).json({ success: false, error: 'Already running' });

    const failedItems = sendQueue.filter(i => i.status === 'failed');
    if (!failedItems.length) return res.json({ success: true, retrying: 0 });

    failedItems.forEach(i => { i.status = 'pending'; i.error = null; });
    stats.remaining += failedItems.length;
    stats.failed     = 0;
    isSending        = true;
    isPaused         = false;
    isStopped        = false;

    res.json({ success: true, retrying: failedItems.length });

    processSendQueue().catch(err => console.error('[RETRY] Error:', err));
});

/* ─── send queue processor ─── */
async function processSendQueue() {
    for (let i = 0; i < sendQueue.length; i++) {
        if (isStopped) break;
        while (isPaused) await sleep(500);

        const item = sendQueue[i];
        if (item.status !== 'pending') continue;

        stats.current = item.name;

        const phoneId = cleanPhone(item.phone);
        if (!phoneId) {
            item.status = 'failed';
            item.error  = 'Invalid phone number';
            stats.failed++;
            stats.remaining--;
            pushLog(item);
            continue;
        }

        try {
            // Verify number is on WhatsApp
            const isRegistered = await waClient.isRegisteredUser(phoneId);
            if (!isRegistered) throw new Error('Number not registered on WhatsApp');

            // Render message for this contact
            const rawMsg = stripHtml(renderTemplate(templateBody, {
                name:    item.name,
                company: item.company,
                mobile:  candidateMobile,
                email:   candidateEmail,
            }));

            // Send text message
            await waClient.sendMessage(phoneId, rawMsg);

            // Attach resume if file exists
            if (resumePath && fs.existsSync(resumePath)) {
                const media = MessageMedia.fromFilePath(resumePath);
                await waClient.sendMessage(phoneId, media);
            }

            item.status = 'sent';
            stats.sent++;
            stats.remaining--;
            pushLog(item);

        } catch (err) {
            item.status = 'failed';
            item.error  = err.message;
            stats.failed++;
            stats.remaining--;
            pushLog(item);
        }

        // Delay between messages: 4-7 seconds (avoid rate limiting)
        if (i < sendQueue.length - 1 && !isStopped) {
            await sleep(4000 + Math.random() * 3000);
        }
    }

    isSending     = false;
    stats.current = null;
    console.log(`[SEND] Done — sent:${stats.sent} failed:${stats.failed}`);
}

function pushLog(item) {
    sendLogs.unshift({
        id:      item.id,
        name:    item.name,
        phone:   item.phone,
        company: item.company,
        status:  item.status,
        error:   item.error,
        time:    new Date().toISOString(),
    });
}

/* ─── start ─── */
app.listen(PORT, () => {
    console.log(`\n🟢 WhatsApp Service running at http://localhost:${PORT}`);
    console.log('   Open ReachOut → WhatsApp tab in your browser\n');
});
