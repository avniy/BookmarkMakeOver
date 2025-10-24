<?php
require_once 'config.php';

// Start session
session_start();

function validateApiKey($apiKey) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, credits FROM users WHERE api_key = ?");
    $stmt->execute([$apiKey]);
    return $stmt->fetch();
}

function createSession($userId) {
    $db = getDB();
    $sessionId = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    $stmt = $db->prepare("INSERT INTO sessions (user_id, session_id, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $expiresAt]);

    $_SESSION['session_id'] = $sessionId;
    $_SESSION['user_id'] = $userId;

    return $sessionId;
}

function validateSession($sessionId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT s.user_id, u.email, u.credits
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.session_id = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$sessionId]);
    return $stmt->fetch();
}

function register($email, $password) {
    $db = getDB();

    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email already registered'];
    }

    // Create user
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $apiKey = bin2hex(random_bytes(32));

    $stmt = $db->prepare("INSERT INTO users (email, password, api_key, credits) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $hashedPassword, $apiKey, FREE_CREDITS_ON_SIGNUP]);

    $userId = $db->lastInsertId();
    $sessionId = createSession($userId);

    return [
        'success' => true,
        'apiKey' => $apiKey,
        'sessionId' => $sessionId,
        'credits' => FREE_CREDITS_ON_SIGNUP
    ];
}

function login($email, $password) {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, password, api_key, credits FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    $sessionId = createSession($user['id']);

    return [
        'success' => true,
        'apiKey' => $user['api_key'],
        'sessionId' => $sessionId,
        'credits' => $user['credits']
    ];
}

// API Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'register':
            echo json_encode(register($data['email'], $data['password']));
            break;

        case 'login':
            echo json_encode(login($data['email'], $data['password']));
            break;

        case 'validate':
            $user = validateApiKey($data['apiKey']);
            if ($user) {
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid API key']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
