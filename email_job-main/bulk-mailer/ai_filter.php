<?php
// ═══════════════════════════════════════════════════════
// ai_filter.php — Groq AI Smart Job Matcher
// User ki skills se job match check karta hai
// ═══════════════════════════════════════════════════════

function checkJobMatch($jobTitle, $jobDescription, $userSkills, $userRole) {

    // DB se Groq key lo (profile.php mein save hoti hai)
    global $pdo;
    $groqApiKey = '';
    if (isset($pdo)) {
        $res = $pdo->query("SELECT groq_key FROM user_profile WHERE id=1 LIMIT 1");
        if ($res) {
            $groqApiKey = $res->fetchColumn() ?: '';
        }
    }
    if (empty($groqApiKey)) {
        $groqApiKey = 'YOUR_GROQ_API_KEY'; // fallback
    }

    $prompt = "You are a job matching expert. Analyze if this job matches the candidate.

Job Title: $jobTitle
Job Description (excerpt): " . substr($jobDescription, 0, 300) . "

Candidate:
- Target Role: $userRole
- Skills: $userSkills

Rules:
1. Reply ONLY with valid JSON, nothing else
2. If 60%+ skills match → matched: true
3. Give a short reason (max 10 words)

JSON format:
{\"matched\": true/false, \"reason\": \"short reason here\", \"score\": 0-100}";

    $payload = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'messages'    => [
            ['role' => 'system', 'content' => 'You are a job matching AI. Reply only with valid JSON.'],
            ['role' => 'user',   'content' => $prompt]
        ],
        'max_tokens'  => 100,
        'temperature' => 0.1  // Low temperature — consistent decisions
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
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        // Groq fail ho toh keyword matching fallback use karo
        return keywordMatch($jobTitle, $jobDescription, $userSkills);
    }

    $data    = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '{}';

    // JSON clean karo (kabhi kabhi Groq extra text deta hai)
    preg_match('/\{.*\}/s', $content, $matches);
    $json = json_decode($matches[0] ?? '{}', true);

    if (isset($json['matched'])) {
        return [
            'matched' => (bool)$json['matched'],
            'reason'  => $json['reason']  ?? '',
            'score'   => $json['score']   ?? 0
        ];
    }

    // JSON parse fail — fallback
    return keywordMatch($jobTitle, $jobDescription, $userSkills);
}

// ── Keyword matching — title + desc + role check ──
function keywordMatch($title, $desc, $userSkills) {
    $skillsArray = array_map('trim', explode(',', strtolower($userSkills)));
    $text        = strtolower($title . ' ' . $desc);
    $matched     = 0;
    $matchedSkills = [];

    foreach ($skillsArray as $skill) {
        if (strlen($skill) > 1 && strpos($text, $skill) !== false) {
            $matched++;
            $matchedSkills[] = $skill;
        }
    }

    $total = count($skillsArray);
    $score = $total > 0 ? round(($matched / $total) * 100) : 0;

    // Title mein developer/engineer/programmer words check karo
    $devWords = ['developer','engineer','programmer','architect','fullstack','full-stack','full stack','backend','frontend','web dev'];
    $titleLow = strtolower($title);
    $titleMatch = false;
    foreach($devWords as $w) {
        if(strpos($titleLow, $w) !== false) { $titleMatch = true; break; }
    }

    // Match conditions — any one is enough:
    // 1. Score >= 15% (1-2 skills match kare)
    // 2. Title developer/engineer word + koi bhi skill match
    $isMatched = $score >= 15 || ($titleMatch && $matched >= 1);

    $reason = $matched > 0
        ? implode(', ', array_slice($matchedSkills, 0, 3)) . " matched"
        : "title match only";

    return [
        'matched' => $isMatched,
        'reason'  => $reason,
        'score'   => $score
    ];
}

// ── Batch match multiple jobs at once (efficient) ──
function batchMatchJobs($jobs, $userSkills, $userRole) {
    $results = [];
    foreach ($jobs as $job) {
        $results[$job['id']] = checkJobMatch(
            $job['title'],
            $job['description'] ?? '',
            $userSkills,
            $userRole
        );
        usleep(200000); // 0.2s delay — Groq rate limit respect
    }
    return $results;
}
?>