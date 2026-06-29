<?php
include 'db.php';

$WA_SERVICE = 'http://localhost:3001';

function isEmailDomainValid($email) {
    $domain = substr(strrchr($email, '@'), 1);
    if (!$domain) return false;
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
}

function isOnWhatsApp($phone, $waService) {
    if (empty($phone)) return false;
    $ch = curl_init("$waService/check-whatsapp");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['phone' => $phone]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return false;
    $data = json_decode($resp, true);
    return !empty($data['exists']);
}

// Load blacklist into memory for fast lookup
$blRows = $pdo->query("SELECT type, value FROM blacklist")->fetchAll();
$blEmails   = [];
$blDomains  = [];
foreach ($blRows as $bl) {
    if ($bl['type'] === 'email')  $blEmails[strtolower($bl['value'])]  = true;
    if ($bl['type'] === 'domain') $blDomains[strtolower($bl['value'])] = true;
}

function isBlacklisted($email, $blEmails, $blDomains) {
    $email  = strtolower(trim($email));
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    return isset($blEmails[$email]) || isset($blDomains[$domain]);
}

// Duplicate email check helper
function emailExists($pdo, $email) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE LOWER(email)=LOWER(?)");
    $s->execute([$email]);
    return $s->fetchColumn() > 0;
}

$imported = 0;
$skipped  = 0;
$reasons  = [];

if (!empty($_FILES['csv']['tmp_name'])) {
    $file = fopen($_FILES['csv']['tmp_name'], "r");
    fgetcsv($file); // skip header

    $stmt = $pdo->prepare("INSERT INTO companies (name, email, company, contact) VALUES (?, ?, ?, ?)");

    while (($data = fgetcsv($file, 1000, ",")) !== false) {
        $name    = trim($data[0] ?? '');
        $email   = trim($data[1] ?? '');
        $company = trim($data[2] ?? '');
        $phone   = trim($data[3] ?? '');

        if (!$name || !$email) { $skipped++; $reasons[] = "Missing name/email"; continue; }
        if (isBlacklisted($email, $blEmails, $blDomains)) { $skipped++; $reasons[] = "Blacklisted: $email"; continue; }
        if (emailExists($pdo, $email)) { $skipped++; $reasons[] = "Duplicate: $email"; continue; }
        if (!isEmailDomainValid($email)) { $skipped++; $reasons[] = "Invalid domain: $email"; continue; }
        if ($phone && !isOnWhatsApp($phone, $WA_SERVICE)) { $skipped++; $reasons[] = "Not on WA: $phone"; continue; }

        $stmt->execute([$name, $email, $company, $phone]);
        $imported++;
    }
    fclose($file);
}

header("Location: index.php?imported=$imported&skipped=$skipped");
