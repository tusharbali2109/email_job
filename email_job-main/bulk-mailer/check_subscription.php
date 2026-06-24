<?php
// check_subscription.php — Fast subscription check
include 'db.php';
header('Content-Type: application/json');

$ADMIN = 'tusharbali855@gmail.com';
$email = strtolower(trim($_GET['email'] ?? ''));

if (!$email) { echo json_encode(['status' => 'error']); exit; }

// ✅ ADMIN — always allowed
if ($email === strtolower($ADMIN)) {
    echo json_encode(['status' => 'admin']);
    exit;
}

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(100) DEFAULT 'N/A',
    name VARCHAR(255) DEFAULT '',
    email VARCHAR(255) DEFAULT '',
    phone VARCHAR(50) DEFAULT '',
    plan VARCHAR(50) DEFAULT '',
    billing VARCHAR(50) DEFAULT '',
    amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('success','failed') DEFAULT 'failed',
    error_msg TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$e = $conn->real_escape_string($email);

// Get latest successful payment
$row = $conn->query("
    SELECT * FROM payments
    WHERE LOWER(email)='$e' AND status='success'
    ORDER BY created_at DESC LIMIT 1
")->fetch_assoc();

// No payment at all
if (!$row) {
    echo json_encode(['status' => 'no_plan']);
    exit;
}

// Calculate expiry
$months = ['monthly'=>1,'semi'=>6,'yearly'=>12][$row['billing']] ?? 1;
$expiry = new DateTime($row['created_at']);
$expiry->modify("+{$months} months");
$now    = new DateTime();

if ($now >= $expiry) {
    echo json_encode([
        'status'     => 'expired',
        'plan'       => $row['plan'],
        'billing'    => $row['billing'],
        'expiry'     => $expiry->format('d M Y'),
        'daysLeft'   => 0
    ]);
    exit;
}

$daysLeft = (int)(new DateTime())->diff($expiry)->days;

echo json_encode([
    'status'   => 'active',
    'plan'     => $row['plan'],
    'billing'  => $row['billing'],
    'expiry'   => $expiry->format('d M Y'),
    'daysLeft' => $daysLeft
]);