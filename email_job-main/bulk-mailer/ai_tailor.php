<?php
include 'db.php';

// Handle API request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action     = $_POST['action']   ?? '';
    $jobTitle   = trim($_POST['job_title']   ?? '');
    $company    = trim($_POST['company']     ?? '');
    $jobDesc    = trim($_POST['job_desc']    ?? '');
    $profile    = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
    $groqKey    = trim($profile['groq_key'] ?? '');

    if (!$groqKey) {
        echo json_encode(['success' => false, 'error' => 'Groq API key not set in Profile']);
        exit;
    }

    if ($action === 'cover_letter') {
        $prompt = "Write a professional job application cover letter for this role:
Job Title: $jobTitle
Company: $company
Job Description: " . substr($jobDesc, 0, 600) . "

Candidate Info:
Name: {$profile['name']}
Skills: {$profile['skills']}
Experience: {$profile['experience']} years
Job Role Target: {$profile['job_role']}

Rules:
1. Max 200 words
2. Mention the company name and job title specifically
3. Reference 2-3 matching skills from the job description
4. Professional but warm tone
5. Return clean HTML with <p> tags only — no markdown, no extra explanation";
    } elseif ($action === 'resume_tips') {
        $prompt = "Analyze this job description and give resume tailoring tips:
Job Title: $jobTitle
Company: $company
Job Description: " . substr($jobDesc, 0, 600) . "

Candidate Skills: {$profile['skills']}
Experience: {$profile['experience']} years

Return EXACTLY this format as HTML:
<h3>Keywords to Add</h3><ul>[3-5 keywords from JD to add to resume]</ul>
<h3>Skills to Highlight</h3><ul>[3-4 most important matching skills]</ul>
<h3>Resume Tips</h3><ul>[3 specific tips to tailor resume for this role]</ul>
Keep it concise and actionable.";
    } elseif ($action === 'email_subject') {
        $prompt = "Generate 3 creative, professional email subject lines for a job application:
Job Title: $jobTitle | Company: $company | Applicant: {$profile['name']}
Return ONLY 3 lines, numbered 1. 2. 3. No explanation.";
    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        exit;
    }

    $payload = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'messages'    => [
            ['role' => 'system', 'content' => 'You are an expert job application coach. Return exactly what is asked.'],
            ['role' => 'user',   'content' => $prompt]
        ],
        'max_tokens'  => 600,
        'temperature' => 0.7
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $groqKey],
        CURLOPT_TIMEOUT        => 30
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        echo json_encode(['success' => false, 'error' => "Groq API error ($code)"]);
        exit;
    }

    $data   = json_decode($resp, true);
    $result = $data['choices'][0]['message']['content'] ?? '';
    echo json_encode(['success' => true, 'result' => $result]);
    exit;
}

$profile    = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
$activePage = 'ai_tailor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AI Tailor — ReachOut</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0c0f14;--surface:#141820;--s2:#1c2230;--b:#252d3d;--a:#00c9ff;--tx:#e8edf5;--mu:#6b7a99;--radius:12px;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;}
.layout{display:flex;min-height:100vh;}
.sidebar{width:240px;flex-shrink:0;background:var(--surface);border-right:1px solid var(--b);display:flex;flex-direction:column;padding:28px 0;position:sticky;top:0;height:100vh;overflow-y:auto;}
.logo{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--tx);padding:0 24px 24px;}
.logo span{color:#4fffb0;}
.nav-label{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--mu);padding:16px 24px 6px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 24px;color:var(--mu);text-decoration:none;font-size:14px;font-weight:500;transition:all 0.2s;border-left:3px solid transparent;}
.nav-item:hover,.nav-item.active{color:var(--tx);background:var(--s2);border-left-color:#4fffb0;}
.icon{font-size:16px;}
.sidebar-bottom{margin-top:auto;padding:16px;}
.main{flex:1;padding:40px;max-width:1000px;}
h1{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;margin-bottom:6px;}
.sub{color:var(--mu);font-size:14px;margin-bottom:32px;}
.card{background:var(--surface);border:1px solid var(--b);border-radius:var(--radius);padding:24px;margin-bottom:20px;}
.card h2{font-size:15px;font-weight:700;margin-bottom:16px;color:var(--a);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group.full{grid-column:1/-1;}
label{font-size:11px;color:var(--mu);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
input,textarea,select{background:var(--s2);border:1px solid var(--b);border-radius:8px;padding:10px 14px;color:var(--tx);font-size:14px;width:100%;font-family:inherit;}
input:focus,textarea:focus{outline:none;border-color:var(--a);}
textarea{min-height:120px;resize:vertical;}
.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;}
.btn{padding:10px 20px;border-radius:8px;border:none;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:8px;}
.btn-ai{background:linear-gradient(135deg,#00c9ff,#0080ff);color:#fff;}
.btn-ai:hover{opacity:0.85;}
.btn-outline{background:transparent;border:1px solid var(--b);color:var(--tx);}
.btn-outline:hover{border-color:var(--a);color:var(--a);}
.btn-copy{background:rgba(79,255,176,0.1);color:#4fffb0;border:1px solid rgba(79,255,176,0.2);}
.result-box{background:var(--s2);border:1px solid var(--b);border-radius:10px;padding:20px;min-height:80px;font-size:14px;line-height:1.7;margin-top:16px;}
.result-box h3{font-size:14px;font-weight:700;color:#4fffb0;margin:12px 0 6px;}
.result-box ul{padding-left:20px;}
.result-box li{margin-bottom:4px;}
.result-box p{margin-bottom:8px;}
.spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}
.tag{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-bottom:12px;}
.no-key{background:rgba(255,95,109,0.1);border:1px solid rgba(255,95,109,0.3);border-radius:10px;padding:16px;color:#ff5f6d;font-size:14px;margin-bottom:20px;}
</style>
</head>
<body>
<div class="layout">
<?php include '_nav.php'; ?>
<main class="main">
  <h1>🤖 AI Application Tailor</h1>
  <p class="sub">Paste a job description — get a custom cover letter, resume tips, and email subjects</p>

  <?php if (empty($profile['groq_key'])): ?>
  <div class="no-key">⚠️ Groq API key not set. <a href="profile.php" style="color:#ff8a65">Go to Profile → add Groq Key</a> (free at console.groq.com)</div>
  <?php endif; ?>

  <div class="card">
    <h2>📋 Job Details</h2>
    <div class="form-grid">
      <div class="form-group">
        <label>Job Title</label>
        <input type="text" id="job_title" placeholder="e.g. Full Stack Developer">
      </div>
      <div class="form-group">
        <label>Company Name</label>
        <input type="text" id="company" placeholder="e.g. Acme Corp">
      </div>
      <div class="form-group full">
        <label>Job Description (paste here)</label>
        <textarea id="job_desc" placeholder="Paste the full job description here..."></textarea>
      </div>
    </div>
    <div class="btn-row">
      <button class="btn btn-ai" onclick="generate('cover_letter')">✉️ Generate Cover Letter</button>
      <button class="btn btn-ai" onclick="generate('resume_tips')">📄 Resume Tips</button>
      <button class="btn btn-ai" onclick="generate('email_subject')">💡 Subject Lines</button>
    </div>
  </div>

  <div id="result-card" class="card" style="display:none">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
      <h2 id="result-title">Result</h2>
      <button class="btn btn-copy" onclick="copyResult()">📋 Copy</button>
    </div>
    <div class="result-box" id="result-box"></div>
  </div>
</main>
</div>
<script>
async function generate(action) {
  const jt = document.getElementById('job_title').value.trim();
  const co = document.getElementById('company').value.trim();
  const jd = document.getElementById('job_desc').value.trim();
  if (!jt || !co || !jd) return alert('Fill in Job Title, Company, and Job Description first.');

  const titles = {
    cover_letter: '✉️ Cover Letter',
    resume_tips:  '📄 Resume Tips',
    email_subject:'💡 Email Subject Lines'
  };
  document.getElementById('result-title').textContent = titles[action];
  document.getElementById('result-box').innerHTML = '<span class="spinner"></span> Generating...';
  document.getElementById('result-card').style.display = '';

  const res = await fetch('ai_tailor.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=${action}&job_title=${encodeURIComponent(jt)}&company=${encodeURIComponent(co)}&job_desc=${encodeURIComponent(jd)}`
  });
  const d = await res.json();
  if (d.success) {
    document.getElementById('result-box').innerHTML = d.result;
  } else {
    document.getElementById('result-box').innerHTML = '<span style="color:#ff5f6d">❌ ' + d.error + '</span>';
  }
}
function copyResult() {
  const text = document.getElementById('result-box').innerText;
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.btn-copy');
    btn.textContent = '✅ Copied!';
    setTimeout(() => btn.textContent = '📋 Copy', 2000);
  });
}
</script>
</body>
</html>
