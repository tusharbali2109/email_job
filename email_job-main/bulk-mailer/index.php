<?php include 'db.php';
$total = (int)$pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$sent  = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE status='sent'")->fetchColumn();
$pend  = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE status='pending' OR status IS NULL OR status=''")->fetchColumn();
$rows  = $pdo->query("SELECT * FROM companies ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Send bulk emails with resume attachments to multiple companies">
<meta name="theme-color" content="#4fffb0">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ReachOut">
<link rel="manifest" href="manifest.json">
<title>ReachOut — Bulk Resume Sender</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #0c0f14;
    --surface: #141820;
    --surface2: #1c2230;
    --border: #252d3d;
    --accent: #4fffb0;
    --accent2: #00c9ff;
    --text: #e8edf5;
    --muted: #6b7a99;
    --danger: #ff5f6d;
    --sent: #4fffb0;
    --pending: #ffd166;
    --radius: 12px;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    background-image:
      radial-gradient(ellipse 60% 40% at 80% -10%, rgba(79,255,176,0.07) 0%, transparent 60%),
      radial-gradient(ellipse 50% 30% at 10% 100%, rgba(0,201,255,0.05) 0%, transparent 50%);
  }

  .layout { display: flex; min-height: 100vh; }

  /* ══════════════════════════════════
     SIDEBAR
  ══════════════════════════════════ */
  .sidebar {
    width: 240px; flex-shrink: 0;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    padding: 28px 0;
    position: sticky; top: 0; height: 100vh;
  }

  .logo {
    font-family: 'Syne', sans-serif;
    font-size: 22px; font-weight: 800;
    padding: 0 24px 32px;
    letter-spacing: -0.5px; color: var(--text);
  }
  .logo span { color: var(--accent); }

  .nav-label {
    font-size: 10px; font-weight: 600;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: var(--muted); padding: 0 24px 10px;
  }

  .nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 24px; font-size: 14px; color: var(--muted);
    text-decoration: none; transition: all 0.15s;
    border-left: 3px solid transparent; cursor: pointer;
  }
  .nav-item:hover { color: var(--text); background: var(--surface2); }
  .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(79,255,176,0.06); }
  .nav-item .icon { font-size: 18px; width: 22px; text-align: center; }

  /* ── SIDEBAR BOTTOM ── */
  .sidebar-bottom {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid var(--border);
  }

  .user-chip {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 10px; margin-bottom: 10px;
    overflow: hidden;
  }
  .user-avatar-sm {
    width: 34px; height: 34px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif; font-weight: 700;
    font-size: 13px; color: #0c0f14;
  }
  .user-info { min-width: 0; }
  .user-name-sm {
    font-size: 13px; font-weight: 600; color: var(--text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .user-email-sm {
    font-size: 11px; color: var(--muted);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }

  /* ── LOGOUT BUTTON ── */
  #logout-btn {
    width: 100%;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 10px 16px;
    background: rgba(255,95,109,0.1);
    border: 1px solid rgba(255,95,109,0.35);
    border-radius: 9px;
    color: #ff8f97;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px; font-weight: 600;
    cursor: pointer; letter-spacing: 0.2px;
    transition: all 0.2s;
  }
  #logout-btn:hover {
    background: rgba(255,95,109,0.2);
    border-color: rgba(255,95,109,0.6);
    color: var(--danger);
    transform: translateY(-1px);
  }
  #logout-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
  #logout-btn svg { width: 15px; height: 15px; flex-shrink: 0; }

  .version-text { font-size: 11px; color: var(--muted); text-align: center; margin-top: 10px; }

  /* ══════════════════════════════════
     MAIN
  ══════════════════════════════════ */
  .main { flex: 1; padding: 36px 40px; overflow-x: hidden; }

  .page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 36px; gap: 20px;
  }
  .page-title {
    font-family: 'Syne', sans-serif; font-size: 30px; font-weight: 800;
    letter-spacing: -1px; line-height: 1.1;
  }
  .page-title small {
    display: block; font-family: 'DM Sans', sans-serif; font-weight: 400;
    font-size: 14px; color: var(--muted); margin-top: 4px; letter-spacing: 0;
  }

  /* Stats */
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px; }
  .stat-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 20px; position: relative; overflow: hidden;
  }
  .stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, var(--accent), var(--accent2));
    opacity: 0; transition: opacity 0.2s;
  }
  .stat-card:hover::before { opacity: 1; }
  .stat-label { font-size: 12px; color: var(--muted); margin-bottom: 8px; }
  .stat-value { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 700; }
  .stat-value.green { color: var(--accent); }
  .stat-value.yellow { color: var(--pending); }
  .stat-value.blue { color: var(--accent2); }

  /* Panels */
  .panels { display: grid; grid-template-columns: 1fr 380px; gap: 24px; margin-bottom: 32px; }
  .panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
  .panel-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 20px 24px; border-bottom: 1px solid var(--border);
  }
  .panel-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
  .panel-icon { font-size: 18px; }
  .panel-body { padding: 24px; }

  /* Forms */
  .form-group { margin-bottom: 16px; }
  .form-label { display: block; font-size: 12px; font-weight: 500; color: var(--muted); margin-bottom: 6px; }
  input[type="text"], input[type="email"], input[type="file"], select {
    width: 100%; background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); font-family: 'DM Sans', sans-serif;
    font-size: 14px; padding: 10px 14px; outline: none; transition: border-color 0.15s;
  }
  input:focus, select:focus { border-color: var(--accent); }
  input[type="file"] { padding: 8px 14px; cursor: pointer; }
  input::placeholder { color: var(--muted); }

  /* Buttons */
  .btn {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
    border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px;
    font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s;
  }
  .btn-primary { background: var(--accent); color: #0c0f14; font-weight: 600; }
  .btn-primary:hover { background: #6bffc0; transform: translateY(-1px); }
  .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); }
  .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
  .btn-send {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: #0c0f14; font-weight: 700; padding: 13px 28px; font-size: 15px; border-radius: 10px;
  }
  .btn-send:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(79,255,176,0.3); }
  .btn-danger { background: rgba(255,95,109,0.1); color: var(--danger); border: 1px solid rgba(255,95,109,0.3); }
  .btn-danger:hover { background: rgba(255,95,109,0.2); }
  .btn-full { width: 100%; justify-content: center; }

  /* Table */
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  thead th {
    text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600;
    letter-spacing: 1px; text-transform: uppercase; color: var(--muted);
    border-bottom: 1px solid var(--border); background: var(--surface2);
  }
  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.12s; }
  tbody tr:hover { background: rgba(255,255,255,0.02); }
  tbody td { padding: 13px 16px; vertical-align: middle; }

  .avatar {
    width: 34px; height: 34px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif; font-weight: 700; font-size: 13px;
    color: #0c0f14; flex-shrink: 0;
  }
  .name-cell { display: flex; align-items: center; gap: 12px; }
  .name-main { font-weight: 500; }

  .badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500;
  }
  .badge-sent    { background: rgba(79,255,176,0.12);  color: var(--sent); }
  .badge-pending { background: rgba(255,209,102,0.12); color: var(--pending); }
  .badge-failed  { background: rgba(255,95,109,0.12);  color: var(--danger); }
  .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

  .actions { display: flex; gap: 8px; }

  /* Dropzone */
  .dropzone {
    border: 2px dashed var(--border); border-radius: 10px; padding: 28px;
    text-align: center; cursor: pointer; transition: all 0.2s; position: relative;
  }
  .dropzone:hover { border-color: var(--accent); background: rgba(79,255,176,0.03); }
  .dropzone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
  .dropzone-icon { font-size: 32px; margin-bottom: 10px; }
  .dropzone-text { font-size: 14px; color: var(--muted); }
  .dropzone-text strong { color: var(--accent); }

  .topbar-actions { display: flex; gap: 12px; align-items: center; }

  /* Modal */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
    z-index: 100; align-items: center; justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; padding: 32px; width: 100%; max-width: 480px;
    animation: slideUp 0.2s ease;
  }
  @keyframes slideUp { from { transform: translateY(16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  .modal-title {
    font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700;
    margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;
  }
  .modal-close { cursor: pointer; font-size: 22px; background: none; border: none; color: var(--muted); }
  .modal-close:hover { color: var(--text); }

  /* Toast */
  .toast {
    position: fixed; bottom: 28px; right: 28px;
    background: var(--surface2); border: 1px solid var(--accent); color: var(--accent);
    padding: 14px 20px; border-radius: 10px; font-size: 14px;
    display: none; align-items: center; gap: 10px; z-index: 200;
    animation: fadeIn 0.2s ease;
  }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

  .empty { text-align: center; padding: 60px 20px; color: var(--muted); }
  .empty-icon { font-size: 40px; margin-bottom: 12px; }
  .empty-text { font-size: 15px; }

  /* Hamburger */
  .menu-toggle {
    display: none; position: fixed; top: 16px; left: 16px;
    width: 44px; height: 44px; background: var(--surface); border: 1px solid var(--border);
    border-radius: 8px; cursor: pointer; z-index: 999;
    flex-direction: column; align-items: center; justify-content: center; gap: 5px;
    transition: all 0.2s;
  }
  .menu-toggle:hover { border-color: var(--accent); }
  .menu-toggle .line { width: 20px; height: 2px; background: var(--text); border-radius: 1px; transition: all 0.2s; }
  .menu-toggle.open .line:nth-child(1) { transform: rotate(45deg) translateY(10px); }
  .menu-toggle.open .line:nth-child(2) { opacity: 0; }
  .menu-toggle.open .line:nth-child(3) { transform: rotate(-45deg) translateY(-10px); }

  .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }
  .sidebar-overlay.open { display: block; }

  @keyframes spin { to { transform: rotate(360deg); } }

  /* Responsive */
  @media (max-width: 1200px) { .main { padding: 28px; } }

  @media (max-width: 900px) {
    .panels { grid-template-columns: 1fr; }
    .sidebar {
      position: fixed; left: 0; top: 0; height: 100vh; width: 240px;
      z-index: 100; transform: translateX(-100%); transition: transform 0.3s ease;
      box-shadow: 2px 0 20px rgba(0,0,0,0.3);
    }
    .sidebar.open { transform: translateX(0); }
    .menu-toggle { display: flex; }
    .main { padding: 24px 20px; padding-top: 80px; }
    .page-header { flex-direction: column; gap: 16px; }
    .stats { grid-template-columns: repeat(2, 1fr); }
  }

  @media (max-width: 640px) {
    .main { padding: 16px 12px; padding-top: 76px; }
    .page-title { font-size: 20px; }
    .stats { gap: 12px; }
    .stat-value { font-size: 20px; }
    .btn { width: 100%; }
    table { font-size: 12px; }
    thead th { padding: 8px 10px; font-size: 9px; }
    tbody td { padding: 8px 10px; }
    .modal { padding: 24px; max-width: 92vw; }
    .toast { bottom: 16px; right: 16px; }
  }
</style>
</head>
<body>

<div class="layout">

  <button class="menu-toggle" id="menu-toggle-btn">
    <span class="line"></span><span class="line"></span><span class="line"></span>
  </button>
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <!-- ═══════ SIDEBAR ═══════ -->
  <aside class="sidebar" id="sidebar">
    <div class="logo">Reach<span>Out</span></div>

    <div class="nav-label">Menu</div>
    <a class="nav-item active" href="index.php"><span class="icon">📋</span> Dashboard</a>
    <a class="nav-item" href="send.php"><span class="icon">📤</span> Send Emails</a>
    <a class="nav-item" href="whatsapp.php"><span class="icon">💬</span> Send WhatsApp</a>
    <a class="nav-item" href="#" id="add-contact-nav"><span class="icon">➕</span> Add Contact</a>
    <a class="nav-item" href="#csv-section"><span class="icon">📂</span> CSV Import</a>
<a class="nav-item" href="profile.php">
  <span class="icon">👤</span> My Profile
</a>
<a class="nav-item" href="job_fetch.php?secret=MY_CRON_SECRET_2024">
  <span class="icon">🔍</span> Fetch Jobs
</a>
<a class="nav-item" href="cron_log.php">
  <span class="icon">🤖</span> Cron Logs
</a>
<a class="nav-item" href="jobs.php">
  <span class="icon">🔍</span> Job Hunt
</a>

    <!-- User info + Logout at bottom -->
    <div class="sidebar-bottom">

      <!-- User chip -->
      <div class="user-chip">
        <div class="user-avatar-sm" id="sidebar-avatar">?</div>
        <div class="user-info">
          <div class="user-name-sm" id="sidebar-name">Loading...</div>
          <div class="user-email-sm" id="sidebar-email">...</div>
        </div>
      </div>

      <!-- LOGOUT BUTTON -->
      <button id="logout-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Logout
      </button>

      <div class="version-text">ReachOut v1.0</div>
    </div>
  </aside>

  <!-- ═══════ MAIN ═══════ -->
  <main class="main">

    <div class="page-header">
      <div class="page-title">
        Dashboard
        <small>Manage contacts &amp; bulk send your resume</small>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-outline" id="add-contact-top">➕ Add Contact</button>
        <a href="whatsapp.php" class="btn btn-outline" style="border-color:#25d366;color:#25d366;">💬 Send WhatsApp</a>
        <a href="send.php" class="btn btn-send">📤 Send All Emails</a>
      </div>
    </div>

    <!-- STATS -->
    <div class="stats">
      <?php // Stats already loaded at top ?>
      <div class="stat-card">
        <div class="stat-label">Total Contacts</div>
        <div class="stat-value blue"><?= $total ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Emails Sent</div>
        <div class="stat-value green"><?= $sent ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value yellow"><?= $pend ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Success Rate</div>
        <div class="stat-value green"><?= $total > 0 ? round(($sent/$total)*100) : 0 ?>%</div>
      </div>
    </div>

    <!-- PANELS -->
    <div class="panels">

      <!-- CONTACTS TABLE -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><span class="panel-icon">👥</span> Contacts</div>
          <button class="btn btn-outline" style="padding:7px 14px; font-size:13px;" id="add-contact-panel">+ Add</button>
        </div>
        <div class="table-wrap">
          <?php // $rows already loaded at top ?>
          <?php if(count($rows) === 0): ?>
            <div class="empty">
              <div class="empty-icon">📭</div>
              <div class="empty-text">No contacts yet. Add one or import a CSV.</div>
            </div>
          <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Contact</th><th>Email</th><th>Company</th>
                <th>Phone</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rows as $r): ?>
              <tr>
                <td>
                  <div class="name-cell">
                    <div class="avatar"><?= strtoupper(substr($r['name'],0,1)) ?></div>
                    <div class="name-main"><?= htmlspecialchars($r['name']) ?></div>
                  </div>
                </td>
                <td style="color:var(--muted)"><?= htmlspecialchars($r['email']) ?></td>
                <td><?= htmlspecialchars($r['company']) ?></td>
                <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($r['contact'] ?? '—') ?></td>
                <td>
                  <?php
                    $s   = strtolower($r['status'] ?? 'pending');
                    $cls = $s==='sent' ? 'badge-sent' : ($s==='failed' ? 'badge-failed' : 'badge-pending');
                    $lbl = $s==='sent' ? 'Sent' : ($s==='failed' ? 'Failed' : 'Pending');
                  ?>
                  <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                </td>
                <td>
                  <div class="actions">
                    <button class="btn btn-danger" style="padding:5px 12px;font-size:12px;border:none;cursor:pointer"
                            data-id="<?= $r['id'] ?>">🗑</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div style="display:flex;flex-direction:column;gap:20px">

        <div class="panel" id="csv-section">
          <div class="panel-header">
            <div class="panel-title"><span class="panel-icon">📂</span> CSV Import</div>
          </div>
          <div class="panel-body">
            <p style="font-size:13px;color:var(--muted);margin-bottom:16px;line-height:1.6">
              Upload a CSV file. You'll then <strong style="color:var(--accent)">map columns</strong> to match your database.
              Supports: <strong style="color:var(--text)">name, email, company, contact, phone</strong>
            </p>
            <form action="csv_preview.php" method="post" enctype="multipart/form-data">
              <div class="dropzone" id="dropzone">
                <input type="file" name="csv" required accept=".csv" id="csv-input">
                <div class="dropzone-icon">📄</div>
                <div class="dropzone-text" id="dz-text">
                  <strong>Click to browse</strong> or drag &amp; drop<br>CSV files only
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-full" style="margin-top:14px">
                ⬆ Upload &amp; Import
              </button>
            </form>
          </div>
        </div>

        <div class="panel">
          <div class="panel-header">
            <div class="panel-title"><span class="panel-icon">🚀</span> Quick Actions</div>
          </div>
          <div class="panel-body">
            <a href="send.php" class="btn btn-send btn-full" style="margin-bottom:12px">
              📤 Send All Pending Emails
            </a>
            <p style="text-align:center;font-size:12px;color:var(--muted)">
              <?= $pend ?> email<?= $pend!==1?'s':'' ?> queued to send
            </p>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modal">
  <div class="modal">
    <div class="modal-title">
      Add New Contact
      <button class="modal-close" id="modal-close-btn">×</button>
    </div>
    <form action="create.php" method="post">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" placeholder="Jane Smith" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" placeholder="jane@company.com" required>
      </div>
      <div class="form-group">
        <label class="form-label">Company</label>
        <input type="text" name="company" placeholder="Acme Corp" required>
      </div>
      <div class="form-group">
        <label class="form-label">Phone / Contact (Optional)</label>
        <input type="text" name="phone" placeholder="+91-9876543210">
      </div>
      <div style="display:flex;gap:12px;margin-top:24px">
        <button type="button" class="btn btn-outline" id="modal-cancel-btn" style="flex:1;justify-content:center">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center">Save Contact</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<!-- Firebase — type="module" for auth -->
<script type="module">
  import { initializeApp }         from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
  import { getAuth, onAuthStateChanged,
           signOut }                from "https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js";

  const firebaseConfig = {
    apiKey:            "AIzaSyCSKtb-4fG9H8d0uPSaBi8bTAEGJ9nBcbc",
    authDomain:        "concise-8c322.firebaseapp.com",
    projectId:         "concise-8c322",
    storageBucket:     "concise-8c322.firebasestorage.app",
    messagingSenderId: "450041646748",
    appId:             "1:450041646748:web:1e91c3fd5873233bb02759"
  };

  const app  = initializeApp(firebaseConfig);
  const auth = getAuth(app);

  // Show user info in sidebar
  onAuthStateChanged(auth, user => {
    if (!user) {
      window.location.href = 'login.html';
      return;
    }
    const name  = user.displayName || user.email.split('@')[0];
    document.getElementById('sidebar-avatar').textContent = name.charAt(0).toUpperCase();
    document.getElementById('sidebar-name').textContent   = name;
    document.getElementById('sidebar-email').textContent  = user.email;
  });

  // Logout — addEventListener (module scope, onclick nahi chalega)
  document.getElementById('logout-btn').addEventListener('click', async () => {
    const btn = document.getElementById('logout-btn');
    btn.disabled = true;
    btn.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"
           stroke-linecap="round" style="animation:spin 0.8s linear infinite">
        <circle cx="12" cy="12" r="9" stroke-dasharray="40" stroke-dashoffset="20"/>
      </svg>
      Logging out...`;
    await signOut(auth);
    window.location.href = 'login.html';
  });
</script>

<!-- Regular JS -->
<script>
  // Sidebar
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const menuBtn = document.getElementById('menu-toggle-btn');

  menuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
    menuBtn.classList.toggle('open');
  });
  overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    menuBtn.classList.remove('open');
  });
  document.querySelectorAll('.nav-item').forEach(el => el.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    menuBtn.classList.remove('open');
  }));

  // Modal
  const modal = document.getElementById('modal');
  function openModal()  { modal.classList.add('open'); }
  function closeModal() { modal.classList.remove('open'); }

  document.getElementById('add-contact-top').addEventListener('click',   openModal);
  document.getElementById('add-contact-nav').addEventListener('click',   openModal);
  document.getElementById('add-contact-panel').addEventListener('click', openModal);
  document.getElementById('modal-close-btn').addEventListener('click',   closeModal);
  document.getElementById('modal-cancel-btn').addEventListener('click',  closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

  // CSV
  document.getElementById('csv-input').addEventListener('change', function() {
    const n = this.files[0]?.name;
    if (n) document.getElementById('dz-text').innerHTML =
      `<strong style="color:var(--accent)">${n}</strong><br>Ready to import`;
  });

  // Toast helper
  function showToast(msg, color) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.display = 'flex';
    t.style.borderColor = color || 'var(--accent)';
    t.style.color = color || 'var(--accent)';
    setTimeout(() => t.style.display = 'none', 3500);
  }

  // Delete
  document.querySelectorAll('[data-id]').forEach(btn => {
    btn.addEventListener('click', async function() {
      if (!confirm('Delete this contact?')) return;
      try {
        const res = await fetch('delete.php?id=' + this.dataset.id);
        if (res.ok) { showToast('✅ Contact deleted!'); setTimeout(() => location.reload(), 1200); }
        else showToast('❌ Error!', 'var(--danger)');
      } catch { showToast('❌ Error!', 'var(--danger)'); }
    });
  });

  // URL params
  const p = new URLSearchParams(location.search);
  if (p.has('import_success')) {
    const imp = p.get('import_success'), fail = p.get('import_failed') || 0;
    showToast(fail > 0 ? `⚠️ Imported ${imp}, ${fail} failed!` : `✅ Imported ${imp} contacts!`,
              fail > 0 ? '#ffd166' : null);
    history.replaceState({}, '', location.pathname);
  }
  if (p.get('success') === '1') {
    showToast('✅ Contact added successfully!');
    history.replaceState({}, '', location.pathname);
  }

  // PWA
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
</script>
</body>
</html>