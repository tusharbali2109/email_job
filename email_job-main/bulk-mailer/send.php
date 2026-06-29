<?php
include 'db.php';
$total   = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE status='pending'")->fetchColumn();
$profile = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Send bulk emails with resume attachments">
<meta name="theme-color" content="#4fffb0">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ReachOut">
<link rel="manifest" href="manifest.json">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%234fffb0' width='100' height='100'/><text x='50%' y='50%' font-size='60' fill='%230c0f14' text-anchor='middle' dy='.35em' font-family='Arial' font-weight='bold'>📧</text></svg>">
<title>Send Emails — ReachOut</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0c0f14; --surface: #141820; --surface2: #1c2230;
    --border: #252d3d; --accent: #4fffb0; --accent2: #00c9ff;
    --text: #e8edf5; --muted: #6b7a99; --danger: #ff5f6d;
    --warning: #ffd166; --radius: 12px;
  }
  body {
    font-family: 'DM Sans', sans-serif; background: var(--bg);
    color: var(--text); min-height: 100vh;
    background-image:
      radial-gradient(ellipse 70% 50% at 50% -20%, rgba(79,255,176,0.08) 0%, transparent 65%),
      radial-gradient(ellipse 40% 40% at 90% 90%, rgba(0,201,255,0.05) 0%, transparent 50%);
  }
  .layout { display: flex; min-height: 100vh; }
  .sidebar {
    width: 240px; flex-shrink: 0; background: var(--surface);
    border-right: 1px solid var(--border); display: flex;
    flex-direction: column; padding: 28px 0;
    position: sticky; top: 0; height: 100vh;
  }
  .logo { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; padding: 0 24px 32px; letter-spacing: -0.5px; }
  .logo span { color: var(--accent); }
  .nav-label { font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); padding: 0 24px 10px; }
  .nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 24px; font-size: 14px; color: var(--muted); text-decoration: none; transition: all 0.15s; border-left: 3px solid transparent; }
  .nav-item:hover { color: var(--text); background: var(--surface2); }
  .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(79,255,176,0.06); }
  .nav-item .icon { font-size: 17px; width: 22px; text-align: center; }
  .main { flex: 1; padding: 40px 48px; }
  .page-header { margin-bottom: 36px; }
  .page-title { font-family: 'Syne', sans-serif; font-size: 30px; font-weight: 800; letter-spacing: -1px; }
  .page-subtitle { font-size: 14px; color: var(--muted); margin-top: 5px; }
  .stat-row { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
  .stat-chip { display: flex; align-items: center; gap: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; min-width: 140px; }
  .stat-chip-icon { font-size: 22px; }
  .stat-chip-label { font-size: 11px; color: var(--muted); }
  .stat-chip-val { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 700; }
  .val-total { color: var(--accent2); } .val-sent { color: var(--accent); }
  .val-fail { color: var(--danger); } .val-remain { color: var(--warning); }
  .send-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 24px; }
  .send-card-header { padding: 22px 28px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 16px; }
  .send-card-title { font-family: 'Syne', sans-serif; font-size: 17px; font-weight: 700; display: flex; align-items: center; gap: 10px; }

  /* ── ERROR BANNER ── */
  .error-banner {
    display: none; margin: 16px 28px;
    background: rgba(255,95,109,0.08); border: 1px solid rgba(255,95,109,0.3);
    border-radius: 10px; padding: 14px 18px;
    font-size: 13px; color: var(--danger);
  }
  .error-banner strong { display: block; margin-bottom: 4px; font-size: 14px; }

  .progress-wrap { padding: 28px; border-bottom: 1px solid var(--border); }
  .progress-labels { display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); margin-bottom: 10px; }
  .progress-labels strong { color: var(--text); font-size: 15px; }
  .progress-track { width: 100%; height: 10px; background: var(--surface2); border-radius: 99px; overflow: hidden; position: relative; }
  .progress-fill { height: 100%; width: 0%; border-radius: 99px; background: linear-gradient(90deg, var(--accent), var(--accent2)); transition: width 0.4s cubic-bezier(0.4,0,0.2,1); position: relative; }
  .progress-fill::after { content: ''; position: absolute; top: 0; right: 0; bottom: 0; width: 60px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3)); animation: shimmer 1.5s infinite; }
  @keyframes shimmer { 0%{opacity:0} 50%{opacity:1} 100%{opacity:0} }
  .current-status { padding: 20px 28px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 14px; min-height: 70px; background: rgba(79,255,176,0.02); }
  .pulse-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--accent); flex-shrink: 0; animation: pulse 1.2s infinite; }
  @keyframes pulse {
    0%,100% { opacity:1; transform:scale(1); box-shadow:0 0 0 0 rgba(79,255,176,0.4); }
    50% { opacity:0.8; transform:scale(1.1); box-shadow:0 0 0 6px rgba(79,255,176,0); }
  }
  .pulse-dot.idle { background: var(--muted); animation: none; }
  .status-text { font-size: 14px; color: var(--muted); flex: 1; }
  .status-text strong { color: var(--text); display: block; font-size: 15px; margin-bottom: 2px; }
  .log-wrap { max-height: 340px; overflow-y: auto; }
  .log-wrap::-webkit-scrollbar { width: 4px; }
  .log-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
  .log-entry { display: flex; align-items: flex-start; gap: 14px; padding: 12px 28px; border-bottom: 1px solid var(--border); font-size: 13px; animation: fadeSlide 0.2s ease; }
  @keyframes fadeSlide { from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:translateX(0)} }
  .log-entry:last-child { border-bottom: none; }
  .log-icon { font-size: 16px; flex-shrink: 0; width: 22px; text-align: center; margin-top: 2px; }
  .log-main { flex: 1; min-width: 0; }
  .log-name { font-weight: 500; color: var(--text); }
  .log-company { color: var(--muted); font-size: 12px; }
  .log-error { color: var(--danger); font-size: 11px; margin-top: 3px; word-break: break-word; }  /* ✅ Error reason */
  .log-email { color: var(--muted); font-size: 12px; margin-left: auto; flex-shrink: 0; padding-left: 8px; }
  .log-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; flex-shrink: 0; }
  .badge-sent { background: rgba(79,255,176,0.12); color: var(--accent); }
  .badge-failed { background: rgba(255,95,109,0.12); color: var(--danger); }
  .badge-sending { background: rgba(0,201,255,0.1); color: var(--accent2); }
  .done-panel { display: none; padding: 40px 28px; text-align: center; }
  .done-icon { font-size: 52px; margin-bottom: 16px; }
  .done-title { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; margin-bottom: 8px; }
  .done-sub { color: var(--muted); font-size: 14px; }
  .done-stats { display: flex; justify-content: center; gap: 24px; margin: 24px 0; }
  .done-stat { text-align: center; }
  .done-stat-val { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 700; }
  .done-stat-label { font-size: 12px; color: var(--muted); margin-top: 2px; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; }
  .btn-primary { background: var(--accent); color: #0c0f14; font-weight: 700; }
  .btn-primary:hover { background: #6bffc0; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,255,176,0.25); }
  .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
  .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
  .btn-ghost:hover { color: var(--text); border-color: var(--text); }
  .rocket-wrap { display: none; flex-direction: column; align-items: center; padding: 48px; gap: 20px; }
  .rocket { font-size: 52px; animation: rocketFly 2s ease-in-out infinite; }
  @keyframes rocketFly { 0%{transform:translateY(0) rotate(-5deg)} 50%{transform:translateY(-16px) rotate(5deg)} 100%{transform:translateY(0) rotate(-5deg)} }
  .rocket-text { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; color: var(--accent); }
  .rocket-sub { font-size: 13px; color: var(--muted); }
  .dots::after { content: ''; animation: dotdot 1.5s infinite; }
  @keyframes dotdot { 0%{content:'.'} 33%{content:'..'} 66%{content:'...'} 100%{content:'.'} }

  @media (max-width: 800px) {
    .layout { flex-direction: column; } .sidebar { display: none; }
    .main { padding: 20px 16px; } .page-title { font-size: 24px; }
    .stat-row { flex-direction: column; gap: 10px; } .stat-chip { min-width: 100%; }
  }
  @media (max-width: 640px) {
    .main { padding: 16px 12px; } .page-title { font-size: 20px; }
    .stat-row { flex-direction: row; flex-wrap: wrap; gap: 8px; }
    .stat-chip { flex: 1; min-width: calc(50% - 4px); }
    .send-card-header { padding: 16px; flex-direction: column; }
    .btn { width: 100%; padding: 10px 14px; font-size: 12px; }
    .log-entry { padding: 10px 16px; }
    .error-banner { margin: 12px 16px; }
  }
  @media (max-width: 480px) {
    .main { padding: 12px 10px; }
    .stat-chip { min-width: calc(50% - 4px); padding: 10px 12px; }
    .log-entry { padding: 8px 12px; font-size: 11px; }
  }
</style>
</head>
<body>
<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">Reach<span>Out</span></div>
    <div class="nav-label">Outreach</div>
    <a class="nav-item" href="index.php"><span class="icon">📋</span> Dashboard</a>
    <a class="nav-item active" href="send.php"><span class="icon">📤</span> Send Emails</a>
    <a class="nav-item" href="whatsapp.php"><span class="icon">💬</span> Send WhatsApp</a>
    <a class="nav-item" href="index.php#csv-section"><span class="icon">📂</span> CSV Import</a>
    <div class="nav-label">Jobs</div>
    <a class="nav-item" href="jobs.php"><span class="icon">🔍</span> Job Hunt</a>
    <a class="nav-item" href="pipeline.php"><span class="icon">📊</span> Pipeline</a>
    <a class="nav-item" href="ai_tailor.php"><span class="icon">🤖</span> AI Tailor</a>
    <div class="nav-label">Automation</div>
    <a class="nav-item" href="job_fetch.php?secret=MY_CRON_SECRET_2024"><span class="icon">🔎</span> Fetch Jobs</a>
    <a class="nav-item" href="followup_cron.php?secret=MY_CRON_SECRET_2024"><span class="icon">🔁</span> Follow-ups</a>
    <a class="nav-item" href="reply_check.php?secret=MY_CRON_SECRET_2024"><span class="icon">📬</span> Check Replies</a>
    <a class="nav-item" href="wa_digest.php?secret=MY_CRON_SECRET_2024"><span class="icon">📱</span> WA Digest</a>
    <a class="nav-item" href="cron_log.php"><span class="icon">📅</span> Cron Logs</a>
    <div class="nav-label">Settings</div>
    <a class="nav-item" href="blacklist.php"><span class="icon">🚫</span> Blacklist</a>
    <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
    <a class="nav-item" href="whatsapp_logs.php"><span class="icon">📃</span> WA Logs</a>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="page-header">
      <div class="page-title">Send Emails</div>
      <div class="page-subtitle">Sending from <strong style="color:var(--text)"><?= htmlspecialchars($profile['email'] ?? 'N/A') ?></strong> with resume attached</div>
    </div>

    <!-- STAT CHIPS -->
    <div class="stat-row">
      <div class="stat-chip">
        <div class="stat-chip-icon">📬</div>
        <div><div class="stat-chip-label">To Send</div><div class="stat-chip-val val-total" id="statTotal"><?= $total ?></div></div>
      </div>
      <div class="stat-chip">
        <div class="stat-chip-icon">✅</div>
        <div><div class="stat-chip-label">Sent</div><div class="stat-chip-val val-sent" id="statSent">0</div></div>
      </div>
      <div class="stat-chip">
        <div class="stat-chip-icon">❌</div>
        <div><div class="stat-chip-label">Failed</div><div class="stat-chip-val val-fail" id="statFail">0</div></div>
      </div>
      <div class="stat-chip">
        <div class="stat-chip-icon">⏳</div>
        <div><div class="stat-chip-label">Remaining</div><div class="stat-chip-val val-remain" id="statRemain"><?= $total ?></div></div>
      </div>
    </div>

    <!-- SEND CARD -->
    <div class="send-card">
      <div class="send-card-header">
        <div class="send-card-title">📤 Bulk Email Dispatch</div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <div style="display:flex;align-items:center;gap:8px;">
            <label style="font-size:14px;color:var(--text);font-weight:500;">Send:</label>
            <select id="sendLimit" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:8px 12px;font-size:13px;cursor:pointer;">
              <option value="all">All <?= $total ?> Emails</option>
              <option value="20">20 Records (Daily)</option>
            </select>
          </div>
          <a href="index.php" class="btn btn-ghost">← Back</a>
          <button class="btn btn-primary" id="startBtn" onclick="startSending()" <?= $total===0 ? 'disabled' : '' ?>>
            🚀 Start Sending
          </button>
        </div>
      </div>

      <!-- IDLE STATE -->
      <div id="idleState">
        <?php if($total === 0): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
          <div style="font-size:40px;margin-bottom:12px;">🎉</div>
          <div style="font-size:16px;font-weight:600;color:var(--text)">No pending emails!</div>
          <div style="font-size:14px;margin-top:6px;">All contacts have been emailed.</div>
          <a href="index.php" class="btn btn-ghost" style="margin-top:20px;">← Dashboard</a>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:40px 20px;color:var(--muted);">
          <div style="font-size:36px;margin-bottom:12px;">📮</div>
          <div style="font-size:15px;">Ready to send <strong style="color:var(--text)"><?= $total ?> emails</strong>. Press Start!</div>
        </div>
        <?php endif; ?>
      </div>

      <!-- SENDING STATE -->
      <div id="sendingState" style="display:none;">

        <!-- Rocket animation -->
        <div class="rocket-wrap" id="rocketWrap">
          <div class="rocket">🚀</div>
          <div class="rocket-text">Launching email campaign<span class="dots"></span></div>
          <div class="rocket-sub">Connecting to SMTP server</div>
        </div>

        <!-- ✅ Error banner — pehli email fail hone pe dikhega -->
        <div class="error-banner" id="errorBanner">
          <strong>⚠️ SMTP Error Detected</strong>
          <span id="errorMsg"></span>
        </div>

        <!-- Progress -->
        <div class="progress-wrap" id="progressWrap" style="display:none;">
          <div class="progress-labels">
            <span><strong id="progressNum">0</strong> / <span id="progressTotal"><?= $total ?></span> emails sent</span>
            <span id="progressPct">0%</span>
          </div>
          <div class="progress-track">
            <div class="progress-fill" id="progressFill"></div>
          </div>
        </div>

        <!-- Current email -->
        <div class="current-status" id="currentStatus" style="display:none;">
          <div class="pulse-dot" id="pulseDot"></div>
          <div class="status-text">
            <strong id="statusName">Sending...</strong>
            <span id="statusEmail"></span>
          </div>
          <div class="log-badge badge-sending">Sending</div>
        </div>

        <!-- Log -->
        <div class="log-wrap" id="logWrap"></div>
      </div>

      <!-- DONE STATE -->
      <div class="done-panel" id="donePanel">
        <div class="done-icon" id="doneIcon">🎉</div>
        <div class="done-title" id="doneTitle">Campaign Complete!</div>
        <div class="done-sub" id="doneSub">All emails have been processed.</div>
        <div class="done-stats">
          <div class="done-stat"><div class="done-stat-val val-sent" id="doneSent">0</div><div class="done-stat-label">Sent</div></div>
          <div class="done-stat"><div class="done-stat-val val-fail" id="doneFail">0</div><div class="done-stat-label">Failed</div></div>
        </div>
        <a href="send.php" class="btn btn-ghost" style="margin-right:10px">🔄 Send More</a>
        <a href="index.php" class="btn btn-primary">← Back to Dashboard</a>
      </div>

    </div>
  </main>
</div>

<script>
const TOTAL = <?= $total ?>;
let sent = 0, failed = 0, actualTotal = TOTAL;
let firstError = true; // ✅ Pehli error banner mein dikhao

async function getPendingIds() {
  const limitVal = document.getElementById('sendLimit').value;
  let url = 'get_pending.php';
  if(limitVal !== 'all') url += '?limit=' + limitVal;
  const r = await fetch(url);
  const data = await r.json();
  return data;
}

async function sendOne(id) {
  const r = await fetch('send_one.php?id=' + id);
  return await r.json();
}

function updateProgress() {
  const done = sent + failed;
  const pct = actualTotal > 0 ? Math.round((done / actualTotal) * 100) : 100;
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressNum').textContent = done;
  document.getElementById('progressPct').textContent = pct + '%';
  document.getElementById('statSent').textContent = sent;
  document.getElementById('statFail').textContent = failed;
  document.getElementById('statRemain').textContent = Math.max(0, actualTotal - done);
}

function addLog(entry, success, errorMsg = '') {
  const wrap = document.getElementById('logWrap');
  const div = document.createElement('div');
  div.className = 'log-entry';
  // ✅ Error reason clearly dikhao
  div.innerHTML = `
    <div class="log-icon">${success ? '✅' : '❌'}</div>
    <div class="log-main">
      <div class="log-name">${entry.name}</div>
      <div class="log-company">${entry.company}</div>
      ${!success && errorMsg ? `<div class="log-error">⚠️ ${errorMsg}</div>` : ''}
    </div>
    <div class="log-email">${entry.email}</div>
    <div class="log-badge ${success ? 'badge-sent' : 'badge-failed'}">${success ? 'Sent' : 'Failed'}</div>
  `;
  wrap.prepend(div);
}

async function startSending() {
  if(TOTAL === 0) return;

  document.getElementById('startBtn').disabled = true;
  document.getElementById('sendLimit').disabled = true;
  document.getElementById('idleState').style.display = 'none';
  document.getElementById('sendingState').style.display = 'block';
  document.getElementById('rocketWrap').style.display = 'flex';

  await new Promise(r => setTimeout(r, 1400));

  document.getElementById('rocketWrap').style.display = 'none';
  document.getElementById('progressWrap').style.display = 'block';
  document.getElementById('currentStatus').style.display = 'flex';

  const ids = await getPendingIds();
  actualTotal = ids.length;

  // ✅ Agar koi pending nahi
  if(actualTotal === 0) {
    document.getElementById('sendingState').style.display = 'none';
    document.getElementById('donePanel').style.display = 'block';
    document.getElementById('doneIcon').textContent = '🎉';
    document.getElementById('doneTitle').textContent = 'Sab ho gaya!';
    document.getElementById('doneSub').textContent = 'Koi pending email nahi bachi.';
    return;
  }

  document.getElementById('progressTotal').textContent = actualTotal;

  for(let i = 0; i < ids.length; i++) {
    const item = ids[i];

    document.getElementById('statusName').textContent = item.name + ' · ' + item.company;
    document.getElementById('statusEmail').textContent = item.email;
    document.getElementById('pulseDot').className = 'pulse-dot';

    const res = await sendOne(item.id);

    if(res.success) {
      sent++;
      addLog(item, true);
    } else {
      failed++;
      const errMsg = res.error || 'Unknown error';
      addLog(item, false, errMsg);

      // ✅ Pehli error banner mein dikhao — SMTP problem clearly samajh aaye
      if(firstError) {
        firstError = false;
        const banner = document.getElementById('errorBanner');
        document.getElementById('errorMsg').textContent = errMsg;
        banner.style.display = 'block';
      }
    }
    updateProgress();
    await new Promise(r => setTimeout(r, 120));
  }

  // Done
  document.getElementById('sendingState').style.display = 'none';
  const dp = document.getElementById('donePanel');
  dp.style.display = 'block';
  document.getElementById('doneSent').textContent = sent;
  document.getElementById('doneFail').textContent = failed;

  if(failed > 0 && sent === 0) {
    document.getElementById('doneIcon').textContent = '😞';
    document.getElementById('doneTitle').textContent = 'All emails failed';
    document.getElementById('doneSub').textContent = 'Check your SMTP settings — error log mein reason dekho.';
  } else if(failed > 0) {
    document.getElementById('doneIcon').textContent = '⚠️';
    document.getElementById('doneTitle').textContent = 'Mostly done!';
    document.getElementById('doneSub').textContent = failed + ' email(s) failed. Log mein reason dekho.';
  }
}

if('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').catch(()=>{});
}
</script>
</body>
</html>