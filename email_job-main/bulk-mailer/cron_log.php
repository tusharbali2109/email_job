<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cron Logs — ReachOut</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0c0f14; --surface: #141820; --surface2: #1c2230;
    --border: #252d3d; --accent: #4fffb0; --accent2: #00c9ff;
    --text: #e8edf5; --muted: #6b7a99; --danger: #ff5f6d;
    --pending: #ffd166; --radius: 12px;
  }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 32px 24px; }

  .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 12px; }
  .title { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
  .title small { display: block; font-family: 'DM Sans', sans-serif; font-size: 13px; color: var(--muted); font-weight: 400; margin-top: 2px; }

  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; }
  .btn-primary { background: var(--accent); color: #0c0f14; }
  .btn-primary:hover { background: #6bffc0; transform: translateY(-1px); }
  .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); }
  .btn-outline:hover { border-color: var(--accent); color: var(--accent); }

  /* Stats row */
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 28px; }
  .stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; }
  .stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
  .stat-value { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; }
  .green  { color: var(--accent); }
  .yellow { color: var(--pending); }
  .red    { color: var(--danger); }
  .blue   { color: var(--accent2); }

  /* Table */
  .panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 24px; }
  .panel-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
  .panel-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; }
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  thead th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); background: var(--surface2); white-space: nowrap; }
  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(255,255,255,0.02); }
  tbody td { padding: 13px 16px; vertical-align: middle; }

  .badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
  .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
  .badge-done    { background: rgba(79,255,176,0.12);  color: var(--accent); }
  .badge-running { background: rgba(0,201,255,0.12);   color: var(--accent2); }
  .badge-error   { background: rgba(255,95,109,0.12);  color: var(--danger); }

  /* Detail expand */
  .detail-list { margin: 0; padding: 0; list-style: none; }
  .detail-list li { font-size: 12px; color: var(--muted); padding: 2px 0; }
  .detail-list li.ok  { color: var(--accent); }
  .detail-list li.err { color: var(--danger); }

  .expand-btn { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 12px; text-decoration: underline; padding: 0; }
  .expand-btn:hover { color: var(--text); }

  .empty { text-align: center; padding: 48px 20px; color: var(--muted); }
  .empty-icon { font-size: 36px; margin-bottom: 10px; }

  /* Run now section */
  .run-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 28px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
  .run-info h3 { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; margin-bottom: 4px; }
  .run-info p  { font-size: 13px; color: var(--muted); }
  #run-output { background: #000; border-radius: 8px; padding: 16px; font-family: monospace; font-size: 13px; color: #4fffb0; margin-top: 16px; min-height: 60px; display: none; white-space: pre-wrap; max-height: 300px; overflow-y: auto; width: 100%; }

  .back-link { color: var(--muted); text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px; }
  .back-link:hover { color: var(--accent); }

  @media (max-width: 640px) {
    body { padding: 16px 12px; }
    .title { font-size: 20px; }
    .stats { grid-template-columns: repeat(2,1fr); }
    table { font-size: 12px; }
    thead th, tbody td { padding: 10px 12px; }
  }
</style>
</head>
<body>

<a href="index.php" class="back-link">← Back to Dashboard</a>

<div class="top">
  <div class="title">
    Cron Logs
    <small>Daily auto job application history</small>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="cron_log.php" class="btn btn-outline">🔄 Refresh</a>
    <button class="btn btn-primary" id="run-now-btn">▶ Run Now (Test)</button>
  </div>
</div>

<?php
$totalRuns = (int)$pdo->query("SELECT COUNT(*) FROM cron_logs")->fetchColumn();
$totalSent = (int)($pdo->query("SELECT COALESCE(SUM(total_sent),0) FROM cron_logs")->fetchColumn() ?? 0);
$totalFail = (int)($pdo->query("SELECT COALESCE(SUM(total_fail),0) FROM cron_logs")->fetchColumn() ?? 0);
$lastRun   = $pdo->query("SELECT run_at FROM cron_logs ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 'Never';
?>

<!-- Stats -->
<div class="stats">
  <div class="stat">
    <div class="stat-label">Total Runs</div>
    <div class="stat-value blue"><?= $totalRuns ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Emails Sent</div>
    <div class="stat-value green"><?= $totalSent ?? 0 ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Failed</div>
    <div class="stat-value red"><?= $totalFail ?? 0 ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Last Run</div>
    <div class="stat-value" style="font-size:14px;margin-top:8px;color:var(--muted)"><?= $lastRun ?></div>
  </div>
</div>

<!-- Manual run section -->
<div class="run-card">
  <div class="run-info">
    <h3>🤖 Run Manually</h3>
    <p>Test karo — pending companies ko abhi email bhejo. Server cron ke baad yeh automatically chalega.</p>
  </div>
  <div id="run-output"></div>
</div>

<!-- Logs table -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">📋 Run History</div>
  </div>
  <div class="table-wrap">
    <?php
      $rows = $pdo->query("SELECT * FROM cron_logs ORDER BY id DESC LIMIT 50")->fetchAll();
    ?>
    <?php if (count($rows) === 0): ?>
      <div class="empty">
        <div class="empty-icon">📭</div>
        <p>No cron runs yet. Click "Run Now" to test or set up a server cron job.</p>
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Run At</th>
          <th>Sent</th>
          <th>Failed</th>
          <th>Skipped</th>
          <th>Status</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $log): ?>
        <?php
          $details = json_decode($log['details'] ?? '[]', true) ?: [];
          $badgeClass = match($log['status']) {
            'done'    => 'badge-done',
            'running' => 'badge-running',
            default   => 'badge-error'
          };
        ?>
        <tr>
          <td style="color:var(--muted)"><?= $log['id'] ?></td>
          <td style="white-space:nowrap"><?= $log['run_at'] ?></td>
          <td><span style="color:var(--accent);font-weight:600"><?= $log['total_sent'] ?></span></td>
          <td><span style="color:var(--danger)"><?= $log['total_fail'] ?></span></td>
          <td style="color:var(--muted)"><?= $log['total_skip'] ?></td>
          <td><span class="badge <?= $badgeClass ?>"><?= ucfirst($log['status']) ?></span></td>
          <td>
            <?php if (count($details) > 0): ?>
              <button class="expand-btn" onclick="toggleDetail(<?= $log['id'] ?>)">
                View <?= count($details) ?> records
              </button>
              <div id="detail-<?= $log['id'] ?>" style="display:none;margin-top:6px">
                <ul class="detail-list">
                  <?php foreach ($details as $d): ?>
                    <li class="<?= str_starts_with($d,'✅') ? 'ok' : 'err' ?>"><?= htmlspecialchars($d) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php else: ?>
              <span style="color:var(--muted);font-size:12px">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Cron setup guide -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">⚙️ Server Cron Setup</div>
  </div>
  <div style="padding:20px 24px">
    <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Server pe yeh command add karo — daily 9 AM pe automatically chalega:</p>

    <div style="background:#000;border-radius:8px;padding:14px 16px;font-family:monospace;font-size:13px;color:#4fffb0;margin-bottom:16px">
      0 9 * * * php <?= $_SERVER['DOCUMENT_ROOT'] ?>/cron.php >> <?= $_SERVER['DOCUMENT_ROOT'] ?>/cron_output.log 2>&1
    </div>

    <p style="font-size:13px;color:var(--muted);margin-bottom:8px"><strong style="color:var(--text)">cPanel mein kaise karo:</strong></p>
    <ol style="font-size:13px;color:var(--muted);padding-left:18px;line-height:2">
      <li>cPanel → Cron Jobs</li>
      <li>Common Settings → "Once a day" select karo</li>
      <li>Command field mein: <code style="color:var(--accent)">php <?= $_SERVER['DOCUMENT_ROOT'] ?>/cron.php</code></li>
      <li>Save karo ✅</li>
    </ol>

    <p style="font-size:13px;color:var(--muted);margin-top:14px">
      <strong style="color:var(--text)">Browser se test:</strong>
      <code style="color:var(--accent);margin-left:8px"><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) ?>/cron.php?secret=MY_CRON_SECRET_2024</code>
    </p>
  </div>
</div>

<script>
  function toggleDetail(id) {
    const el = document.getElementById('detail-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }

  // Run cron manually via AJAX
  document.getElementById('run-now-btn').addEventListener('click', async function() {
    const btn = this;
    const out = document.getElementById('run-output');

    btn.disabled = true;
    btn.textContent = '⏳ Running...';
    out.style.display = 'block';
    out.textContent = 'Starting cron job...\n';

    try {
      const res = await fetch('cron.php?secret=MY_CRON_SECRET_2024');
      const text = await res.text();
      out.textContent = text || 'Done!';
    } catch (e) {
      out.textContent = 'Error: ' + e.message;
    }

    btn.disabled = false;
    btn.textContent = '▶ Run Now (Test)';

    // Refresh stats after 1.5s
    setTimeout(() => location.reload(), 1500);
  });
</script>
</body>
</html>