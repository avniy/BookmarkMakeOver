// Content script injected into extension.php page
// This bridges the gap between the web page and the extension

// Listen for messages from the page
window.addEventListener('message', async (event) => {
    // Only accept messages from our own page
    const allowedOrigins = [
        'https://phpstack-1510887-5948558.cloudwaysapps.com'
    ];
    if (!allowedOrigins.includes(event.origin)) return;

    const { action, data } = event.data;

    // Respond to page ready signal
    if (action === 'PAGE_READY') {
        console.log('📨 Received PAGE_READY, sending EXTENSION_READY');
        window.postMessage({ action: 'EXTENSION_READY' }, '*');
        return;
    }

    if (action === 'GET_BOOKMARKS') {
        console.log('📚 Relaying GET_BOOKMARKS to background...');
        chrome.runtime.sendMessage({ action: 'GET_BOOKMARKS' }, (response) => {
            if (response && response.success) {
                console.log('✅ Got bookmarks from background:', response.data.length, 'root nodes');
                window.postMessage({ action: 'GET_BOOKMARKS_RESPONSE', data: response.data }, '*');
            } else {
                console.error('❌ Error from background:', response?.error);
                window.postMessage({ action: 'GET_BOOKMARKS_ERROR', error: response?.error || 'Unknown error' }, '*');
            }
        });
    }

    if (action === 'DOWNLOAD_BACKUP') {
        console.log('💾 Relaying DOWNLOAD_BACKUP to background...');
        chrome.runtime.sendMessage({ action: 'DOWNLOAD_BACKUP' }, (response) => {
            if (response && response.success) {
                console.log('✅ Backup created by background');
                window.postMessage({ action: 'DOWNLOAD_BACKUP_RESPONSE' }, '*');
            } else {
                console.error('❌ Backup error from background:', response?.error);
                window.postMessage({ action: 'DOWNLOAD_BACKUP_ERROR', error: response?.error || 'Unknown error' }, '*');
            }
        });
    }

    if (action === 'GET_STORED_API_KEY') {
        try {
            const result = await chrome.storage.local.get('apiKey');
            window.postMessage({ action: 'API_KEY_RESPONSE', data: result.apiKey }, '*');
        } catch (err) {
            window.postMessage({ action: 'API_KEY_ERROR', error: err.message }, '*');
        }
    }

    if (action === 'STORE_API_KEY') {
        try {
            await chrome.storage.local.set({ apiKey: data.apiKey });
            window.postMessage({ action: 'API_KEY_STORED' }, '*');
        } catch (err) {
            window.postMessage({ action: 'API_KEY_STORE_ERROR', error: err.message }, '*');
        }
    }

    if (action === 'CREATE_FOLDER') {
        console.log('📁 Relaying CREATE_FOLDER to background...', data);
        chrome.runtime.sendMessage({ action: 'CREATE_FOLDER', data }, (response) => {
            if (response && response.success) {
                console.log('✅ Folder created:', response.data);
                window.postMessage({ action: 'CREATE_FOLDER_RESPONSE', data: response.data }, '*');
            } else {
                console.error('❌ Error creating folder:', response?.error);
                window.postMessage({ action: 'CREATE_FOLDER_ERROR', error: response?.error || 'Unknown error' }, '*');
            }
        });
    }

    if (action === 'MOVE_BOOKMARK') {
        console.log('🔀 Relaying MOVE_BOOKMARK to background...', data);
        chrome.runtime.sendMessage({ action: 'MOVE_BOOKMARK', data }, (response) => {
            if (response && response.success) {
                console.log('✅ Bookmark moved:', response.data);
                window.postMessage({ action: 'MOVE_BOOKMARK_RESPONSE', data: response.data }, '*');
            } else {
                console.error('❌ Error moving bookmark:', response?.error);
                window.postMessage({ action: 'MOVE_BOOKMARK_ERROR', error: response?.error || 'Unknown error' }, '*');
            }
        });
    }

    if (action === 'REMOVE_BOOKMARK') {
        console.log('🗑️ Relaying REMOVE_BOOKMARK to background...', data);
        chrome.runtime.sendMessage({ action: 'REMOVE_BOOKMARK', data }, (response) => {
            if (response && response.success) {
                console.log('✅ Bookmark removed');
                window.postMessage({ action: 'REMOVE_BOOKMARK_RESPONSE' }, '*');
            } else {
                console.error('❌ Error removing bookmark:', response?.error);
                window.postMessage({ action: 'REMOVE_BOOKMARK_ERROR', error: response?.error || 'Unknown error' }, '*');
            }
        });
    }
});

// Notify page that content script is ready
console.log('🔌 Sorted AI content script loaded!');

// Send ready message after page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('📤 Sending EXTENSION_READY message');
        window.postMessage({ action: 'EXTENSION_READY' }, '*');
    });
} else {
    console.log('📤 Sending EXTENSION_READY message (immediate)');
    window.postMessage({ action: 'EXTENSION_READY' }, '*');
}
