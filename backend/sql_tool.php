<?php
require_once 'config.php';

// Simple auth
session_start();
if (!isset($_SESSION['sql_tool']) && (!isset($_GET['secret']) || $_GET['secret'] !== 'bookmarks123')) {
    die('Access denied. Add ?secret=bookmarks123 to URL');
}
$_SESSION['sql_tool'] = true;

$db = getDB();
$result = null;
$error = null;

// Handle query execution
if (isset($_GET['q'])) {
    $query = $_GET['q'];

    try {
        // Execute query
        $stmt = $db->query($query);

        // Check if it's a SELECT query
        if (stripos(trim($query), 'SELECT') === 0) {
            $result = [
                'success' => true,
                'type' => 'select',
                'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'count' => $stmt->rowCount()
            ];
        } else {
            $result = [
                'success' => true,
                'type' => 'modification',
                'affected_rows' => $stmt->rowCount(),
                'last_insert_id' => $db->lastInsertId()
            ];
        }
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }

    // If JSON requested, return JSON
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
}

// Quick actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'create_logs_table':
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
                $result = ['success' => true, 'message' => 'Table created successfully'];
            } catch (Exception $e) {
                $result = ['success' => false, 'error' => $e->getMessage()];
            }

            if (isset($_GET['json'])) {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            }
            break;

        case 'show_tables':
            $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
            $result = ['success' => true, 'tables' => $tables];

            if (isset($_GET['json'])) {
                header('Content-Type: application/json');
                echo json_encode($result, JSON_PRETTY_PRINT);
                exit;
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Tool - Sorted AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">🛠️ SQL Query Tool</h1>
            <p class="text-gray-600 mb-4">Execute SQL queries on the database</p>

            <!-- Quick Actions -->
            <div class="flex gap-2 mb-6 flex-wrap">
                <a href="?action=create_logs_table&secret=bookmarks123" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm">
                    📝 Create Logs Table
                </a>
                <a href="?action=show_tables&secret=bookmarks123" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 text-sm">
                    📋 Show All Tables
                </a>
                <a href="?q=SELECT%20*%20FROM%20claude_api_logs%20LIMIT%2010&secret=bookmarks123" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 text-sm">
                    🔍 View Recent Logs
                </a>
                <a href="admin_logs.php?secret=bookmarks123" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 text-sm">
                    📊 Admin Dashboard
                </a>
            </div>

            <!-- Query Form -->
            <form method="GET" class="mb-6">
                <input type="hidden" name="secret" value="bookmarks123">

                <label class="block text-sm font-semibold text-gray-700 mb-2">SQL Query:</label>
                <textarea
                    name="q"
                    rows="4"
                    class="w-full p-3 border-2 border-gray-300 rounded-lg font-mono text-sm focus:border-blue-500 focus:outline-none"
                    placeholder="SELECT * FROM claude_api_logs LIMIT 10"><?php echo htmlspecialchars($_GET['q'] ?? ''); ?></textarea>

                <div class="flex gap-2 mt-3">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700">
                        Execute Query
                    </button>
                    <button type="submit" name="json" value="1" class="bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-700">
                        Execute as JSON
                    </button>
                </div>
            </form>

            <!-- Example Queries -->
            <details class="mb-6">
                <summary class="cursor-pointer font-semibold text-gray-700 mb-2">📚 Example Queries</summary>
                <div class="bg-gray-50 p-4 rounded-lg text-sm space-y-2">
                    <div><strong>View all logs:</strong> <code class="bg-white px-2 py-1 rounded">SELECT * FROM claude_api_logs ORDER BY timestamp DESC LIMIT 20</code></div>
                    <div><strong>Total cost:</strong> <code class="bg-white px-2 py-1 rounded">SELECT SUM(total_cost) as total FROM claude_api_logs</code></div>
                    <div><strong>Token stats:</strong> <code class="bg-white px-2 py-1 rounded">SELECT SUM(input_tokens) as in_tokens, SUM(output_tokens) as out_tokens FROM claude_api_logs</code></div>
                    <div><strong>Show tables:</strong> <code class="bg-white px-2 py-1 rounded">SELECT name FROM sqlite_master WHERE type='table'</code></div>
                </div>
            </details>
        </div>

        <?php if ($result !== null): ?>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Results</h2>

            <?php if ($result['success']): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <span class="text-green-700 font-semibold">✓ Query executed successfully</span>
                </div>

                <?php if ($result['type'] === 'select'): ?>
                    <p class="text-gray-600 mb-4">Rows returned: <strong><?php echo $result['count']; ?></strong></p>

                    <?php if (count($result['rows']) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <?php foreach (array_keys($result['rows'][0]) as $column): ?>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase border-r">
                                                <?php echo htmlspecialchars($column); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($result['rows'] as $row): ?>
                                        <tr class="hover:bg-gray-50">
                                            <?php foreach ($row as $value): ?>
                                                <td class="px-4 py-2 text-sm text-gray-900 border-r max-w-md truncate" title="<?php echo htmlspecialchars($value); ?>">
                                                    <?php echo htmlspecialchars(substr($value, 0, 100)) . (strlen($value) > 100 ? '...' : ''); ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500">No rows returned</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-600">Affected rows: <strong><?php echo $result['affected_rows']; ?></strong></p>
                    <?php if ($result['last_insert_id']): ?>
                        <p class="text-gray-600">Last insert ID: <strong><?php echo $result['last_insert_id']; ?></strong></p>
                    <?php endif; ?>
                <?php endif; ?>

            <?php else: ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <span class="text-red-700 font-semibold">✗ Error:</span>
                    <pre class="mt-2 text-sm text-red-600"><?php echo htmlspecialchars($result['error']); ?></pre>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
