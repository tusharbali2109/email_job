<?php include __DIR__ . '/db.php'; ?>
<?php
// Stats
$stats = [
    'total'   => (int)$pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
    'matched' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE matched=1")->fetchColumn(),
    'applied' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE applied=1")->fetchColumn(),
    'queue'   => (int)$pdo->query("SELECT COUNT(*) FROM ai_queue WHERE status='pending'")->fetchColumn(),
    'sent'    => (int)$pdo->query("SELECT COUNT(*) FROM ai_queue WHERE status='sent'")->fetchColumn(),
];

// Filter + Search + Pagination
$filter  = $_GET['filter'] ?? 'all';
$search  = $_GET['search'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$params = [];
$where  = "1=1";
if ($filter === 'matched') $where .= " AND matched=1";
elseif ($filter === 'applied') $where .= " AND applied=1";
elseif ($filter === 'new') $where .= " AND matched=0 AND applied=0";
if (!empty($search)) {
    $where   .= " AND (title ILIKE ? OR company ILIKE ? OR location ILIKE ?)";
    $s        = '%' . $search . '%';
    $params   = [$s, $s, $s];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE $where");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$jobStmt = $pdo->prepare("SELECT * FROM jobs WHERE $where ORDER BY matched DESC, created_at DESC LIMIT $perPage OFFSET $offset");
$jobStmt->execute($params);
$jobRows = $jobStmt->fetchAll();

// Profile
$profile = $pdo->query("SELECT name, email FROM user_profile LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0c0f14">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ReachOut">
<link rel="manifest" href="manifest.json">
<title>Job Hunt — ReachOut</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0c0f14;--surface:#141820;--s2:#1c2230;
  --b:#252d3d;--a:#4fffb0;--a2:#00c9ff;
  --tx:#e8edf5;--mu:#6b7a99;--dan:#ff5f6d;--pen:#ffd166;
  --r:12px;--fd:'Syne',sans-serif;--fb:'DM Sans',sans-serif;
}
body{font-family:var(--fb);background:var(--bg);color:var(--tx);min-height:100vh;
  background-image:radial-gradient(ellipse 60% 40% at 80% -10%,rgba(79,255,176,.06) 0%,transparent 60%),
    radial-gradient(ellipse 50% 30% at 10% 100%,rgba(0,201,255,.04) 0%,transparent 50%)}
.layout{display:flex;min-height:100vh}

/* ── Sidebar ── */
.sidebar{width:240px;flex-shrink:0;background:var(--surface);border-right:1px solid var(--b);
  display:flex;flex-direction:column;padding:28px 0;position:sticky;top:0;height:100vh;overflow-y:auto}
.logo{font-family:var(--fd);font-size:22px;font-weight:800;padding:0 24px 28px;letter-spacing:-.5px}
.logo span{color:var(--a)}
.nav-sec{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:var(--mu);padding:0 24px 8px;margin-top:8px}
.nav-item{display:flex;align-items:center;gap:12px;padding:10px 24px;font-size:14px;color:var(--mu);
  text-decoration:none;transition:all .15s;border-left:3px solid transparent}
.nav-item:hover{color:var(--tx);background:var(--s2)}
.nav-item.active{color:var(--a);border-left-color:var(--a);background:rgba(79,255,176,.06)}
.nav-item .ic{font-size:16px;width:20px;text-align:center}
.sb-bottom{margin-top:auto;padding:14px 16px;border-top:1px solid var(--b)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;
  background:var(--s2);border:1px solid var(--b);border-radius:10px;margin-bottom:10px;overflow:hidden}
.u-av{width:32px;height:32px;flex-shrink:0;background:linear-gradient(135deg,var(--a),var(--a2));
  border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-family:var(--fd);font-weight:800;font-size:13px;color:#0c0f14}
.u-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.u-email{font-size:11px;color:var(--mu);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#logoutBtn{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;
  padding:9px;background:rgba(255,95,109,.1);border:1px solid rgba(255,95,109,.3);
  border-radius:8px;color:#ff8f97;font-family:var(--fb);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
#logoutBtn:hover{background:rgba(255,95,109,.2);color:var(--dan)}

/* ── Main ── */
.main{flex:1;padding:32px 36px;overflow-x:hidden;min-width:0}

/* Page header */
.ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:14px;flex-wrap:wrap}
.ph-title{font-family:var(--fd);font-size:26px;font-weight:800;letter-spacing:-1px;line-height:1.1}
.ph-sub{font-size:13px;color:var(--mu);margin-top:3px}
.btn-group{display:flex;gap:10px;flex-wrap:wrap}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:9px;
  font-family:var(--fd);font-weight:700;font-size:13px;cursor:pointer;border:none;
  text-decoration:none;transition:all .2s;white-space:nowrap}
.btn-primary{background:linear-gradient(135deg,var(--a),var(--a2));color:#0c0f14}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(79,255,176,.3)}
.btn-secondary{background:rgba(79,255,176,.1);border:1px solid rgba(79,255,176,.3);color:var(--a)}
.btn-secondary:hover{background:rgba(79,255,176,.18);transform:translateY(-1px)}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none !important}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{display:inline-block;animation:spin .7s linear infinite}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px}
.stat{background:var(--surface);border:1px solid var(--b);border-radius:var(--r);
  padding:16px 18px;position:relative;overflow:hidden;transition:border-color .2s}
.stat::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,var(--a),var(--a2));opacity:0;transition:opacity .2s}
.stat:hover::before{opacity:1}
.stat-label{font-size:10px;color:var(--mu);text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px}
.stat-val{font-family:var(--fd);font-size:22px;font-weight:800}
.c-green{color:var(--a)}.c-blue{color:var(--a2)}.c-yellow{color:var(--pen)}.c-red{color:var(--dan)}

/* Terminal panels */
.terminal{display:none;border-radius:10px;padding:14px 16px;margin-bottom:18px;
  font-family:monospace;font-size:12px;max-height:200px;overflow-y:auto;
  line-height:1.7;white-space:pre-wrap}
.terminal.show{display:block;animation:fadeIn .2s ease}
.term-fetch{background:#000;border:1px solid rgba(79,255,176,.25);color:var(--a)}
.term-send{background:#000;border:1px solid rgba(0,201,255,.25);color:var(--a2)}
@keyframes fadeIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.search-box{flex:1;min-width:180px;position:relative}
.search-box input{width:100%;background:var(--surface);border:1px solid var(--b);border-radius:8px;
  color:var(--tx);font-family:var(--fb);font-size:14px;padding:9px 14px 9px 36px;outline:none;transition:border-color .15s}
.search-box input:focus{border-color:var(--a)}
.search-box input::placeholder{color:var(--mu)}
.search-box .si{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--mu);font-size:14px}
.filter-tabs{display:flex;background:var(--surface);border:1px solid var(--b);border-radius:8px;padding:3px;gap:2px;overflow-x:auto}
.ftab{padding:6px 12px;border-radius:6px;font-size:12px;font-weight:500;color:var(--mu);
  cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;border:1px solid transparent}
.ftab:hover{color:var(--tx)}
.ftab.active{background:var(--s2);color:var(--a);border-color:rgba(79,255,176,.2)}

/* Job cards */
.jobs-list{display:flex;flex-direction:column;gap:10px}
.job-card{background:var(--surface);border:1px solid var(--b);border-radius:var(--r);
  padding:16px 18px;display:flex;align-items:flex-start;gap:14px;
  transition:border-color .15s,transform .1s;position:relative;overflow:hidden}
.job-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
  background:var(--b);transition:background .2s}
.job-card:hover{border-color:rgba(79,255,176,.25);transform:translateX(2px)}
.job-card:hover::before,.job-card.matched::before{background:var(--a)}
.job-card.applied::before{background:var(--a2)}
.job-card.matched{border-color:rgba(79,255,176,.12)}

/* Company avatar */
.co-av{width:42px;height:42px;flex-shrink:0;background:linear-gradient(135deg,var(--s2),var(--b));
  border:1px solid var(--b);border-radius:9px;display:flex;align-items:center;justify-content:center;
  font-family:var(--fd);font-weight:800;font-size:15px;color:var(--tx)}

/* Job info */
.job-info{flex:1;min-width:0}
.job-title{font-family:var(--fd);font-size:14px;font-weight:700;margin-bottom:5px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.job-title a{color:var(--tx);text-decoration:none}
.job-title a:hover{color:var(--a)}
.job-meta{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:12px;color:var(--mu)}
.m-dot{width:3px;height:3px;border-radius:50%;background:var(--b);flex-shrink:0}

/* Badges */
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor}
.b-match{background:rgba(79,255,176,.1);color:var(--a)}
.b-apply{background:rgba(0,201,255,.1);color:var(--a2)}
.b-new{background:rgba(255,209,102,.08);color:var(--pen)}
.b-src{background:var(--s2);color:var(--mu);font-size:10px}
.b-src::before{display:none}

/* Job right */
.job-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0}
.j-date{font-size:11px;color:var(--mu)}
.j-reason{font-size:10px;color:rgba(79,255,176,.6);font-style:italic;max-width:140px;text-align:right;line-height:1.4}

/* Empty state */
.empty{text-align:center;padding:56px 20px;color:var(--mu)}
.empty .ei{font-size:36px;margin-bottom:10px}
.empty p{font-size:14px}

/* Pagination */
.pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:20px;flex-wrap:wrap}
.ppage{padding:6px 12px;border-radius:7px;font-size:13px;color:var(--mu);
  text-decoration:none;border:1px solid var(--b);background:var(--surface);transition:all .15s}
.ppage:hover{border-color:var(--a);color:var(--a)}
.ppage.active{background:rgba(79,255,176,.1);border-color:var(--a);color:var(--a);font-weight:700}
.ppage.disabled{opacity:.3;pointer-events:none}

/* Hamburger */
.menu-btn{display:none;position:fixed;top:14px;left:14px;width:42px;height:42px;
  background:var(--surface);border:1px solid var(--b);border-radius:8px;cursor:pointer;
  z-index:999;flex-direction:column;align-items:center;justify-content:center;gap:5px}
.menu-btn:hover{border-color:var(--a)}
.menu-btn .ln{width:18px;height:2px;background:var(--tx);border-radius:1px;transition:all .2s}
.menu-btn.open .ln:nth-child(1){transform:rotate(45deg) translateY(10px)}
.menu-btn.open .ln:nth-child(2){opacity:0}
.menu-btn.open .ln:nth-child(3){transform:rotate(-45deg) translateY(-10px)}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99}
.sb-overlay.open{display:block}

/* Responsive */
@media(max-width:1024px){
  .stats{grid-template-columns:repeat(3,1fr)}
  .main{padding:24px 20px}
}
@media(max-width:900px){
  .sidebar{position:fixed;left:0;top:0;height:100vh;z-index:100;
    transform:translateX(-100%);transition:transform .3s ease;box-shadow:2px 0 20px rgba(0,0,0,.4)}
  .sidebar.open{transform:translateX(0)}
  .menu-btn{display:flex}
  .main{padding:20px 16px;padding-top:72px}
}
@media(max-width:640px){
  .main{padding:14px 12px;padding-top:68px}
  .stats{grid-template-columns:repeat(2,1fr);gap:10px}
  .stat{padding:12px 14px}
  .stat-val{font-size:18px}
  .ph-title{font-size:20px}
  .btn-group{width:100%}
  .btn{flex:1;justify-content:center;padding:10px 14px;font-size:12px}
  .job-card{flex-wrap:wrap;gap:10px}
  .co-av{display:none}
  .job-right{flex-direction:row;align-items:center;width:100%}
  .j-reason{display:none}
  .toolbar{flex-direction:column;align-items:stretch}
  .filter-tabs{width:100%}
}
@media(max-width:420px){
  .stats{grid-template-columns:repeat(2,1fr)}
  .btn{font-size:11px;padding:9px 10px}
}
</style>
</head>
<body>
<div class="layout">

<button class="menu-btn" id="menuBtn"><span class="ln"></span><span class="ln"></span><span class="ln"></span></button>
<div class="sb-overlay" id="sbOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="logo">Reach<span>Out</span></div>
  <div class="nav-sec">Outreach</div>
  <a class="nav-item" href="index.php"><span class="ic">📋</span> Dashboard</a>
  <a class="nav-item" href="send.php"><span class="ic">📤</span> Send Emails</a>
  <a class="nav-item" href="whatsapp.php"><span class="ic">💬</span> Send WhatsApp</a>
  <a class="nav-item" href="index.php#csv-section"><span class="ic">📂</span> CSV Import</a>
  <div class="nav-sec">Jobs</div>
  <a class="nav-item active" href="jobs.php"><span class="ic">🔍</span> Job Hunt</a>
  <a class="nav-item" href="pipeline.php"><span class="ic">📊</span> Pipeline</a>
  <a class="nav-item" href="ai_tailor.php"><span class="ic">🤖</span> AI Tailor</a>
  <div class="nav-sec">Automation</div>
  <a class="nav-item" href="job_fetch.php?secret=MY_CRON_SECRET_2024"><span class="ic">🔎</span> Fetch Jobs</a>
  <a class="nav-item" href="followup_cron.php?secret=MY_CRON_SECRET_2024"><span class="ic">🔁</span> Follow-ups</a>
  <a class="nav-item" href="reply_check.php?secret=MY_CRON_SECRET_2024"><span class="ic">📬</span> Check Replies</a>
  <a class="nav-item" href="wa_digest.php?secret=MY_CRON_SECRET_2024"><span class="ic">📱</span> WA Digest</a>
  <a class="nav-item" href="cron_log.php"><span class="ic">📅</span> Cron Logs</a>
  <div class="nav-sec">Settings</div>
  <a class="nav-item" href="blacklist.php"><span class="ic">🚫</span> Blacklist</a>
  <a class="nav-item" href="profile.php"><span class="ic">👤</span> My Profile</a>
  <a class="nav-item" href="whatsapp_logs.php"><span class="ic">📃</span> WA Logs</a>

  <div class="sb-bottom">
    <div class="user-chip">
      <div class="u-av"><?= strtoupper(substr($profile['name']??'U',0,1)) ?></div>
      <div>
        <div class="u-name"><?= htmlspecialchars($profile['name']??'User') ?></div>
        <div class="u-email"><?= htmlspecialchars($profile['email']??'') ?></div>
      </div>
    </div>
    <button id="logoutBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Logout
    </button>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <!-- Header -->
  <div class="ph">
    <div>
      <div class="ph-title">Job Hunt</div>
      <div class="ph-sub">
        <?= $stats['total'] ?> fetched &nbsp;·&nbsp;
        <?= $stats['matched'] ?> matched &nbsp;·&nbsp;
        <?= $stats['queue'] ?> in queue &nbsp;·&nbsp;
        <?= $stats['sent'] ?> sent
      </div>
    </div>
    <div class="btn-group">
      <button class="btn btn-secondary" id="fetchBtn">
        <span id="fIcon">🔍</span>
        <span id="fText">Fetch Jobs</span>
      </button>
      <button class="btn btn-primary" id="sendBtn">
        <span id="sIcon">📤</span>
        <span id="sText">Send Emails <?= $stats['queue']>0 ? "({$stats['queue']})" : "" ?></span>
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat"><div class="stat-label">Total Jobs</div><div class="stat-val c-blue"><?= $stats['total'] ?></div></div>
    <div class="stat"><div class="stat-label">Matched</div><div class="stat-val c-green"><?= $stats['matched'] ?></div></div>
    <div class="stat"><div class="stat-label">AI Queue</div><div class="stat-val c-yellow"><?= $stats['queue'] ?></div></div>
    <div class="stat"><div class="stat-label">Sent</div><div class="stat-val c-green"><?= $stats['sent'] ?></div></div>
    <div class="stat"><div class="stat-label">Applied</div><div class="stat-val c-blue"><?= $stats['applied'] ?></div></div>
  </div>

  <!-- Terminals -->
  <div class="terminal term-fetch" id="fetchPanel"></div>
  <div class="terminal term-send"  id="sendPanel"></div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-box">
      <span class="si">🔍</span>
      <input type="text" id="searchInput" placeholder="Search jobs, companies..."
        value="<?= htmlspecialchars($search) ?>"
        onkeydown="if(event.key==='Enter')doSearch()">
    </div>
    <div class="filter-tabs">
      <?php
        $filters = ['all'=>"All ({$stats['total']})","matched"=>"✅ Matched","new"=>"🆕 New","applied"=>"📤 Applied"];
        foreach($filters as $k=>$label):
      ?>
      <a class="ftab <?= $filter===$k?'active':'' ?>"
         href="?filter=<?=$k?>&search=<?=urlencode($search)?>"><?=$label?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Jobs list -->
  <div class="jobs-list">
    <?php if ($stats['total'] == 0): ?>
    <div class="empty">
      <div class="ei">🔭</div>
      <p>No jobs yet — click <strong>Fetch Jobs</strong> to start</p>
    </div>
    <?php elseif ($totalRows == 0): ?>
    <div class="empty">
      <div class="ei">😕</div>
      <p>No jobs found for this filter</p>
    </div>
    <?php else: ?>
    <?php foreach ($jobRows as $j):
      $cls = $j['applied'] ? 'applied' : ($j['matched'] ? 'matched' : '');
    ?>
    <div class="job-card <?=$cls?>">
      <div class="co-av"><?= strtoupper(substr($j['company'],0,1)) ?></div>
      <div class="job-info">
        <div class="job-title">
          <?php if(!empty($j['job_link'])): ?>
          <a href="<?=htmlspecialchars($j['job_link'])?>" target="_blank" rel="noopener">
            <?=htmlspecialchars($j['title'])?>
          </a>
          <?php else: ?><?=htmlspecialchars($j['title'])?><?php endif; ?>
        </div>
        <div class="job-meta">
          <span>🏢 <?=htmlspecialchars($j['company'])?></span>
          <span class="m-dot"></span>
          <span>📍 <?=htmlspecialchars($j['location']?:'Remote')?></span>
          <?php if(!empty($j['email'])): ?>
          <span class="m-dot"></span>
          <span>✉️ <?=htmlspecialchars($j['email'])?></span>
          <?php endif; ?>
          <span class="m-dot"></span>
          <span class="badge b-src"><?=htmlspecialchars($j['source'])?></span>
          <?php if($j['applied']): ?>
          <span class="badge b-apply">Applied</span>
          <?php elseif($j['matched']): ?>
          <span class="badge b-match">Matched</span>
          <?php else: ?>
          <span class="badge b-new">New</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="job-right">
        <span class="j-date"><?= date('d M',strtotime($j['created_at'])) ?></span>
        <?php if(!empty($j['match_reason'])): ?>
        <span class="j-reason"><?=htmlspecialchars($j['match_reason'])?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if($totalPages>1): ?>
  <div class="pagination">
    <a class="ppage <?=$page<=1?'disabled':''?>"
       href="?filter=<?=$filter?>&search=<?=urlencode($search)?>&page=<?=$page-1?>">← Prev</a>
    <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
    <a class="ppage <?=$i==$page?'active':''?>"
       href="?filter=<?=$filter?>&search=<?=urlencode($search)?>&page=<?=$i?>"><?=$i?></a>
    <?php endfor; ?>
    <a class="ppage <?=$page>=$totalPages?'disabled':''?>"
       href="?filter=<?=$filter?>&search=<?=urlencode($search)?>&page=<?=$page+1?>">Next →</a>
  </div>
  <?php endif; ?>

</main>
</div>

<!-- Firebase Auth -->
<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
import { getAuth, onAuthStateChanged, signOut } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js";
const cfg = {
  apiKey:"AIzaSyCSKtb-4fG9H8d0uPSaBi8bTAEGJ9nBcbc",
  authDomain:"concise-8c322.firebaseapp.com",
  projectId:"concise-8c322",
  storageBucket:"concise-8c322.firebasestorage.app",
  messagingSenderId:"450041646748",
  appId:"1:450041646748:web:1e91c3fd5873233bb02759"
};
const auth = getAuth(initializeApp(cfg));
onAuthStateChanged(auth, u => { if(!u) location.href='login.html'; });
document.getElementById('logoutBtn').addEventListener('click', async ()=>{
  await signOut(auth); location.href='login.html';
});
</script>

<script>
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sbOverlay');
const menuBtn = document.getElementById('menuBtn');
function openSB(){ sidebar.classList.add('open'); overlay.classList.add('open'); menuBtn.classList.add('open'); }
function closeSB(){ sidebar.classList.remove('open'); overlay.classList.remove('open'); menuBtn.classList.remove('open'); }
menuBtn.addEventListener('click', ()=> sidebar.classList.contains('open') ? closeSB() : openSB());
overlay.addEventListener('click', closeSB);
document.querySelectorAll('.nav-item').forEach(el => el.addEventListener('click', closeSB));

// Search
function doSearch(){
  const q = document.getElementById('searchInput').value;
  location.href = '?filter=<?=$filter?>&search='+encodeURIComponent(q)+'&page=1';
}

// Stream helper
async function streamRun(url, panel, terminal) {
  panel.textContent = '';
  panel.className = 'terminal ' + terminal + ' show';
  try {
    const res    = await fetch(url);
    const reader = res.body.getReader();
    const dec    = new TextDecoder();
    while(true){
      const {done,value} = await reader.read();
      if(done) break;
      panel.textContent += dec.decode(value);
      panel.scrollTop = panel.scrollHeight;
    }
  } catch(e) {
    panel.textContent += '\n❌ Error: ' + e.message;
  }
}

// Fetch Jobs
document.getElementById('fetchBtn').addEventListener('click', async ()=>{
  const btn = document.getElementById('fetchBtn');
  const icon= document.getElementById('fIcon');
  const txt = document.getElementById('fText');
  btn.disabled=true; icon.className='spin'; icon.textContent='⟳'; txt.textContent='Fetching...';
  await streamRun('job_fetch.php?secret=MY_CRON_SECRET_2024', document.getElementById('fetchPanel'), 'term-fetch');
  btn.disabled=false; icon.className=''; icon.textContent='🔍'; txt.textContent='Fetch Jobs';
  setTimeout(()=> location.reload(), 1500);
});

// Send Emails
document.getElementById('sendBtn').addEventListener('click', async ()=>{
  if(!confirm('AI se personalized email + resume bhejne ke liye confirm karo?')) return;
  const btn = document.getElementById('sendBtn');
  const icon= document.getElementById('sIcon');
  const txt = document.getElementById('sText');
  btn.disabled=true; icon.className='spin'; icon.textContent='⟳'; txt.textContent='Sending...';
  await streamRun('cron.php?secret=MY_CRON_SECRET_2024', document.getElementById('sendPanel'), 'term-send');
  btn.disabled=false; icon.className=''; icon.textContent='📤'; txt.textContent='Send Emails';
  setTimeout(()=> location.reload(), 2000);
});

// PWA
if('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(()=>{});
</script>
</body>
</html>