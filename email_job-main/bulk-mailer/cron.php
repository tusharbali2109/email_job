<?php
// ═══════════════════════════════════════════════════════
// cron.php — Daily Auto Job Apply Engine
// Browser: cron.php?secret=MY_CRON_SECRET_2024
// Server:  0 9 * * 1-5 php /path/to/cron.php
// ═══════════════════════════════════════════════════════

// CLI se bhi run ho sake aur browser se bhi
if (php_sapi_name() !== 'cli') {
    $secret = $_GET['secret'] ?? '';
    if ($secret !== 'MY_CRON_SECRET_2024') {
        http_response_code(403);
        die('Access denied. Use: cron.php?secret=MY_CRON_SECRET_2024');
    }
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai_email.php';
require_once __DIR__ . '/vendor/autoload.php';
// $pdo is available from db.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Profile load karo ──
$profileRow = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
if (!$profileRow) {
    log_msg("ERROR: Profile setup nahi hua! Pehle profile.php pe jao.");
    exit;
}

// ══════════════════════════════════════════════
// SCHEDULE CHECK — Sirf sahi time/day pe chale
// ══════════════════════════════════════════════
$cronEnabled = (int)($profileRow['cron_enabled'] ?? 0);
$cronTime    = $profileRow['cron_time']  ?? '09:00';
$cronDays    = $profileRow['cron_days']  ?? 'mon,tue,wed,thu,fri';

// Browser se manual run karo toh schedule check skip karo
$isManual = (php_sapi_name() !== 'cli');

if (!$isManual && $cronEnabled) {
    // Day check
    $dayMap      = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
    $todayNum    = (int)date('w'); // 0=Sun, 6=Sat
    $allowedDays = array_map(fn($d) => $dayMap[trim($d)] ?? -1, explode(',', $cronDays));

    if (!in_array($todayNum, $allowedDays)) {
        log_msg("Skipped — Aaj ka din schedule mein nahi hai.");
        log_msg("Allowed days: $cronDays | Today: " . date('D'));
        exit;
    }

    // Time check — 30 min window
    $scheduledTs = strtotime(date('Y-m-d') . ' ' . $cronTime);
    $nowTs       = time();
    $diff        = abs($nowTs - $scheduledTs);

    if ($diff > 1800) { // 30 min se zyada difference
        log_msg("Skipped — Scheduled time: $cronTime | Now: " . date('H:i'));
        exit;
    }
}

if (!$isManual && !$cronEnabled) {
    log_msg("Cron disabled hai. profile.php mein enable karo.");
    exit;
}

// ── Constants set karo profile se ──
define('SENDER_NAME',   $profileRow['name']        ?? $profileRow['full_name'] ?? 'Your Name');
define('SENDER_EMAIL',  $profileRow['email']        ?? '');
define('SENDER_SKILLS', $profileRow['skills']       ?? '');
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_USER',     $profileRow['email']        ?? '');
define('SMTP_PASS',     $profileRow['smtp_pass']    ?? '');
define('SMTP_PORT',     587);
define('RESUME_PATH',   __DIR__ . '/uploads/' . ($profileRow['resume'] ?? $profileRow['resume_name'] ?? 'resume.pdf'));
define('RESUME_NAME',   'Resume_' . str_replace(' ', '_', SENDER_NAME) . '.pdf');
define('MAX_PER_RUN',   (int)($profileRow['daily_limit'] ?? 20));
define('SITE_URL',      (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/bulk-mailer');

// ── Cron last_run update karo ──
$pdo->query("UPDATE user_profile SET cron_last_run=NOW() WHERE id=1");

// ── Log entry start karo ──
$pdo->query("INSERT INTO cron_logs (status) VALUES ('running')");
$logId   = $pdo->lastInsertId();
$runTime = date('Y-m-d H:i:s');

$sent    = 0;
$failed  = 0;
$skipped = 0;
$details = [];

log_msg("=== CRON STARTED: $runTime ===");
log_msg("Sender : " . SENDER_NAME . " <" . SENDER_EMAIL . ">");
log_msg("Limit  : " . MAX_PER_RUN . " emails this run");
log_msg("Mode   : " . ($isManual ? 'Manual (browser)' : 'Scheduled (cron)'));

// ── Daily limit check — aaj kitne bhej diye ──
$todaySent = (int)$pdo->query(
    "SELECT COUNT(*) FROM companies WHERE DATE(sent_at) = CURRENT_DATE"
)->fetchColumn();

if ($todaySent >= MAX_PER_RUN) {
    log_msg("Daily limit reached: $todaySent/" . MAX_PER_RUN . " already sent today.");
    updateLog($pdo, $logId, 0, 0, 0, ["Daily limit reached: $todaySent sent today"], 'done');
    exit;
}

$remainingToday = MAX_PER_RUN - $todaySent;
log_msg("Today sent: $todaySent | Remaining: $remainingToday");

// ── AI Queue se pending jobs lo (companies table UNTOUCHED rehta hai) ──
$result = $pdo->query(
    "SELECT * FROM ai_queue WHERE status='pending' ORDER BY id ASC LIMIT $remainingToday"
);

$queueRows = $result->fetchAll();
if (count($queueRows) === 0) {
    log_msg("No pending jobs in AI queue.");
    log_msg("Pehle jobs.php se 'Fetch New Jobs' karo.");
    updateLog($pdo, $logId, 0, 0, 0, ["AI queue empty"], 'done');
    exit;
}

log_msg("Found " . count($queueRows) . " jobs in AI queue.");

// ── Resume check ──
if (!file_exists(RESUME_PATH)) {
    log_msg("ERROR: Resume not found: " . RESUME_PATH);
    log_msg("Uploads folder mein resume.pdf daalo.");
    updateLog($pdo, $logId, 0, 0, 0, ["Resume file missing: " . RESUME_PATH], 'error');
    exit;
}
log_msg("Resume: " . RESUME_NAME . " ✅");
log_msg("---");

// ══════════════════════════════════════════════
// MAIN LOOP — Har company ko email bhejo
// ══════════════════════════════════════════════
foreach ($queueRows as $company) {
    $companyId   = $company['id'];
    $contactName = 'Hiring Manager';
    $companyName = $company['company'] ?? 'Your Company';
    $toEmail     = trim($company['email'] ?? '');
    $jobTitle    = $company['title']    ?? ''; // job title from ai_queue

    // Email validation
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        log_msg("SKIP [$companyId] Invalid/empty email: '$toEmail'");
        $skipped++;
        continue;
    }

    log_msg("[$companyId] $companyName → $toEmail");

    // ── Groq se AI personalized email generate karo ──
    $emailBody = generateJobEmail(
        $contactName,
        $companyName,
        SENDER_NAME,
        SENDER_SKILLS
    );
    log_msg("  ✍️  AI email generated");

    // ── Subject line — job title agar available ho ──
    $subject = !empty($jobTitle)
        ? "Application for $jobTitle — " . SENDER_NAME
        : "Job Application — " . SENDER_NAME;

    // ── PHPMailer send ──
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;

        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($toEmail, $contactName);
        $mail->addAttachment(RESUME_PATH, RESUME_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = buildEmailTemplate($emailBody, $contactName, $companyName, $companyId);
        $mail->AltBody = strip_tags($emailBody);

        $mail->send();

        // ── Success — ai_queue update karo (companies table UNTOUCHED) ──
        $pdo->prepare("UPDATE ai_queue SET status='sent', sent_at=NOW(), ai_used=1 WHERE id=?")->execute([$companyId]);
        $pdo->prepare("UPDATE jobs SET applied=1 WHERE email=?")->execute([$toEmail]);

        $sent++;
        $details[] = "✅ $companyName ($toEmail)";
        log_msg("  ✅ Sent successfully!");

    } catch (Exception $e) {
        $pdo->prepare("UPDATE ai_queue SET status='failed' WHERE id=?")->execute([$companyId]);

        $failed++;
        $details[] = "❌ $companyName — " . $mail->ErrorInfo;
        log_msg("  ❌ Failed: " . $mail->ErrorInfo);
    }

    // ── Anti-spam delay ──
    $delay = rand(5, 15);
    log_msg("  ⏳ Waiting {$delay}s (anti-spam)...");
    sleep($delay);
} // end foreach

// ── Summary ──
log_msg("");
log_msg("════════════════════════");
log_msg("=== CRON FINISHED ===");
log_msg("════════════════════════");
log_msg("✅ Sent    : $sent");
log_msg("❌ Failed  : $failed");
log_msg("⏭️  Skipped : $skipped");
log_msg("📊 Today total: " . ($todaySent + $sent) . "/" . MAX_PER_RUN);

updateLog($pdo, $logId, $sent, $failed, $skipped, $details, 'done');

// ══════════════════════
// HELPER FUNCTIONS
// ══════════════════════

function buildEmailTemplate($aiBody, $contactName, $companyName, $companyId = 0) {
    $senderName = SENDER_NAME;
    $trackUrl   = SITE_URL . "/track.php?id=$companyId";
    return "<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <style>
    body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px}
    .wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)}
    .hdr{background:#0f172a;color:#fff;padding:24px 32px}
    .hdr h2{margin:0;font-size:20px;font-weight:700}
    .hdr p{margin:6px 0 0;color:#94a3b8;font-size:13px}
    .bdy{padding:32px;color:#334155;line-height:1.8;font-size:15px}
    .ftr{background:#f8fafc;padding:14px 32px;color:#94a3b8;font-size:12px;border-top:1px solid #e2e8f0;text-align:center}
  </style>
</head>
<body>
  <div class='wrap'>
    <div class='hdr'>
      <h2>Job Application</h2>
      <p>From $senderName &nbsp;·&nbsp; Resume attached</p>
    </div>
    <div class='bdy'>$aiBody</div>
    <div class='ftr'>Resume attached as PDF &nbsp;·&nbsp; Sent via ReachOut</div>
    <img src='$trackUrl' width='1' height='1' style='display:none' alt=''>
  </div>
</body>
</html>";
}

function updateLog($pdo, $id, $sent, $fail, $skip, $details, $status) {
    $j = json_encode($details, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("UPDATE cron_logs SET total_sent=?,total_fail=?,total_skip=?,details=?,status=? WHERE id=?");
    $stmt->execute([$sent, $fail, $skip, $j, $status, $id]);
}

function log_msg($msg) {
    echo "[" . date('H:i:s') . "] $msg\n";
    flush();
}
?>