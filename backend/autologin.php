<?php
require_once 'config.php';

// Quick auto-login for testing - REMOVE IN PRODUCTION!
session_start();

if (isset($_GET['user_id']) && isset($_GET['secret']) && $_GET['secret'] === 'bookmarks123') {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$_GET['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];

        echo "✅ Logged in as: " . htmlspecialchars($user['email']) . "<br>";
        echo "<a href='wizard.php'>Go to Wizard</a><br>";
        echo "<a href='index.html'>Go to Dashboard</a>";
    } else {
        echo "❌ User not found";
    }
} else {
    echo "❌ Invalid request. Use: ?user_id=2&secret=bookmarks123";
}
?>
