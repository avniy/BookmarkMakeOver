<?php
require_once 'config.php';

$db = getDB();

$email = 'test@example.com';
$password = password_hash('test123', PASSWORD_BCRYPT);
$apiKey = bin2hex(random_bytes(32));
$credits = 99999;

// Check if user exists first
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo "User already exists. Updating credits and API key...\n";
    $apiKey = bin2hex(random_bytes(32));
    $stmt = $db->prepare('UPDATE users SET api_key = ?, credits = ? WHERE email = ?');
    $stmt->execute([$apiKey, $credits, $email]);
} else {
    echo "Creating new user...\n";
    $stmt = $db->prepare('INSERT INTO users (email, password, api_key, credits) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $password, $apiKey, $credits]);
}

echo "\n===================================\n";
echo "Test User Created Successfully!\n";
echo "===================================\n";
echo "Email: $email\n";
echo "Password: test123\n";
echo "API Key: $apiKey\n";
echo "Credits: $credits\n";
echo "===================================\n";
