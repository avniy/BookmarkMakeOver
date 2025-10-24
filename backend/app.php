<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sorted AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-8">
                    <span class="text-xl font-bold text-indigo-600">📚 Sorted AI</span>
                    <div id="creditsDisplay" class="bg-green-100 text-green-700 px-4 py-1 rounded-full text-sm font-semibold">
                        -- Credits
                    </div>
                </div>
                <div>
                    <button id="logoutBtn" class="text-gray-600 hover:text-gray-900">Logout</button>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-gray-500 text-sm font-semibold mb-2">Your Credits</h3>
                <div class="text-3xl font-bold text-indigo-600" id="creditsLarge">--</div>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-gray-500 text-sm font-semibold mb-2">Your API Key</h3>
                <div class="text-sm font-mono bg-gray-100 p-2 rounded break-all" id="apiKeyDisplay">
                    Loading...
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-gray-500 text-sm font-semibold mb-2">Chrome Extension</h3>
                <a href="../extension" download class="inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">
                    Download Extension
                </a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-8">
            <h2 class="text-2xl font-bold mb-6">How to Use</h2>

            <div class="space-y-6">
                <div class="flex items-start gap-4">
                    <div class="bg-indigo-100 text-indigo-600 w-8 h-8 rounded-full flex items-center justify-center font-bold flex-shrink-0">1</div>
                    <div>
                        <h4 class="font-semibold mb-2">Download & Install Extension</h4>
                        <p class="text-gray-600 text-sm">
                            Download the Chrome extension above, then:
                            <br>1. Open <code class="bg-gray-100 px-2 py-1 rounded">chrome://extensions</code>
                            <br>2. Enable "Developer mode"
                            <br>3. Click "Load unpacked" and select the extension folder
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="bg-indigo-100 text-indigo-600 w-8 h-8 rounded-full flex items-center justify-center font-bold flex-shrink-0">2</div>
                    <div>
                        <h4 class="font-semibold mb-2">Connect Your Account</h4>
                        <p class="text-gray-600 text-sm">
                            Open the extension and paste your API key (shown above).
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="bg-indigo-100 text-indigo-600 w-8 h-8 rounded-full flex items-center justify-center font-bold flex-shrink-0">3</div>
                    <div>
                        <h4 class="font-semibold mb-2">Organize Your Bookmarks</h4>
                        <p class="text-gray-600 text-sm">
                            Choose your options (icon-only, hide sensitive, brutal honesty, etc.) and click "Organize Bookmarks".
                            <br>Cost: 1 credit per bookmark.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-8 mt-8">
            <h2 class="text-2xl font-bold mb-4">Need More Credits?</h2>
            <p class="text-gray-600 mb-6">Credits never expire. Buy once, use forever.</p>

            <div class="grid md:grid-cols-3 gap-4">
                <div class="border-2 border-gray-200 rounded-lg p-6 text-center hover:border-indigo-600 cursor-pointer transition">
                    <div class="text-2xl font-bold mb-2">100 Credits</div>
                    <div class="text-gray-600 text-sm mb-4">~100 bookmarks</div>
                    <div class="text-3xl font-bold text-indigo-600 mb-4">$1.80</div>
                    <button class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 w-full">
                        Buy Now
                    </button>
                </div>

                <div class="border-2 border-indigo-600 rounded-lg p-6 text-center relative">
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-indigo-600 text-white px-3 py-1 rounded-full text-xs">
                        Popular
                    </div>
                    <div class="text-2xl font-bold mb-2">500 Credits</div>
                    <div class="text-gray-600 text-sm mb-4">~500 bookmarks</div>
                    <div class="text-3xl font-bold text-indigo-600 mb-4">$9.00</div>
                    <button class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 w-full">
                        Buy Now
                    </button>
                </div>

                <div class="border-2 border-gray-200 rounded-lg p-6 text-center hover:border-indigo-600 cursor-pointer transition">
                    <div class="text-2xl font-bold mb-2">1000 Credits</div>
                    <div class="text-gray-600 text-sm mb-4">~1000 bookmarks</div>
                    <div class="text-3xl font-bold text-indigo-600 mb-4">$18.00</div>
                    <button class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 w-full">
                        Buy Now
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        $(document).ready(async function() {
            const apiKey = localStorage.getItem('apiKey');

            if (!apiKey) {
                window.location.href = 'login.php';
                return;
            }

            $('#apiKeyDisplay').text(apiKey);

            // Load user data
            try {
                const res = await fetch(`api?action=user&apiKey=${apiKey}`);
                const data = await res.json();

                if (data.success) {
                    $('#creditsDisplay').text(`${data.credits} Credits`);
                    $('#creditsLarge').text(data.credits);
                    localStorage.setItem('credits', data.credits);
                } else {
                    alert('Session expired. Please login again.');
                    window.location.href = 'login.php';
                }
            } catch (err) {
                console.error('Failed to load user data', err);
            }

            $('#logoutBtn').on('click', function() {
                localStorage.removeItem('apiKey');
                localStorage.removeItem('credits');
                window.location.href = 'login.php';
            });
        });
    </script>
</body>
</html>
