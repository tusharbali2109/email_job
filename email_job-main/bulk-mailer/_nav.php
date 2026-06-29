<?php
// Shared sidebar nav — include in every page
// Usage: include '_nav.php'; (pass $activePage before including)
// e.g.: $activePage = 'pipeline'; include '_nav.php';
$activePage = $activePage ?? '';
function navItem($href, $icon, $label, $active) {
    $cls = $active ? ' active' : '';
    echo "<a class='nav-item$cls' href='$href'><span class='icon'>$icon</span> $label</a>\n";
}
?>
<aside class="sidebar" id="sidebar">
  <div class="logo">Reach<span>Out</span></div>

  <div class="nav-label">Outreach</div>
  <?php navItem('index.php',    '📋', 'Dashboard',      $activePage==='dashboard') ?>
  <?php navItem('send.php',     '📤', 'Send Emails',    $activePage==='send') ?>
  <?php navItem('whatsapp.php', '💬', 'Send WhatsApp',  $activePage==='whatsapp') ?>

  <div class="nav-label">Jobs</div>
  <?php navItem('jobs.php',     '🔍', 'Job Hunt',       $activePage==='jobs') ?>
  <?php navItem('pipeline.php', '📊', 'Pipeline',       $activePage==='pipeline') ?>
  <?php navItem('ai_tailor.php','🤖', 'AI Tailor',      $activePage==='ai_tailor') ?>

  <div class="nav-label">Automation</div>
  <?php navItem('followup_cron.php?secret=MY_CRON_SECRET_2024', '🔁', 'Run Follow-ups', false) ?>
  <?php navItem('reply_check.php?secret=MY_CRON_SECRET_2024',   '📬', 'Check Replies',  false) ?>
  <?php navItem('wa_digest.php?secret=MY_CRON_SECRET_2024',     '📱', 'WA Digest',      false) ?>
  <?php navItem('cron_log.php', '📅', 'Cron Logs',     $activePage==='cron_log') ?>

  <div class="nav-label">Settings</div>
  <?php navItem('blacklist.php','🚫', 'Blacklist',      $activePage==='blacklist') ?>
  <?php navItem('profile.php',  '👤', 'My Profile',     $activePage==='profile') ?>
  <?php navItem('whatsapp_logs.php','📃','WA Logs',     $activePage==='wa_logs') ?>

  <div class="sidebar-bottom">
    <div style="font-size:11px;color:var(--muted);text-align:center;">ReachOut v2.0</div>
  </div>
</aside>
