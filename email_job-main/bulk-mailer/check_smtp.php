<?php
include 'db.php';
$profile = $conn->query("SELECT email, smtp_pass FROM user_profile LIMIT 1")->fetch_assoc();

echo "<pre>";
echo "Email:     " . $profile['email'] . "\n";
echo "SMTP Pass: " . (empty($profile['smtp_pass']) ? '❌ EMPTY — profile mein save nahi hua!' : '✅ Set (' . strlen($profile['smtp_pass']) . ' chars)') . "\n";

// Test karo
if(!empty($profile['smtp_pass'])) {
    require __DIR__ . '/vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $profile['email'];
        $mail->Password   = $profile['smtp_pass'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0;
        // Sirf connection test — email nahi bhejta
        $mail->smtpConnect();
        echo "Connection: ✅ SMTP connected successfully!\n";
        $mail->smtpClose();
    } catch(Exception $e) {
        echo "Connection: ❌ FAILED — " . $mail->ErrorInfo . "\n";
    }
}
echo "</pre>";