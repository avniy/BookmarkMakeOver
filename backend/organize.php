<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'credits.php';
require_once 'claude.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$apiKey = $data['apiKey'] ?? '';
$bookmarks = $data['bookmarks'] ?? [];
$options = $data['options'] ?? [];

// Validate API key
$user = validateApiKey($apiKey);
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

if (empty($bookmarks)) {
    echo json_encode(['success' => false, 'error' => 'No bookmarks provided']);
    exit;
}

// Calculate cost
$cost = count($bookmarks) * CREDITS_PER_BOOKMARK;

// Check credits
if ($user['credits'] < $cost) {
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient credits',
        'required' => $cost,
        'available' => $user['credits']
    ]);
    exit;
}

// Call Claude API
$claudeResult = callClaude($bookmarks, $options);

if (!$claudeResult['success']) {
    echo json_encode($claudeResult);
    exit;
}

// Deduct credits
$deductResult = deductCredits($user['id'], $cost, "Organized {$cost} bookmarks");

if (!$deductResult['success']) {
    echo json_encode($deductResult);
    exit;
}

// Save to history
$db = getDB();
$stmt = $db->prepare("
    INSERT INTO organization_history (user_id, bookmarks_count, credits_used, options, result)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    $user['id'],
    count($bookmarks),
    $cost,
    json_encode($options),
    json_encode($claudeResult['result'])
]);

echo json_encode([
    'success' => true,
    'result' => $claudeResult['result'],
    'creditsUsed' => $cost,
    'creditsRemaining' => $deductResult['remaining']
]);
