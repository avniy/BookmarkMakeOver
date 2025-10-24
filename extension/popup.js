const API_URL = 'https://phpstack-1510887-5948558.cloudwaysapps.com';

// Prevent popup from closing when clicking outside
document.addEventListener('click', (e) => {
    e.stopPropagation();
}, true);

// Device fingerprinting
function getDeviceFingerprint() {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    ctx.textBaseline = 'top';
    ctx.font = '14px Arial';
    ctx.fillText('fingerprint', 2, 2);
    const canvasHash = canvas.toDataURL();

    return {
        userAgent: navigator.userAgent,
        language: navigator.language,
        platform: navigator.platform,
        screenResolution: `${screen.width}x${screen.height}`,
        colorDepth: screen.colorDepth,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        canvasFingerprint: canvasHash.substring(0, 50), // First 50 chars
        hardwareConcurrency: navigator.hardwareConcurrency || 0,
        deviceMemory: navigator.deviceMemory || 0
    };
}

document.getElementById('authForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('email').value.trim();
    const apiKey = document.getElementById('apiKey').value.trim();
    const submitBtn = document.getElementById('submitBtn');
    const statusEl = document.getElementById('status');

    if (!email || !apiKey) return;

    // Disable form
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="spinner"></div>';

    showStatus('Authenticating...', 'info');

    try {
        const fingerprint = getDeviceFingerprint();

        const res = await fetch(`${API_URL}/session`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include', // Important: send cookies
            body: JSON.stringify({
                action: 'create',
                email,
                apiKey,
                fingerprint
            })
        });

        const data = await res.json();

        if (data.success) {
            showStatus('✅ Success! Opening wizard...', 'success');

            // Wait 1 second then open wizard and close popup
            setTimeout(() => {
                chrome.tabs.create({ url: `${API_URL}/wizard` });
                window.close();
            }, 1000);
        } else {
            showStatus('❌ ' + (data.error || 'Authentication failed'), 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Connect Account';
        }
    } catch (err) {
        showStatus('❌ Connection failed. Try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Connect Account';
    }
});

function showStatus(msg, type) {
    const statusEl = document.getElementById('status');
    statusEl.textContent = msg;
    statusEl.className = `status ${type}`;
    statusEl.style.display = 'block';
}
