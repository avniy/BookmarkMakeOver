<?php
session_start();
require_once 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php?redirect=purchase_lifetime');
  exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT email, credits, plan_type, monthly_uses, last_reset_month FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if already has lifetime plan
$hasLifetime = ($user['plan_type'] === 'lifetime');

// Calculate remaining uses this month
$remainingUses = $hasLifetime ? (LIFETIME_MONTHLY_LIMIT - $user['monthly_uses']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lifetime Plan - Sorted AI</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    h1, h2, h3 { font-family: 'Space Grotesk', sans-serif; }
    .price-badge {
      background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
      animation: pulse 2s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-indigo-900 to-purple-900 text-gray-100 min-h-screen">

  <nav class="border-b border-white/10 bg-black/20 backdrop-blur">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
      <a href="/" class="text-2xl font-bold text-white">📚 Sorted AI</a>
      <div class="flex items-center gap-4">
        <span class="text-gray-300"><?php echo htmlspecialchars($user['email']); ?></span>
        <?php if ($hasLifetime): ?>
          <span class="bg-gradient-to-r from-yellow-400 to-orange-500 text-black px-4 py-1 rounded-full text-sm font-bold">
            ⭐ LIFETIME
          </span>
        <?php else: ?>
          <span class="bg-indigo-600 px-4 py-1 rounded-full text-sm font-semibold">
            <?php echo number_format($user['credits']); ?> credits
          </span>
        <?php endif; ?>
        <a href="wizard.php" class="text-indigo-400 hover:text-indigo-300">Wizard</a>
        <a href="index.html" class="text-indigo-400 hover:text-indigo-300">Dashboard</a>
        <a href="logout.php" class="text-gray-400 hover:text-white">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container mx-auto px-4 py-12 max-w-4xl">
    <?php if ($hasLifetime): ?>
      <!-- Already has lifetime plan -->
      <div class="bg-gradient-to-br from-yellow-900/40 to-orange-900/40 backdrop-blur-lg rounded-3xl p-12 border-2 border-yellow-500/50 text-center">
        <div class="text-6xl mb-6">⭐</div>
        <h1 class="text-4xl font-bold mb-4 bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">
          You're a Lifetime Member!
        </h1>
        <p class="text-xl text-gray-300 mb-8">
          Thank you for supporting Sorted AI
        </p>

        <div class="grid grid-cols-2 gap-6 max-w-xl mx-auto mb-8">
          <div class="bg-black/30 rounded-xl p-6 border border-white/10">
            <div class="text-gray-400 text-sm mb-2">Monthly Analyses</div>
            <div class="text-4xl font-bold text-white"><?php echo LIFETIME_MONTHLY_LIMIT; ?></div>
            <div class="text-green-400 text-sm mt-2">Every month forever</div>
          </div>

          <div class="bg-black/30 rounded-xl p-6 border border-white/10">
            <div class="text-gray-400 text-sm mb-2">Remaining This Month</div>
            <div class="text-4xl font-bold text-yellow-400"><?php echo $remainingUses; ?></div>
            <div class="text-gray-400 text-sm mt-2">Resets <?php echo date('M 1'); ?></div>
          </div>
        </div>

        <div class="flex justify-center gap-4">
          <a href="wizard.php" class="bg-gradient-to-r from-yellow-500 to-orange-500 text-black font-bold px-8 py-4 rounded-xl hover:scale-105 transition text-lg">
            Start Organizing →
          </a>
          <a href="index.html" class="bg-white/10 hover:bg-white/20 text-white font-semibold px-8 py-4 rounded-xl transition">
            Dashboard
          </a>
        </div>
      </div>

    <?php else: ?>
      <!-- Purchase lifetime plan -->
      <div class="text-center mb-12">
        <div class="inline-block price-badge text-black px-6 py-2 rounded-full font-bold text-lg mb-4">
          🎉 LIMITED TIME OFFER
        </div>
        <h1 class="text-5xl md:text-6xl font-bold mb-4 bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 bg-clip-text text-transparent">
          Lifetime Access
        </h1>
        <p class="text-2xl text-gray-300">
          One payment. Unlimited value. Forever.
        </p>
      </div>

      <div class="bg-gradient-to-br from-gray-800/50 to-purple-900/30 backdrop-blur-lg rounded-3xl p-8 md:p-12 border-2 border-purple-500/30 mb-8">
        <!-- Price -->
        <div class="text-center mb-12">
          <div class="text-gray-400 text-lg mb-2">One-time payment</div>
          <div class="text-7xl md:text-8xl font-bold mb-4">
            <span class="bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">$19</span>
          </div>
          <div class="text-2xl text-gray-300">No subscriptions. No hidden fees.</div>
        </div>

        <!-- Features -->
        <div class="grid md:grid-cols-2 gap-6 mb-12">
          <div class="bg-black/30 rounded-2xl p-6 border border-white/10">
            <div class="text-4xl mb-4">🔄</div>
            <h3 class="text-xl font-bold mb-2">30 AI Analyses/Month</h3>
            <p class="text-gray-400">
              Organize your bookmarks up to 30 times every month. Perfect for keeping your collection fresh and preventing account sharing.
            </p>
          </div>

          <div class="bg-black/30 rounded-2xl p-6 border border-white/10">
            <div class="text-4xl mb-4">♾️</div>
            <h3 class="text-xl font-bold mb-2">Lifetime Access</h3>
            <p class="text-gray-400">
              Pay once, use forever. No expiration dates, no renewals, no surprises.
            </p>
          </div>

          <div class="bg-black/30 rounded-2xl p-6 border border-white/10">
            <div class="text-4xl mb-4">🧠</div>
            <h3 class="text-xl font-bold mb-2">AI-Powered Intelligence</h3>
            <p class="text-gray-400">
              Smart folder structure, hobby detection, life event analysis, and brutal honesty about your productivity.
            </p>
          </div>

          <div class="bg-black/30 rounded-2xl p-6 border border-white/10">
            <div class="text-4xl mb-4">🎨</div>
            <h3 class="text-xl font-bold mb-2">All Features Included</h3>
            <p class="text-gray-400">
              Icon-only bookmarks, smart cleanup, rename suggestions, and everything else we'll ever build.
            </p>
          </div>
        </div>

        <!-- CTA -->
        <div class="space-y-4">
          <button onclick="handlePurchase()" class="w-full bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-400 hover:to-orange-400 text-black font-bold py-6 rounded-2xl transition text-xl shadow-2xl">
            Get Lifetime Access for $19
          </button>

          <div class="text-center text-gray-400 text-sm">
            <p>✓ Instant activation • ✓ Secure payment • ✓ 30-day money-back guarantee</p>
          </div>

          <a href="wizard.php" class="block text-center text-gray-400 hover:text-white">
            ← Continue with credits
          </a>
        </div>

        <div id="status" class="mt-6 hidden"></div>
      </div>

      <!-- Comparison -->
      <div class="bg-black/30 rounded-2xl p-8 border border-white/10">
        <h3 class="text-2xl font-bold mb-6 text-center">Why Lifetime?</h3>
        <div class="grid md:grid-cols-3 gap-6 text-center">
          <div>
            <div class="text-3xl mb-2">💸</div>
            <div class="font-bold text-lg mb-1">Credits System</div>
            <div class="text-gray-400 text-sm">$<?php echo number_format(CREDIT_PRICE, 3); ?>/credit</div>
            <div class="text-gray-400 text-sm">Pay per use</div>
          </div>
          <div class="relative">
            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-gradient-to-r from-yellow-500 to-orange-500 text-black text-xs font-bold px-3 py-1 rounded-full">
              BEST VALUE
            </div>
            <div class="text-3xl mb-2">⭐</div>
            <div class="font-bold text-lg mb-1 text-yellow-400">Lifetime Plan</div>
            <div class="text-gray-300 text-sm">$19 one-time</div>
            <div class="text-green-400 text-sm font-semibold">30 uses/month forever</div>
          </div>
          <div class="opacity-50">
            <div class="text-3xl mb-2">🔄</div>
            <div class="font-bold text-lg mb-1">Subscription</div>
            <div class="text-gray-400 text-sm line-through">$9.99/month</div>
            <div class="text-gray-400 text-sm">Not available</div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    function handlePurchase() {
      const status = $('#status');
      status.removeClass('hidden bg-red-900 bg-green-900')
           .addClass('bg-blue-900 border border-blue-700 text-blue-200 p-4 rounded-xl')
           .text('Processing payment...');

      // TODO: Integrate actual payment processor (Stripe, PayPal, etc.)
      // For now, simulate success (REMOVE THIS IN PRODUCTION)
      setTimeout(() => {
        // Update user to lifetime plan
        $.post('api.php', {
          action: 'upgradeToLifetime',
          apiKey: window.USER_API_KEY || localStorage.getItem('apiKey')
        }, (data) => {
          if (data.success) {
            status.removeClass('bg-blue-900 border-blue-700 text-blue-200')
                  .addClass('bg-green-900 border border-green-700 text-green-200')
                  .html(`
                    ✅ <strong>Welcome to Lifetime!</strong><br>
                    Your account has been upgraded. You now have 30 analyses per month, forever.<br>
                    <a href="wizard.php" class="underline">Start organizing →</a>
                  `);

            setTimeout(() => window.location.reload(), 2000);
          } else {
            status.removeClass('bg-blue-900 border-blue-700 text-blue-200')
                  .addClass('bg-red-900 border border-red-700 text-red-200')
                  .text('Error: ' + (data.error || 'Payment failed'));
          }
        });
      }, 1500);
    }
  </script>

</body>
</html>
