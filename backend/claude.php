<?php
require_once 'config.php';

function callClaude($bookmarks, $options) {
    // Build compact prompt
    $bookmarkList = '';
    foreach ($bookmarks as $b) {
        $bookmarkList .= "{$b['id']}. {$b['title']} ({$b['domain']})\n";
    }

    $hideDepthMap = [
        'deep' => 1,
        'deeper' => 3,
        'deepest' => 5,
        'mariana' => 10
    ];

    $hideDepth = $hideDepthMap[$options['hideDepth']] ?? 1;

    $prompt = "You are an AI bookmark psychologist. Analyze these " . count($bookmarks) . " bookmarks.\n\n";
    $prompt .= "USER OPTIONS:\n";
    $prompt .= "- Icon-Only Bar: " . ($options['iconOnly'] ? 'Yes' : 'No') . "\n";
    $prompt .= "- Folders Only: " . ($options['foldersOnly'] ? 'Yes' : 'No') . "\n";
    $prompt .= "- Remove 404s: " . ($options['remove404'] ? 'Yes' : 'No') . "\n";
    $prompt .= "- Brutal Honesty: " . ($options['brutalHonesty'] ? 'Yes' : 'No') . "\n";
    $prompt .= "- Hide Sensitive: " . ($options['hideSensitive'] ? "Yes ({$hideDepth} levels deep)" : 'No') . "\n\n";

    $prompt .= "BOOKMARKS:\n{$bookmarkList}\n";

    $prompt .= "TASKS:\n";
    $prompt .= "1. Detect hobbies, career, life events from patterns\n";
    $prompt .= "2. Identify sensitive bookmarks (adult, job search, medical)\n";
    $prompt .= "3. Find duplicates and categorization opportunities\n";
    if ($options['brutalHonesty']) {
        $prompt .= "4. Be BRUTALLY HONEST about their bookmark hoarding habits\n";
    }
    $prompt .= "5. Organize into max 2-level folder structure\n";
    if ($options['hideSensitive']) {
        $prompt .= "6. Hide sensitive content {$hideDepth} folders deep with boring names\n";
    }

    $prompt .= "\nRETURN ONLY THIS JSON FORMAT (no markdown, no explanation):\n";
    $prompt .= "{\n";
    $prompt .= '  "analysis": {' . "\n";
    $prompt .= '    "hobbies": ["hobby1", "hobby2"],' . "\n";
    $prompt .= '    "career": "description",' . "\n";
    $prompt .= '    "lifeEvents": ["event1"],' . "\n";
    $prompt .= '    "productivityScore": "X/10"' . "\n";
    $prompt .= "  },\n";
    $prompt .= '  "folders": {' . "\n";
    $prompt .= '    "Category Name": [1, 2, 3],' . "\n";
    $prompt .= '    "Work": {' . "\n";
    $prompt .= '      "Subcategory": [4, 5]' . "\n";
    $prompt .= "    }\n";
    $prompt .= "  },\n";
    $prompt .= '  "_remove": [bookmark IDs to remove],' . "\n";
    $prompt .= '  "_suggestions": ["suggestion 1", "suggestion 2"]' . "\n";
    $prompt .= "}";

    // CURL to Claude API
    $ch = curl_init(CLAUDE_API_URL);

    $payload = [
        'model' => CLAUDE_MODEL,
        'max_tokens' => 4096,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'Claude API error: ' . $httpCode];
    }

    $data = json_decode($response, true);

    if (!isset($data['content'][0]['text'])) {
        return ['success' => false, 'error' => 'Invalid Claude response'];
    }

    // Parse JSON from Claude's response
    $resultText = $data['content'][0]['text'];

    // Clean up markdown code blocks if present
    $resultText = preg_replace('/```json\s*/', '', $resultText);
    $resultText = preg_replace('/```\s*/', '', $resultText);
    $resultText = trim($resultText);

    $result = json_decode($resultText, true);

    if (!$result) {
        return ['success' => false, 'error' => 'Failed to parse Claude response'];
    }

    return ['success' => true, 'result' => $result];
}
