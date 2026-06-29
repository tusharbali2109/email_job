<?php
include 'db.php';

// API: update stage or save notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    if ($action === 'move') {
        $id    = (int)($_POST['id'] ?? 0);
        $stage = $_POST['stage'] ?? 'applied';
        $allowed = ['applied','replied','interview','offer','rejected'];
        if ($id && in_array($stage, $allowed)) {
            $pdo->prepare("UPDATE companies SET pipeline_stage=?, stage_updated_at=NOW() WHERE id=?")
                ->execute([$stage, $id]);
        }
        echo json_encode(['success' => true]);
    } elseif ($action === 'note') {
        $id   = (int)($_POST['id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        if ($id) {
            $pdo->prepare("UPDATE companies SET notes=? WHERE id=?")->execute([$note, $id]);
        }
        echo json_encode(['success' => true]);
    }
    exit;
}

// Fetch all sent companies grouped by stage
$stages = ['applied','replied','interview','offer','rejected'];
$cards = [];
foreach ($stages as $s) {
    $stmt = $pdo->prepare("SELECT id,name,company,email,contact,opened,replied,notes,pipeline_stage,stage_updated_at FROM companies WHERE status='sent' AND (pipeline_stage=? OR (pipeline_stage IS NULL AND ?='applied')) ORDER BY stage_updated_at DESC NULLS LAST");
    $stmt->execute([$s, $s]);
    $cards[$s] = $stmt->fetchAll();
}

$stageMeta = [
    'applied'   => ['label'=>'Applied',   'color'=>'#4fffb0', 'icon'=>'📤'],
    'replied'   => ['label'=>'Replied',   'color'=>'#00c9ff', 'icon'=>'📬'],
    'interview' => ['label'=>'Interview', 'color'=>'#ffd166', 'icon'=>'🎯'],
    'offer'     => ['label'=>'Offer',     'color'=>'#b388ff', 'icon'=>'🎉'],
    'rejected'  => ['label'=>'Rejected',  'color'=>'#ff5f6d', 'icon'=>'❌'],
];

$activePage = 'pipeline';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pipeline — ReachOut</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0c0f14;--surface:#141820;--s2:#1c2230;--b:#252d3d;--tx:#e8edf5;--mu:#6b7a99;--radius:12px;}
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
.main{flex:1;padding:32px;overflow-x:auto;}
h1{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;margin-bottom:6px;}
.sub{color:var(--mu);font-size:14px;margin-bottom:28px;}
.board{display:flex;gap:16px;min-width:900px;}
.col{flex:1;min-width:200px;background:var(--surface);border-radius:14px;border:1px solid var(--b);display:flex;flex-direction:column;min-height:400px;}
.col-header{padding:16px;border-bottom:1px solid var(--b);display:flex;align-items:center;justify-content:space-between;}
.col-title{font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;}
.col-count{background:var(--s2);border-radius:20px;padding:2px 10px;font-size:12px;color:var(--mu);}
.col-body{padding:12px;display:flex;flex-direction:column;gap:10px;flex:1;}
.card{background:var(--s2);border-radius:10px;padding:14px;border:1px solid var(--b);cursor:grab;transition:box-shadow 0.2s;user-select:none;}
.card:hover{box-shadow:0 4px 20px rgba(0,0,0,0.3);}
.card.dragging{opacity:0.5;cursor:grabbing;}
.col.drag-over{background:rgba(79,255,176,0.04);border-color:#4fffb0;}
.card-name{font-size:13px;font-weight:600;margin-bottom:4px;}
.card-company{font-size:12px;color:#4fffb0;margin-bottom:6px;}
.card-email{font-size:11px;color:var(--mu);margin-bottom:8px;}
.card-tags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;}
.tag{font-size:10px;padding:2px 7px;border-radius:4px;font-weight:600;}
.tag-opened{background:rgba(79,255,176,0.15);color:#4fffb0;}
.tag-replied{background:rgba(0,201,255,0.15);color:#00c9ff;}
.card-note{font-size:11px;color:var(--mu);background:rgba(255,255,255,0.03);border-radius:6px;padding:6px 8px;cursor:text;min-height:28px;}
.card-note:empty::before{content:'Add note...';color:rgba(107,122,153,0.5);}
.drop-placeholder{border:2px dashed var(--b);border-radius:10px;min-height:60px;}
</style>
</head>
<body>
<div class="layout">
<?php include '_nav.php'; ?>
<main class="main">
  <h1>📊 Application Pipeline</h1>
  <p class="sub">Drag companies across stages — Applied → Replied → Interview → Offer</p>

  <div class="board" id="board">
    <?php foreach ($stages as $stage):
      $meta = $stageMeta[$stage];
      $list = $cards[$stage];
    ?>
    <div class="col" data-stage="<?= $stage ?>" ondragover="onDragOver(event)" ondrop="onDrop(event,this)" ondragleave="onDragLeave(this)">
      <div class="col-header">
        <div class="col-title" style="color:<?= $meta['color'] ?>"><?= $meta['icon'] ?> <?= $meta['label'] ?></div>
        <span class="col-count"><?= count($list) ?></span>
      </div>
      <div class="col-body">
        <?php foreach ($list as $c): ?>
        <div class="card" draggable="true" data-id="<?= $c['id'] ?>"
             ondragstart="onDragStart(event,this)" ondragend="onDragEnd(this)">
          <div class="card-name"><?= htmlspecialchars($c['name']) ?></div>
          <div class="card-company"><?= htmlspecialchars($c['company']) ?></div>
          <div class="card-email"><?= htmlspecialchars($c['email']) ?></div>
          <div class="card-tags">
            <?php if ($c['opened']): ?><span class="tag tag-opened">Opened</span><?php endif; ?>
            <?php if ($c['replied']): ?><span class="tag tag-replied">Replied</span><?php endif; ?>
          </div>
          <div class="card-note" contenteditable="true"
               onblur="saveNote(<?= $c['id'] ?>, this)"><?= htmlspecialchars($c['notes'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
</div>
<script>
let dragging = null;

function onDragStart(e, el) {
  dragging = el;
  el.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function onDragEnd(el) {
  el.classList.remove('dragging');
  document.querySelectorAll('.col').forEach(c => c.classList.remove('drag-over'));
}
function onDragOver(e) {
  e.preventDefault();
  e.currentTarget.classList.add('drag-over');
}
function onDragLeave(col) { col.classList.remove('drag-over'); }
function onDrop(e, col) {
  e.preventDefault();
  col.classList.remove('drag-over');
  if (!dragging) return;
  const stage = col.dataset.stage;
  const id    = dragging.dataset.id;
  col.querySelector('.col-body').appendChild(dragging);

  // Update count badges
  document.querySelectorAll('.col').forEach(c => {
    c.querySelector('.col-count').textContent = c.querySelectorAll('.card').length;
  });

  fetch('pipeline.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=move&id=${id}&stage=${stage}`
  });
}
function saveNote(id, el) {
  const note = el.innerText.trim();
  fetch('pipeline.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=note&id=${id}&note=${encodeURIComponent(note)}`
  });
}
</script>
</body>
</html>
