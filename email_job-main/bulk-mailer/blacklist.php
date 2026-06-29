<?php
include 'db.php';

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'add') {
        $type  = in_array($_POST['type'] ?? '', ['email','domain']) ? $_POST['type'] : 'email';
        $value = strtolower(trim($_POST['value'] ?? ''));
        $reason= trim($_POST['reason'] ?? '');
        if ($value) {
            try {
                $pdo->prepare("INSERT INTO blacklist (type,value,reason) VALUES (?,?,?) ON CONFLICT (value) DO NOTHING")
                    ->execute([$type, $value, $reason]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Value required']);
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM blacklist WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
    }
    exit;
}

$items = $pdo->query("SELECT * FROM blacklist ORDER BY created_at DESC")->fetchAll();
$activePage = 'blacklist';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Blacklist — ReachOut</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0c0f14;--surface:#141820;--s2:#1c2230;--b:#252d3d;--a:#ff5f6d;--a2:#ff8a65;--tx:#e8edf5;--mu:#6b7a99;--radius:12px;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;}
.layout{display:flex;min-height:100vh;}
.sidebar{width:240px;flex-shrink:0;background:var(--surface);border-right:1px solid var(--b);display:flex;flex-direction:column;padding:28px 0;position:sticky;top:0;height:100vh;overflow-y:auto;}
.logo{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--tx);padding:0 24px 24px;letter-spacing:-0.5px;}
.logo span{color:#4fffb0;}
.nav-label{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--mu);padding:16px 24px 6px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 24px;color:var(--mu);text-decoration:none;font-size:14px;font-weight:500;transition:all 0.2s;border-left:3px solid transparent;}
.nav-item:hover,.nav-item.active{color:var(--tx);background:var(--s2);border-left-color:#4fffb0;}
.icon{font-size:16px;}
.sidebar-bottom{margin-top:auto;padding:16px;}
.main{flex:1;padding:40px;}
h1{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;margin-bottom:8px;}
.sub{color:var(--mu);font-size:14px;margin-bottom:32px;}
.card{background:var(--surface);border:1px solid var(--b);border-radius:var(--radius);padding:24px;margin-bottom:24px;}
.card h2{font-size:16px;font-weight:700;margin-bottom:16px;}
.form-row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.form-group{display:flex;flex-direction:column;gap:6px;}
label{font-size:12px;color:var(--mu);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
input,select{background:var(--s2);border:1px solid var(--b);border-radius:8px;padding:10px 14px;color:var(--tx);font-size:14px;min-width:200px;}
input:focus,select:focus{outline:none;border-color:var(--a);}
.btn{padding:10px 20px;border-radius:8px;border:none;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.2s;}
.btn-danger{background:var(--a);color:#fff;}
.btn-danger:hover{opacity:0.85;}
.btn-sm{padding:6px 12px;font-size:12px;border-radius:6px;border:none;cursor:pointer;font-weight:600;}
.btn-del{background:rgba(255,95,109,0.15);color:#ff5f6d;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;font-size:11px;color:var(--mu);text-transform:uppercase;letter-spacing:1px;padding:10px 12px;border-bottom:1px solid var(--b);}
td{padding:12px;border-bottom:1px solid rgba(37,45,61,0.5);font-size:14px;}
.badge{padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;}
.badge-email{background:rgba(255,95,109,0.15);color:#ff5f6d;}
.badge-domain{background:rgba(255,138,101,0.15);color:#ff8a65;}
.empty{color:var(--mu);text-align:center;padding:40px;font-size:14px;}
</style>
</head>
<body>
<div class="layout">
<?php include '_nav.php'; ?>
<main class="main">
  <h1>🚫 Blacklist</h1>
  <p class="sub">Block emails or domains from being imported or added to queue</p>

  <div class="card">
    <h2>Add to Blacklist</h2>
    <div class="form-row">
      <div class="form-group">
        <label>Type</label>
        <select id="bl-type">
          <option value="email">Email</option>
          <option value="domain">Domain</option>
        </select>
      </div>
      <div class="form-group">
        <label>Value</label>
        <input type="text" id="bl-value" placeholder="e.g. spam@example.com or example.com">
      </div>
      <div class="form-group">
        <label>Reason (optional)</label>
        <input type="text" id="bl-reason" placeholder="Why blacklisted?">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button class="btn btn-danger" onclick="addBlacklist()">Add</button>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Blacklisted Entries (<?= count($items) ?>)</h2>
    <?php if (empty($items)): ?>
      <div class="empty">No blacklist entries yet. Add emails or domains above.</div>
    <?php else: ?>
    <table>
      <tr><th>Type</th><th>Value</th><th>Reason</th><th>Added</th><th></th></tr>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><span class="badge badge-<?= $item['type'] ?>"><?= $item['type'] ?></span></td>
        <td><?= htmlspecialchars($item['value']) ?></td>
        <td style="color:var(--mu)"><?= htmlspecialchars($item['reason'] ?? '') ?></td>
        <td style="color:var(--mu);font-size:12px"><?= date('d M Y', strtotime($item['created_at'])) ?></td>
        <td><button class="btn btn-sm btn-del" onclick="delBlacklist(<?= $item['id'] ?>, this)">Delete</button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</main>
</div>
<script>
async function addBlacklist() {
  const type   = document.getElementById('bl-type').value;
  const value  = document.getElementById('bl-value').value.trim();
  const reason = document.getElementById('bl-reason').value.trim();
  if (!value) return alert('Enter a value');
  const res = await fetch('blacklist.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=add&type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}&reason=${encodeURIComponent(reason)}`
  });
  const d = await res.json();
  if (d.success) location.reload();
  else alert(d.error);
}
async function delBlacklist(id, btn) {
  if (!confirm('Remove from blacklist?')) return;
  const res = await fetch('blacklist.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=delete&id=${id}`
  });
  const d = await res.json();
  if (d.success) btn.closest('tr').remove();
}
</script>
</body>
</html>
