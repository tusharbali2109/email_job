<?php
include 'db.php';

// Create table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_logs (
            id             SERIAL PRIMARY KEY,
            company_id     INT,
            hr_name        VARCHAR(255),
            mobile         VARCHAR(50),
            candidate_name VARCHAR(255),
            sent_at        TIMESTAMP DEFAULT NOW(),
            status         VARCHAR(20),
            failure_reason TEXT
        )
    ");
} catch (Exception $e) {}

$total  = (int)$pdo->query("SELECT COUNT(*) FROM whatsapp_logs")->fetchColumn();
$sent   = (int)$pdo->query("SELECT COUNT(*) FROM whatsapp_logs WHERE status='sent'")->fetchColumn();
$failed = (int)$pdo->query("SELECT COUNT(*) FROM whatsapp_logs WHERE status='failed'")->fetchColumn();
$rows   = $pdo->query("SELECT * FROM whatsapp_logs ORDER BY sent_at DESC LIMIT 200")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#4fffb0">
<title>WhatsApp Logs — ReachOut</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0c0f14; --surface: #141820; --surface2: #1c2230;
  --border: #252d3d; --accent: #25d366; --accent2: #128c7e;
  --text: #e8edf5; --muted: #6b7a99; --danger: #ff5f6d;
  --warning: #ffd166; --radius: 12px;
}
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
.layout { display: flex; min-height: 100vh; }
.sidebar { width: 240px; flex-shrink: 0; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 28px 0; position: sticky; top: 0; height: 100vh; }
.logo { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; padding: 0 24px 32px; letter-spacing: -0.5px; }
.logo span { color: #4fffb0; }
.nav-label { font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); padding: 0 24px 10px; }
.nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 24px; font-size: 14px; color: var(--muted); text-decoration: none; transition: all 0.15s; border-left: 3px solid transparent; }
.nav-item:hover { color: var(--text); background: var(--surface2); }
.nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(37,211,102,0.06); }
.nav-item .icon { font-size: 17px; width: 22px; text-align: center; }
.main { flex: 1; padding: 40px 48px; }
.page-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; letter-spacing: -1px; margin-bottom: 6px; }
.page-subtitle { font-size: 14px; color: var(--muted); margin-bottom: 28px; }
.stats { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 18px 22px; min-width: 130px; }
.stat-label { font-size: 12px; color: var(--muted); margin-bottom: 6px; }
.stat-value { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 700; }
.green { color: var(--accent); } .red { color: var(--danger); } .blue { color: #00c9ff; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th { text-align: left; padding: 11px 16px; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); background: var(--surface2); }
tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
tbody tr:hover { background: rgba(255,255,255,0.02); }
tbody td { padding: 12px 16px; vertical-align: middle; }
.badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.badge-sent   { background: rgba(37,211,102,0.12); color: var(--accent); }
.badge-failed { background: rgba(255,95,109,0.12);  color: var(--danger); }
.empty { text-align: center; padding: 60px; color: var(--muted); font-size: 15px; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; }
.btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
.btn-ghost:hover { color: var(--text); border-color: var(--text); }
@media (max-width: 800px) { .layout { flex-direction: column; } .sidebar { display: none; } .main { padding: 20px 16px; } }
</style>
</head>
<body>
<div class="layout">

<aside class="sidebar">
  <div class="logo">Reach<span>Out</span></div>
  <div class="nav-label">Menu</div>
  <a class="nav-item" href="index.php"><span class="icon">📋</span> Dashboard</a>
  <a class="nav-item" href="send.php"><span class="icon">📤</span> Send Emails</a>
  <a class="nav-item" href="whatsapp.php"><span class="icon">💬</span> Send WhatsApp</a>
  <a class="nav-item active" href="whatsapp_logs.php"><span class="icon">📝</span> WA Logs</a>
  <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
  <a class="nav-item" href="index.php#csv-section"><span class="icon">📂</span> CSV Import</a>
</aside>

<main class="main">
  <div class="page-title">WhatsApp Activity Logs</div>
  <div class="page-subtitle">History of all WhatsApp messages sent via ReachOut</div>

  <div class="stats">
    <div class="stat-card"><div class="stat-label">Total</div><div class="stat-value blue"><?= $total ?></div></div>
    <div class="stat-card"><div class="stat-label">Sent</div><div class="stat-value green"><?= $sent ?></div></div>
    <div class="stat-card"><div class="stat-label">Failed</div><div class="stat-value red"><?= $failed ?></div></div>
    <div class="stat-card"><div class="stat-label">Success Rate</div><div class="stat-value green"><?= $total > 0 ? round(($sent/$total)*100) : 0 ?>%</div></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">📝 Send History</div>
      <a href="whatsapp.php" class="btn btn-ghost">💬 Send More</a>
    </div>
    <div class="table-wrap">
      <?php if (empty($rows)): ?>
      <div class="empty">
        <div style="font-size:36px;margin-bottom:12px">📭</div>
        No WhatsApp messages sent yet.
      </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>HR Name</th>
            <th>Mobile</th>
            <th>Candidate</th>
            <th>Sent At</th>
            <th>Status</th>
            <th>Reason</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td style="color:var(--muted)"><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['hr_name'] ?? '') ?></td>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($r['mobile'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['candidate_name'] ?? '') ?></td>
            <td style="color:var(--muted);font-size:12px"><?= $r['sent_at'] ? date('d M Y, H:i', strtotime($r['sent_at'])) : '—' ?></td>
            <td><span class="badge badge-<?= $r['status'] === 'sent' ? 'sent' : 'failed' ?>"><?= htmlspecialchars($r['status'] ?? '') ?></span></td>
            <td style="color:var(--danger);font-size:12px"><?= htmlspecialchars($r['failure_reason'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
</body>
</html>
