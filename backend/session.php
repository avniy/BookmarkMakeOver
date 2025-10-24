<?php
require_once 'config.php';

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS for localhost development
header('Access-Control-Allow-Origin: chrome-extension://*');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

header('Content-Type: application/json');

// Rate limiting
function checkRateLimit($identifier, $maxAttempts = 5, $window = 3600) {
    $db = getDB();

    // Clean old attempts
    $db->exec("DELETE FROM rate_limits WHERE created_at < datetime('now', '-{$window} seconds')");

    // Count recent attempts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE identifier = ? AND created_at > datetime('now', '-{$window} seconds')");
    $stmt->execute([$identifier]);
    $row = $stmt->fetch();

    if ($row['count'] >= $maxAttempts) {
        return false;
    }

    // Log this attempt
    $stmt = $db->prepare("INSERT INTO rate_limits (identifier, created_at) VALUES (?, datetime('now'))");
    $stmt->execute([$identifier]);

    return true;
}

// Generate secure token
function generateSecureToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Create session
function createSession($email, $apiKey, $fingerprint) {
    $db = getDB();

    // Rate limit by email
    if (!checkRateLimit('auth_' . $email, 5, 3600)) {
        return ['success' => false, 'error' => 'Too many login attempts. Try again in 1 hour.'];
    }

    // Rate limit by IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit('auth_ip_' . $ip, 10, 3600)) {
        return ['success' => false, 'error' => 'Too many login attempts from this IP. Try again in 1 hour.'];
    }

    // Validate email + API key
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND api_key = ?");
    $stmt->execute([$email, $apiKey]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => 'Invalid email or API key'];
    }

    // Generate session token
    $sessionToken = generateSecureToken();
    $csrfToken = generateSecureToken();

    // Store fingerprint as JSON
    $fingerprintJson = json_encode($fingerprint);

    // Create session in database
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $stmt = $db->prepare("
        INSERT INTO secure_sessions (
            user_id,
            session_token,
            csrf_token,
            ip_address,
            user_agent,
            device_fingerprint,
            expires_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $user['id'],
        $sessionToken,
        $csrfToken,
        $ip,
        $fingerprint['userAgent'] ?? '',
        $fingerprintJson,
        $expiresAt
    ]);

    // Set HttpOnly cookie
    $isSecure = strpos(APP_URL, 'https://') === 0;
    setcookie(
        'bmo_session',
        $sessionToken,
        [
            'expires' => time() + 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure, // Auto-detect based on APP_URL
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Lax' // CSRF protection
        ]
    );

    return [
        'success' => true,
        'message' => 'Session created successfully'
    ];
}

// Validate session
function validateSession() {
    $db = getDB();

    // Get session token from cookie
    $sessionToken = $_COOKIE['bmo_session'] ?? null;

    if (!$sessionToken) {
        return ['success' => false, 'error' => 'No session found'];
    }

    // Get current IP and user agent
    $currentIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Fetch session from database
    $stmt = $db->prepare("
        SELECT s.*, u.email, u.credits, u.api_key
        FROM secure_sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.session_token = ? AND s.expires_at > datetime('now')
    ");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();

    if (!$session) {
        return ['success' => false, 'error' => 'Invalid or expired session'];
    }

    // Validate IP address
    if ($session['ip_address'] !== $currentIp) {
        // IP changed - log and potentially alert user
        // For now, we'll allow but could add stricter validation
        logSecurityEvent($session['user_id'], 'IP_CHANGE', "Old: {$session['ip_address']}, New: {$currentIp}");
    }

    // Validate user agent
    if ($session['user_agent'] !== $currentUserAgent) {
        logSecurityEvent($session['user_id'], 'USER_AGENT_CHANGE', "Session may be compromised");
        return ['success' => false, 'error' => 'Session validation failed'];
    }

    return [
        'success' => true,
        'user' => [
            'id' => $session['user_id'],
            'email' => $session['email'],
            'credits' => $session['credits'],
            'apiKey' => $session['api_key']
        ],
        'csrf_token' => $session['csrf_token']
    ];
}

// Validate CSRF token
function validateCsrfToken($providedToken) {
    $db = getDB();
    $sessionToken = $_COOKIE['bmo_session'] ?? null;

    if (!$sessionToken) {
        return false;
    }

    $stmt = $db->prepare("SELECT csrf_token FROM secure_sessions WHERE session_token = ?");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();

    return $session && hash_equals($session['csrf_token'], $providedToken);
}

// Log security events
function logSecurityEvent($userId, $eventType, $details) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO security_logs (user_id, event_type, details, ip_address, created_at)
        VALUES (?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$userId, $eventType, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}

// Logout
function logout() {
    $db = getDB();
    $sessionToken = $_COOKIE['bmo_session'] ?? null;

    if ($sessionToken) {
        // Delete session from database
        $stmt = $db->prepare("DELETE FROM secure_sessions WHERE session_token = ?");
        $stmt->execute([$sessionToken]);
    }

    // Clear cookie
    setcookie('bmo_session', '', time() - 3600, '/');

    return ['success' => true, 'message' => 'Logged out successfully'];
}

// Handle requests
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? ($_GET['action'] ?? '');

switch ($action) {
    case 'create':
        $email = $data['email'] ?? '';
        $apiKey = $data['apiKey'] ?? '';
        $fingerprint = $data['fingerprint'] ?? [];

        echo json_encode(createSession($email, $apiKey, $fingerprint));
        break;

    case 'validate':
        echo json_encode(validateSession());
        break;

    case 'logout':
        echo json_encode(logout());
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
