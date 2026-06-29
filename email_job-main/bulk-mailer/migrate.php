<?php
// ════════════════════════════════════════════
// migrate.php — Run once to set up new tables
// Visit: /migrate.php?secret=MY_CRON_SECRET_2024
// ════════════════════════════════════════════
$secret = $_GET['secret'] ?? '';
if ($secret !== 'MY_CRON_SECRET_2024') { http_response_code(403); die('Access denied.'); }

include 'db.php';

$steps = [];

function run($pdo, $sql, $label) {
    global $steps;
    try {
        $pdo->exec($sql);
        $steps[] = "✅ $label";
    } catch (Exception $e) {
        $steps[] = "⚠️ $label — " . $e->getMessage();
    }
}

// ── Blacklist ──
run($pdo, "CREATE TABLE IF NOT EXISTS blacklist (
    id SERIAL PRIMARY KEY,
    type VARCHAR(10) NOT NULL DEFAULT 'email',
    value TEXT NOT NULL UNIQUE,
    reason TEXT,
    created_at TIMESTAMP DEFAULT NOW()
)", "blacklist table");

// ── Pipeline stage on companies ──
run($pdo, "ALTER TABLE companies ADD COLUMN IF NOT EXISTS pipeline_stage VARCHAR(20) DEFAULT 'applied'", "companies.pipeline_stage");
run($pdo, "ALTER TABLE companies ADD COLUMN IF NOT EXISTS replied SMALLINT DEFAULT 0", "companies.replied");
run($pdo, "ALTER TABLE companies ADD COLUMN IF NOT EXISTS replied_at TIMESTAMP", "companies.replied_at");
run($pdo, "ALTER TABLE companies ADD COLUMN IF NOT EXISTS open_count INT DEFAULT 0", "companies.open_count");
run($pdo, "ALTER TABLE companies ADD COLUMN IF NOT EXISTS followup_sent_at TIMESTAMP", "companies.followup_sent_at");
run($pdo, "ALTER TABLE companies ADD COLUMN IF NOT EXISTS notes TEXT", "companies.notes");
run($pdo, "ALTER TABLE companies ADD COLUMN IF NOT EXISTS stage_updated_at TIMESTAMP", "companies.stage_updated_at");

// ── Follow-up log ──
run($pdo, "CREATE TABLE IF NOT EXISTS followup_log (
    id SERIAL PRIMARY KEY,
    company_id INT NOT NULL,
    email TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT NOW(),
    status VARCHAR(20) DEFAULT 'sent'
)", "followup_log table");

// ── A/B Templates ──
run($pdo, "ALTER TABLE email_template ADD COLUMN IF NOT EXISTS variant CHAR(1) DEFAULT 'A'", "email_template.variant");
run($pdo, "ALTER TABLE email_template ADD COLUMN IF NOT EXISTS open_count INT DEFAULT 0", "email_template.open_count");
run($pdo, "ALTER TABLE email_template ADD COLUMN IF NOT EXISTS click_count INT DEFAULT 0", "email_template.click_count");

// ── sent_at column on companies ──
run($pdo, "ALTER TABLE companies ADD COLUMN IF NOT EXISTS sent_at TIMESTAMP", "companies.sent_at");

// ── Duplicate check helper ──
run($pdo, "CREATE INDEX IF NOT EXISTS idx_companies_email ON companies(email)", "companies email index");
run($pdo, "CREATE INDEX IF NOT EXISTS idx_companies_status ON companies(status)", "companies status index");

// ── user_profile IMAP columns ──
run($pdo, "ALTER TABLE user_profile ADD COLUMN IF NOT EXISTS imap_host TEXT", "user_profile.imap_host");
run($pdo, "ALTER TABLE user_profile ADD COLUMN IF NOT EXISTS imap_pass TEXT", "user_profile.imap_pass");

// ── wa_digest_log ──
run($pdo, "CREATE TABLE IF NOT EXISTS wa_digest_log (
    id SERIAL PRIMARY KEY,
    sent_at TIMESTAMP DEFAULT NOW(),
    summary TEXT
)", "wa_digest_log table");

echo "<pre style='font-family:monospace;background:#0c0f14;color:#4fffb0;padding:24px;'>";
echo "=== Migration Results ===\n\n";
foreach ($steps as $s) echo $s . "\n";
echo "\n✅ Done. You can delete migrate.php now for security.";
echo "</pre>";
