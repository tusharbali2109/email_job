<?php
// save_payment.php — Razorpay payment result DB mein save karta hai
include 'db.php';
header('Content-Type: application/json');

// ── Payments table auto-create ──
$conn->query("CREATE TABLE IF NOT EXISTS payments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    payment_id      VARCHAR(100)  DEFAULT 'N/A',
    name            VARCHAR(255)  DEFAULT '',
    email           VARCHAR(255)  DEFAULT '',
    phone           VARCHAR(50)   DEFAULT '',
    plan            VARCHAR(50)   DEFAULT '',
    billing         VARCHAR(50)   DEFAULT '',
    amount          DECIMAL(10,2) DEFAULT 0,
    status          ENUM('success','failed') DEFAULT 'failed',
    error_msg       TEXT          NULL,
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP
)");

// ── JSON input ──
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if(!$data){
    echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
    exit;
}

$payment_id = $conn->real_escape_string($data['razorpay_payment_id'] ?? 'N/A');
$name       = $conn->real_escape_string($data['name']    ?? '');
$email      = $conn->real_escape_string($data['email']   ?? '');
$phone      = $conn->real_escape_string($data['phone']   ?? '');
$plan       = $conn->real_escape_string($data['plan']    ?? '');
$billing    = $conn->real_escape_string($data['billing'] ?? '');
$amount     = floatval($data['amount']  ?? 0);
$status     = in_array($data['status']??'', ['success','failed']) ? $data['status'] : 'failed';
$error_msg  = $conn->real_escape_string($data['error']   ?? '');

// ── Insert payment record ──
$stmt = $conn->prepare("INSERT INTO payments 
    (payment_id, name, email, phone, plan, billing, amount, status, error_msg)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssdss",
    $payment_id,$name,$email,$phone,$plan,$billing,$amount,$status,$error_msg);
$stmt->execute();

// ── Agar success — user_profile mein plan + expiry update karo ──
if($status === 'success' && !empty($email)){
    // Columns add karo agar nahi hain
    $conn->query("ALTER TABLE user_profile ADD COLUMN IF NOT EXISTS plan         VARCHAR(50)  DEFAULT 'free'");
    $conn->query("ALTER TABLE user_profile ADD COLUMN IF NOT EXISTS plan_expiry  DATETIME     NULL");
    $conn->query("ALTER TABLE user_profile ADD COLUMN IF NOT EXISTS plan_billing VARCHAR(50)  DEFAULT ''");

    $months = match($billing){
        'semi'   => 6,
        'yearly' => 12,
        default  => 1
    };
    $expiry = date('Y-m-d H:i:s', strtotime("+{$months} months"));

    $upd = $conn->prepare("UPDATE user_profile SET plan=?, plan_expiry=?, plan_billing=? WHERE email=?");
    $upd->bind_param("ssss", $plan, $expiry, $billing, $email);
    $upd->execute();

    // Agar user profile exist nahi karta — insert karo
    if($upd->affected_rows === 0){
        $ins = $conn->prepare("INSERT INTO user_profile (name,email,plan,plan_expiry,plan_billing) VALUES (?,?,?,?,?)");
        $ins->bind_param("sssss",$name,$email,$plan,$expiry,$billing);
        $ins->execute();
    }
}

echo json_encode([
    'success' => true,
    'status'  => $status,
    'message' => $status==='success' ? 'Plan activated!' : 'Payment failed recorded'
]);