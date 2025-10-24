<?php
// Prevent double execution (Nginx rewrite bug workaround)
if (defined('API_EXECUTED')) {
    exit;
}
define('API_EXECUTED', true);

require_once 'config.php';
require_once 'auth.php';
require_once 'credits.php';
require_once 'claude.php';

// API headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Start output buffering to prevent double output
ob_start();

// Log this script execution
error_log("=== API.PHP EXECUTION START ===");
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
error_log("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

// Handle GET requests (user info)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $apiKey = $_GET['apiKey'] ?? '';

    if ($action === 'user' && $apiKey) {
        $user = validateApiKey($apiKey);
        if ($user) {
            echo json_encode([
                'success' => true,
                'credits' => $user['credits'],
                'email' => $user['email']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid API key']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
    }
    exit;
}

// Handle POST requests (organize bookmarks, add credits)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');

    // Log the request
    error_log("API POST Request - Raw Input: " . substr($rawInput, 0, 200));

    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("API POST - JSON Error: " . json_last_error_msg());
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }

    $action = $data['action'] ?? '';
    $apiKey = $data['apiKey'] ?? '';

    error_log("API POST - Action: '$action', API Key: " . substr($apiKey, 0, 10) . "...");

    if (empty($action)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Missing action parameter']);
        exit;
    }

    // Handle addCredits action
    if ($action === 'addCredits') {
        $user = validateApiKey($apiKey);
        if (!$user) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Invalid API key']);
            exit;
        }

        $creditsToAdd = intval($data['credits'] ?? 0);
        if ($creditsToAdd <= 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Invalid credit amount']);
            exit;
        }

        $result = addCredits($user['id'], $creditsToAdd, 'Purchase');
        ob_end_clean();
        echo json_encode($result);
        exit;
    }

    // Handle upgradeToLifetime action
    if ($action === 'upgradeToLifetime') {
        $user = validateApiKey($apiKey);
        if (!$user) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Invalid API key']);
            exit;
        }

        // Check if already lifetime
        if ($user['plan_type'] === 'lifetime') {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Already have lifetime plan']);
            exit;
        }

        // Upgrade to lifetime
        $db = getDB();
        $currentMonth = date('Y-m');
        $stmt = $db->prepare("UPDATE users SET plan_type = 'lifetime', monthly_uses = 0, last_reset_month = ? WHERE id = ?");
        $stmt->execute([$currentMonth, $user['id']]);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Upgraded to lifetime plan',
            'monthlyLimit' => LIFETIME_MONTHLY_LIMIT
        ]);
        exit;
    }

    if ($action !== 'organize') {
        error_log("REJECTING: Action is '$action', not 'organize'");
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        error_log("=== API.PHP EXECUTION END (Invalid Action) ===");
        exit;
    }

    error_log("PROCEEDING: Action is 'organize'");

    // Validate API key
    $user = validateApiKey($apiKey);
    if (!$user) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid API key']);
        exit;
    }

    // Check monthly limit for lifetime plan users
    if ($user['plan_type'] === 'lifetime') {
        $currentMonth = date('Y-m');

        // Reset counter if new month
        if ($user['last_reset_month'] !== $currentMonth) {
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET monthly_uses = 0, last_reset_month = ? WHERE id = ?");
            $stmt->execute([$currentMonth, $user['id']]);
            $user['monthly_uses'] = 0;
        }

        // Check if limit exceeded
        if ($user['monthly_uses'] >= LIFETIME_MONTHLY_LIMIT) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Monthly limit reached',
                'message' => 'You have used all ' . LIFETIME_MONTHLY_LIMIT . ' analyses for this month. Limit resets on ' . date('F 1, Y', strtotime('first day of next month')),
                'limit' => LIFETIME_MONTHLY_LIMIT,
                'used' => $user['monthly_uses']
            ]);
            exit;
        }

        // Increment usage counter
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET monthly_uses = monthly_uses + 1 WHERE id = ?");
        $stmt->execute([$user['id']]);
    }

    $bookmarks = $data['bookmarks'] ?? [];
    $options = $data['options'] ?? [];

    if (empty($bookmarks)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No bookmarks provided']);
        exit;
    }

    // Calculate cost (1 credit per bookmark)
    $cost = count($bookmarks) * CREDITS_PER_BOOKMARK;

    // Check credits (skip for lifetime plan users)
    if ($user['plan_type'] !== 'lifetime' && $user['credits'] < $cost) {
        ob_end_clean();
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
        ob_end_clean();
        echo json_encode($claudeResult);
        exit;
    }

    // Deduct credits (skip for lifetime plan users)
    if ($user['plan_type'] !== 'lifetime') {
        $deductResult = deductCredits(
            $user['id'],
            $cost,
            "Organized {$cost} bookmarks"
        );

        if (!$deductResult['success']) {
            ob_end_clean();
            echo json_encode($deductResult);
            exit;
        }
        $creditsRemaining = $deductResult['remaining'];
    } else {
        $creditsRemaining = 'unlimited';
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
        'creditsUsed' => $user['plan_type'] === 'lifetime' ? 0 : $cost,
        'creditsRemaining' => $creditsRemaining,
        'monthlyUsesRemaining' => $user['plan_type'] === 'lifetime' ? (LIFETIME_MONTHLY_LIMIT - $user['monthly_uses'] - 1) : null,
        'tokenUsage' => $claudeResult['tokenUsage'] ?? null,
        'pricing' => $claudeResult['pricing'] ?? null
    ]);

    ob_end_flush();
    exit;
}

// Should never reach here
ob_end_clean();
echo json_encode(['success' => false, 'error' => 'Unknown error']);
exit;
