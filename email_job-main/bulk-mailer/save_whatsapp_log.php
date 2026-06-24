<?php
include 'db.php';
header('Content-Type: application/json');

$data          = json_decode(file_get_contents('php://input'), true) ?? [];
$logs          = $data['logs']          ?? [];
$candidateName = $data['candidateName'] ?? '';

if (empty($logs)) {
    echo json_encode(['success' => true, 'saved' => 0]);
    exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_logs (
            id             SERIAL PRIMARY KEY,
            company_id     INT,
            hr_name        VARCHAR(255),
            mobile         VARCHAR(50),
            candidate_name VARCHAR(255),
            sent_at        TIMESTAMP DEFAULT NOW(),
            status         VARCHAR(20),
            failure_reason TEXT
        )
    ");

    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_logs (company_id, hr_name, mobile, candidate_name, sent_at, status, failure_reason)
        VALUES (?, ?, ?, ?, NOW(), ?, ?)
    ");

    $saved = 0;
    foreach ($logs as $log) {
        $stmt->execute([
            $log['id']     ?? null,
            $log['name']   ?? '',
            $log['phone']  ?? '',
            $candidateName,
            $log['status'] ?? 'failed',
            $log['error']  ?? null,
        ]);
        $saved++;
    }

    echo json_encode(['success' => true, 'saved' => $saved]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
