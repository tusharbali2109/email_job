<?php
// ═══════════════════════════════════════════════════════
// job_fetch.php — Auto Job Search Engine
// Browser: job_fetch.php?secret=MY_CRON_SECRET_2024
// Cron:    0 8 * * * php /path/to/job_fetch.php
// ═══════════════════════════════════════════════════════

$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    $secret = $_GET['secret'] ?? '';
    if ($secret !== 'MY_CRON_SECRET_2024') {
        http_response_code(403);
        die('Access denied.');
    }
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai_filter.php';

// ── Profile load ──
$profile    = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
$userSkills = $profile['skills']   ?? 'PHP, MySQL, JavaScript';
$userRole   = $profile['job_role'] ?? 'Web Developer';

$totalFetched = 0;
$totalNew     = 0;
$totalMatched = 0;
$logs         = [];

// If browser request — show HTML UI
if (!$isCLI) {
    // Run fetch in background via output buffering
    ob_start();
}

log_msg("=== JOB FETCH STARTED ===");
log_msg("Role   : $userRole");
log_msg("Skills : $userSkills");
log_msg("---");

// ══════════════════════════════
// SOURCE 1: Remotive.io
// ══════════════════════════════
log_msg("[SOURCE 1] Remotive.io...");
$categories = ['software-dev', 'devops-sysadmin', 'product'];
foreach ($categories as $cat) {
    $url      = "https://remotive.com/api/remote-jobs?category=$cat&limit=20";
    $response = @file_get_contents($url);
    if (!$response) { log_msg("  ⚠ Remotive $cat: failed"); continue; }
    $data = json_decode($response, true);
    foreach ($data['jobs'] ?? [] as $job) {
        $title    = $job['title']          ?? '';
        $company  = $job['company_name']   ?? '';
        $link     = $job['url']            ?? '';
        $location = $job['candidate_required_location'] ?? 'Remote';
        $desc     = substr(strip_tags($job['description'] ?? ''), 0, 500);
        $email    = extractEmailFromText($desc) ?? generateGuessEmail($company);
        $res      = saveJob($pdo, $title, $company, $email, $link, $location, 'remotive', $desc);
        if ($res === 'new') {
            $totalNew++;
            $match = keywordMatch($title, $desc, $userSkills);
            log_msg("  NEW: $title @ $company [" . $match['score'] . "%]");
            if ($match['matched']) {
                markJobMatched($pdo, $title, $company, $match['reason']);
                syncToQueue($pdo, $title, $company, $email);
                $totalMatched++;
                log_msg("  ✅ MATCHED → queue");
            }
        }
        $totalFetched++;
    }
    sleep(1);
}

// ══════════════════════════════
// SOURCE 2: Jobicy.com
// ══════════════════════════════
log_msg("\n[SOURCE 2] Jobicy.com...");
$response = @file_get_contents("https://jobicy.com/api/v2/remote-jobs?count=30&tag=php,javascript,react");
if ($response) {
    foreach (json_decode($response, true)['jobs'] ?? [] as $job) {
        $title    = $job['jobTitle']    ?? '';
        $company  = $job['companyName'] ?? '';
        $link     = $job['url']         ?? '';
        $location = $job['jobGeo']      ?? 'Remote';
        $desc     = substr(strip_tags($job['jobDescription'] ?? ''), 0, 500);
        $email    = $job['companyEmail'] ?? extractEmailFromText($desc) ?? generateGuessEmail($company);
        $res      = saveJob($pdo, $title, $company, $email, $link, $location, 'jobicy', $desc);
        if ($res === 'new') {
            $totalNew++;
            $match = keywordMatch($title, $desc, $userSkills);
            if ($match['matched']) {
                markJobMatched($pdo, $title, $company, $match['reason']);
                syncToQueue($pdo, $title, $company, $email);
                $totalMatched++;
                log_msg("  ✅ MATCHED: $title @ $company");
            }
        }
        $totalFetched++;
    }
} else { log_msg("  ⚠ Jobicy: failed"); }

// ══════════════════════════════
// SOURCE 3: RSS Feeds
// ══════════════════════════════
log_msg("\n[SOURCE 3] RSS Feeds...");
$rssFeeds = [
    "https://weworkremotely.com/remote-jobs.rss",
    "https://remoteok.com/remote-jobs.rss",
];
foreach ($rssFeeds as $feedUrl) {
    $xml = @simplexml_load_file($feedUrl);
    if (!$xml) continue;
    $items = $xml->channel->item ?? $xml->item ?? [];
    foreach (array_slice((array)$items, 0, 10) as $item) {
        $title   = (string)($item->title ?? '');
        $link    = (string)($item->link  ?? '');
        $desc    = substr(strip_tags((string)($item->description ?? '')), 0, 500);
        $company = extractCompanyFromTitle($title);
        $email   = extractEmailFromText($desc) ?? generateGuessEmail($company);
        if (empty($title) || empty($company)) continue;
        $res = saveJob($pdo, $title, $company, $email, $link, 'Remote', 'rss', $desc);
        if ($res === 'new') {
            $totalNew++;
            $match = keywordMatch($title, $desc, $userSkills);
            if ($match['matched']) {
                markJobMatched($pdo, $title, $company, $match['reason']);
                syncToQueue($pdo, $title, $company, $email);
                $totalMatched++;
                log_msg("  ✅ MATCHED RSS: $title");
            }
        }
        $totalFetched++;
    }
}

log_msg("\n════════════════════════");
log_msg("=== DONE ===");
log_msg("Fetched : $totalFetched");
log_msg("New     : $totalNew");
log_msg("Matched : $totalMatched");
log_msg("════════════════════════");

// ══════════════════════════════
// HELPER FUNCTIONS
// ══════════════════════════════
function syncToQueue($pdo, $title, $company, $email) {
    if (empty($email)) return;
    $exists = $pdo->prepare("SELECT COUNT(*) FROM ai_queue WHERE email=?");
    $exists->execute([$email]);
    if ($exists->fetchColumn() > 0) return;

    $jobRow = $pdo->prepare("SELECT id FROM jobs WHERE company=? AND title=? LIMIT 1");
    $jobRow->execute([$company, $title]);
    $jobId  = $jobRow->fetchColumn() ?: 0;

    $pdo->prepare("INSERT INTO ai_queue (job_id,title,company,email,status) VALUES (?,?,?,?,'pending')")
        ->execute([$jobId, $title, $company, $email]);
}

function saveJob($pdo, $title, $company, $email, $link, $location, $source, $desc) {
    if (empty($title) || empty($company)) return 'skip';
    $desc = substr($desc, 0, 500);

    $chk = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE job_link=? OR (title=? AND company=?)");
    $chk->execute([$link, $title, $company]);
    if ($chk->fetchColumn() > 0) return 'duplicate';

    $pdo->prepare("INSERT INTO jobs (title,company,email,job_link,location,source,description) VALUES (?,?,?,?,?,?,?)")
        ->execute([$title, $company, $email ?? '', $link, $location, $source, $desc]);
    return 'new';
}

function markJobMatched($pdo, $title, $company, $reason) {
    $pdo->prepare("UPDATE jobs SET matched=1,match_reason=? WHERE title=? AND company=?")
        ->execute([$reason, $title, $company]);
}

function extractEmailFromText($text) {
    if (preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $m)) return $m[0];
    return null;
}

function generateGuessEmail($company) {
    $d = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $company));
    return empty($d) ? null : "hr@$d.com";
}

function extractCompanyFromTitle($title) {
    if (preg_match('/\bat\s+(.+)$/i', $title, $m)) return trim($m[1]);
    if (preg_match('/^(.+?)\s+[—\-–]/', $title, $m)) return trim($m[1]);
    return $title;
}

function log_msg($msg) {
    global $logs;
    $line = "[" . date('H:i:s') . "] $msg";
    $logs[] = $msg;
    if (php_sapi_name() === 'cli') {
        echo $line . "\n";
    } else {
        echo $line . "\n";
        ob_flush(); flush();
    }
}
?>