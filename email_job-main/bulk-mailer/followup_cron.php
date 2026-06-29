<?php
// ════════════════════════════════════════════
// followup_cron.php — Auto Follow-up Sender
// Cron: 0 10 * * * php /path/to/followup_cron.php
// Browser: followup_cron.php?secret=MY_CRON_SECRET_2024
// ════════════════════════════════════════════
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    $secret = $_GET['secret'] ?? '';
    if ($secret !== 'MY_CRON_SECRET_2024') { http_response_code(403); die('Access denied.'); }
}

require_once __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$profile = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
if (!$profile || empty($profile['smtp_pass'])) {
    die("❌ SMTP not configured in profile.");
}

$logs = [];
function log_f($msg) {
    global $logs;
    $line = "[" . date('H:i:s') . "] $msg";
    $logs[] = $line;
    if (php_sapi_name() === 'cli') echo $line . "\n";
    else { echo $line . "\n"; ob_flush(); flush(); }
}

// Companies: sent 3–10 days ago, no reply, no followup yet, not rejected
$stmt = $pdo->query("
    SELECT c.* FROM companies c
    WHERE c.status = 'sent'
      AND (c.replied = 0 OR c.replied IS NULL)
      AND (c.pipeline_stage IS NULL OR c.pipeline_stage NOT IN ('rejected','offer','interview'))
      AND c.followup_sent_at IS NULL
      AND c.email IS NOT NULL AND c.email <> ''
      AND c.sent_at IS NOT NULL
      AND c.sent_at < NOW() - INTERVAL '3 days'
      AND c.sent_at > NOW() - INTERVAL '10 days'
    ORDER BY c.sent_at ASC
    LIMIT 50
");
$companies = $stmt->fetchAll();

log_f("=== FOLLOW-UP CRON STARTED ===");
log_f("Companies due for follow-up: " . count($companies));

$sent = 0; $failed = 0;

foreach ($companies as $c) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $profile['email'];
        $mail->Password   = $profile['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($profile['email'], $profile['name']);
        $mail->addAddress($c['email'], $c['name']);
        $mail->isHTML(true);

        $mail->Subject = "Following up — " . $profile['name'] . " | Application";

        $body = "
        <p>Dear " . htmlspecialchars($c['name']) . ",</p>
        <p>I hope you're doing well. I wanted to follow up on my earlier application to
        <strong>" . htmlspecialchars($c['company']) . "</strong>.</p>
        <p>I remain very interested in contributing to your team and would love the opportunity
        to discuss how my skills in <strong>" . htmlspecialchars($profile['skills'] ?? '') . "</strong>
        can add value to " . htmlspecialchars($c['company']) . ".</p>
        <p>Please let me know if you need any additional information. I look forward to hearing from you.</p>
        <p>Best regards,<br><strong>" . htmlspecialchars($profile['name']) . "</strong><br>"
        . htmlspecialchars($profile['mobile'] ?? '') . " | "
        . htmlspecialchars($profile['email']) . "</p>";

        if (!empty($profile['resume'])) {
            $rp = __DIR__ . '/uploads/' . $profile['resume'];
            if (file_exists($rp)) {
                $ext  = pathinfo($rp, PATHINFO_EXTENSION);
                $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $profile['name']) . '_Resume.' . $ext;
                $mail->addAttachment($rp, $name);
            }
        }

        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();

        $pdo->prepare("UPDATE companies SET followup_sent_at=NOW() WHERE id=?")->execute([$c['id']]);
        $pdo->prepare("INSERT INTO followup_log (company_id,email,status) VALUES (?,?,'sent')")
            ->execute([$c['id'], $c['email']]);

        log_f("  ✅ Sent to {$c['name']} @ {$c['company']} ({$c['email']})");
        $sent++;
        sleep(3); // rate limit

    } catch (Exception $e) {
        $pdo->prepare("INSERT INTO followup_log (company_id,email,status) VALUES (?,?,'failed')")
            ->execute([$c['id'], $c['email']]);
        log_f("  ❌ Failed: {$c['email']} — " . $e->getMessage());
        $failed++;
    }
}

log_f("════════════════════════");
log_f("Sent: $sent | Failed: $failed");
log_f("=== DONE ===");

if (!$isCLI) {
    echo "<br><a href='index.php' style='color:#4fffb0;'>← Back to Dashboard</a>";
}
