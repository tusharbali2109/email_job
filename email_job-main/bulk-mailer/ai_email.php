<?php
// ═══════════════════════════════════════════════
// ai_email.php — Groq API se personalized email
// FREE: https://console.groq.com se API key lo
// ═══════════════════════════════════════════════

function generateJobEmail($contactName, $companyName, $senderName, $senderSkills) {
    global $conn;

    // ✅ DB se Groq key lo — profile.php mein save ki hui
    $profile    = $conn->query("SELECT groq_key FROM user_profile LIMIT 1")->fetch_assoc();
    $groqApiKey = trim($profile['groq_key'] ?? '');

    // ✅ Key nahi hai toh seedha fallback
    if(empty($groqApiKey)) {
        return getFallbackEmail($contactName, $companyName, $senderName, $senderSkills);
    }

    $prompt = "Write a professional job application email body (HTML format) with these details:
- Recipient: $contactName at $companyName
- Sender: $senderName
- Skills: $senderSkills

Rules:
1. Keep it short (max 150 words)
2. Mention $companyName specifically to show interest
3. Professional but warm tone
4. Return ONLY the HTML email body (no subject, no extra text)
5. Use simple HTML: <p> tags only, no complex styling";

    $payload = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'messages'    => [
            [
                'role'    => 'system',
                'content' => 'You are an expert job application writer. Return only clean HTML email body. No markdown, no explanation.'
            ],
            [
                'role'    => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens'  => 400,
        'temperature' => 0.7
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $groqApiKey
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if($httpCode !== 200) {
        return getFallbackEmail($contactName, $companyName, $senderName, $senderSkills);
    }

    $data      = json_decode($response, true);
    $emailBody = $data['choices'][0]['message']['content'] ?? null;

    if(empty($emailBody)) {
        return getFallbackEmail($contactName, $companyName, $senderName, $senderSkills);
    }

    return $emailBody;
}

// ── Fallback: Groq down/key missing ho toh yeh use hoga ──
function getFallbackEmail($contactName, $companyName, $senderName, $senderSkills) {
    return "
    <p>Dear $contactName,</p>

    <p>I hope this message finds you well. I am writing to express my strong interest in joining
    <strong>$companyName</strong> as a developer.</p>

    <p>I am <strong>$senderName</strong>, a professional with expertise in <strong>$senderSkills</strong>.
    I have been following $companyName's work and I am excited about the possibility of contributing
    to your team.</p>

    <p>I have attached my resume for your review. I would love the opportunity to discuss how my
    skills can benefit $companyName. Please feel free to reach out at your convenience.</p>

    <p>Thank you for your time and consideration.</p>

    <p>Best regards,<br>
    <strong>$senderName</strong></p>
    ";
}