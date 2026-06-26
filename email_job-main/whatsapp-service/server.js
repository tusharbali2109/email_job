/**
 * WhatsApp Automation Service for ReachOut — v2 (Baileys, no browser)
 * Connects in 5-10s instead of 60-160s.
 *
 * Usage:
 *   cd whatsapp-service
 *   npm install
 *   node server.js
 */

const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore,
    isJidUser,
} = require('@whiskeysockets/baileys');
const pino    = require('pino');
const express = require('express');
const cors    = require('cors');
const QRCode  = require('qrcode');
const path    = require('path');
const fs      = require('fs');
const fetch   = require('node-fetch');

const app  = express();
const PORT = 3001;
const AUTH_DIR = path.join(__dirname, '.baileys_auth');

app.use(cors({ origin: '*' }));
app.use(express.json({ limit: '100mb' }));
app.use(express.static(path.join(__dirname, '..', 'bulk-mailer')));

/* ─── state ─── */
let sock        = null;
let clientState = 'disconnected'; // disconnected | connecting | qr | ready
let currentQR   = null;
let reconnectTimer = null;

let sendQueue = [];
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
    d = d.replace(/^0+/, '');
    if (d.length === 10) d = '91' + d;
    return d + '@s.whatsapp.net';
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

/* ─── Baileys client ─── */
async function startWAClient() {
    if (clientState === 'ready' || clientState === 'connecting' || clientState === 'qr') return;

    clientState = 'connecting';
    currentQR   = null;
    if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }

    console.log('[WA] Starting Baileys client (no browser)…');

    try {
        const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
        const { version } = await fetchLatestBaileysVersion();
        console.log('[WA] Using WA version:', version.join('.'));

        sock = makeWASocket({
            version,
            auth: {
                creds: state.creds,
                keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' })),
            },
            printQRInTerminal: true,
            logger: pino({ level: 'silent' }),
            browser: ['ReachOut', 'Chrome', '120.0.0'],
            connectTimeoutMs: 30000,
            defaultQueryTimeoutMs: 20000,
            keepAliveIntervalMs: 15000,
        });

        sock.ev.on('creds.update', saveCreds);

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                try {
                    currentQR   = await QRCode.toDataURL(qr);
                    clientState = 'qr';
                    console.log('[WA] QR ready — scan in your app');
                } catch (e) {
                    console.error('[WA] QR gen error:', e.message);
                }
            }

            if (connection === 'open') {
                clientState = 'ready';
                currentQR   = null;
                console.log('[WA] Connected!');
            }

            if (connection === 'close') {
                const code   = lastDisconnect?.error?.output?.statusCode;
                const reason = DisconnectReason[code] || code;
                console.log('[WA] Closed — reason:', reason);

                if (code === DisconnectReason.loggedOut) {
                    // Auth revoked — wipe saved session
                    console.log('[WA] Logged out — clearing auth');
                    try { fs.rmSync(AUTH_DIR, { recursive: true, force: true }); } catch (_) {}
                    clientState = 'disconnected';
                    sock        = null;
                } else {
                    // Network blip or restart — reconnect after 3s
                    clientState = 'disconnected';
                    sock        = null;
                    reconnectTimer = setTimeout(() => startWAClient(), 3000);
                }
            }
        });

    } catch (err) {
        console.error('[WA] Start error:', err.message);
        clientState = 'disconnected';
        sock        = null;
    }
}

/* ─── routes ─── */

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
  background-image:radial-gradient(ellipse 70% 50% at 50% -20%,rgba(37,211,102,.08) 0%,transparent 65%);
  display:flex;align-items:flex-start;justify-content:center;padding:48px 16px;}
.container{width:100%;max-width:520px;}
.logo{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;letter-spacing:-1px;margin-bottom:4px;}
.logo span{color:#4fffb0;}
.tagline{font-size:13px;color:var(--muted);margin-bottom:32px;}
.badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;padding:3px 10px;border-radius:99px;background:rgba(37,211,102,.15);color:var(--accent);margin-bottom:32px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px;}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.card-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;}
.card-body{padding:20px;}
.pill{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:99px;font-size:13px;font-weight:600;}
.pill-ready{background:rgba(37,211,102,.15);color:var(--accent);}
.pill-qr{background:rgba(255,209,102,.12);color:var(--warning);}
.pill-other{background:rgba(107,122,153,.12);color:var(--muted);}
.dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.dot-green{background:var(--accent);box-shadow:0 0 6px rgba(37,211,102,.6);}
.dot-yellow{background:var(--warning);animation:blink 1s infinite;}
.dot-red{background:var(--danger);}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;}
.stat-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;}
.stat-val{font-family:'Syne',sans-serif;font-size:26px;font-weight:700;}
.c-sent{color:var(--accent)}.c-fail{color:var(--danger)}.c-total{color:var(--accent2)}.c-remain{color:var(--warning);}
.progress-track{width:100%;height:8px;background:var(--surface2);border-radius:99px;overflow:hidden;margin:12px 0;}
.progress-fill{height:100%;width:0%;border-radius:99px;background:linear-gradient(90deg,var(--accent),var(--accent2));transition:width .4s ease;}
.progress-labels{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);}
.qr-wrap{text-align:center;padding:8px 0;}
.qr-wrap img{width:200px;height:200px;border-radius:12px;border:3px solid var(--accent);background:#fff;padding:6px;}
.qr-hint{font-size:12px;color:var(--muted);margin-top:10px;}
.ep-list{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.ep{font-size:13px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;color:var(--text);text-decoration:none;transition:.15s;display:flex;flex-direction:column;gap:2px;}
.ep:hover{border-color:var(--accent);background:rgba(37,211,102,.05);}
</style>
</head>
<body>
<div class="container">
  <div style="margin-bottom:20px;">
    <a href="http://localhost:5500/email_job-main/bulk-mailer/whatsapp.html" style="display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);text-decoration:none;background:var(--surface);border:1px solid var(--border);padding:8px 16px;border-radius:8px;transition:.15s;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Back to ReachOut App</a>
  </div>
  <div class="logo">Reach<span>Out</span></div>
  <div class="tagline">WhatsApp Service · Port ${PORT}</div>
  <div class="badge">⚡ Baileys — No browser needed</div>

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

  <div class="card">
    <div class="card-header">
      <span style="font-size:16px;">📊</span>
      <div class="card-title">Send Progress</div>
      <div id="sendBadge" style="margin-left:auto;font-size:12px;color:var(--muted);"></div>
    </div>
    <div class="card-body">
      <div class="stats-grid">
        <div class="stat-box"><div class="stat-label">Total</div><div class="stat-val c-total" id="vTotal">0</div></div>
        <div class="stat-box"><div class="stat-label">Sent</div><div class="stat-val c-sent" id="vSent">0</div></div>
        <div class="stat-box"><div class="stat-label">Failed</div><div class="stat-val c-fail" id="vFail">0</div></div>
        <div class="stat-box"><div class="stat-label">Remaining</div><div class="stat-val c-remain" id="vRemain">0</div></div>
      </div>
      <div class="progress-track"><div class="progress-fill" id="pFill"></div></div>
      <div class="progress-labels"><span id="pDone">0 done</span><span id="pPct">0%</span></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span style="font-size:16px;">🔗</span>
      <div class="card-title">API Endpoints</div>
    </div>
    <div class="card-body">
      <div class="ep-list">
        <a href="/status" target="_blank" class="ep"><span style="color:var(--accent);font-family:monospace;font-size:11px;font-weight:700;">GET</span><span>/status</span></a>
        <a href="/qr" target="_blank" class="ep"><span style="color:var(--accent);font-family:monospace;font-size:11px;font-weight:700;">GET</span><span>/qr</span></a>
        <a href="/progress" target="_blank" class="ep"><span style="color:var(--accent);font-family:monospace;font-size:11px;font-weight:700;">GET</span><span>/progress</span></a>
        <a href="/logs" target="_blank" class="ep"><span style="color:var(--accent);font-family:monospace;font-size:11px;font-weight:700;">GET</span><span>/logs</span></a>
      </div>
    </div>
  </div>
</div>
<script>
let qrTimer = null;
async function refresh() {
  try {
    const [s,p] = await Promise.all([fetch('/status').then(r=>r.json()),fetch('/progress').then(r=>r.json())]);
    const pillMap = {
      ready:       ['pill pill-ready', '<span class="dot dot-green"></span>Connected ✓'],
      qr:          ['pill pill-qr',    '<span class="dot dot-yellow"></span>Scan QR'],
      connecting:  ['pill pill-other', '<span class="dot dot-yellow"></span>Connecting…'],
      disconnected:['pill pill-other', '<span class="dot dot-red"></span>Disconnected'],
    };
    const [cls,html] = pillMap[s.state]||pillMap.disconnected;
    document.getElementById('statusPill').className=cls;
    document.getElementById('statusPill').innerHTML=html;
    const subMap = {
      ready:'WhatsApp is connected and ready to send messages.',
      qr:'Open WhatsApp on your phone → Linked Devices → Link a Device → scan QR below.',
      connecting:'Connecting to WhatsApp (no browser — should be fast)…',
      disconnected:'Service running but WhatsApp not connected. Use your ReachOut app to connect.',
    };
    document.getElementById('statusText').textContent=subMap[s.state]||'';
    const qrSec=document.getElementById('qrSection');
    if(s.state==='qr'){qrSec.style.display='block';if(!qrTimer)qrTimer=setInterval(loadQR,3000);loadQR();}
    else{qrSec.style.display='none';clearInterval(qrTimer);qrTimer=null;}
    const done=p.sent+p.failed,pct=p.total>0?Math.round(done/p.total*100):0;
    document.getElementById('vTotal').textContent=p.total;
    document.getElementById('vSent').textContent=p.sent;
    document.getElementById('vFail').textContent=p.failed;
    document.getElementById('vRemain').textContent=p.remaining;
    document.getElementById('pFill').style.width=pct+'%';
    document.getElementById('pDone').textContent=done+' done';
    document.getElementById('pPct').textContent=pct+'%';
    document.getElementById('sendBadge').textContent=p.isSending?'● Live':(p.total>0?'Done':'');
  } catch(_){}
}
async function loadQR(){try{const d=await fetch('/qr').then(r=>r.json());if(d.qr)document.getElementById('qrImg').src=d.qr;}catch(_){}}
refresh();setInterval(refresh,2000);
</script>
</body>
</html>`);
});

app.post('/init', (req, res) => {
    startWAClient();
    res.json({ success: true, state: clientState });
});

app.post('/disconnect', async (req, res) => {
    if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
    if (sock) {
        try { await sock.logout(); } catch (_) {}
        try { await sock.end(); } catch (_) {}
        sock = null;
    }
    // Wipe saved auth so next connect shows fresh QR
    try { fs.rmSync(AUTH_DIR, { recursive: true, force: true }); } catch (_) {}
    clientState = 'disconnected';
    currentQR   = null;
    res.json({ success: true });
});

app.get('/status', (req, res) => {
    res.json({ state: clientState, hasQR: !!currentQR });
});

app.get('/qr', (req, res) => {
    res.json({ qr: currentQR });
});

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

    templateBody    = template || '';
    resumePath      = resume   || '';
    candidateMobile = cm       || '';
    candidateEmail  = ce       || '';

    sendQueue = contacts.map(c => ({ ...c, status: 'pending', error: null }));
    stats     = { total: contacts.length, sent: 0, failed: 0, remaining: contacts.length, current: null };
    sendLogs  = [];
    isSending = true;
    isPaused  = false;
    isStopped = false;

    res.json({ success: true, total: contacts.length });
    processSendQueue().catch(err => console.error('[SEND] Error:', err));
});

app.post('/control', (req, res) => {
    const { action } = req.body;
    if (action === 'pause')  isPaused = true;
    if (action === 'resume') isPaused = false;
    if (action === 'stop')   { isStopped = true; isSending = false; }
    res.json({ success: true, action, isPaused, isStopped });
});

app.get('/progress', (req, res) => {
    res.json({ ...stats, isPaused, isSending, isStopped });
});

app.get('/logs', (req, res) => {
    res.json(sendLogs);
});

app.post('/retry', (req, res) => {
    if (isSending) return res.status(400).json({ success: false, error: 'Already running' });
    const failed = sendQueue.filter(i => i.status === 'failed');
    if (!failed.length) return res.json({ success: true, retrying: 0 });
    failed.forEach(i => { i.status = 'pending'; i.error = null; });
    stats.remaining += failed.length;
    stats.failed     = 0;
    isSending = true; isPaused = false; isStopped = false;
    res.json({ success: true, retrying: failed.length });
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
        const jid = cleanPhone(item.phone);

        if (!jid) {
            item.status = 'failed';
            item.error  = 'Invalid phone number';
            stats.failed++;
            stats.remaining--;
            pushLog(item);
            continue;
        }

        try {
            const msg = stripHtml(renderTemplate(templateBody, {
                name:    item.name,
                company: item.company,
                mobile:  candidateMobile,
                email:   candidateEmail,
            }));

            await sock.sendMessage(jid, { text: msg });

            // Attach resume if URL provided
            if (resumePath && resumePath.startsWith('http')) {
                try {
                    const resp = await fetch(resumePath);
                    const buffer = await resp.buffer();
                    const mime   = resp.headers.get('content-type') || 'application/pdf';
                    const fname  = resumePath.split('/').pop() || 'resume.pdf';
                    await sock.sendMessage(jid, { document: buffer, mimetype: mime, fileName: fname });
                } catch (mediaErr) {
                    console.warn('[SEND] Resume attach failed:', mediaErr.message);
                }
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
    console.log('  ⚡ Using Baileys (no browser — fast connect)');
    console.log('  Press Ctrl+C to stop');
    console.log('─────────────────────────────────────────\n');
    startWAClient();
});
