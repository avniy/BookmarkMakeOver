<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sorted AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-black min-h-screen flex items-center justify-center">
    <div class="bg-white/5 backdrop-blur-sm border-2 border-teal-500/30 rounded-2xl shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white">📚 Sorted AI</h1>
            <p class="text-gray-300 mt-2">Create your account</p>
            <div class="mt-4 bg-green-100 text-green-700 px-4 py-2 rounded-lg text-sm">
                🎉 Get 100 free credits on signup!
            </div>
        </div>

        <form id="registerForm">
            <div class="mb-4">
                <label class="block text-gray-200 text-sm font-semibold mb-2">Email</label>
                <input type="email" id="email" required
                    class="w-full px-4 py-3 border border-teal-500/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>

            <div class="mb-4">
                <label class="block text-gray-200 text-sm font-semibold mb-2">Password</label>
                <input type="password" id="password" required minlength="6"
                    class="w-full px-4 py-3 border border-teal-500/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                <p class="text-xs text-gray-400 mt-1">Minimum 6 characters</p>
            </div>

            <div class="mb-6">
                <label class="block text-gray-200 text-sm font-semibold mb-2">Confirm Password</label>
                <input type="password" id="confirmPassword" required
                    class="w-full px-4 py-3 border border-teal-500/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-teal-500 to-cyan-500 text-white py-3 rounded-lg font-semibold hover:from-teal-600 hover:to-cyan-600 transition">
                Create Account
            </button>
        </form>

        <div id="message" class="mt-4 hidden"></div>

        <p class="text-center text-gray-300 mt-6">
            Already have an account?
            <a href="login" class="text-teal-400 font-semibold hover:underline">Login</a>
        </p>

        <p class="text-center mt-4">
            <a href="index" class="text-gray-300 text-sm hover:underline">← Back to home</a>
        </p>
    </div>

    <script>
        $('#registerForm').on('submit', async function(e) {
            e.preventDefault();

            const email = $('#email').val();
            const password = $('#password').val();
            const confirmPassword = $('#confirmPassword').val();

            if (password !== confirmPassword) {
                $('#message')
                    .removeClass('hidden bg-green-100 text-green-700')
                    .addClass('bg-red-100 text-red-700 p-4 rounded-lg')
                    .text('Passwords do not match');
                return;
            }

            try {
                const res = await fetch('auth', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'register', email, password })
                });

                const data = await res.json();

                if (data.success) {
                    $('#message')
                        .removeClass('hidden bg-red-100 text-red-700')
                        .addClass('bg-green-100 text-green-700 p-4 rounded-lg')
                        .html(`
                            <strong>Success!</strong> Your API Key:<br>
                            <code class="bg-white/5 backdrop-blur-sm border-2 border-teal-500/30 px-2 py-1 rounded text-xs">${data.apiKey}</code><br>
                            <small>Save this key! Redirecting...</small>
                        `);

                    localStorage.setItem('apiKey', data.apiKey);
                    localStorage.setItem('credits', data.credits);

                    setTimeout(() => {
                        window.location.href = 'app';
                    }, 3000);
                } else {
                    $('#message')
                        .removeClass('hidden bg-green-100 text-green-700')
                        .addClass('bg-red-100 text-red-700 p-4 rounded-lg')
                        .text(data.error || 'Registration failed');
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
