<?php
require_once 'config.php';

// Simple authentication - you should add proper auth
session_start();
if (!isset($_SESSION['admin']) && !isset($_GET['secret'])) {
    if ($_GET['secret'] ?? '' === 'bookmarks123') {
        $_SESSION['admin'] = true;
    } else {
        die('Access denied. Add ?secret=bookmarks123 to URL');
    }
}

$db = getDB();

// Create table if it doesn't exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS claude_api_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        model TEXT,
        system_prompt TEXT,
        user_prompt TEXT,
        response_text TEXT,
        input_tokens INTEGER,
        output_tokens INTEGER,
        cache_creation_tokens INTEGER,
        cache_read_tokens INTEGER,
        input_cost REAL,
        output_cost REAL,
        cache_write_cost REAL,
        cache_read_cost REAL,
        total_cost REAL,
        full_request_json TEXT,
        full_response_json TEXT
    )");
} catch (Exception $e) {
    die('Error creating table: ' . $e->getMessage());
}

// Get stats
$totalCalls = $db->query("SELECT COUNT(*) as count FROM claude_api_logs")->fetch()['count'] ?? 0;
$totalCost = $db->query("SELECT SUM(total_cost) as cost FROM claude_api_logs")->fetch()['cost'] ?? 0;
$totalInputTokens = $db->query("SELECT SUM(input_tokens) as tokens FROM claude_api_logs")->fetch()['tokens'] ?? 0;
$totalOutputTokens = $db->query("SELECT SUM(output_tokens) as tokens FROM claude_api_logs")->fetch()['tokens'] ?? 0;

// Get recent logs
$stmt = $db->query("SELECT * FROM claude_api_logs ORDER BY timestamp DESC LIMIT 50");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claude API Logs - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">🔍 Claude API Logs</h1>

        <!-- Stats Cards -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600 mb-1">Total API Calls</div>
                <div class="text-3xl font-bold text-blue-600"><?php echo number_format($totalCalls); ?></div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600 mb-1">Total Cost</div>
                <div class="text-3xl font-bold text-green-600">$<?php echo number_format($totalCost, 4); ?></div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600 mb-1">Input Tokens</div>
                <div class="text-3xl font-bold text-purple-600"><?php echo number_format($totalInputTokens); ?></div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600 mb-1">Output Tokens</div>
                <div class="text-3xl font-bold text-orange-600"><?php echo number_format($totalOutputTokens); ?></div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tokens (In/Out)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cache</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo htmlspecialchars($log['model']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="text-blue-600"><?php echo number_format($log['input_tokens']); ?></span> /
                            <span class="text-purple-600"><?php echo number_format($log['output_tokens']); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?php if ($log['cache_creation_tokens'] > 0 || $log['cache_read_tokens'] > 0): ?>
                                W: <span class="text-orange-600"><?php echo number_format($log['cache_creation_tokens']); ?></span>
                                R: <span class="text-green-600"><?php echo number_format($log['cache_read_tokens']); ?></span>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                            $<?php echo number_format($log['total_cost'], 6); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <button onclick="viewLog(<?php echo $log['id']; ?>)" class="text-indigo-600 hover:text-indigo-900">
                                View Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Log Detail Modal -->
    <div id="logModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900">Log Details</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>

            <div id="logContent" class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>

    <script>
        function viewLog(id) {
            fetch('admin_logs.php?action=view&id=' + id)
                .then(r => r.json())
                .then(data => {
                    const content = document.getElementById('logContent');
                    content.innerHTML = `
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">System Prompt</h3>
                                <pre class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">${escapeHtml(data.system_prompt)}</pre>
                            </div>

                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">User Prompt</h3>
                                <pre class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">${escapeHtml(data.user_prompt)}</pre>
                            </div>

                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">Response</h3>
                                <pre class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">${escapeHtml(data.response_text)}</pre>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">Full Request JSON</h3>
                                    <pre class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">${escapeHtml(JSON.stringify(JSON.parse(data.full_request_json), null, 2))}</pre>
                                </div>

                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">Full Response JSON</h3>
                                    <pre class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">${escapeHtml(JSON.stringify(JSON.parse(data.full_response_json), null, 2))}</pre>
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById('logModal').classList.remove('hidden');
                });
        }

        function closeModal() {
            document.getElementById('logModal').classList.add('hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>

<?php
// API endpoint for viewing log details
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM claude_api_logs WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($log);
    exit;
}
?>
