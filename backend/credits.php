<?php
require_once 'config.php';

function getCredits($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? $result['credits'] : 0;
}

function deductCredits($userId, $amount, $description = '') {
    $db = getDB();

    // Check if user has enough credits
    $currentCredits = getCredits($userId);
    if ($currentCredits < $amount) {
        return ['success' => false, 'error' => 'Insufficient credits'];
    }

    // Deduct credits
    $stmt = $db->prepare("UPDATE users SET credits = credits - ? WHERE id = ?");
    $stmt->execute([$amount, $userId]);

    // Log transaction
    $stmt = $db->prepare("INSERT INTO credit_transactions (user_id, amount, type, description) VALUES (?, ?, 'usage', ?)");
    $stmt->execute([$userId, -$amount, $description]);

    return ['success' => true, 'remaining' => $currentCredits - $amount];
}

function addCredits($userId, $amount, $type = 'purchase', $description = '') {
    $db = getDB();

    $stmt = $db->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
    $stmt->execute([$amount, $userId]);

    $stmt = $db->prepare("INSERT INTO credit_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $amount, $type, $description]);

    return ['success' => true, 'newBalance' => getCredits($userId)];
}

function getCreditHistory($userId, $limit = 50) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM credit_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}
