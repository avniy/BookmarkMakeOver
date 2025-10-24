<?php
require_once 'config.php';

function logClaudeCall($message, $data = null) {
    $logFile = __DIR__ . '/logs/claude_api.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";

    if ($data !== null) {
        $logEntry .= "\n" . print_r($data, true);
    }

    $logEntry .= "\n" . str_repeat('-', 80) . "\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function callClaude($bookmarks, $options) {
    logClaudeCall("Claude API Call Started", [
        'bookmark_count' => count($bookmarks),
        'options' => $options
    ]);

    // Build compact prompt and identify icon-only and untitled bookmarks
    $bookmarkList = '';
    $iconOnlyIds = [];
    $untitledIds = [];
    foreach ($bookmarks as $b) {
        $domain = $b['domain'] ?? 'unknown';
        $title = $b['title'] ?? 'Untitled';
        $isIconOnly = $b['isIconOnly'] ?? false;
        $isUntitled = ($title === 'Untitled' || trim($title) === '');

        if ($isIconOnly) {
            $iconOnlyIds[] = $b['id'];
            $bookmarkList .= "{$b['id']}. {$title} ({$domain}) [ICON-ONLY]\n";
        } elseif ($isUntitled) {
            $untitledIds[] = $b['id'];
            $bookmarkList .= "{$b['id']}. {$title} ({$domain}) [NEEDS-NAME]\n";
        } else {
            $bookmarkList .= "{$b['id']}. {$title} ({$domain})\n";
        }
    }

    $hideDepthMap = [
        'deep' => 1,
        'deeper' => 3,
        'deepest' => 5,
        'mariana' => 10
    ];

    $hideDepth = $hideDepthMap[$options['hideDepth']] ?? 1;

    // System instruction (cacheable)
    $systemInstruction = "You are an AI bookmark psychologist. Your role is to analyze browser bookmarks and organize them intelligently.\n\n";
    $systemInstruction .= "IMPORTANT: You MUST return ONLY valid JSON with no additional text, explanations, or markdown formatting before or after the JSON.\n\n";
    $systemInstruction .= "JSON FORMAT TO RETURN:\n";
    $systemInstruction .= "{\n";
    $systemInstruction .= '  "analysis": {' . "\n";
    $systemInstruction .= '    "hobbies": ["hobby1", "hobby2"],' . "\n";
    $systemInstruction .= '    "career": "description",' . "\n";
    $systemInstruction .= '    "lifeEvents": ["event1"],' . "\n";
    $systemInstruction .= '    "productivityScore": "X/10"' . "\n";
    $systemInstruction .= "  },\n";
    $systemInstruction .= '  "folders": {' . "\n";
    $systemInstruction .= '    "Category Name": [1, 2, 3],' . "\n";
    $systemInstruction .= '    "Work": {' . "\n";
    $systemInstruction .= '      "Subcategory": [4, 5]' . "\n";
    $systemInstruction .= "    }\n";
    $systemInstruction .= "  },\n";
    $systemInstruction .= '  "_remove": [bookmark IDs to remove],' . "\n";
    $systemInstruction .= '  "_renamed": {' . "\n";
    $systemInstruction .= '    "bookmarkID": "New Improved Name"' . "\n";
    $systemInstruction .= '  },' . "\n";
    $systemInstruction .= '  "_suggestions": ["suggestion 1", "suggestion 2"]' . "\n";
    $systemInstruction .= "}";

    // Comprehensive examples (cacheable as second block)
    $examplesInstruction = "\n\n═══════════════════════════════════════════════════════════════\n";
    $examplesInstruction .= "COMPREHENSIVE EXAMPLES - ALL CASES\n";
    $examplesInstruction .= "═══════════════════════════════════════════════════════════════\n\n";

    // EXAMPLE 1: Icon-Only Bookmarks (MOST CRITICAL)
    $examplesInstruction .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $examplesInstruction .= "EXAMPLE 1: Icon-Only Bookmarks (ABSOLUTE PRIORITY)\n";
    $examplesInstruction .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $examplesInstruction .= "INPUT BOOKMARKS:\n";
    $examplesInstruction .= "1. GitHub Repository (github.com)\n";
    $examplesInstruction .= "2. Untitled (stackoverflow.com) [NEEDS-NAME]\n";
    $examplesInstruction .= "3. Untitled (facebook.com) [ICON-ONLY]  // User has keepIconOnlyAtRoot enabled\n";
    $examplesInstruction .= "4. Work Docs (docs.google.com)\n";
    $examplesInstruction .= "5. Untitled (twitter.com) [ICON-ONLY]   // No title, icon-only bookmark\n";
    $examplesInstruction .= "6. YouTube Tutorial (youtube.com)\n\n";
    $examplesInstruction .= "CORRECT OUTPUT:\n";
    $examplesInstruction .= "{\n";
    $examplesInstruction .= '  "analysis": { "hobbies": ["Programming", "Social Media"], "career": "Software Developer", "lifeEvents": [], "productivityScore": "7/10" },' . "\n";
    $examplesInstruction .= '  "folders": {' . "\n";
    $examplesInstruction .= '    "💻 Development": [1, 2],  // NOTICE: Only IDs 1 and 2 - NOT 3 or 5!' . "\n";
    $examplesInstruction .= '    "📁 Work": [4],' . "\n";
    $examplesInstruction .= '    "🎓 Learning": [6]' . "\n";
    $examplesInstruction .= '    // ⚠️ CRITICAL: Bookmarks 3 and 5 marked [ICON-ONLY] are NOT in folders!' . "\n";
    $examplesInstruction .= '    // They stay at root level completely unorganized' . "\n";
    $examplesInstruction .= "  },\n";
    $examplesInstruction .= '  "_remove": [],' . "\n";
    $examplesInstruction .= '  "_renamed": {' . "\n";
    $examplesInstruction .= '    "2": "Stack Overflow - Programming Questions"  // Rename [NEEDS-NAME] bookmark' . "\n";
    $examplesInstruction .= '    // ⚠️ NOTICE: We do NOT rename IDs 3 or 5 because they are [ICON-ONLY]' . "\n";
    $examplesInstruction .= '    // Icon-only bookmarks stay untouched at root' . "\n";
    $examplesInstruction .= "  },\n";
    $examplesInstruction .= '  "_suggestions": ["Consider organizing learning resources", "Archive old work documents"]' . "\n";
    $examplesInstruction .= "}\n\n";

    // EXAMPLE 2: Untitled Bookmark Renaming
    $examplesInstruction .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $examplesInstruction .= "EXAMPLE 2: Untitled Bookmark Renaming (renameUntitled mode)\n";
    $examplesInstruction .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $examplesInstruction .= "INPUT BOOKMARKS:\n";
    $examplesInstruction .= "10. Untitled (github.com) [NEEDS-NAME]\n";
    $examplesInstruction .= "11. Untitled (reddit.com) [NEEDS-NAME]\n";
    $examplesInstruction .= "12. Python Documentation (python.org)\n\n";
    $examplesInstruction .= "CORRECT OUTPUT:\n";
    $examplesInstruction .= "{\n";
    $examplesInstruction .= '  "analysis": { "hobbies": ["Programming"], "career": "Developer", "lifeEvents": [], "productivityScore": "8/10" },' . "\n";
    $examplesInstruction .= '  "folders": {' . "\n";
    $examplesInstruction .= '    "💻 Development": [10, 11, 12]  // All three bookmarks organized together' . "\n";
    $examplesInstruction .= "  },\n";
    $examplesInstruction .= '  "_remove": [],' . "\n";
    $examplesInstruction .= '  "_renamed": {' . "\n";
    $examplesInstruction .= '    "10": "GitHub - Code Repositories",  // REQUIRED: Give meaningful name' . "\n";
    $examplesInstruction .= '    "11": "Reddit - Programming Communities"  // REQUIRED: Based on domain' . "\n";
    $examplesInstruction .= '    // ⚠️ Every [NEEDS-NAME] bookmark MUST appear in _renamed with descriptive title' . "\n";
    $examplesInstruction .= "  },\n";
    $examplesInstruction .= '  "_suggestions": ["Great programming resource collection"]' . "\n";
    $examplesInstruction .= "}\n\n";

    // EXAMPLE 3: Combined Case
    $examplesInstruction .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $examplesInstruction .= "EXAMPLE 3: Combined - Icon-Only + Untitled + Duplicates\n";
    $examplesInstruction .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $examplesInstruction .= "INPUT BOOKMARKS:\n";
    $examplesInstruction .= "20. Amazon (amazon.com)\n";
    $examplesInstruction .= "21. Amazon Shopping (amazon.com)  // Duplicate domain\n";
    $examplesInstruction .= "22. Untitled (linkedin.com) [NEEDS-NAME]\n";
    $examplesInstruction .= "23. Untitled (instagram.com) [ICON-ONLY]  // Icon-only, stays at root\n";
    $examplesInstruction .= "24. Netflix (netflix.com)\n\n";
    $examplesInstruction .= "OPTIONS: duplicates=remove, naming=renameUntitled, keepIconOnlyAtRoot=true\n\n";
    $examplesInstruction .= "CORRECT OUTPUT:\n";
    $examplesInstruction .= "{\n";
    $examplesInstruction .= '  "analysis": { "hobbies": ["Shopping", "Entertainment"], "career": "Marketing", "lifeEvents": [], "productivityScore": "5/10" },' . "\n";
    $examplesInstruction .= '  "folders": {' . "\n";
    $examplesInstruction .= '    "🛍️ Shopping": [20],  // Only one Amazon, duplicate removed' . "\n";
    $examplesInstruction .= '    "💼 Professional": [22],  // LinkedIn for career' . "\n";
    $examplesInstruction .= '    "🎬 Entertainment": [24]  // Netflix' . "\n";
    $examplesInstruction .= '    // ⚠️ Bookmark 23 [ICON-ONLY] NOT in folders - stays at root!' . "\n";
    $examplesInstruction .= "  },\n";
    $examplesInstruction .= '  "_remove": [21],  // Remove duplicate Amazon' . "\n";
    $examplesInstruction .= '  "_renamed": {' . "\n";
    $examplesInstruction .= '    "22": "LinkedIn - Professional Network"  // Rename untitled LinkedIn' . "\n";
    $examplesInstruction .= '    // ⚠️ ID 23 NOT in _renamed - it\'s [ICON-ONLY], leave it untouched' . "\n";
    $examplesInstruction .= "  },\n";
    $examplesInstruction .= '  "_suggestions": ["Consider work-life balance", "Reduce shopping bookmarks"]' . "\n";
    $examplesInstruction .= "}\n\n";

    // EXAMPLE 4: Smart Rename All
    $examplesInstruction .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $examplesInstruction .= "EXAMPLE 4: Smart Rename Mode (smartRename)\n";
    $examplesInstruction .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $examplesInstruction .= "INPUT BOOKMARKS:\n";
    $examplesInstruction .= "30. Login (github.com)  // Vague title\n";
    $examplesInstruction .= "31. Home (reddit.com)   // Generic title\n";
    $examplesInstruction .= "32. Untitled (twitter.com) [ICON-ONLY]  // Icon-only, don't touch\n\n";
    $examplesInstruction .= "OPTIONS: naming=smartRename, keepIconOnlyAtRoot=true\n\n";
    $examplesInstruction .= "CORRECT OUTPUT:\n";
    $examplesInstruction .= "{\n";
    $examplesInstruction .= '  "analysis": { "hobbies": ["Programming", "Social Media"], "career": "Developer", "lifeEvents": [], "productivityScore": "7/10" },' . "\n";
    $examplesInstruction .= '  "folders": {' . "\n";
    $examplesInstruction .= '    "💻 Development": [30],' . "\n";
    $examplesInstruction .= '    "💬 Social": [31]' . "\n";
    $examplesInstruction .= '    // ⚠️ Bookmark 32 [ICON-ONLY] NOT in folders' . "\n";
    $examplesInstruction .= "  },\n";
    $examplesInstruction .= '  "_remove": [],' . "\n";
    $examplesInstruction .= '  "_renamed": {' . "\n";
    $examplesInstruction .= '    "30": "GitHub - Developer Platform",  // Improve vague "Login"' . "\n";
    $examplesInstruction .= '    "31": "Reddit - Community Discussions"  // Improve generic "Home"' . "\n";
    $examplesInstruction .= '    // ⚠️ ID 32 NOT renamed - [ICON-ONLY] bookmarks stay untouched' . "\n";
    $examplesInstruction .= "  },\n";
    $examplesInstruction .= '  "_suggestions": ["Better bookmark naming improves organization"]' . "\n";
    $examplesInstruction .= "}\n\n";

    $examplesInstruction .= "═══════════════════════════════════════════════════════════════\n";
    $examplesInstruction .= "KEY RULES DEMONSTRATED IN EXAMPLES:\n";
    $examplesInstruction .= "═══════════════════════════════════════════════════════════════\n";
    $examplesInstruction .= "1. [ICON-ONLY] bookmarks NEVER appear in 'folders' structure\n";
    $examplesInstruction .= "2. [ICON-ONLY] bookmarks NEVER appear in '_renamed' object\n";
    $examplesInstruction .= "3. [NEEDS-NAME] bookmarks MUST be renamed in '_renamed' object\n";
    $examplesInstruction .= "4. [NEEDS-NAME] bookmarks CAN be organized in folders (after renaming)\n";
    $examplesInstruction .= "5. Duplicates (same domain) should have one kept, others in '_remove'\n";
    $examplesInstruction .= "6. Always provide analysis with hobbies, career, life events, productivity\n";
    $examplesInstruction .= "7. Icon-only rule overrides ALL other organization preferences\n";
    $examplesInstruction .= "═══════════════════════════════════════════════════════════════\n";

    // User-specific prompt
    $userPrompt = "Analyze these " . count($bookmarks) . " bookmarks.\n\n";

    // CRITICAL INSTRUCTION - Handle icon-only bookmarks FIRST if enabled
    if ($options['keepIconOnlyAtRoot'] ?? false) {
        if (count($iconOnlyIds) > 0) {
            $userPrompt .= "⚠️ ABSOLUTE PRIORITY RULE #1 ⚠️\n";
            $userPrompt .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $userPrompt .= "The following bookmark IDs are marked [ICON-ONLY]: " . implode(', ', $iconOnlyIds) . "\n";
            $userPrompt .= "These bookmarks MUST NOT appear in your 'folders' structure.\n";
            $userPrompt .= "They MUST remain at the root level, unorganized.\n";
            $userPrompt .= "DO NOT include these IDs anywhere in your folder hierarchy.\n";
            $userPrompt .= "This rule overrides ALL other organization preferences.\n";

            // Add bookmark bar order preference
            $orderPreference = $options['currentBookmarkBarOrder'] ?? 'icons-first';
            if ($orderPreference === 'folders-first') {
                $userPrompt .= "⚠️ USER'S CURRENT ORDER PREFERENCE: Folders appear BEFORE icon-only bookmarks.\n";
                $userPrompt .= "When applied, folders should be listed first in the bookmark bar, then icon-only bookmarks.\n";
            } else {
                $userPrompt .= "⚠️ USER'S CURRENT ORDER PREFERENCE: Icon-only bookmarks appear BEFORE folders.\n";
                $userPrompt .= "When applied, icon-only bookmarks should appear first in the bookmark bar, then folders.\n";
            }

            $userPrompt .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        }
    }

    $userPrompt .= "USER OPTIONS & PREFERENCES:\n";
    $userPrompt .= "- Output Language: " . ($options['outputLanguage'] ?? 'en') . " (name folders and provide insights in this language)\n";
    $userPrompt .= "- Structure Style: " . ($options['structureStyle'] ?? 'smart') . "\n";

    if (!empty($options['structurePreferences'])) {
        $userPrompt .= "- User Preference: \"" . $options['structurePreferences'] . "\" (PRIORITY: respect this user input!)\n";
    }

    $userPrompt .= "- Folder Display: " . (($options['folderDisplay'] ?? 'emoji') === 'emoji' ? 'Add emojis to folder names' : 'Text-only folder names') . "\n";
    $userPrompt .= "- Sorting: " . ($options['sorting'] ?? 'smart') . "\n";
    $userPrompt .= "- Bookmark Bar Style: " . ($options['bookmarkBarStyle'] ?? 'full') . "\n";

    if (!empty($options['bookmarkBarPreferences'])) {
        $userPrompt .= "- Bookmark Bar Preference: \"" . $options['bookmarkBarPreferences'] . "\" (PRIORITY: respect this!)\n";
    }

    $userPrompt .= "- Duplicates: " . ($options['duplicates'] ?? 'remove') . "\n";
    $userPrompt .= "- Naming Strategy: " . ($options['naming'] ?? 'renameUntitled') . "\n";
    $userPrompt .= "- Shorten Long Names: " . (($options['shortenNames'] ?? 'off') === 'on' ? "Yes (max " . ($options['shortenWordLimit'] ?? 5) . " words)" : 'No') . "\n";
    $userPrompt .= "- Old Bookmarks: " . ($options['oldBookmarks'] ?? 'keep');

    if (($options['oldBookmarks'] ?? 'keep') === 'archive') {
        $userPrompt .= " (older than " . ($options['oldBookmarksYears'] ?? 2) . " years)\n";
    } else {
        $userPrompt .= "\n";
    }

    $userPrompt .= "- Sensitive Content: " . ($options['sensitiveContent'] ?? 'normal');

    if (($options['sensitiveContent'] ?? 'normal') === 'hide') {
        $hideDepthValue = $hideDepthMap[$options['hideDepth'] ?? 'deep'] ?? 1;
        $userPrompt .= " ({$hideDepthValue} levels deep with boring folder names)\n";
    } else {
        $userPrompt .= "\n";
    }

    $userPrompt .= "\nBOOKMARKS:\n{$bookmarkList}\n";

    $userPrompt .= "TASKS:\n";
    $userPrompt .= "1. ALWAYS provide hobbies, career insights, and productivity score (be honest but constructive)\n";
    $userPrompt .= "2. Organize according to structure style:\n";
    $userPrompt .= "   - smart: Analyze and create optimal structure (default)\n";
    $userPrompt .= "   - flat: Maximum 2 levels of folders\n";
    $userPrompt .= "   - deep: Detailed nested categories (3-5 levels)\n";
    $userPrompt .= "   - contentType: Group by type (Videos, Articles, Tools, Docs)\n";
    $userPrompt .= "   - domain: Group by website (YouTube, GitHub, etc.)\n";
    $userPrompt .= "   - yearly: Group by year added (2024, 2023, etc.)\n";
    $userPrompt .= "3. Handle duplicates: " . (($options['duplicates'] ?? 'remove') === 'remove' ? 'Remove them (list IDs in _remove)' : 'Keep all') . "\n";

    $namingStrategy = $options['naming'] ?? 'renameUntitled';
    if ($namingStrategy === 'renameUntitled') {
        if (count($untitledIds) > 0) {
            $userPrompt .= "4. Naming strategy: RENAME UNTITLED BOOKMARKS\n";
            $userPrompt .= "   Bookmarks marked [NEEDS-NAME] (IDs: " . implode(', ', $untitledIds) . ") need proper names.\n";
            $userPrompt .= "   REQUIRED: Add each of these IDs to the _renamed object with a descriptive name based on the domain.\n";
            $userPrompt .= "   Example: \"42\": \"GitHub - Project Repository\" or \"15\": \"YouTube - Tutorial Video\"\n";
        } else {
            $userPrompt .= "4. Naming strategy: Rename untitled bookmarks (none found in this batch)\n";
        }
    } elseif ($namingStrategy === 'smartRename') {
        $userPrompt .= "4. Naming strategy: Improve ALL bookmark names for clarity. Use _renamed object to provide better names.\n";
    } else {
        $userPrompt .= "4. Naming strategy: Keep original names (no _renamed needed)\n";
    }

    if (($options['shortenNames'] ?? 'off') === 'on') {
        $userPrompt .= "5. Shorten bookmark names longer than " . ($options['shortenWordLimit'] ?? 5) . " words to concise versions\n";
    }

    if (($options['oldBookmarks'] ?? 'keep') === 'archive') {
        $userPrompt .= "6. Create 'Archive' folder for bookmarks older than " . ($options['oldBookmarksYears'] ?? 2) . " years\n";
    } elseif (($options['oldBookmarks'] ?? 'keep') === 'remove') {
        $userPrompt .= "6. Remove bookmarks older than 2 years (add to _remove)\n";
    }

    if (($options['sensitiveContent'] ?? 'normal') === 'hide') {
        $hideDepthValue = $hideDepthMap[$options['hideDepth'] ?? 'deep'] ?? 1;
        $userPrompt .= "7. Hide sensitive content (adult, job search, medical) {$hideDepthValue} folders deep with boring folder names\n";
    } elseif (($options['sensitiveContent'] ?? 'normal') === 'remove') {
        $userPrompt .= "7. Remove sensitive bookmarks (add to _remove)\n";
    }

    $userPrompt .= "\nIMPORTANT: Provide 3-5 actionable suggestions in _suggestions array.\n";

    // FINAL VALIDATION REMINDER
    if ($options['keepIconOnlyAtRoot'] ?? false && count($iconOnlyIds) > 0) {
        $userPrompt .= "\n⚠️ FINAL CHECK BEFORE RETURNING JSON ⚠️\n";
        $userPrompt .= "Verify that NONE of these IDs appear in your folders structure: " . implode(', ', $iconOnlyIds) . "\n";
        $userPrompt .= "If you accidentally included any, remove them now.\n\n";
    }

    $userPrompt .= "Return ONLY the JSON object in the exact format specified. No other text.";

    // CURL to Claude API
    $ch = curl_init(CLAUDE_API_URL);

    $payload = [
        'model' => CLAUDE_MODEL,
        'max_tokens' => 8192, // Updated for Claude 4.5 Sonnet
        'system' => [
            [
                'type' => 'text',
                'text' => $systemInstruction,
                'cache_control' => ['type' => 'ephemeral'] // Enable prompt caching (block 1)
            ],
            [
                'type' => 'text',
                'text' => $examplesInstruction,
                'cache_control' => ['type' => 'ephemeral'] // Enable prompt caching (block 2)
            ]
        ],
        'messages' => [
            [
                'role' => 'user',
                'content' => $userPrompt
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

    logClaudeCall("Claude API Response", [
        'http_code' => $httpCode,
        'response_length' => strlen($response)
    ]);

    if ($httpCode !== 200) {
        logClaudeCall("Claude API Error", [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        return ['success' => false, 'error' => 'Claude API error: ' . $httpCode];
    }

    $data = json_decode($response, true);

    if (!isset($data['content'][0]['text'])) {
        logClaudeCall("Invalid Claude Response Structure", $data);
        return ['success' => false, 'error' => 'Invalid Claude response'];
    }

    // Parse JSON from Claude's response
    $resultText = $data['content'][0]['text'];

    logClaudeCall("Raw Claude Text Response", ['text' => $resultText]);

    // Clean up markdown code blocks if present
    $resultText = preg_replace('/```json\s*/', '', $resultText);
    $resultText = preg_replace('/```\s*/', '', $resultText);
    $resultText = trim($resultText);

    // Remove any text before the first { or [
    $firstBrace = strpos($resultText, '{');
    if ($firstBrace !== false && $firstBrace > 0) {
        $resultText = substr($resultText, $firstBrace);
    }

    // Remove any text after the last } or ]
    $lastBrace = strrpos($resultText, '}');
    if ($lastBrace !== false && $lastBrace < strlen($resultText) - 1) {
        $resultText = substr($resultText, 0, $lastBrace + 1);
    }

    logClaudeCall("Cleaned JSON Text", ['text' => $resultText]);

    $result = json_decode($resultText, true);

    if (!$result) {
        $jsonError = json_last_error_msg();
        logClaudeCall("JSON Parse Error", [
            'error' => $jsonError,
            'text' => $resultText
        ]);
        return [
            'success' => false,
            'error' => 'Failed to parse Claude response: ' . $jsonError,
            'raw_response' => substr($resultText, 0, 500) // First 500 chars for debugging
        ];
    }

    // Extract token usage from Claude's response
    $tokenUsage = [
        'input_tokens' => $data['usage']['input_tokens'] ?? 0,
        'output_tokens' => $data['usage']['output_tokens'] ?? 0,
        'cache_creation_input_tokens' => $data['usage']['cache_creation_input_tokens'] ?? 0,
        'cache_read_input_tokens' => $data['usage']['cache_read_input_tokens'] ?? 0
    ];

    // Calculate pricing (per Claude API pricing)
    // Input: $3 per 1M tokens ($0.000003 per token)
    // Output: $15 per 1M tokens ($0.000015 per token)
    // Cache write: $3.75 per 1M tokens ($0.00000375 per token)
    // Cache read: $0.30 per 1M tokens ($0.0000003 per token)

    $inputCost = ($tokenUsage['input_tokens'] * 0.000003);
    $outputCost = ($tokenUsage['output_tokens'] * 0.000015);
    $cacheWriteCost = ($tokenUsage['cache_creation_input_tokens'] * 0.00000375);
    $cacheReadCost = ($tokenUsage['cache_read_input_tokens'] * 0.0000003);

    $totalCost = $inputCost + $outputCost + $cacheWriteCost + $cacheReadCost;

    $pricing = [
        'input_cost' => $inputCost,
        'output_cost' => $outputCost,
        'cache_write_cost' => $cacheWriteCost,
        'cache_read_cost' => $cacheReadCost,
        'total_cost' => $totalCost
    ];

    logClaudeCall("Claude API Call Success", [
        'result_keys' => array_keys($result),
        'token_usage' => $tokenUsage,
        'pricing' => $pricing
    ]);

    // Log to database
    logToDatabase($payload, $data, $tokenUsage, $pricing);

    return [
        'success' => true,
        'result' => $result,
        'tokenUsage' => $tokenUsage,
        'pricing' => $pricing
    ];
}

function logToDatabase($request, $response, $tokenUsage, $pricing) {
    try {
        $db = getDB();

        // Create table if it doesn't exist
        $db->exec("CREATE TABLE IF NOT EXISTS claude_api_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            model TEXT,
            system_prompt TEXT,
            user_prompt TEXT,
            response_text TEXT,
            input_tokens INTEGER,
            output_tokens INTEGER,
            cache_creation_tokens INTEGER,
            cache_read_tokens INTEGER,
            input_cost REAL,
            output_cost REAL,
            cache_write_cost REAL,
            cache_read_cost REAL,
            total_cost REAL,
            full_request_json TEXT,
            full_response_json TEXT
        )");

        // Extract prompts
        $systemPrompt = '';
        if (isset($request['system']) && is_array($request['system'])) {
            foreach ($request['system'] as $block) {
                if (isset($block['text'])) {
                    $systemPrompt .= $block['text'] . "\n\n";
                }
            }
        }

        $userPrompt = '';
        if (isset($request['messages'][0]['content'])) {
            $userPrompt = $request['messages'][0]['content'];
        }

        $responseText = '';
        if (isset($response['content'][0]['text'])) {
            $responseText = $response['content'][0]['text'];
        }

        // Insert log
        $stmt = $db->prepare("
            INSERT INTO claude_api_logs (
                model, system_prompt, user_prompt, response_text,
                input_tokens, output_tokens, cache_creation_tokens, cache_read_tokens,
                input_cost, output_cost, cache_write_cost, cache_read_cost, total_cost,
                full_request_json, full_response_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $request['model'] ?? 'unknown',
            $systemPrompt,
            $userPrompt,
            $responseText,
            $tokenUsage['input_tokens'],
            $tokenUsage['output_tokens'],
            $tokenUsage['cache_creation_input_tokens'],
            $tokenUsage['cache_read_input_tokens'],
            $pricing['input_cost'],
            $pricing['output_cost'],
            $pricing['cache_write_cost'],
            $pricing['cache_read_cost'],
            $pricing['total_cost'],
            json_encode($request),
            json_encode($response)
        ]);

        logClaudeCall("Logged to database", ['log_id' => $db->lastInsertId()]);
    } catch (Exception $e) {
        logClaudeCall("Database logging failed", ['error' => $e->getMessage()]);
    }
}
