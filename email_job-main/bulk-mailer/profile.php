<?php
include 'db.php';

// Load existing profile
$profile = $pdo->query("SELECT * FROM user_profile WHERE id=1")->fetch();

if (isset($_POST['save'])) {
    $name         = $_POST['name']        ?? '';
    $email        = $_POST['email']       ?? '';
    $mobile       = $_POST['mobile']      ?? '';
    $cover        = $_POST['cover']       ?? '';
    $skills       = $_POST['skills']      ?? '';
    $job_role     = $_POST['job_role']    ?? '';
    $experience   = $_POST['experience']  ?? '';
    $daily        = (int)($_POST['daily_limit'] ?? 20);
    $smtp_pass    = $_POST['smtp_pass']   ?? '';
    $groq_key     = $_POST['groq_key']    ?? '';
    $location     = $_POST['location']    ?? '';
    $cron_enabled = isset($_POST['cron_enabled']) ? 1 : 0;
    $cron_time    = $_POST['cron_time']   ?? '09:00';
    $cron_days    = implode(',', $_POST['cron_days'] ?? ['mon','tue','wed','thu','fri']);

    $file = $profile['resume'] ?? '';
    if (isset($_FILES['resume']) && !empty($_FILES['resume']['name'])) {
        $ext     = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $allowed = ['pdf','doc','docx'];
        if (!in_array(strtolower($ext), $allowed)) die("❌ Only PDF, DOC, DOCX allowed");
        $file = time() . '_' . basename($_FILES['resume']['name']);
        if (!move_uploaded_file($_FILES['resume']['tmp_name'], __DIR__ . "/uploads/" . $file))
            die("❌ Upload failed. Check uploads/ folder permission.");
    }

    $stmt = $pdo->prepare("INSERT INTO user_profile
        (id, name, email, mobile, resume, cover_letter, skills, job_role, experience, daily_limit, smtp_pass, groq_key, location, cron_enabled, cron_time, cron_days)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT (id) DO UPDATE SET
            name=EXCLUDED.name, email=EXCLUDED.email, mobile=EXCLUDED.mobile,
            resume=EXCLUDED.resume, cover_letter=EXCLUDED.cover_letter,
            skills=EXCLUDED.skills, job_role=EXCLUDED.job_role,
            experience=EXCLUDED.experience, daily_limit=EXCLUDED.daily_limit,
            smtp_pass=EXCLUDED.smtp_pass, groq_key=EXCLUDED.groq_key,
            location=EXCLUDED.location, cron_enabled=EXCLUDED.cron_enabled,
            cron_time=EXCLUDED.cron_time, cron_days=EXCLUDED.cron_days");
    $stmt->execute([$name, $email, $mobile, $file, $cover, $skills, $job_role,
                    $experience, $daily, $smtp_pass, $groq_key, $location,
                    $cron_enabled, $cron_time, $cron_days]);

    $success = true;
    $profile  = $pdo->query("SELECT * FROM user_profile WHERE id=1")->fetch();
}

// Stats
$todaySent = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE DATE(sent_at)=CURRENT_DATE AND status='sent'")->fetchColumn();
$totalSent = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE status='sent'")->fetchColumn();
$opened    = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE opened=1")->fetchColumn();
$jobsFound = (int)$pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#4fffb0">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="manifest" href="manifest.json">
<title>Profile — ReachOut</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0c0f14; --surface: #141820; --surface2: #1c2230;
    --border: #252d3d; --accent: #4fffb0; --accent2: #00c9ff;
    --text: #e8edf5; --muted: #6b7a99; --danger: #ff5f6d; --radius: 12px;
  }
  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg); color: var(--text); min-height: 100vh;
    background-image:
      radial-gradient(ellipse 60% 40% at 80% -10%, rgba(79,255,176,0.06) 0%, transparent 60%),
      radial-gradient(ellipse 50% 30% at 10% 100%, rgba(0,201,255,0.04) 0%, transparent 50%);
  }
  .layout { display: flex; min-height: 100vh; }

  /* Sidebar */
  .sidebar {
    width: 240px; flex-shrink: 0; background: var(--surface);
    border-right: 1px solid var(--border); display: flex;
    flex-direction: column; padding: 28px 0;
    position: sticky; top: 0; height: 100vh;
  }
  .logo { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; padding: 0 24px 32px; letter-spacing: -0.5px; }
  .logo span { color: var(--accent); }
  .nav-label { font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); padding: 0 24px 10px; }
  .nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 24px; font-size: 14px; color: var(--muted); text-decoration: none; transition: all 0.15s; border-left: 3px solid transparent; cursor: pointer; }
  .nav-item:hover { color: var(--text); background: var(--surface2); }
  .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(79,255,176,0.06); }
  .nav-item .icon { font-size: 17px; width: 22px; text-align: center; }

  /* Main */
  .main { flex: 1; padding: 40px 48px; max-width: 900px; }
  .page-header { margin-bottom: 28px; }
  .page-title { font-family: 'Syne', sans-serif; font-size: 30px; font-weight: 800; letter-spacing: -1px; }
  .page-subtitle { font-size: 14px; color: var(--muted); margin-top: 4px; }

  /* Banner */
  .banner {
    display: flex; align-items: center; gap: 12px;
    background: rgba(79,255,176,0.08); border: 1px solid rgba(79,255,176,0.25);
    border-radius: 10px; padding: 14px 18px; margin-bottom: 28px;
    font-size: 14px; color: var(--accent); animation: slideDown 0.25s ease;
  }
  @keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }

  /* Stats row */
  .stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 24px; }
  .stat { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; }
  .stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
  .stat-val { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 700; margin-top: 4px; color: var(--accent); }

  /* Cards */
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 20px; overflow: hidden; }
  .card-header { display: flex; align-items: center; gap: 12px; padding: 18px 24px; border-bottom: 1px solid var(--border); }
  .card-icon { width: 36px; height: 36px; background: var(--surface2); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 17px; }
  .card-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; }
  .card-desc { font-size: 12px; color: var(--muted); }
  .card-body { padding: 24px; }

  /* Avatar */
  .avatar-row { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
  .avatar-circle {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; color: #0c0f14; flex-shrink: 0;
  }
  .avatar-info h3 { font-size: 16px; font-weight: 600; margin-bottom: 2px; }
  .avatar-info p { font-size: 13px; color: var(--muted); }

  /* Form */
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .form-group { display: flex; flex-direction: column; gap: 6px; }
  .form-group.full { grid-column: 1 / -1; }
  label { font-size: 12px; font-weight: 500; color: var(--muted); letter-spacing: 0.3px; }
  .hint { font-size: 11px; color: var(--muted); margin-top: 3px; }

  input[type="text"], input[type="email"], input[type="tel"],
  input[type="password"], input[type="number"], select, textarea {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); font-family: 'DM Sans', sans-serif;
    font-size: 14px; padding: 11px 14px; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s; width: 100%;
  }
  input:focus, textarea:focus, select:focus {
    border-color: var(--accent); box-shadow: 0 0 0 3px rgba(79,255,176,0.08);
  }
  input::placeholder, textarea::placeholder { color: var(--muted); }
  textarea { resize: vertical; min-height: 120px; line-height: 1.6; }
  select option { background: var(--surface2); }

  /* Skills tags */
  .skills-quick { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 8px; }
  .stag {
    background: rgba(79,255,176,0.08); border: 1px solid rgba(79,255,176,0.2);
    color: var(--accent); padding: 3px 11px; border-radius: 20px;
    font-size: 12px; cursor: pointer; transition: all 0.15s; user-select: none;
  }
  .stag:hover { background: rgba(79,255,176,0.18); }

  /* Range */
  .range-wrap { display: flex; align-items: center; gap: 14px; }
  .range-wrap input[type="range"] { flex: 1; accent-color: var(--accent); }
  .range-val { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 20px; color: var(--accent); min-width: 36px; }

  /* File upload */
  .upload-zone {
    position: relative; border: 2px dashed var(--border); border-radius: 10px;
    padding: 24px; text-align: center; cursor: pointer;
    transition: all 0.2s; background: var(--surface2);
  }
  .upload-zone:hover { border-color: var(--accent); background: rgba(79,255,176,0.03); }
  .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
  .upload-icon { font-size: 28px; margin-bottom: 8px; }
  .upload-label { font-size: 14px; color: var(--text); font-weight: 500; }
  .upload-sub { font-size: 12px; color: var(--muted); margin-top: 3px; }
  .upload-filename {
    margin-top: 12px; display: inline-flex; align-items: center; gap: 8px;
    background: rgba(79,255,176,0.08); border: 1px solid rgba(79,255,176,0.2);
    color: var(--accent); padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;
  }
  .existing-file {
    display: flex; align-items: center; gap: 12px;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; padding: 12px 16px; margin-bottom: 12px;
  }
  .existing-file-name { font-size: 13px; color: var(--text); flex: 1; }
  .existing-file-tag { font-size: 11px; font-weight: 600; background: rgba(79,255,176,0.1); color: var(--accent); padding: 3px 10px; border-radius: 20px; }

  /* Key field */
  .key-field { position: relative; }
  .key-field input { padding-right: 80px; font-family: monospace; font-size: 13px; }
  .key-toggle { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); font-size: 12px; cursor: pointer; padding: 4px 8px; }
  .key-toggle:hover { color: var(--accent); }

  /* Buttons */
  .btn-row { display: flex; justify-content: flex-end; gap: 12px; margin-top: 28px; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; }
  .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
  .btn-ghost:hover { color: var(--text); border-color: var(--text); }
  .btn-primary { background: var(--accent); color: #0c0f14; font-weight: 700; }
  .btn-primary:hover { background: #6bffc0; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,255,176,0.25); }


  /* Location tags */
  .loc-wrap { background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; padding: 16px; }
  .loc-section-title { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px; margin: 14px 0 8px; }
  .loc-section-title:first-child { margin-top: 0; }
  .loc-tags { display: flex; flex-wrap: wrap; gap: 7px; }
  .loc-tag {
    padding: 5px 13px; border-radius: 20px; font-size: 12px; font-weight: 500;
    cursor: pointer; transition: all 0.15s; user-select: none;
    background: var(--surface); border: 1px solid var(--border); color: var(--muted);
  }
  .loc-tag:hover { border-color: var(--accent); color: var(--accent); }
  .loc-tag.selected { background: rgba(79,255,176,0.12); border-color: var(--accent); color: var(--accent); }
  .char-counter { text-align: right; font-size: 11px; color: var(--muted); margin-top: 4px; }

  /* Cron toggle switch */
  .cron-toggle { display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; }
  .cron-toggle input { position:absolute; opacity:0; width:0; height:0; }
  .cron-track {
    position:relative; width:44px; height:24px;
    background:var(--border); border-radius:12px;
    transition:background .25s; flex-shrink:0;
  }
  .cron-toggle input:checked ~ .cron-track { background:var(--accent); }
  .cron-thumb {
    position:absolute; top:3px; left:3px;
    width:18px; height:18px; background:#fff;
    border-radius:50%; transition:transform .25s;
  }
  .cron-toggle input:checked ~ .cron-track .cron-thumb { transform:translateX(20px); }
  .cron-label { font-size:13px; font-weight:600; color:var(--muted); transition:color .2s; min-width:52px; }
  .cron-toggle input:checked ~ .cron-label { color:var(--accent); }

  /* Days pills */
  .days-wrap { display:flex; gap:6px; flex-wrap:wrap; margin-top:2px; }
  .day-pill { cursor:pointer; user-select:none; }
  .day-pill input { display:none; }
  .day-pill span {
    display:block; padding:6px 12px; border-radius:8px;
    font-size:12px; font-weight:600;
    background:var(--surface2); border:1px solid var(--border);
    color:var(--muted); transition:all .15s;
  }
  .day-pill input:checked + span {
    background:rgba(79,255,176,.12);
    border-color:var(--accent); color:var(--accent);
  }
  .day-pill span:hover { border-color:var(--accent); color:var(--accent); }

  /* Cron command box */
  .cron-cmd-box {
    background:var(--surface2); border:1px solid var(--border);
    border-radius:10px; padding:14px 16px; margin-top:16px;
  }
  .cron-cmd-title { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; font-size:12px; color:var(--muted); font-weight:500; }
  .cron-cmd-code {
    font-family:monospace; font-size:12px; color:var(--accent);
    word-break:break-all; line-height:1.6;
    background:#000; border-radius:6px; padding:10px 12px;
  }
  .copy-btn {
    background:rgba(79,255,176,.1); border:1px solid rgba(79,255,176,.25);
    color:var(--accent); border-radius:6px; padding:3px 10px;
    font-size:11px; font-weight:600; cursor:pointer; transition:all .15s;
  }
  .copy-btn:hover { background:rgba(79,255,176,.2); }

  /* Local warning */
  .local-warn {
    display:flex; gap:12px; align-items:flex-start;
    background:rgba(255,209,102,.06); border:1px solid rgba(255,209,102,.2);
    border-radius:10px; padding:14px 16px; margin-top:16px;
    font-size:13px; color:var(--muted); line-height:1.6;
  }

  /* Flow guide */
  .flow-guide { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px 24px; margin-top: 4px; }
  .flow-guide h4 { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; margin-bottom: 12px; color: var(--accent); }
  .flow-step { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; font-size: 13px; color: var(--muted); line-height: 1.5; }
  .flow-step .num { background: rgba(79,255,176,0.12); color: var(--accent); border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; margin-top: 1px; }
  .flow-step a { color: var(--accent); text-decoration: none; }
  .flow-step a:hover { text-decoration: underline; }

  /* Hamburger */
  .menu-toggle { display: none; position: fixed; top: 16px; left: 16px; width: 44px; height: 44px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; z-index: 999; flex-direction: column; align-items: center; justify-content: center; gap: 5px; }
  .menu-toggle .line { width: 20px; height: 2px; background: var(--text); border-radius: 1px; }
  .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }
  .sidebar-overlay.open { display: block; }

  @media (max-width: 900px) {
    .sidebar { position: fixed; left: 0; top: 0; height: 100vh; z-index: 100; transform: translateX(-100%); transition: transform 0.3s ease; }
    .sidebar.open { transform: translateX(0); }
    .menu-toggle { display: flex; }
    .main { padding: 24px 20px; padding-top: 76px; max-width: 100%; }
    .stats-row { grid-template-columns: repeat(2,1fr); }
  }
  @media (max-width: 640px) {
    .main { padding: 16px 12px; padding-top: 72px; }
    .page-title { font-size: 22px; }
    .form-grid { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: repeat(2,1fr); gap: 10px; }
    .btn-row { flex-direction: column; }
    .btn { justify-content: center; }
  }
</style>
</head>
<body>
<div class="layout">

<button class="menu-toggle" id="menuBtn"><span class="line"></span><span class="line"></span><span class="line"></span></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="logo">Reach<span>Out</span></div>
  <div class="nav-label">Menu</div>
  <a class="nav-item" href="index.php"><span class="icon">📋</span> Dashboard</a>
  <a class="nav-item" href="send.php"><span class="icon">📤</span> Send Emails</a>
  <a class="nav-item active" href="profile.php"><span class="icon">👤</span> My Profile</a>
  <a class="nav-item" href="index.php#csv-section"><span class="icon">📂</span> CSV Import</a>
  <a class="nav-item" href="cron_log.php"><span class="icon">🤖</span> Cron Logs</a>
  <a class="nav-item" href="job_fetch.php?secret=MY_CRON_SECRET_2024"><span class="icon">🔍</span> Fetch Jobs</a>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="page-header">
    <div class="page-title">My Profile</div>
    <div class="page-subtitle">Ek baar set karo — system automatically apply karta rahega</div>
  </div>

  <?php if(!empty($success)): ?>
  <div class="banner">✅ <span>Profile saved! Cron ab automatically yeh details use karega.</span></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats-row">
    <div class="stat"><div class="stat-label">Today sent</div><div class="stat-val"><?= $todaySent ?></div></div>
    <div class="stat"><div class="stat-label">Total sent</div><div class="stat-val"><?= $totalSent ?></div></div>
    <div class="stat"><div class="stat-label">Emails opened</div><div class="stat-val"><?= $opened ?></div></div>
    <div class="stat"><div class="stat-label">Jobs fetched</div><div class="stat-val"><?= $jobsFound ?></div></div>
  </div>

  <form method="post" enctype="multipart/form-data">

    <!-- PERSONAL INFO -->
    <div class="card">
      <div class="card-header">
        <div class="card-icon">👤</div>
        <div><div class="card-title">Personal Information</div><div class="card-desc">Aapka naam aur contact</div></div>
      </div>
      <div class="card-body">
        <?php
          $initials    = strtoupper(substr($profile['name'] ?? 'Y', 0, 1));
          $displayName  = $profile['name']  ?? 'Your Name';
          $displayEmail = $profile['email'] ?? '';
        ?>
        <div class="avatar-row">
          <div class="avatar-circle" id="avatarCircle"><?= $initials ?></div>
          <div class="avatar-info">
            <h3 id="avatarName"><?= htmlspecialchars($displayName) ?></h3>
            <p id="avatarEmail"><?= htmlspecialchars($displayEmail) ?: 'No email set' ?></p>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" id="nameInput" name="name" placeholder="Rahul Sharma" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Email (SMTP sender)</label>
            <input type="email" id="emailInput" name="email" placeholder="rahul@gmail.com" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Mobile Number</label>
            <input type="tel" name="mobile" placeholder="+91 98765 43210" value="<?= htmlspecialchars($profile['mobile'] ?? '') ?>">
          </div>
          <div class="form-group full">
            <label>Preferred Locations <span style="color:var(--muted);font-weight:400">(multiple select kar sakte ho)</span></label>
            <?php
              $savedLocs = array_map('trim', explode(',', $profile['location'] ?? ''));
            ?>
            <div class="loc-wrap" id="locWrap">

              <div class="loc-section-title">🌐 Remote / Flexible</div>
              <div class="loc-tags">
                <?php foreach(['Remote','Work from Home','Hybrid','Anywhere'] as $l): ?>
                <span class="loc-tag <?= in_array($l,$savedLocs)?'selected':'' ?>" onclick="toggleLoc(this,'<?= $l ?>')"><?= $l ?></span>
                <?php endforeach; ?>
              </div>

              <div class="loc-section-title">🇮🇳 India — Metro</div>
              <div class="loc-tags">
                <?php foreach(['Bangalore','Mumbai','Delhi','Hyderabad','Chennai','Pune','Kolkata','Ahmedabad','Noida','Gurgaon'] as $l): ?>
                <span class="loc-tag <?= in_array($l,$savedLocs)?'selected':'' ?>" onclick="toggleLoc(this,'<?= $l ?>')"><?= $l ?></span>
                <?php endforeach; ?>
              </div>

              <div class="loc-section-title">🇮🇳 India — Other Cities</div>
              <div class="loc-tags">
                <?php foreach(['Jaipur','Lucknow','Indore','Bhopal','Chandigarh','Surat','Coimbatore','Kochi','Thiruvananthapuram','Nagpur','Vizag','Bhubaneswar'] as $l): ?>
                <span class="loc-tag <?= in_array($l,$savedLocs)?'selected':'' ?>" onclick="toggleLoc(this,'<?= $l ?>')"><?= $l ?></span>
                <?php endforeach; ?>
              </div>

              <div class="loc-section-title">🌍 International</div>
              <div class="loc-tags">
                <?php foreach(['USA','UK','Canada','Australia','Germany','UAE','Singapore','Netherlands','New Zealand','Ireland','Sweden','Denmark','France','Japan','Switzerland'] as $l): ?>
                <span class="loc-tag <?= in_array($l,$savedLocs)?'selected':'' ?>" onclick="toggleLoc(this,'<?= $l ?>')"><?= $l ?></span>
                <?php endforeach; ?>
              </div>

            </div>
            <input type="hidden" name="location" id="locationHidden" value="<?= htmlspecialchars($profile['location'] ?? '') ?>">
            <div class="hint" style="margin-top:6px">Selected: <span id="locCount" style="color:var(--accent)"><?= count(array_filter($savedLocs)) ?></span> location(s)</div>
          </div>
        </div>
      </div>
    </div>

    <!-- JOB PREFERENCES (NEW) -->
    <div class="card">
      <div class="card-header">
        <div class="card-icon">🎯</div>
        <div><div class="card-title">Job Preferences</div><div class="card-desc">Groq AI job match karne ke liye use karega</div></div>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-group full">
            <label>Target Job Role</label>
            <input type="text" name="job_role" placeholder="Backend Developer / Full Stack / PHP Developer" value="<?= htmlspecialchars($profile['job_role'] ?? '') ?>">
          </div>
          <div class="form-group full">
            <label>Your Skills (comma separated)</label>
            <textarea name="skills" id="skillsInput" placeholder="PHP, MySQL, JavaScript, React, Laravel, Node.js"><?= htmlspecialchars($profile['skills'] ?? '') ?></textarea>
            <div class="hint">Groq AI inhe use karke relevant jobs match karega — jitne accurate utna better</div>
            <div class="skills-quick">
              <?php foreach(['PHP','MySQL','JavaScript','React','Laravel','Node.js','Python','Vue.js','WordPress','MongoDB','AWS','Docker','Flutter','Android'] as $s): ?>
              <span class="stag" onclick="addSkill('<?= $s ?>')"><?= $s ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label>Experience Level</label>
            <select name="experience">
              <?php foreach(['Fresher','0-1 year','1-2 years','2-3 years','3-5 years','5+ years'] as $e): ?>
              <option <?= ($profile['experience'] ?? '') === $e ? 'selected' : '' ?>><?= $e ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Daily Email Limit (max 50 — spam se bachao)</label>
            <div class="range-wrap">
              <input type="range" name="daily_limit" id="limitRange" min="5" max="50" step="5"
                value="<?= $profile['daily_limit'] ?? 20 ?>"
                oninput="document.getElementById('limitVal').textContent=this.value">
              <span class="range-val" id="limitVal"><?= $profile['daily_limit'] ?? 20 ?></span>
            </div>
            <div class="hint">Week 1→10/day &nbsp;·&nbsp; Week 2→20/day &nbsp;·&nbsp; Week 3+→50/day &nbsp;(warmup strategy)</div>
          </div>
        </div>
      </div>
    </div>

    <!-- SMTP + GROQ KEYS (NEW) -->
    <div class="card">
      <div class="card-header">
        <div class="card-icon">🔑</div>
        <div><div class="card-title">API Keys & SMTP</div><div class="card-desc">Sirf aapke server pe save hota hai</div></div>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-group full">
            <label>Gmail App Password (SMTP)</label>
            <div class="key-field">
              <input type="password" id="smtpPass" name="smtp_pass" placeholder="xxxx xxxx xxxx xxxx"
                value="<?= htmlspecialchars($profile['smtp_pass'] ?? '') ?>">
              <button type="button" class="key-toggle" onclick="toggleVis('smtpPass',this)">Show</button>
            </div>
            <div class="hint">Google Account → Security → 2-Step Verification ON → App Passwords → Mail → 16 digit code</div>
          </div>
          <div class="form-group full">
            <label>Groq API Key (Free — console.groq.com)</label>
            <div class="key-field">
              <input type="password" id="groqKey" name="groq_key" placeholder="gsk_xxxxxxxxxxxxxxxxxxxx"
                value="<?= htmlspecialchars($profile['groq_key'] ?? '') ?>">
              <button type="button" class="key-toggle" onclick="toggleVis('groqKey',this)">Show</button>
            </div>
            <div class="hint">Save karte hi ai_email.php mein automatically update ho jaayegi</div>
          </div>
        </div>
      </div>
    </div>

    <!-- RESUME -->
    <div class="card">
      <div class="card-header">
        <div class="card-icon">📄</div>
        <div><div class="card-title">Resume</div><div class="card-desc">Har email ke saath automatically attach hoga</div></div>
      </div>
      <div class="card-body">
        <?php if(!empty($profile['resume'])): ?>
        <div class="existing-file">
          <span style="font-size:22px">📎</span>
          <div class="existing-file-name"><?= htmlspecialchars($profile['resume']) ?></div>
          <span class="existing-file-tag">Current</span>
        </div>
        <?php endif; ?>
        <div class="upload-zone" id="resumeZone">
          <input type="file" name="resume" accept=".pdf,.doc,.docx" onchange="showFile(this,'resumeZone','resumeName')">
          <div class="upload-icon">☁️</div>
          <div class="upload-label"><?= !empty($profile['resume']) ? 'Upload new resume' : 'Upload your resume' ?></div>
          <div class="upload-sub">PDF, DOC, DOCX — max 10MB</div>
          <div class="upload-filename" id="resumeName" style="display:none"></div>
        </div>
      </div>
    </div>

    <!-- COVER LETTER -->
    <div class="card">
      <div class="card-header">
        <div class="card-icon">✉️</div>
        <div><div class="card-title">Cover Letter / Email Body</div><div class="card-desc">Fallback — Groq AI override kar sakta hai</div></div>
      </div>
      <div class="card-body">
        <div class="form-group full">
          <label>Cover Letter</label>
          <textarea name="cover" maxlength="2000" oninput="updateCount(this,'coverCount')"
            placeholder="Dear Hiring Manager,&#10;&#10;I am writing to express my interest in..."><?= htmlspecialchars($profile['cover_letter'] ?? '') ?></textarea>
          <div class="char-counter"><span id="coverCount"><?= strlen($profile['cover_letter'] ?? '') ?></span> / 2000</div>
        </div>
      </div>
    </div>

    <!-- CRON SCHEDULE CARD -->
    <div class="card">
      <div class="card-header">
        <div class="card-icon">⏰</div>
        <div>
          <div class="card-title">Auto Apply Schedule</div>
          <div class="card-desc">Daily automatically jobs fetch + email send karega</div>
        </div>
        <!-- Toggle switch -->
        <label class="cron-toggle" style="margin-left:auto">
          <input type="checkbox" name="cron_enabled" id="cronEnabled" <?= !empty($profile['cron_enabled']) ? 'checked' : '' ?>>
          <span class="cron-track"><span class="cron-thumb"></span></span>
          <span class="cron-label" id="cronLabel"><?= !empty($profile['cron_enabled']) ? 'Active' : 'Inactive' ?></span>
        </label>
      </div>
      <div class="card-body" id="cronBody" style="<?= empty($profile['cron_enabled']) ? 'opacity:.4;pointer-events:none' : '' ?>">

        <div class="form-grid">

          <!-- Time picker -->
          <div class="form-group">
            <label>Daily Run Time</label>
            <input type="time" name="cron_time" id="cronTime"
              value="<?= htmlspecialchars($profile['cron_time'] ?? '09:00') ?>">
            <div class="hint">Is time pe automatically job fetch + email bhejega</div>
          </div>

          <!-- Days -->
          <div class="form-group">
            <label>Run On Days</label>
            <?php
              $savedDays = array_map('trim', explode(',', $profile['cron_days'] ?? 'mon,tue,wed,thu,fri'));
              $allDays   = ['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'];
            ?>
            <div class="days-wrap">
              <?php foreach($allDays as $val => $label): ?>
              <label class="day-pill">
                <input type="checkbox" name="cron_days[]" value="<?= $val ?>"
                  <?= in_array($val, $savedDays) ? 'checked' : '' ?>>
                <span><?= $label ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div class="hint">Weekdays only recommended — spam filters se bachao</div>
          </div>

        </div>

        <!-- Cron command box -->
        <div class="cron-cmd-box">
          <div class="cron-cmd-title">
            <span>⚙️ Server Cron Command</span>
            <button type="button" class="copy-btn" onclick="copyCron()">Copy</button>
          </div>
          <div class="cron-cmd-code" id="cronCmd">
            <?php
              $t = $profile['cron_time'] ?? '09:00';
              $parts = explode(':', $t);
              $h = intval($parts[0]); $m = intval($parts[1] ?? 0);
              $days = $profile['cron_days'] ?? 'mon,tue,wed,thu,fri';
              $dayMap = ['mon'=>'1','tue'=>'2','wed'=>'3','thu'=>'4','fri'=>'5','sat'=>'6','sun'=>'0'];
              $dayNums = implode(',', array_map(fn($d) => $dayMap[trim($d)] ?? '*', explode(',', $days)));
            ?>
            <?php
              $dir = str_replace('\\', '/', __DIR__);
              echo "$m $h * * $dayNums php $dir/job_fetch.php && php $dir/cron.php";
            ?>
          </div>
          <div class="hint" style="margin-top:8px">
            cPanel → Cron Jobs mein paste karo &nbsp;·&nbsp; Ya hosting provider ke scheduler mein
          </div>
        </div>

        <!-- Last run info -->
        <?php if(!empty($profile['cron_last_run'])): ?>
        <div style="margin-top:14px;font-size:13px;color:var(--muted)">
          Last run: <span style="color:var(--accent)"><?= $profile['cron_last_run'] ?></span>
        </div>
        <?php endif; ?>

        <!-- LOCAL warning -->
        <div class="local-warn">
          <span style="font-size:16px">💻</span>
          <div>
            <strong style="color:var(--pending)">Local Server (XAMPP) pe cron automatically nahi chalega</strong><br>
            <span>XAMPP band hone pe sab band ho jaata hai. Daily auto apply ke liye
            <strong style="color:var(--text)">live hosting chahiye</strong>
            (Hostinger ₹99/month, 000webhost free).<br>
            Tabhi tak <a href="jobs.php" style="color:var(--accent)">jobs.php</a> se manually "Fetch + Send" kar sakte ho.</span>
          </div>
        </div>

      </div>
    </div>

    <div class="btn-row">
      <a href="index.php" class="btn btn-ghost">Cancel</a>
      <button type="submit" name="save" class="btn btn-primary">💾 Save Profile & Activate</button>
    </div>
  </form>

  <!-- AUTO APPLY FLOW GUIDE -->
  <div class="flow-guide" style="margin-top:24px">
    <h4>🚀 Auto Apply Flow — Profile save ke baad yeh karo</h4>
    <div class="flow-step"><span class="num">1</span><span>Profile save karo (aap yahan ho) ✅</span></div>
    <div class="flow-step"><span class="num">2</span><span><a href="job_fetch.php?secret=MY_CRON_SECRET_2024">job_fetch.php</a> run karo — Remotive + Jobicy se jobs aayengi, Groq match karega</span></div>
    <div class="flow-step"><span class="num">3</span><span><a href="cron.php?secret=MY_CRON_SECRET_2024">cron.php</a> run karo — Matched jobs ko AI personalized email + resume bhejega</span></div>
    <div class="flow-step"><span class="num">4</span><span><a href="cron_log.php">cron_log.php</a> pe results dekho — kitne bheje, kitne opened</span></div>
    <div class="flow-step"><span class="num">5</span><span>Server cron set karo — <code style="color:var(--accent);font-size:12px">0 9 * * * php /path/job_fetch.php && php /path/cron.php</code></span></div>
  </div>

</main>
</div>

<script>
  // Avatar live update
  document.getElementById('nameInput').addEventListener('input', function(){
    document.getElementById('avatarCircle').textContent = (this.value[0] || 'Y').toUpperCase();
    document.getElementById('avatarName').textContent   = this.value || 'Your Name';
  });
  document.getElementById('emailInput').addEventListener('input', function(){
    document.getElementById('avatarEmail').textContent = this.value || 'No email set';
  });

  function showFile(input, zoneId, nameId) {
    const file = input.files[0];
    if(!file) return;
    const el = document.getElementById(nameId);
    el.textContent = '📎 ' + file.name;
    el.style.display = 'inline-flex';
    document.getElementById(zoneId).style.borderColor = 'var(--accent)';
  }

  function updateCount(el, id) {
    document.getElementById(id).textContent = el.value.length;
  }

  function addSkill(skill) {
    const inp = document.getElementById('skillsInput');
    const cur = inp.value.trim();
    if(!cur.toLowerCase().includes(skill.toLowerCase())){
      inp.value = cur ? cur + ', ' + skill : skill;
    }
  }


  function toggleLoc(el, val) {
    el.classList.toggle('selected');
    const hidden = document.getElementById('locationHidden');
    let selected = hidden.value ? hidden.value.split(',').map(s=>s.trim()).filter(Boolean) : [];
    if (el.classList.contains('selected')) {
      if (!selected.includes(val)) selected.push(val);
    } else {
      selected = selected.filter(s => s !== val);
    }
    hidden.value = selected.join(', ');
    document.getElementById('locCount').textContent = selected.length;
  }
  function toggleVis(id, btn) {
    const inp = document.getElementById(id);
    const isPass = inp.type === 'password';
    inp.type = isPass ? 'text' : 'password';
    btn.textContent = isPass ? 'Hide' : 'Show';
  }

  // Sidebar mobile
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const menuBtn  = document.getElementById('menuBtn');
  menuBtn.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('open'); });
  overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('open'); });

  if('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(()=>{});

  // Cron toggle — enable/disable card
  document.getElementById('cronEnabled').addEventListener('change', function() {
    const body  = document.getElementById('cronBody');
    const label = document.getElementById('cronLabel');
    body.style.opacity         = this.checked ? '1' : '0.4';
    body.style.pointerEvents   = this.checked ? 'auto' : 'none';
    label.textContent          = this.checked ? 'Active' : 'Inactive';
  });

  // Update cron command live when time changes
  document.getElementById('cronTime').addEventListener('change', function() {
    updateCronCmd();
  });
  document.querySelectorAll('.day-pill input').forEach(cb => {
    cb.addEventListener('change', updateCronCmd);
  });

  function updateCronCmd() {
    const time = document.getElementById('cronTime').value || '09:00';
    const [h, m] = time.split(':');
    const dayMap = {mon:'1',tue:'2',wed:'3',thu:'4',fri:'5',sat:'6',sun:'0'};
    const checked = [...document.querySelectorAll('.day-pill input:checked')].map(i => dayMap[i.value]);
    const dayStr  = checked.length ? checked.join(',') : '*';
    const path    = document.getElementById('cronCmd').textContent.match(/php (.+?job_fetch)/)?.[1] || '/path/to/job_fetch';
    document.getElementById('cronCmd').textContent =
      `${parseInt(m)} ${parseInt(h)} * * ${dayStr} php ${path}.php && php ${path.replace('job_fetch','cron')}.php`;
  }

  function copyCron() {
    const txt = document.getElementById('cronCmd').textContent.trim();
    navigator.clipboard.writeText(txt).then(() => {
      const btn = document.querySelector('.copy-btn');
      btn.textContent = '✅ Copied!';
      setTimeout(() => btn.textContent = 'Copy', 2000);
    });
  }
</script>
</body>
</html>