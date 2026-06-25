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

// Serve the bulk-mailer frontend from the same server
app.use(express.static(path.join(__dirname, '..', 'bulk-mailer')));

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
                '--disable-extensions',
            ],
        },
    });
}

/* ─── routes ─── */

// Status page
app.get('/', (req, res) => {
    res.send(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WhatsApp Service — ReachOut</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0c0f14;--surface:#141820;--surface2:#1c2230;
  --border:#252d3d;--accent:#25d366;--accent2:#128c7e;
  --text:#e8edf5;--muted:#6b7a99;--danger:#ff5f6d;--warning:#ffd166;
}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;
  background-image:radial-gradient(ellipse 70% 50% at 50% -20%,rgba(37,211,102,.08) 0%,transparent 65%),
  radial-gradient(ellipse 40% 40% at 90% 90%,rgba(18,140,126,.05) 0%,transparent 50%);
  display:flex;align-items:flex-start;justify-content:center;padding:48px 16px;}
.container{width:100%;max-width:520px;}
.logo{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;letter-spacing:-1px;margin-bottom:4px;}
.logo span{color:#4fffb0;}
.tagline{font-size:13px;color:var(--muted);margin-bottom:32px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px;}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.card-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;}
.card-body{padding:20px;}
/* status pill */
.pill{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:99px;font-size:13px;font-weight:600;}
.pill-ready{background:rgba(37,211,102,.15);color:var(--accent);}
.pill-qr{background:rgba(255,209,102,.12);color:var(--warning);}
.pill-other{background:rgba(107,122,153,.12);color:var(--muted);}
.dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.dot-green{background:var(--accent);box-shadow:0 0 6px rgba(37,211,102,.6);}
.dot-yellow{background:var(--warning);animation:blink 1s infinite;}
.dot-red{background:var(--danger);}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
/* stats grid */
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;}
.stat-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;}
.stat-val{font-family:'Syne',sans-serif;font-size:26px;font-weight:700;}
.c-sent{color:var(--accent);}
.c-fail{color:var(--danger);}
.c-total{color:var(--accent2);}
.c-remain{color:var(--warning);}
/* progress */
.progress-track{width:100%;height:8px;background:var(--surface2);border-radius:99px;overflow:hidden;margin:12px 0;}
.progress-fill{height:100%;width:0%;border-radius:99px;background:linear-gradient(90deg,var(--accent),var(--accent2));transition:width .4s ease;}
.progress-labels{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);}
/* current */
.current-row{display:flex;align-items:center;gap:10px;padding:12px 0;border-top:1px solid var(--border);margin-top:12px;}
.pulse{width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0;animation:pulse 1.2s infinite;}
.pulse.idle{background:var(--muted);animation:none;}
@keyframes pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(37,211,102,.4)}50%{transform:scale(1.15);box-shadow:0 0 0 5px rgba(37,211,102,0)}}
.current-name{font-size:14px;font-weight:500;}
.current-sub{font-size:12px;color:var(--muted);margin-top:2px;}
/* qr */
.qr-wrap{text-align:center;padding:8px 0;}
.qr-wrap img{width:200px;height:200px;border-radius:12px;border:3px solid var(--accent);background:#fff;padding:6px;}
.qr-hint{font-size:12px;color:var(--muted);margin-top:10px;}
/* endpoints */
.ep-list{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.ep{font-size:13px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;color:var(--text);text-decoration:none;transition:.15s;display:flex;flex-direction:column;gap:2px;}
.ep:hover{border-color:var(--accent);background:rgba(37,211,102,.05);}
/* sending banner */
.sending-banner{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:8px;background:rgba(37,211,102,.07);border:1px solid rgba(37,211,102,.2);font-size:13px;color:var(--accent);margin-bottom:12px;}
</style>
</head>
<body>
<div class="container">

  <div style="margin-bottom:20px;">
    <a href="http://localhost:5500/email_job-main/bulk-mailer/whatsapp.html" style="display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);text-decoration:none;background:var(--surface);border:1px solid var(--border);padding:8px 16px;border-radius:8px;transition:.15s;" onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--text)'" onmouseout="this.style.color='var(--muted)';this.style.borderColor='var(--border)'">← Back to ReachOut App</a>
  </div>
  <div class="logo">Reach<span>Out</span></div>
  <div class="tagline">WhatsApp Service · Port ${PORT}</div>

  <!-- Connection Status -->
  <div class="card">
    <div class="card-header">
      <span style="font-size:16px;">💬</span>
      <div class="card-title">Connection Status</div>
      <div id="statusPill" style="margin-left:auto;"></div>
    </div>
    <div class="card-body">
      <div id="statusText" style="font-size:13px;color:var(--muted);margin-bottom:12px;"></div>
      <div id="qrSection" style="display:none">
        <div class="qr-wrap">
          <img id="qrImg" src="" alt="QR">
          <div class="qr-hint">Open WhatsApp → Linked Devices → Scan this QR</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="card">
    <div class="card-header">
      <span style="font-size:16px;">📊</span>
      <div class="card-title">Send Progress</div>
      <div id="sendBadge" style="margin-left:auto;font-size:12px;color:var(--muted);"></div>
    </div>
    <div class="card-body">
      <div id="sendingBanner" style="display:none" class="sending-banner">
        <div class="pulse"></div> Sending in progress...
      </div>
      <div class="stats-grid">
        <div class="stat-box"><div class="stat-label">Total</div><div class="stat-val c-total" id="vTotal">0</div></div>
        <div class="stat-box"><div class="stat-label">Sent</div><div class="stat-val c-sent" id="vSent">0</div></div>
        <div class="stat-box"><div class="stat-label">Failed</div><div class="stat-val c-fail" id="vFail">0</div></div>
        <div class="stat-box"><div class="stat-label">Remaining</div><div class="stat-val c-remain" id="vRemain">0</div></div>
      </div>
      <div class="progress-track"><div class="progress-fill" id="pFill"></div></div>
      <div class="progress-labels"><span id="pDone">0 done</span><span id="pPct">0%</span></div>
      <div class="current-row" id="currentRow" style="display:none">
        <div class="pulse" id="pDot"></div>
        <div><div class="current-name" id="curName"></div><div class="current-sub" id="curSub"></div></div>
      </div>
    </div>
  </div>

  <!-- API Endpoints -->
  <div class="card">
    <div class="card-header">
      <span style="font-size:16px;">🔗</span>
      <div class="card-title">API Endpoints</div>
      <span style="margin-left:auto;font-size:11px;color:var(--muted);">Click to open in browser</span>
    </div>
    <div class="card-body">
      <div class="ep-list">
        <a href="/status" target="_blank" class="ep">
          <span style="color:var(--accent);font-family:monospace;font-size:11px;font-weight:700;">GET</span>
          <span>/status</span>
          <span style="display:block;font-size:11px;color:var(--muted);margin-top:2px;">Connection state</span>
        </a>
        <a href="/qr" target="_blank" class="ep">
          <span style="color:var(--accent);font-family:monospace;font-size:11px;font-weight:700;">GET</span>
          <span>/qr</span>
          <span style="display:block;font-size:11px;color:var(--muted);margin-top:2px;">QR code image</span>
        </a>
        <a href="/progress" target="_blank" class="ep">
          <span style="color:var(--accent);font-family:monospace;font-size:11px;font-weight:700;">GET</span>
          <span>/progress</span>
          <span style="display:block;font-size:11px;color:var(--muted);margin-top:2px;">Sent / failed count</span>
        </a>
        <a href="/logs" target="_blank" class="ep">
          <span style="color:var(--accent);font-family:monospace;font-size:11px;font-weight:700;">GET</span>
          <span>/logs</span>
          <span style="display:block;font-size:11px;color:var(--muted);margin-top:2px;">Full message log</span>
        </a>
      </div>
    </div>
  </div>

</div>

<script>
let qrTimer = null;

async function refresh() {
  try {
    const [s, p] = await Promise.all([
      fetch('/status').then(r => r.json()),
      fetch('/progress').then(r => r.json()),
    ]);

    // Status pill
    const pillMap = {
      ready:        ['pill pill-ready',  '<span class="dot dot-green"></span>Connected ✓'],
      qr:           ['pill pill-qr',     '<span class="dot dot-yellow"></span>Scan QR'],
      initializing: ['pill pill-other',  '<span class="dot dot-yellow"></span>Initializing…'],
      disconnected: ['pill pill-other',  '<span class="dot dot-red"></span>Disconnected'],
    };
    const [cls, html] = pillMap[s.state] || pillMap.disconnected;
    document.getElementById('statusPill').className = cls;
    document.getElementById('statusPill').innerHTML = html;

    const subMap = {
      ready:        'WhatsApp is connected and ready to send messages.',
      qr:           'Open WhatsApp on your phone → Linked Devices → Link a Device → scan QR below.',
      initializing: 'Launching WhatsApp Web, please wait…',
      disconnected: 'Service is running but WhatsApp is not connected. Use your ReachOut app to connect.',
    };
    document.getElementById('statusText').textContent = subMap[s.state] || '';

    // QR
    const qrSec = document.getElementById('qrSection');
    if (s.state === 'qr') {
      qrSec.style.display = 'block';
      if (!qrTimer) qrTimer = setInterval(loadQR, 3000);
      loadQR();
    } else {
      qrSec.style.display = 'none';
      clearInterval(qrTimer); qrTimer = null;
    }

    // Progress
    const done = p.sent + p.failed;
    const pct  = p.total > 0 ? Math.round((done / p.total) * 100) : 0;
    document.getElementById('vTotal').textContent   = p.total;
    document.getElementById('vSent').textContent    = p.sent;
    document.getElementById('vFail').textContent    = p.failed;
    document.getElementById('vRemain').textContent  = p.remaining;
    document.getElementById('pFill').style.width    = pct + '%';
    document.getElementById('pDone').textContent    = done + ' done';
    document.getElementById('pPct').textContent     = pct + '%';
    document.getElementById('sendingBanner').style.display = p.isSending ? 'flex' : 'none';
    document.getElementById('sendBadge').textContent = p.isSending ? '● Live' : (p.total > 0 ? 'Done' : '');

    const crow = document.getElementById('currentRow');
    if (p.current) {
      crow.style.display = 'flex';
      document.getElementById('curName').textContent = p.current;
      document.getElementById('curSub').textContent  = p.isPaused ? 'Paused' : 'Sending…';
      document.getElementById('pDot').className = p.isPaused ? 'pulse idle' : 'pulse';
    } else {
      crow.style.display = 'none';
    }
  } catch(_) {}
}

async function loadQR() {
  try {
    const d = await fetch('/qr').then(r => r.json());
    if (d.qr) document.getElementById('qrImg').src = d.qr;
  } catch(_) {}
}

refresh();
setInterval(refresh, 3000);
</script>
</body>
</html>`);
});

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
    console.log('\n─────────────────────────────────────────');
    console.log(`  ✅ ReachOut running at http://localhost:${PORT}`);
    console.log('  📱 WhatsApp → Connect to link your account');
    console.log('  Press Ctrl+C to stop');
    console.log('─────────────────────────────────────────\n');
});
