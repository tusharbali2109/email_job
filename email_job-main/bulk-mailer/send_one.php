<?php
include 'db.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'error' => 'No ID']); exit; }

$stmt = $pdo->prepare("SELECT * FROM companies WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$c = $stmt->fetch();

$profile  = $pdo->query("SELECT * FROM user_profile LIMIT 1")->fetch();
$template = $pdo->query("SELECT * FROM email_template LIMIT 1")->fetch();

if (!$c || !$profile) { echo json_encode(['success' => false, 'error' => 'Missing data']); exit; }

if (empty($profile['smtp_pass'])) {
    echo json_encode(['success' => false, 'error' => 'SMTP password not set. Profile mein Gmail App Password save karo.']);
    exit;
}

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

    if (!empty($profile['resume'])) {
        $resumePath = __DIR__ . '/uploads/' . $profile['resume'];
        if (file_exists($resumePath)) {
            $ext       = pathinfo($resumePath, PATHINFO_EXTENSION);
            $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $profile['name']) . '_Resume.' . $ext;
            $mail->addAttachment($resumePath, $cleanName);
        }
    }

    $subject = str_replace(
        ['{{name}}', '{{company}}'],
        [$profile['name'], $c['company']],
        $template['subject'] ?? 'Application from ' . $profile['name']
    );

    $body = str_replace(
        ['{{name}}', '{{company}}', '{{mobile}}', '{{email}}'],
        [
            htmlspecialchars($profile['name']),
            htmlspecialchars($c['company']),
            htmlspecialchars($profile['mobile']),
            htmlspecialchars($profile['email']),
        ],
        $template['body'] ?? 'Please find my resume attached. Best regards, ' . $profile['name']
    );

    $mail->Subject = $subject;
    $mail->Body    = nl2br($body);
    $mail->AltBody = strip_tags($body);

    $mail->send();

    $pdo->prepare("UPDATE companies SET status='sent' WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->prepare("UPDATE companies SET status='failed' WHERE id=?")->execute([$id]);
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}
