<?php include 'db.php';

if(isset($_POST['save'])){
  $sub = $conn->real_escape_string($_POST['subject'] ?? '');
  $body = $conn->real_escape_string($_POST['body'] ?? '');
  
  $conn->query("REPLACE INTO email_template(id,subject,body) VALUES(1,'$sub','$body')");
  $success = true;
}

// Fetch existing template
$result = $conn->query("SELECT subject, body FROM email_template LIMIT 1");
$template = $result ? $result->fetch_assoc() : null;
$current_subject = $template['subject'] ?? '';
$current_body = $template['body'] ?? "Hello {{company}},\n\nMy name is {{name}}.\nPlease find my resume attached.\n\n{{cover_letter}}\n\nRegards,\n{{name}}\n{{mobile}}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Create and manage email templates for bulk sending">
<meta name="theme-color" content="#4fffb0">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ReachOut">
<link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 180 180'><rect fill='%234fffb0' width='180' height='180' rx='40'/><text x='50%' y='50%' font-size='90' fill='%230c0f14' text-anchor='middle' dy='.35em' font-family='Arial' font-weight='bold'>📧</text></svg>">
<link rel="manifest" href="manifest.json">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%234fffb0' width='100' height='100'/><text x='50%' y='50%' font-size='60' fill='%230c0f14' text-anchor='middle' dy='.35em' font-family='Arial' font-weight='bold'>📧</text></svg>">
<title>Email Template — ReachOut</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0c0f14;
    --surface: #141820;
    --surface2: #1c2230;
    --border: #252d3d;
    --accent: #4fffb0;
    --text: #e8edf5;
    --muted: #6b7a99;
  }
  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    padding: 40px 20px;
  }
  .container { max-width: 800px; margin: 0 auto; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 32px; }
  .card-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; margin-bottom: 24px; }
  .form-group { margin-bottom: 20px; }
  .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
  input[type="text"], textarea {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    padding: 12px;
    outline: none;
    transition: border-color 0.15s;
  }
  input[type="text"]:focus, textarea:focus { border-color: var(--accent); }
  textarea { resize: vertical; min-height: 300px; font-family: 'Courier New', monospace; }
  .help-text { font-size: 11px; color: var(--muted); margin-top: 6px; }
  .variables { background: rgba(79,255,176,0.05); border-left: 3px solid var(--accent); padding: 12px; border-radius: 4px; margin-top: 12px; font-size: 12px; }
  .variables strong { color: var(--accent); }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; border: none; background: var(--accent); color: var(--bg); transition: all 0.15s; }
  .btn:hover { background: #6bffc0; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(79,255,176,0.25); }
  .btn-group { display: flex; gap: 12px; }

  @media (max-width: 1200px) { body { padding: 32px 16px; } .card { padding: 28px; } }
  @media (max-width: 768px) {
    body { padding: 24px 12px; }
    .card { padding: 20px; }
    .card-title { font-size: 24px; margin-bottom: 20px; }
    input[type="text"], textarea { font-size: 16px; padding: 11px; }
    textarea { min-height: 250px; }
    .btn { padding: 11px 20px; font-size: 13px; width: 100%; justify-content: center; }
    .btn-group { flex-direction: column; }
  }
  @media (max-width: 640px) {
    body { padding: 16px 10px; }
    .card { padding: 16px; border-radius: 10px; }
    .card-title { font-size: 20px; margin-bottom: 16px; }
    .form-label { font-size: 11px; }
    input[type="text"], textarea { font-size: 16px; padding: 10px; border-radius: 6px; }
    textarea { min-height: 200px; }
    .help-text { font-size: 10px; }
  }
  @media (max-width: 480px) {
    body { padding: 12px 8px; }
    .card { padding: 14px; }
    .card-title { font-size: 18px; }
    input[type="text"], textarea { font-size: 16px; padding: 9px; }
  }
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="card-title">✉️ Email Template</div>
    <?php if(isset($success)): ?>
    <div style="background:rgba(79,255,176,0.1);border-left:3px solid #4fffb0;padding:12px;border-radius:4px;margin-bottom:16px;color:#4fffb0;font-size:13px;">✅ Template saved successfully!</div>
    <?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label class="form-label">Email Subject</label>
        <input type="text" name="subject" value="<?= htmlspecialchars($current_subject) ?>" placeholder="e.g., Application for {{position}}" required>
        <div class="help-text">Use {{variable}} for dynamic content</div>
      </div>

      <div class="form-group">
        <label class="form-label">Email Body</label>
        <textarea name="body" required><?= htmlspecialchars($current_body) ?></textarea>
        <div class="variables">
          <strong>Available Variables:</strong><br>
          {{name}} • {{email}} • {{company}} • {{mobile}} • {{cover_letter}}
        </div>
      </div>

      <div class="btn-group">
        <button type="submit" name="save" class="btn">💾 Save Template</button>
        <a href="index.php" class="btn" style="background:transparent;color:var(--text);border:1px solid var(--border);">← Back</a>
      </div>
    </form>
  </div>
</div>

<script>
// Register Service Worker for PWA
if('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').then(reg => {
    console.log('Service Worker registered');
  }).catch(err => {
    console.log('Service Worker registration failed:', err);
  });
}
</script>
</body>
</html>