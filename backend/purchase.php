<?php
session_start();
require_once 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php?redirect=purchase');
  exit;
}

$credits = isset($_GET['credits']) ? intval($_GET['credits']) : 100;
$price = isset($_GET['price']) ? floatval($_GET['price']) : round($credits * CREDIT_PRICE, 2);

// Verify price calculation
$expectedPrice = round($credits * CREDIT_PRICE, 2);
if (abs($price - $expectedPrice) > 0.01) {
  $price = $expectedPrice; // Use correct price if mismatch
}

$db = getDB();
$stmt = $db->prepare("SELECT email, credits FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Purchase Credits - Sorted AI</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    h1, h2, h3 { font-family: 'Space Grotesk', sans-serif; }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">

  <nav class="border-b border-gray-800 bg-gray-900/50 backdrop-blur">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
      <a href="/" class="text-2xl font-bold text-white">📚 Sorted AI</a>
      <div class="flex items-center gap-4">
        <span class="text-gray-400"><?php echo htmlspecialchars($user['email']); ?></span>
        <span class="bg-indigo-600 px-4 py-1 rounded-full text-sm font-semibold">
          <?php echo number_format($user['credits']); ?> credits
        </span>
        <a href="app" class="text-indigo-400 hover:text-indigo-300">Dashboard</a>
        <a href="logout" class="text-gray-400 hover:text-white">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container mx-auto px-4 py-12 max-w-2xl">
    <div class="bg-gray-800 rounded-2xl p-8 border border-gray-700">
      <h1 class="text-3xl font-bold mb-2">Purchase Credits</h1>
      <p class="text-gray-400 mb-8">One-time purchase for your current bookmarks</p>

      <div class="bg-gray-900 rounded-xl p-6 mb-8 border border-gray-700">
        <div class="flex justify-between items-center mb-4">
          <span class="text-gray-400">Credits needed:</span>
          <span class="text-2xl font-bold text-white"><?php echo number_format($credits); ?></span>
        </div>
        <div class="flex justify-between items-center mb-4">
          <span class="text-gray-400">Price per credit:</span>
          <span class="text-lg text-indigo-400">$<?php echo number_format(CREDIT_PRICE, 3); ?></span>
        </div>
        <div class="border-t border-gray-700 pt-4 mt-4">
          <div class="flex justify-between items-center">
            <span class="text-xl font-semibold">Total:</span>
            <span class="text-3xl font-bold text-indigo-400">$<?php echo number_format($price, 2); ?></span>
          </div>
        </div>
      </div>

      <div class="bg-indigo-900/30 border border-indigo-700 rounded-xl p-6 mb-8">
        <h3 class="text-lg font-semibold mb-3 text-indigo-300">What you get:</h3>
        <ul class="space-y-2 text-gray-300">
          <li>✅ Exactly <?php echo number_format($credits); ?> credits</li>
          <li>✅ Enough for one complete bookmark organization</li>
          <li>✅ Credits never expire</li>
          <li>✅ AI-powered folder structure</li>
          <li>✅ Hobby & life event detection</li>
          <li>✅ Smart suggestions & cleanup</li>
        </ul>
      </div>

      <div class="space-y-4">
        <button onclick="handlePurchase()" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-4 rounded-xl transition text-lg">
          Purchase <?php echo number_format($credits); ?> Credits for $<?php echo number_format($price, 2); ?>
        </button>

        <a href="app" class="block text-center text-gray-400 hover:text-white">
          ← Back to Dashboard
        </a>
      </div>

      <div id="status" class="mt-6 hidden"></div>
    </div>

    <div class="mt-8 text-center text-gray-500 text-sm">
      <p>Secure payment processing • Credits added instantly</p>
      <p class="mt-2">Want unlimited? Check out our <a href="/#pricing" class="text-indigo-400 hover:text-indigo-300">Gold Lifetime</a> plan</p>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    function handlePurchase() {
      const credits = <?php echo $credits; ?>;
      const price = <?php echo $price; ?>;

      // Show status
      const status = $('#status');
      status.removeClass('hidden bg-red-900 bg-green-900')
           .addClass('bg-blue-900 border border-blue-700 text-blue-200 p-4 rounded-xl')
           .text('Processing payment...');

      // TODO: Integrate actual payment processor (Stripe, PayPal, etc.)
      // For now, simulate success (REMOVE THIS IN PRODUCTION)
      setTimeout(() => {
        status.removeClass('bg-blue-900 border-blue-700 text-blue-200')
              .addClass('bg-green-900 border border-green-700 text-green-200')
              .html(`
                ✅ <strong>Payment successful!</strong><br>
                ${credits.toLocaleString()} credits added to your account.<br>
                <a href="app" class="underline">Go to Dashboard</a>
              `);

        // Add credits to account (in production, this would happen server-side after payment)
        $.post('api', {
          action: 'addCredits',
          apiKey: localStorage.getItem('apiKey'),
          credits: credits
        }, (data) => {
          if (data.success) {
            setTimeout(() => window.location.href = 'app', 2000);
          }
        });
      }, 1500);
    }
  </script>

</body>
</html>
