<?php
require_once 'config.php';

// Validate session server-side (directly, not via HTTP to avoid deadlock)
$sessionValid = false;
$userData = null;
$csrfToken = null;

// Check if session cookie exists
if (isset($_COOKIE['bmo_session'])) {
    $db = getDB();
    $sessionToken = $_COOKIE['bmo_session'];

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

    if ($session) {
        // Validate User Agent (IP changes can happen, so we log but allow)
        if ($session['user_agent'] === $currentUserAgent) {
            $sessionValid = true;
            $userData = [
                'id' => $session['user_id'],
                'email' => $session['email'],
                'credits' => $session['credits'],
                'apiKey' => $session['api_key']
            ];
            $csrfToken = $session['csrf_token'];
        }
    }
}

// If not authenticated, redirect to login
if (!$sessionValid) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Session Expired - BookmarkMakeOver</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-2xl shadow-xl max-w-md text-center">
            <div class="text-6xl mb-4">🔒</div>
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Session Expired</h1>
            <p class="text-gray-600 mb-6">Please click the extension icon again to sign in.</p>
            <button onclick="window.close()" class="bg-blue-600 text-white px-6 py-3 rounded-full font-semibold hover:bg-blue-700">
                Close This Tab
            </button>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookmarkMakeOver - Organize Your Bookmarks</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); }
        .btn-primary {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            box-shadow: 0 4px 14px 0 rgba(37, 99, 235, 0.25);
        }
        .btn-primary:hover { box-shadow: 0 6px 20px 0 rgba(37, 99, 235, 0.35); transform: translateY(-1px); }
        .btn-success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.25);
        }
        .btn-success:hover { box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.35); transform: translateY(-1px); }
        .card {
            background: white;
            border: 2px solid #BFDBFE;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .spinner {
            border: 3px solid #E5E7EB;
            border-top: 3px solid #2563EB;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">

    <!-- Header -->
    <div class="bg-white border-b border-gray-200 py-4 px-6 mb-8">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="text-2xl">📚</span>
                <span class="text-xl font-bold text-blue-600">BookmarkMakeOver</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-gray-600"><?php echo htmlspecialchars($userData['email']); ?></span>
                <span class="bg-green-100 text-green-700 px-4 py-2 rounded-full font-semibold text-sm">
                    <span id="creditsDisplay"><?php echo number_format($userData['credits']); ?></span> credits
                </span>
                <button onclick="logout()" class="text-gray-600 hover:text-gray-900 font-semibold">Logout</button>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-6 pb-12">

        <!-- Stats Card -->
        <div class="card mb-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <div class="text-sm text-gray-600 font-semibold">Your Bookmarks</div>
                    <div class="text-3xl font-bold text-blue-600" id="bookmarkCount">--</div>
                </div>
                <div>
                    <div class="text-sm text-gray-600 font-semibold">Credits Needed</div>
                    <div class="text-3xl font-bold text-gray-900">
                        <span id="creditsNeeded">--</span>
                        <span class="text-lg text-gray-600">($<span id="costEstimate">--</span>)</span>
                    </div>
                </div>
            </div>

            <div id="insufficientCredits" class="hidden bg-amber-50 border-2 border-amber-300 rounded-lg p-4 mt-4">
                <p class="text-amber-900 font-semibold mb-3">⚠️ Not enough credits</p>
                <a id="buyCreditsLink" href="#" class="inline-block bg-amber-500 text-white px-6 py-3 rounded-full font-bold hover:bg-amber-600 transition">
                    Buy Exact Amount ($<span id="exactPrice">--</span>)
                </a>
            </div>
        </div>

        <!-- Backup Section -->
        <div class="card bg-green-50 border-green-200 mb-6">
            <div class="flex items-start gap-4">
                <div class="text-4xl">📥</div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Backup First (Recommended)</h3>
                    <p class="text-gray-600 text-sm mb-4">Create a backup before organizing. Restore anytime if needed.</p>
                    <button id="backupBtn" class="btn-success text-white px-6 py-3 rounded-full font-bold transition">
                        Download Backup
                    </button>
                </div>
            </div>
        </div>

        <!-- Options Card -->
        <div class="card mb-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Organization Options</h3>
            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="iconOnly" class="w-5 h-5 accent-blue-600" />
                    <span class="font-semibold text-gray-700">Icon-Only Bookmark Bar</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="foldersOnly" class="w-5 h-5 accent-blue-600" />
                    <span class="font-semibold text-gray-700">Folders Only</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="remove404" class="w-5 h-5 accent-blue-600" />
                    <span class="font-semibold text-gray-700">Remove 404s & Dead Links</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="brutalHonesty" class="w-5 h-5 accent-blue-600" />
                    <span class="font-semibold text-gray-700">Brutal Honesty Mode 🔥</span>
                </label>
                <div class="border-t-2 border-blue-100 pt-4 mt-4">
                    <label class="flex items-center gap-3 cursor-pointer mb-3">
                        <input type="checkbox" id="hideSensitive" class="w-5 h-5 accent-blue-600" />
                        <span class="font-semibold text-gray-700">Hide Sensitive Content</span>
                    </label>
                    <select id="hideDepth" class="w-full p-3 border-2 border-blue-200 rounded-lg font-semibold" disabled>
                        <option value="deep">Deep (1 folder level)</option>
                        <option value="deeper">Deeper (3 folder levels)</option>
                        <option value="deepest">Deepest (5 folder levels)</option>
                        <option value="mariana">Mariana Trench 😏 (10 levels)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Organize Button -->
        <button id="organizeBtn" class="btn-primary text-white px-10 py-4 rounded-full text-lg font-bold w-full transition">
            🚀 Organize My Bookmarks
        </button>

        <div id="status" class="hidden mt-6"></div>

        <!-- Preview -->
        <div id="preview" class="card hidden mt-6">
            <h3 class="text-2xl font-bold text-blue-600 mb-4">📊 Preview</h3>
            <div id="previewContent" class="text-gray-700"></div>
            <div class="flex gap-4 mt-6">
                <button id="applyBtn" class="btn-primary text-white px-8 py-3 rounded-full font-bold flex-1 transition">
                    ✅ Apply Changes
                </button>
                <button id="cancelBtn" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-full font-bold transition hover:bg-gray-300">
                    ❌ Cancel
                </button>
            </div>
        </div>

    </div>

    <script>
        const API_URL = '<?php echo APP_URL; ?>';
        const CREDIT_PRICE = 0.018;
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
        const USER_DATA = <?php echo json_encode($userData); ?>;

        let currentCredits = USER_DATA.credits;
        let bookmarkCount = 0;
        let organizationResult = null;
        let extensionReady = false;

        // Message passing helper
        function sendMessageToExtension(action, data = null) {
            return new Promise((resolve, reject) => {
                const listener = (event) => {
                    if (event.origin !== window.location.origin) return;
                    const response = event.data;
                    console.log('📨 Received message:', response.action);
                    if (response.action === `${action}_RESPONSE`) {
                        console.log('✅ Got response for', action);
                        window.removeEventListener('message', listener);
                        resolve(response.data);
                    } else if (response.action === `${action}_ERROR`) {
                        console.error('❌ Error for', action, ':', response.error);
                        window.removeEventListener('message', listener);
                        reject(new Error(response.error));
                    }
                };
                window.addEventListener('message', listener);
                console.log('📤 Sending message:', action);
                window.postMessage({ action, data }, '*');
                setTimeout(() => {
                    window.removeEventListener('message', listener);
                    console.error('⏱️ Timeout waiting for', action);
                    reject(new Error('Extension timeout'));
                }, 10000);
            });
        }

        // Wait for extension with timeout
        window.addEventListener('message', (event) => {
            if (event.data.action === 'EXTENSION_READY') {
                extensionReady = true;
                console.log('✅ Extension bridge ready!');
                loadBookmarks();
            }
        });

        // Request extension to announce itself
        console.log('📡 Requesting extension connection...');
        window.postMessage({ action: 'PAGE_READY' }, '*');

        // Show error if content script doesn't load in 3 seconds
        setTimeout(() => {
            if (!extensionReady) {
                console.error('❌ Content script not loaded!');
                document.getElementById('bookmarkCount').textContent = 'ERROR';
                document.getElementById('creditsNeeded').textContent = '--';
                document.getElementById('costEstimate').textContent = '--';
                showStatus('⚠️ Extension not connected. Please reload the extension in Chrome and refresh this page.', 'error');
            }
        }, 3000);

        async function loadBookmarks() {
            try {
                const tree = await sendMessageToExtension('GET_BOOKMARKS');
                const bookmarks = flattenBookmarks(tree);
                bookmarkCount = bookmarks.length;

                const creditsNeeded = bookmarkCount;
                const cost = (creditsNeeded * CREDIT_PRICE).toFixed(2);

                document.getElementById('bookmarkCount').textContent = bookmarkCount;
                document.getElementById('creditsNeeded').textContent = creditsNeeded;
                document.getElementById('costEstimate').textContent = cost;

                if (currentCredits < creditsNeeded) {
                    document.getElementById('exactPrice').textContent = cost;
                    document.getElementById('buyCreditsLink').href = `${API_URL}/purchase?credits=${creditsNeeded}&price=${cost}`;
                    document.getElementById('insufficientCredits').classList.remove('hidden');
                }
            } catch (err) {
                showStatus('⚠️ Could not load bookmarks. Extension may not be ready.', 'error');
            }
        }

        function flattenBookmarks(tree, list = []) {
            tree.forEach(node => {
                if (node.url) {
                    list.push({
                        id: list.length + 1,
                        chromeId: node.id,
                        title: node.title || 'Untitled',
                        url: node.url,
                        domain: new URL(node.url).hostname.replace('www.', ''),
                        dateAdded: node.dateAdded
                    });
                }
                if (node.children) {
                    flattenBookmarks(node.children, list);
                }
            });
            return list;
        }

        document.getElementById('backupBtn').addEventListener('click', async () => {
            const btn = document.getElementById('backupBtn');
            btn.disabled = true;
            btn.textContent = '📥 Creating backup...';
            try {
                await sendMessageToExtension('DOWNLOAD_BACKUP');
                showStatus('✅ Backup saved!', 'success');
                setTimeout(() => hideStatus(), 3000);
            } catch (err) {
                showStatus('❌ Backup failed', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Download Backup';
            }
        });

        // Remove any existing listeners and add fresh one
        const organizeBtn = document.getElementById('organizeBtn');
        const newBtn = organizeBtn.cloneNode(true);
        organizeBtn.parentNode.replaceChild(newBtn, organizeBtn);

        document.getElementById('organizeBtn').addEventListener('click', async () => {
            console.log('🔘 Organize button clicked');
            const btn = document.getElementById('organizeBtn');
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner mx-auto"></div>';
            showStatus('🤖 AI is analyzing...', 'info');

            try {
                console.log('🎬 Starting organization...');
                const tree = await sendMessageToExtension('GET_BOOKMARKS');
                const bookmarks = flattenBookmarks(tree);

                const options = {
                    iconOnly: document.getElementById('iconOnly').checked,
                    foldersOnly: document.getElementById('foldersOnly').checked,
                    remove404: document.getElementById('remove404').checked,
                    brutalHonesty: document.getElementById('brutalHonesty').checked,
                    hideSensitive: document.getElementById('hideSensitive').checked,
                    hideDepth: document.getElementById('hideDepth').value
                };

                console.log('📤 Sending organize request with', bookmarks.length, 'bookmarks');
                const res = await fetch(`${API_URL}/organize`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        apiKey: USER_DATA.apiKey,
                        bookmarks,
                        options
                    })
                });

                console.log('📥 Response status:', res.status, res.statusText);
                const responseText = await res.text();
                console.log('📄 Raw response:', responseText);

                let data;
                try {
                    data = JSON.parse(responseText);
                    console.log('✅ Parsed data:', data);
                } catch (parseError) {
                    console.error('❌ JSON Parse Error:', parseError);
                    console.error('📄 Problematic text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }

                if (data.success) {
                    organizationResult = data.result;
                    currentCredits = data.creditsRemaining;
                    document.getElementById('creditsDisplay').textContent = currentCredits.toLocaleString();
                    showPreview(data.result);
                    showStatus('✅ Ready to apply!', 'success');
                } else {
                    showStatus('❌ ' + (data.error || 'Failed'), 'error');
                }
            } catch (err) {
                showStatus('❌ Error: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '🚀 Organize My Bookmarks';
            }
        });

        document.getElementById('hideSensitive').addEventListener('change', (e) => {
            document.getElementById('hideDepth').disabled = !e.target.checked;
        });

        document.getElementById('applyBtn').addEventListener('click', () => {
            showStatus('✅ Application in progress...', 'info');
        });

        document.getElementById('cancelBtn').addEventListener('click', () => {
            document.getElementById('preview').classList.add('hidden');
            organizationResult = null;
        });

        function showPreview(result) {
            let html = '';
            if (result.analysis) {
                html += `<div class="mb-6"><h4 class="font-bold text-lg mb-2">📊 Analysis</h4>`;
                html += `<p><strong>Hobbies:</strong> ${result.analysis.hobbies?.join(', ') || 'N/A'}</p>`;
                html += `<p><strong>Career:</strong> ${result.analysis.career || 'N/A'}</p>`;
                if (result.analysis.productivityScore) html += `<p><strong>Score:</strong> ${result.analysis.productivityScore}</p>`;
                html += `</div>`;
            }
            if (result._suggestions?.length) {
                html += `<div class="mb-6"><h4 class="font-bold text-lg mb-2">💡 Suggestions</h4><ul class="list-disc pl-6 space-y-1">`;
                result._suggestions.forEach(s => html += `<li>${s}</li>`);
                html += `</ul></div>`;
            }
            html += `<div class="mb-6"><h4 class="font-bold text-lg mb-2">📁 New Structure</h4><div class="bg-gray-50 p-4 rounded-lg">`;
            html += formatFolders(result.folders);
            html += `</div></div>`;
            if (result._remove?.length) html += `<p class="text-red-600 font-semibold">🗑️ To Remove: ${result._remove.length}</p>`;

            document.getElementById('previewContent').innerHTML = html;
            document.getElementById('preview').classList.remove('hidden');
        }

        function formatFolders(folders, indent = 0) {
            let html = '';
            for (const [name, content] of Object.entries(folders)) {
                if (name.startsWith('_')) continue;
                const prefix = '&nbsp;'.repeat(indent * 4);
                if (Array.isArray(content)) {
                    html += `${prefix}📁 <strong>${name}</strong> (${content.length})<br>`;
                } else {
                    html += `${prefix}📁 <strong>${name}</strong><br>`;
                    html += formatFolders(content, indent + 1);
                }
            }
            return html;
        }

        async function logout() {
            await fetch(`${API_URL}/session?action=logout`, { credentials: 'include' });
            window.location.reload();
        }

        function showStatus(msg, type) {
            const status = document.getElementById('status');
            status.className = `p-4 rounded-lg font-semibold text-center ${type === 'success' ? 'bg-green-100 text-green-700 border-2 border-green-300' : type === 'error' ? 'bg-red-100 text-red-700 border-2 border-red-300' : 'bg-blue-100 text-blue-700 border-2 border-blue-300'}`;
            status.textContent = msg;
            status.classList.remove('hidden');
        }

        function hideStatus() {
            document.getElementById('status').classList.add('hidden');
        }
    </script>

</body>
</html>
