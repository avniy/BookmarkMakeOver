<?php
// Suppress PHP warnings in production
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'cloudwaysapps.com') !== false) {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', '0');
}

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

// App URL
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8000');

// Database Configuration
define('DB_TYPE', $_ENV['DB_TYPE'] ?? 'sqlite');
define('DB_PATH', __DIR__ . '/' . ($_ENV['DB_PATH'] ?? 'database.sqlite'));

// MySQL Configuration (for future use)
define('MYSQL_HOST', $_ENV['MYSQL_HOST'] ?? '');
define('MYSQL_DATABASE', $_ENV['MYSQL_DATABASE'] ?? '');
define('MYSQL_USERNAME', $_ENV['MYSQL_USERNAME'] ?? '');
define('MYSQL_PASSWORD', $_ENV['MYSQL_PASSWORD'] ?? '');

// API Keys
define('CLAUDE_API_KEY', $_ENV['CLAUDE_API_KEY']);
define('CLAUDE_API_URL', $_ENV['CLAUDE_API_URL']);
define('CLAUDE_MODEL', $_ENV['CLAUDE_MODEL']);

// App Settings
define('CREDITS_PER_BOOKMARK', (int)$_ENV['CREDITS_PER_BOOKMARK']);
define('CREDIT_PRICE', (float)$_ENV['CREDIT_PRICE']);
define('FREE_CREDITS_ON_SIGNUP', (int)$_ENV['FREE_CREDITS_ON_SIGNUP']);

// Lifetime Plan Settings
define('LIFETIME_PLAN_PRICE', 19.00);  // $19 one-time payment
define('LIFETIME_MONTHLY_LIMIT', 30);  // 30 AI analyses per month

// Security
define('SESSION_LIFETIME', (int)$_ENV['SESSION_LIFETIME']);

// Database Connection
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Create tables if they don't exist
            initDatabase($pdo);
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    return $pdo;
}

function initDatabase($pdo) {
    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            api_key VARCHAR(64) UNIQUE NOT NULL,
            credits INTEGER DEFAULT 100,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            session_id VARCHAR(64) UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS credit_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            type VARCHAR(20) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS organization_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            bookmarks_count INTEGER NOT NULL,
            credits_used INTEGER NOT NULL,
            options TEXT,
            result TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Secure sessions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secure_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            session_token VARCHAR(128) UNIQUE NOT NULL,
            csrf_token VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            device_fingerprint TEXT,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Rate limiting table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Security logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS security_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            event_type VARCHAR(50) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    // Create indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_api_key ON users(api_key)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_session_id ON sessions(session_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_transactions ON credit_transactions(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_session_token ON secure_sessions(session_token)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rate_limit_identifier ON rate_limits(identifier, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_security_logs_user ON security_logs(user_id, created_at)");
}

// Note: Headers removed from config.php - each file sets its own Content-Type
