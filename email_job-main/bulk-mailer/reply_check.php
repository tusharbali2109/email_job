<?php
// ════════════════════════════════════════════
// reply_check.php — IMAP Reply Detector
// Checks your inbox for replies from companies
// Cron: 0 */4 * * * php /path/to/reply_check.php
// Browser: reply_check.php?secret=MY_CRON_SECRET_2024
// ════════════════════════════════════════════
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    $secret = $_GET['secret'] ?? '';
    if ($secret !== 'MY_CRON_SECRET_2024') { http_response_code(403); die('Access denied.'); }
}

require_once __DIR__ . '/db.php';

$profile = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
$imapHost = $profile['imap_host'] ?? '';
$imapUser = $profile['email']     ?? '';
$imapPass = $profile['imap_pass'] ?? $profile['smtp_pass'] ?? '';

$logs = [];
function log_r($msg) {
    global $logs;
    $line = "[" . date('H:i:s') . "] $msg";
    $logs[] = $line;
    if (php_sapi_name() === 'cli') echo $line . "\n";
    else { echo $line . "\n"; ob_flush(); flush(); }
}

log_r("=== REPLY CHECK STARTED ===");

if (!function_exists('imap_open')) {
    log_r("❌ PHP IMAP extension not enabled. Enable php_imap in php.ini and restart.");
    if (!$isCLI) echo "<br><a href='index.php' style='color:#4fffb0;'>← Back</a>";
    exit;
}

if (empty($imapUser) || empty($imapPass)) {
    log_r("❌ Email/password not configured in Profile.");
    if (!$isCLI) echo "<br><a href='profile.php' style='color:#4fffb0;'>→ Go to Profile</a>";
    exit;
}

// Default Gmail IMAP
$mailbox = $imapHost ?: '{imap.gmail.com:993/imap/ssl}INBOX';
if (!str_starts_with($mailbox, '{')) $mailbox = "{{$mailbox}:993/imap/ssl}INBOX";

$inbox = @imap_open($mailbox, $imapUser, $imapPass);
if (!$inbox) {
    log_r("❌ Cannot connect to IMAP: " . imap_last_error());
    log_r("   → For Gmail: enable IMAP in Gmail settings + use App Password");
    if (!$isCLI) echo "<br><a href='index.php' style='color:#4fffb0;'>← Back</a>";
    exit;
}

// Load all sent company emails
$companies = $pdo->query("SELECT id, email FROM companies WHERE status='sent' AND email IS NOT NULL AND email <> ''")->fetchAll();
$emailMap  = [];
foreach ($companies as $c) $emailMap[strtolower(trim($c['email']))] = $c['id'];

// Search last 30 days
$since  = date('d-M-Y', strtotime('-30 days'));
$emails = imap_search($inbox, "SINCE \"$since\" UNSEEN");

$found = 0; $marked = 0;
if ($emails) {
    $found = count($emails);
    log_r("Emails in inbox (last 30d): $found");
    foreach ($emails as $num) {
        $header = imap_headerinfo($inbox, $num);
        $from   = strtolower(trim($header->from[0]->mailbox . '@' . $header->from[0]->host));
        $domain = strtolower(trim($header->from[0]->host));
        // Check exact email match OR domain match
        $matchId = $emailMap[$from] ?? null;
        if (!$matchId) {
            foreach ($emailMap as $email => $id) {
                if (str_ends_with($email, '@' . $domain)) { $matchId = $id; break; }
            }
        }
        if ($matchId) {
            $pdo->prepare("UPDATE companies SET replied=1, replied_at=NOW(), pipeline_stage='replied' WHERE id=? AND replied=0")
                ->execute([$matchId]);
            log_r("  ✅ Reply from $from → company_id=$matchId → marked replied");
            $marked++;
        }
    }
} else {
    log_r("No new emails found.");
}

imap_close($inbox);
log_r("════════════════════════");
log_r("Emails scanned: $found | Companies marked replied: $marked");
log_r("=== DONE ===");

if (!$isCLI) echo "<br><a href='pipeline.php' style='color:#4fffb0;'>→ View Pipeline</a>";
