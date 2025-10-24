<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BookmarkMakeOver</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">📚 BookmarkMakeOver</h1>
            <p class="text-gray-600 mt-2">Login to your account</p>
        </div>

        <form id="loginForm">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                <input type="email" id="email" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                <input type="password" id="password" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                Login
            </button>
        </form>

        <div id="message" class="mt-4 hidden"></div>

        <p class="text-center text-gray-600 mt-6">
            Don't have an account?
            <a href="register.php" class="text-indigo-600 font-semibold hover:underline">Register</a>
        </p>

        <p class="text-center mt-4">
            <a href="index.php" class="text-gray-600 text-sm hover:underline">← Back to home</a>
        </p>
    </div>

    <script>
        $('#loginForm').on('submit', async function(e) {
            e.preventDefault();

            const email = $('#email').val();
            const password = $('#password').val();

            try {
                const res = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', email, password })
                });

                const data = await res.json();

                if (data.success) {
                    $('#message')
                        .removeClass('hidden bg-red-100 text-red-700')
                        .addClass('bg-green-100 text-green-700 p-4 rounded-lg')
                        .text('Login successful! Redirecting...');

                    localStorage.setItem('apiKey', data.apiKey);
                    localStorage.setItem('credits', data.credits);

                    setTimeout(() => {
                        window.location.href = 'app.php';
                    }, 1000);
                } else {
                    $('#message')
                        .removeClass('hidden bg-green-100 text-green-700')
                        .addClass('bg-red-100 text-red-700 p-4 rounded-lg')
                        .text(data.error || 'Login failed');
                }
            } catch (err) {
                $('#message')
                    .removeClass('hidden bg-green-100 text-green-700')
                    .addClass('bg-red-100 text-red-700 p-4 rounded-lg')
                    .text('Connection error');
            }
        });
    </script>
</body>
</html>
