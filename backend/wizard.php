<?php
require_once 'config.php';

// Validate session
$sessionValid = false;
$userData = null;
$csrfToken = null;

if (isset($_COOKIE['bmo_session'])) {
    $db = getDB();
    $sessionToken = $_COOKIE['bmo_session'];
    $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $db->prepare("
        SELECT s.*, u.email, u.credits, u.api_key
        FROM secure_sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.session_token = ? AND s.expires_at > datetime('now')
    ");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();

    if ($session && $session['user_agent'] === $currentUserAgent) {
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
    <title>BookmarkMakeOver Wizard</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Smooth transitions */
        .step {
            display: none;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .step.active {
            display: block;
            opacity: 1;
            transform: translateX(0);
            animation: slideIn 0.4s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Progress bar */
        .progress-bar {
            height: 4px;
            background: #E5E7EB;
            position: relative;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563EB, #3B82F6);
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 2px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-color: #BFDBFE;
        }

        /* Tree view */
        .tree-item {
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .tree-item:hover {
            background: #F3F4F6;
        }
        .tree-children {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding-left: 20px;
        }
        .tree-children.open {
            max-height: 2000px;
        }
        .tree-toggle {
            transition: transform 0.3s;
            display: inline-block;
        }
        .tree-toggle.open {
            transform: rotate(90deg);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
            transform: translateY(-2px);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Option cards */
        .option-card {
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .option-card:hover {
            border-color: #3B82F6;
            background: #EFF6FF;
        }
        .option-card.selected {
            border-color: #2563EB;
            background: #DBEAFE;
        }

        /* Spinner */
        .spinner {
            border: 3px solid #E5E7EB;
            border-top: 3px solid #2563EB;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Insights */
        .insight-card {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            border-left: 4px solid #F59E0B;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Favicon */
        .favicon {
            width: 16px;
            height: 16px;
            margin-right: 8px;
            display: inline-block;
        }

        /* Floating card animations */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3) translateY(20px);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeIn {
            animation: fadeIn 0.4s ease-out;
        }

        /* Scrollbar styling for card container */
        #cardsContainer::-webkit-scrollbar {
            width: 8px;
        }

        #cardsContainer::-webkit-scrollbar-track {
            background: transparent;
        }

        #cardsContainer::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        #cardsContainer::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">

    <!-- Chrome-style Bookmark Bar (Fixed at Top) -->
    <div class="fixed top-0 left-0 right-0 bg-gray-100 border-b border-gray-300 z-[9999]" style="height: 28px;">
        <div id="bookmarkBarDemo" class="flex items-center gap-0.5 px-2 overflow-hidden whitespace-nowrap" style="height: 28px; font-size: 11px;">
            <!-- Items will be added here by JavaScript -->
        </div>
        <!-- Overflow dropdown menu (hidden by default) -->
        <div id="overflowMenu" class="hidden absolute right-2 top-7 bg-white rounded shadow-xl border border-gray-300 py-1 z-50" style="min-width: 200px; max-height: 400px; overflow-y: auto; font-size: 11px;">
            <!-- Overflow items will be added here -->
        </div>
    </div>

    <!-- Spacer for fixed bookmark bar -->
    <div style="height: 28px;"></div>

    <!-- Header -->
    <div class="bg-white border-b border-gray-200 py-4 px-6 mb-4">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="text-2xl">🧙‍♂️</span>
                <span class="text-xl font-bold text-blue-600">BookmarkMakeOver Wizard</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-gray-600"><?php echo htmlspecialchars($userData['email']); ?></span>
                <span class="bg-green-100 text-green-700 px-4 py-2 rounded-full font-semibold text-sm">
                    <span id="creditsDisplay"><?php echo number_format($userData['credits']); ?></span> credits
                </span>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="progress-bar max-w-6xl mx-auto mb-8">
        <div id="progressFill" class="progress-fill" style="width: 0%"></div>
    </div>

    <!-- Step Indicators -->
    <div class="max-w-6xl mx-auto px-6 mb-8">
        <div class="flex justify-between">
            <div id="stepIndicator1" class="step-indicator flex items-center gap-2">
                <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold">1</div>
                <span class="font-semibold text-gray-700">Load Bookmarks</span>
            </div>
            <div id="stepIndicator2" class="step-indicator flex items-center gap-2 opacity-50">
                <div class="w-10 h-10 rounded-full bg-gray-300 text-white flex items-center justify-center font-bold">2</div>
                <span class="font-semibold text-gray-500">Options</span>
            </div>
            <div id="stepIndicator3" class="step-indicator flex items-center gap-2 opacity-50">
                <div class="w-10 h-10 rounded-full bg-gray-300 text-white flex items-center justify-center font-bold">3</div>
                <span class="font-semibold text-gray-500">AI Analysis</span>
            </div>
            <div id="stepIndicator4" class="step-indicator flex items-center gap-2 opacity-50">
                <div class="w-10 h-10 rounded-full bg-gray-300 text-white flex items-center justify-center font-bold">4</div>
                <span class="font-semibold text-gray-500">Customize</span>
            </div>
            <div id="stepIndicator5" class="step-indicator flex items-center gap-2 opacity-50">
                <div class="w-10 h-10 rounded-full bg-gray-300 text-white flex items-center justify-center font-bold">5</div>
                <span class="font-semibold text-gray-500">Apply</span>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-6 pb-12">

        <!-- Step 1: Load Bookmarks -->
        <div id="step1" class="step active">
            <div class="card text-center">
                <div class="text-6xl mb-6">📚</div>
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Welcome to the Bookmark Wizard!</h2>
                <p class="text-gray-600 mb-6">Let's organize your bookmarks with AI magic. First, we'll load and analyze your bookmarks.</p>

                <div id="loadingState" class="hidden mb-6">
                    <div class="spinner mx-auto mb-4"></div>
                    <p class="text-gray-600">Loading your bookmarks...</p>
                </div>

                <div id="loadedState" class="hidden mb-6">
                    <div class="text-5xl mb-4">✅</div>
                    <p class="text-2xl font-bold text-gray-900 mb-2"><span id="bookmarkCount">0</span> Bookmarks Found</p>
                    <p class="text-gray-600">Cost: <span id="creditsNeeded">0</span> credits ($<span id="costEstimate">0</span>)</p>
                </div>

                <button id="loadBtn" class="btn btn-primary">Load Bookmarks</button>
            </div>
        </div>

        <!-- Step 2: Organization Settings -->
        <div id="step2" class="step">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">⚙️ Organization Settings</h2>
            <p class="text-gray-600 mb-4">Configure how AI will organize your bookmarks. <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-sm font-semibold">💙 = Recommended</span></p>

            <!-- Basic Settings -->
            <div class="space-y-4">

                <!-- Language -->
                <div class="card p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">🌍</span>
                            <h3 class="text-base font-bold text-gray-900">Output Language</h3>
                        </div>
                        <select id="outputLanguage" class="p-2 border-2 border-gray-300 rounded-lg font-semibold bg-white text-sm min-w-[150px]">
                            <option value="en">🇺🇸 English</option>
                            <option value="es">🇪🇸 Español</option>
                            <option value="fr">🇫🇷 Français</option>
                            <option value="de">🇩🇪 Deutsch</option>
                            <option value="it">🇮🇹 Italiano</option>
                            <option value="pt">🇧🇷 Português</option>
                            <option value="ru">🇷🇺 Русский</option>
                            <option value="ja">🇯🇵 日本語</option>
                            <option value="ko">🇰🇷 한국어</option>
                            <option value="zh">🇨🇳 中文</option>
                            <option value="ar">🇸🇦 العربية</option>
                            <option value="he">🇮🇱 עברית</option>
                        </select>
                    </div>
                </div>

                <!-- Structure Style -->
                <div class="card p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-2xl">📁</span>
                        <h3 class="text-base font-bold text-gray-900">Organization Structure</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-blue-300 bg-blue-50">
                            <input type="radio" name="structureStyle" value="smart" class="w-4 h-4 accent-blue-600" checked />
                            <div class="text-sm font-semibold text-gray-900">💙 Smart</div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                            <input type="radio" name="structureStyle" value="flat" class="w-4 h-4 accent-blue-600" />
                            <div class="text-sm font-semibold text-gray-900">Flat (2 levels)</div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                            <input type="radio" name="structureStyle" value="deep" class="w-4 h-4 accent-blue-600" />
                            <div class="text-sm font-semibold text-gray-900">Deep (nested)</div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                            <input type="radio" name="structureStyle" value="contentType" class="w-4 h-4 accent-blue-600" />
                            <div class="text-sm font-semibold text-gray-900">By Type</div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                            <input type="radio" name="structureStyle" value="domain" class="w-4 h-4 accent-blue-600" />
                            <div class="text-sm font-semibold text-gray-900">By Domain</div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                            <input type="radio" name="structureStyle" value="yearly" class="w-4 h-4 accent-blue-600" />
                            <div class="text-sm font-semibold text-gray-900">By Year</div>
                        </label>
                    </div>
                    <button type="button" class="mt-2 text-xs text-blue-600 hover:text-blue-800" onclick="document.getElementById('structureInput').classList.toggle('hidden')">
                        💬 Add your preferences
                    </button>
                    <textarea id="structureInput" class="hidden mt-2 w-full p-2 border-2 border-gray-300 rounded-lg text-xs resize-none" rows="2" placeholder="E.g., 'I want work and personal completely separated'"></textarea>
                </div>

                <!-- Folder Display -->
                <div class="card p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">✨</span>
                            <h3 class="text-base font-bold text-gray-900">Folder Display</h3>
                        </div>
                        <div class="flex gap-2">
                            <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-blue-300 bg-blue-50">
                                <input type="radio" name="folderDisplay" value="emoji" class="w-4 h-4 accent-blue-600" checked />
                                <div class="text-sm font-semibold text-gray-900">💙 With Emojis</div>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                <input type="radio" name="folderDisplay" value="text" class="w-4 h-4 accent-blue-600" />
                                <div class="text-sm font-semibold text-gray-900">Text Only</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Sorting -->
                <div class="card p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-2xl">🔤</span>
                        <h3 class="text-base font-bold text-gray-900">Sort Order</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-blue-300 bg-blue-50">
                            <input type="radio" name="sorting" value="smart" class="w-4 h-4 accent-blue-600" checked />
                            <div class="text-sm font-semibold text-gray-900">💙 Smart</div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                            <input type="radio" name="sorting" value="alphabetical" class="w-4 h-4 accent-blue-600" />
                            <div class="text-sm font-semibold text-gray-900">A-Z</div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                            <input type="radio" name="sorting" value="dateNewest" class="w-4 h-4 accent-blue-600" />
                            <div class="text-sm font-semibold text-gray-900">Newest First</div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                            <input type="radio" name="sorting" value="dateOldest" class="w-4 h-4 accent-blue-600" />
                            <div class="text-sm font-semibold text-gray-900">Oldest First</div>
                        </label>
                    </div>
                </div>

            </div>

            <!-- Advanced Options (Collapsible) -->
            <div class="mt-8 mb-6">
                <button type="button" id="toggleAdvanced" class="flex items-center gap-2 text-gray-700 hover:text-gray-900 font-semibold p-4 bg-gradient-to-r from-gray-100 to-gray-200 rounded-lg w-full shadow-sm hover:shadow-md transition">
                    <span id="advancedIcon" class="text-blue-600 font-bold">▶</span>
                    <span>Advanced Options (optional)</span>
                    <span class="ml-auto text-xs text-gray-500">Click to expand</span>
                </button>
                <div id="advancedOptions" class="hidden mt-6 space-y-6 p-6 bg-gradient-to-br from-gray-50 to-blue-50 rounded-lg border-2 border-gray-200">

                <!-- Bookmark Bar Style -->
                <div class="card">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-2xl">🔖</span>
                                <h3 class="text-lg font-bold text-gray-900">Bookmark Bar Style</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">How should bookmarks appear on the bookmark bar?</p>
                            <div class="space-y-2 mb-4">
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-blue-300 bg-blue-50">
                                    <input type="radio" name="bookmarkBarStyle" value="full" class="w-5 h-5 accent-blue-600" checked />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">💙 Full Titles</div>
                                        <div class="text-xs text-gray-600">Show complete bookmark names</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                    <input type="radio" name="bookmarkBarStyle" value="iconOnly" class="w-5 h-5 accent-blue-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Icon-Only (No Text)</div>
                                        <div class="text-xs text-gray-600">Show only favicons, saves space</div>
                                    </div>
                                </label>
                            </div>

                            <!-- Special Case: Keep existing icon-only bookmarks -->
                            <div class="border-t-2 border-gray-200 pt-4">
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer bg-yellow-50 border-2 border-yellow-300">
                                    <input type="checkbox" id="keepIconOnlyAtRoot" class="w-5 h-5 accent-yellow-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">📌 Keep Existing Icon-Only at Root Level</div>
                                        <div class="text-xs text-gray-600">Preserve bookmarks that already have no text (special case - works with any style above)</div>
                                    </div>
                                </label>
                            </div>

                            <!-- Optional: User preferences -->
                            <div class="mt-4">
                                <button type="button" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1" onclick="document.getElementById('bookmarkBarInput').classList.toggle('hidden')">
                                    <span>💬</span> Add your preferences (optional)
                                </button>
                                <textarea id="bookmarkBarInput" class="hidden mt-2 w-full p-3 border-2 border-gray-300 rounded-lg text-sm resize-none" rows="2" placeholder="E.g., 'I prefer icons for quick access but keep my work bookmarks with full names'"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Duplicate Handling -->
                <div class="card">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-2xl">🔄</span>
                                <h3 class="text-lg font-bold text-gray-900">Duplicate Handling</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">What to do with duplicate URLs?</p>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-blue-300 bg-blue-50">
                                    <input type="radio" name="duplicates" value="remove" class="w-5 h-5 accent-blue-600" checked />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">💙 Remove Duplicates</div>
                                        <div class="text-xs text-gray-600">Keep only one copy of each URL</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                    <input type="radio" name="duplicates" value="keep" class="w-5 h-5 accent-blue-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Keep All</div>
                                        <div class="text-xs text-gray-600">Don't remove duplicates</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Naming Strategy -->
                <div class="card">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-2xl">✏️</span>
                                <h3 class="text-lg font-bold text-gray-900">Bookmark Naming</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">How should AI handle bookmark titles?</p>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-blue-300 bg-blue-50">
                                    <input type="radio" name="naming" value="renameUntitled" class="w-5 h-5 accent-blue-600" checked />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">💙 Rename Untitled Only</div>
                                        <div class="text-xs text-gray-600">AI fixes "Untitled" bookmarks</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                    <input type="radio" name="naming" value="smartRename" class="w-5 h-5 accent-blue-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Smart Rename All</div>
                                        <div class="text-xs text-gray-600">AI improves ALL bookmark names for clarity</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                    <input type="radio" name="naming" value="keepOriginal" class="w-5 h-5 accent-blue-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Keep Original Names</div>
                                        <div class="text-xs text-gray-600">Don't change any names</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Long Name Shortening -->
                <div class="card">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-2xl">✂️</span>
                                <h3 class="text-lg font-bold text-gray-900">Shorten Long Names</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">Automatically shorten bookmark names longer than X words</p>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                    <input type="radio" name="shortenNames" value="off" class="w-5 h-5 accent-blue-600" checked />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">💙 Off</div>
                                        <div class="text-xs text-gray-600">Keep all names as is</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                    <input type="radio" name="shortenNames" value="on" class="w-5 h-5 accent-blue-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Shorten names over</div>
                                        <select id="shortenWordLimit" class="mt-2 p-2 border-2 border-gray-300 rounded-lg text-sm">
                                            <option value="5">5 words</option>
                                            <option value="7">7 words</option>
                                            <option value="10" selected>10 words</option>
                                            <option value="15">15 words</option>
                                        </select>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Old Bookmarks -->
                <div class="card">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-2xl">📦</span>
                                <h3 class="text-lg font-bold text-gray-900">Old Bookmarks (2+ Years)</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">How to handle bookmarks older than 2 years?</p>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-blue-300 bg-blue-50">
                                    <input type="radio" name="oldBookmarks" value="keep" class="w-5 h-5 accent-blue-600" checked />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">💙 Keep in Place</div>
                                        <div class="text-xs text-gray-600">Organize normally with others</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                    <input type="radio" name="oldBookmarks" value="archive" class="w-5 h-5 accent-blue-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Move to Archive Folder</div>
                                        <div class="text-xs text-gray-600">Separate "Archive" folder for old items</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer border-2 border-transparent">
                                    <input type="radio" name="oldBookmarks" value="remove" class="w-5 h-5 accent-blue-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Remove</div>
                                        <div class="text-xs text-gray-600">Delete bookmarks over 2 years old</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sensitive Content -->
                <div class="card border-red-200 bg-red-50">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-2xl">🔒</span>
                                <h3 class="text-lg font-bold text-gray-900">Sensitive/Adult Content</h3>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">AI will detect and handle sensitive content</p>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer bg-white border-2 border-transparent">
                                    <input type="radio" name="sensitiveContent" value="normal" class="w-5 h-5 accent-blue-600" checked />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">💙 Organize Normally</div>
                                        <div class="text-xs text-gray-600">No special handling</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer bg-white border-2 border-transparent">
                                    <input type="radio" name="sensitiveContent" value="hide" class="w-5 h-5 accent-red-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Hide Deep in Folders</div>
                                        <select id="hideDepth" class="mt-2 p-2 border-2 border-red-300 rounded-lg text-sm w-full">
                                            <option value="deep">1 level deep</option>
                                            <option value="deeper">3 levels deep</option>
                                            <option value="deepest">5 levels deep</option>
                                            <option value="mariana">10 levels deep (Mariana Trench 😏)</option>
                                        </select>
                                        <p class="text-xs text-gray-600 mt-2" id="depthPreview">💡 Example: "Personal" → Your bookmarks</p>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer bg-white border-2 border-transparent">
                                    <input type="radio" name="sensitiveContent" value="remove" class="w-5 h-5 accent-red-600" />
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Remove</div>
                                        <div class="text-xs text-gray-600">Delete sensitive bookmarks</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                </div><!-- End Advanced Options -->
            </div><!-- End Advanced Options Container -->

            <div class="flex gap-4 mt-8">
                <button id="backToStep1" class="btn bg-gray-200 text-gray-700 hover:bg-gray-300">← Back</button>
                <button id="continueToStep3" class="btn btn-primary flex-1">Continue to AI Analysis →</button>
            </div>
        </div>
        <!-- Step 3: AI Analysis & Before/After Comparison -->
        <div id="step3" class="step">
            <div id="analysisLoading" class="card text-center">
                <div class="spinner mx-auto mb-4"></div>
                <p class="text-xl font-semibold text-gray-900">AI is analyzing your bookmarks...</p>
                <p class="text-gray-600 mt-2">This may take a minute</p>
            </div>

            <div id="analysisResults" class="hidden">
                <h2 class="text-3xl font-bold text-gray-900 mb-6">🤖 AI Analysis Complete</h2>

                <!-- Insights Section -->
                <div id="insightsSection" class="mb-8"></div>

                <!-- Token Usage & Pricing -->
                <div id="tokenUsageCard" class="card bg-gradient-to-r from-green-50 to-blue-50 border-green-200 mb-6 hidden">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-3">💰 API Usage & Cost</h3>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-gray-600 mb-1">Input Tokens:</div>
                                    <div class="font-semibold text-gray-900"><span id="inputTokens">0</span> tokens</div>
                                    <div class="text-xs text-gray-500">Cost: $<span id="inputCost">0.000</span></div>
                                </div>

                                <div>
                                    <div class="text-gray-600 mb-1">Output Tokens:</div>
                                    <div class="font-semibold text-gray-900"><span id="outputTokens">0</span> tokens</div>
                                    <div class="text-xs text-gray-500">Cost: $<span id="outputCost">0.000</span></div>
                                </div>

                                <div>
                                    <div class="text-gray-600 mb-1">Cache Write:</div>
                                    <div class="font-semibold text-gray-900"><span id="cacheWriteTokens">0</span> tokens</div>
                                    <div class="text-xs text-gray-500">Cost: $<span id="cacheWriteCost">0.000</span></div>
                                </div>

                                <div>
                                    <div class="text-gray-600 mb-1">Cache Read:</div>
                                    <div class="font-semibold text-gray-900"><span id="cacheReadTokens">0</span> tokens</div>
                                    <div class="text-xs text-gray-500">Cost: $<span id="cacheReadCost">0.000</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-gray-600 text-sm mb-1">Total Cost</div>
                            <div class="text-3xl font-bold text-green-600">$<span id="totalCost">0.000</span></div>
                            <div class="text-xs text-gray-500 mt-1">
                                Model: <span id="modelUsed">claude-sonnet-4.5</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-green-200 text-xs text-gray-600">
                        💡 <strong>Pricing:</strong> Input $3/1M tokens • Output $15/1M tokens • Cache Write $3.75/1M • Cache Read $0.30/1M
                    </div>
                </div>

                <!-- View Toggle -->
                <div class="card bg-gradient-to-r from-indigo-50 to-purple-50 border-indigo-200 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-1">📊 Preview Mode</h3>
                            <p class="text-sm text-gray-600">Choose how to view your changes</p>
                        </div>
                        <div class="flex gap-2">
                            <button id="viewTree" class="btn bg-white border-2 border-indigo-300 text-indigo-700 hover:bg-indigo-50">
                                📁 Tree View
                            </button>
                            <button id="viewComparison" class="btn bg-indigo-600 text-white hover:bg-indigo-700">
                                ⚖️ Before/After Comparison
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tree View (Original) -->
                <div id="treeViewContainer" class="hidden">

                    <!-- Spacer to push content below fixed bar -->
                    <div style="height: 120px;"></div>

                    <!-- Legend -->
                    <div class="card bg-gradient-to-r from-gray-50 to-gray-100 border-gray-300 mb-4">
                        <h3 class="text-lg font-bold text-gray-900 mb-3">🏷️ Badge Legend</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded">🔞</span>
                                <span class="text-gray-700">Sensitive/Adult content</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded">🔒 Private</span>
                                <span class="text-gray-700">Hidden folder</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">📋</span>
                                <span class="text-gray-700">Duplicate</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">🗑️</span>
                                <span class="text-gray-700">Will be removed</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded">📆</span>
                                <span class="text-gray-700">Old (2+ years)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">✏️ New name</span>
                                <span class="text-gray-700">Renamed by AI</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tree Preview -->
                    <div class="card mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">📁 Proposed Structure</h3>
                            <div class="flex gap-2">
                                <button id="expandAll" class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded-lg hover:bg-blue-200">Expand All</button>
                                <button id="collapseAll" class="text-sm bg-gray-100 text-gray-700 px-3 py-1 rounded-lg hover:bg-gray-200">Collapse All</button>
                            </div>
                        </div>
                        <div id="treePreview" class="bg-gray-50 p-4 rounded-lg overflow-y-auto"></div>

                        <div class="mt-4 text-sm text-gray-600">
                            <strong>💡 Tip:</strong> Click folders to expand/collapse. Hover over badges for more details.
                        </div>
                    </div>
                </div>

                <!-- Before/After Comparison View -->
                <div id="comparisonViewContainer">
                    <div class="card mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">⚖️ Before vs After Comparison</h3>
                            <div class="flex gap-2">
                                <button id="acceptAllChanges" class="text-sm bg-green-100 text-green-700 px-3 py-1 rounded-lg hover:bg-green-200 font-semibold">
                                    ✓ Accept All
                                </button>
                                <button id="rejectAllChanges" class="text-sm bg-red-100 text-red-700 px-3 py-1 rounded-lg hover:bg-red-200 font-semibold">
                                    ✗ Reject All
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="text-center font-bold text-gray-700 bg-red-50 py-2 rounded-lg">
                                📂 Current Bookmarks
                            </div>
                            <div class="text-center font-bold text-gray-700 bg-green-50 py-2 rounded-lg">
                                ✨ AI Suggestion
                            </div>
                        </div>

                        <div id="comparisonContent" class="bg-gray-50 rounded-lg max-h-[600px] overflow-y-auto">
                            <!-- Comparison rows will be inserted here by JavaScript -->
                        </div>

                        <div class="mt-4 text-sm text-gray-600">
                            <strong>💡 Tip:</strong> Use the action buttons to keep, remove, or modify each bookmark individually.
                        </div>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button id="backToStep2" class="btn bg-gray-200 text-gray-700 hover:bg-gray-300">← Back</button>
                    <button id="continueToStep4" class="btn btn-primary flex-1">Looks Good! Continue →</button>
                    <button id="revertVersion" class="btn bg-yellow-100 text-yellow-700 hover:bg-yellow-200 hidden">↶ Revert to Previous</button>
                </div>
            </div>
        </div>

        <!-- Step 4: Customize -->
        <div id="step4" class="step">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">✨ Fine-Tune Your Organization</h2>

            <div class="card mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">💭 Add Your Custom Instructions</h3>
                <p class="text-gray-600 mb-4">Want to tweak something? Tell the AI what changes you'd like:</p>
                <textarea
                    id="customPrompt"
                    class="w-full p-4 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                    rows="4"
                    placeholder="Example: 'Move all WordPress bookmarks to a separate folder' or 'Rename folders to be more specific'"
                ></textarea>
                <button id="refineWithPrompt" class="btn btn-primary mt-4">🔄 Refine with AI</button>
            </div>

            <div id="versionHistory" class="card mb-6 hidden">
                <h3 class="text-xl font-bold text-gray-900 mb-4">📜 Version History</h3>
                <div id="versionList" class="space-y-2"></div>
            </div>

            <div class="flex gap-4">
                <button id="backToStep3" class="btn bg-gray-200 text-gray-700 hover:bg-gray-300">← Back</button>
                <button id="skipToStep5" class="btn btn-primary flex-1">Skip & Apply Changes →</button>
            </div>
        </div>

        <!-- Step 5: Apply -->
        <div id="step5" class="step">
            <div class="card text-center">
                <div id="applyReady" class="hidden">
                    <div class="text-6xl mb-6">🚀</div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Ready to Transform Your Bookmarks?</h2>
                    <p class="text-gray-600 mb-6">This will reorganize your bookmarks based on the AI analysis. Don't worry - you can always restore from backup!</p>

                    <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4 mb-6">
                        <p class="text-yellow-900 font-semibold mb-2">⚠️ Recommended: Download Backup First</p>
                        <button id="downloadBackup" class="bg-yellow-500 text-white px-6 py-3 rounded-full font-bold hover:bg-yellow-600">
                            📥 Download Backup Now
                        </button>
                    </div>

                    <button id="applyChanges" class="btn btn-primary text-xl px-12 py-4">✅ Apply Changes</button>
                </div>

                <div id="applyProgress" class="hidden">
                    <div class="spinner mx-auto mb-4"></div>
                    <p class="text-xl font-semibold text-gray-900 mb-2">Applying changes...</p>
                    <p class="text-gray-600" id="applyStatus">Starting...</p>
                </div>

                <div id="applyComplete" class="hidden">
                    <div class="text-6xl mb-6">🎉</div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">All Done!</h2>
                    <p class="text-gray-600 mb-6">Your bookmarks have been reorganized. Open your bookmarks bar to see the magic!</p>

                    <div class="flex gap-4 justify-center items-center">
                        <button id="restoreFromBackup" class="btn bg-orange-500 text-white hover:bg-orange-600">
                            ↶ Restore from Backup
                        </button>
                        <button onclick="window.close()" class="btn btn-primary">Close Wizard</button>
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Not happy with the result? Click "Restore from Backup" to revert all changes.
                    </div>
                </div>

                <div id="applyError" class="hidden">
                    <div class="text-6xl mb-6">❌</div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Something Went Wrong</h2>
                    <p class="text-gray-600 mb-6" id="errorMessage"></p>
                    <button id="retryApply" class="btn btn-primary">Retry</button>
                </div>
            </div>

            <div class="flex gap-4 mt-6">
                <button id="backToStep4" class="btn bg-gray-200 text-gray-700 hover:bg-gray-300">← Back</button>
            </div>
        </div>

    </div>

    <!-- Floating AI Insights Trigger - Piled Cards -->
    <div id="insightsTrigger" class="fixed bottom-6 right-6 z-50 hidden cursor-pointer group">
        <div class="relative" style="width: 70px; height: 90px;">
            <!-- Card 1 (bottom) -->
            <div class="absolute inset-0 bg-gradient-to-br from-pink-400 to-rose-500 rounded-lg shadow-lg transform rotate-[-8deg] translate-y-2 opacity-70 group-hover:translate-y-3 transition-all duration-300" style="width: 70px; height: 90px;"></div>

            <!-- Card 2 (middle) -->
            <div class="absolute inset-0 bg-gradient-to-br from-purple-400 to-indigo-500 rounded-lg shadow-lg transform rotate-[4deg] translate-y-1 opacity-85 group-hover:translate-y-2 transition-all duration-300" style="width: 70px; height: 90px;"></div>

            <!-- Card 3 (top) - main card -->
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg shadow-2xl flex items-center justify-center text-white group-hover:scale-105 transition-all duration-300" style="width: 70px; height: 90px;">
                <div class="text-4xl">💡</div>
            </div>

            <!-- Badge -->
            <div class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center shadow-lg" id="insightBadge">0</div>
        </div>
    </div>

    <!-- Insights Modal - Card Pile Design -->
    <div id="insightsModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden items-center justify-center p-4">
        <div class="relative max-w-2xl w-full bg-white/10 backdrop-blur-sm rounded-3xl p-8" style="perspective: 1000px;">
            <!-- Close button outside -->
            <button id="closeInsights" class="absolute -top-4 -right-4 bg-white text-gray-700 hover:text-gray-900 w-12 h-12 rounded-full shadow-xl flex items-center justify-center text-2xl font-bold z-10 hover:scale-110 transition">
                ×
            </button>

            <!-- Card Stack Container -->
            <div class="relative" style="min-height: 500px;">
                <!-- Card Display Area -->
                <div class="absolute inset-0 flex items-center justify-center" id="insightsCarousel">
                    <!-- Cards will be inserted here with piled effect -->
                </div>
            </div>

            <!-- Navigation Below Cards -->
            <div class="mt-8 flex items-center justify-center gap-6">
                <button id="prevCard" class="w-12 h-12 bg-white/90 hover:bg-white rounded-full shadow-lg font-bold text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center">
                    ←
                </button>

                <!-- Card-style Dots -->
                <div class="flex gap-3" id="insightsDots">
                    <!-- Mini card dots will be inserted here -->
                </div>

                <button id="nextCard" class="w-12 h-12 bg-white/90 hover:bg-white rounded-full shadow-lg font-bold text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center">
                    →
                </button>
            </div>
        </div>
    </div>

    <script>
        // Pass user data to JavaScript
        window.USER_API_KEY = <?php echo json_encode($userData['apiKey']); ?>;
    </script>
    <script>
        // Toggle Advanced Options
        document.getElementById('toggleAdvanced').addEventListener('click', function() {
            const advancedOptions = document.getElementById('advancedOptions');
            const advancedIcon = document.getElementById('advancedIcon');

            if (advancedOptions.classList.contains('hidden')) {
                advancedOptions.classList.remove('hidden');
                advancedIcon.textContent = '▼';
            } else {
                advancedOptions.classList.add('hidden');
                advancedIcon.textContent = '▶';
            }
        });
    </script>
    <script src="wizard.js"></script>

</body>
</html>
