<?php
// ════════════════════════════════════════════
// wa_digest.php — Daily WhatsApp Morning Digest
// Cron: 0 8 * * * php /path/to/wa_digest.php
// Browser: wa_digest.php?secret=MY_CRON_SECRET_2024
// ════════════════════════════════════════════
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    $secret = $_GET['secret'] ?? '';
    if ($secret !== 'MY_CRON_SECRET_2024') { http_response_code(403); die('Access denied.'); }
}

require_once __DIR__ . '/db.php';

$WA_SERVICE = 'http://localhost:3001';

$profile = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
$mobile  = $profile['mobile'] ?? '';

function log_d($msg) {
    if (php_sapi_name() === 'cli') echo "[" . date('H:i:s') . "] $msg\n";
    else { echo "[" . date('H:i:s') . "] $msg\n"; ob_flush(); flush(); }
}

log_d("=== WA DIGEST STARTED ===");

if (!$mobile) {
    log_d("❌ No mobile number in profile.");
    exit;
}

// ── Today's stats ──
$today = date('Y-m-d');
$emailsSent   = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE DATE(sent_at) = '$today' AND status='sent'")->fetchColumn();
$totalSent    = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE status='sent'")->fetchColumn();
$replies      = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE replied=1")->fetchColumn();
$newReplies   = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE replied=1 AND DATE(replied_at)='$today'")->fetchColumn();
$opens        = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE opened=1")->fetchColumn();
$jobsFound    = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE DATE(created_at)='$today'")->fetchColumn();
$jobsMatched  = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE matched=1 AND DATE(created_at)='$today'")->fetchColumn();
$inQueue      = (int)$pdo->query("SELECT COUNT(*) FROM ai_queue WHERE status='pending'")->fetchColumn();
$followups    = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE followup_sent_at IS NOT NULL AND DATE(followup_sent_at)='$today'")->fetchColumn();

// Top recent companies that opened email
$hotLeads = $pdo->query("SELECT name, company FROM companies WHERE opened=1 AND replied=0 AND pipeline_stage NOT IN ('rejected','offer') ORDER BY opened_at DESC LIMIT 3")->fetchAll();

// ── Build message ──
$date    = date('d M Y, D');
$msg     = "🌅 *ReachOut Daily Digest*\n";
$msg    .= "📅 $date\n";
$msg    .= "━━━━━━━━━━━━━━━━━━\n\n";

$msg    .= "📤 *Emails Today:* $emailsSent\n";
$msg    .= "📊 *Total Sent:* $totalSent\n";
$msg    .= "👀 *Total Opens:* $opens\n";
$msg    .= "📬 *New Replies Today:* $newReplies\n";
$msg    .= "💬 *Total Replies:* $replies\n\n";

$msg    .= "🔍 *Jobs Found Today:* $jobsFound\n";
$msg    .= "✅ *Jobs Matched:* $jobsMatched\n";
$msg    .= "📥 *In Queue:* $inQueue\n";
$msg    .= "🔁 *Follow-ups Today:* $followups\n\n";

if (!empty($hotLeads)) {
    $msg .= "🔥 *Hot Leads (opened, no reply):*\n";
    foreach ($hotLeads as $h) {
        $msg .= "  • " . $h['name'] . " @ " . $h['company'] . "\n";
    }
    $msg .= "\n";
}

$msg .= "━━━━━━━━━━━━━━━━━━\n";
$msg .= "💪 Keep going! Check pipeline: pipeline.php";

log_d("Digest message prepared. Sending to $mobile...");

// ── Send via WA service ──
$payload = json_encode([
    'phone'    => $mobile,
    'message'  => $msg,
    'name'     => 'Me',
    'batch'    => false,
]);

$ch = curl_init("$WA_SERVICE/send");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($resp, true);
if ($data['success'] ?? false) {
    log_d("✅ Digest sent to $mobile");
    $pdo->prepare("INSERT INTO wa_digest_log (summary) VALUES (?)")->execute([$msg]);
} else {
    log_d("❌ Failed to send: " . ($data['error'] ?? "HTTP $code"));
    log_d("   → Make sure WhatsApp service is running and connected");
}

log_d("=== DONE ===");

if (!$isCLI) echo "<br><a href='index.php' style='color:#4fffb0;'>← Back to Dashboard</a>";
