<?php
include 'db.php';
header('Content-Type: application/json');

try {
    $rows = $pdo->query("
        SELECT
            c.id,
            c.name,
            c.company,
            c.contact AS phone,
            c.email,
            CASE WHEN wl.company_id IS NOT NULL THEN true ELSE false END AS wa_sent
        FROM companies c
        LEFT JOIN (
            SELECT DISTINCT company_id
            FROM whatsapp_logs
            WHERE status = 'sent'
        ) wl ON wl.company_id = c.id
        WHERE c.contact IS NOT NULL AND c.contact <> ''
        ORDER BY c.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
} catch (Exception $e) {
    // whatsapp_logs table may not exist yet — return contacts without wa_sent flag
    $rows = $pdo->query("
        SELECT id, name, company, contact AS phone, email, false AS wa_sent
        FROM companies
        WHERE contact IS NOT NULL AND contact <> ''
        ORDER BY id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
}
