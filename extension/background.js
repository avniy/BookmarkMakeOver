// Background service worker for Sorted AI

chrome.runtime.onInstalled.addListener(() => {
  console.log('Sorted AI installed');
});

// Handle messages from content script
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  console.log('🎯 Background received:', request.action);

  if (request.action === 'GET_BOOKMARKS') {
    chrome.bookmarks.getTree().then(tree => {
      console.log('✅ Got bookmarks from Chrome API');
      sendResponse({ success: true, data: tree });
    }).catch(error => {
      console.error('❌ Error getting bookmarks:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true; // Keep channel open for async response
  }

  if (request.action === 'DOWNLOAD_BACKUP') {
    chrome.bookmarks.getTree().then(tree => {
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
      const filename = `bookmarks-backup-${timestamp}.json`;

      // Convert to data URL (blob URLs don't work in service workers)
      const jsonString = JSON.stringify(tree, null, 2);
      const dataUrl = 'data:application/json;charset=utf-8,' + encodeURIComponent(jsonString);

      chrome.downloads.download({
        url: dataUrl,
        filename: filename,
        saveAs: true
      }).then(() => {
        console.log('✅ Backup created');
        sendResponse({ success: true });
      }).catch(error => {
        console.error('❌ Backup error:', error);
        sendResponse({ success: false, error: error.message });
      });
    }).catch(error => {
      sendResponse({ success: false, error: error.message });
    });
    return true; // Keep channel open for async response
  }

  if (request.action === 'CREATE_FOLDER') {
    const { parentId, title } = request.data;
    chrome.bookmarks.create({
      parentId: parentId,
      title: title
    }).then(folder => {
      console.log('✅ Folder created:', folder);
      sendResponse({ success: true, data: folder });
    }).catch(error => {
      console.error('❌ Error creating folder:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true;
  }

  if (request.action === 'MOVE_BOOKMARK') {
    const { id, destination } = request.data;
    chrome.bookmarks.move(id, destination).then(bookmark => {
      console.log('✅ Bookmark moved:', bookmark);
      sendResponse({ success: true, data: bookmark });
    }).catch(error => {
      console.error('❌ Error moving bookmark:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true;
  }

  if (request.action === 'REMOVE_BOOKMARK') {
    const { id } = request.data;
    chrome.bookmarks.remove(id).then(() => {
      console.log('✅ Bookmark removed:', id);
      sendResponse({ success: true });
    }).catch(error => {
      console.error('❌ Error removing bookmark:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true;
  }

  if (request.action === 'REMOVE_TREE') {
    const { id } = request.data;
    chrome.bookmarks.removeTree(id).then(() => {
      console.log('✅ Folder tree removed:', id);
      sendResponse({ success: true });
    }).catch(error => {
      console.error('❌ Error removing tree:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true;
  }

  if (request.action === 'CREATE_BOOKMARK') {
    const { parentId, title, url } = request.data;
    chrome.bookmarks.create({
      parentId: parentId,
      title: title,
      url: url
    }).then(bookmark => {
      console.log('✅ Bookmark created:', bookmark);
      sendResponse({ success: true, data: bookmark });
    }).catch(error => {
      console.error('❌ Error creating bookmark:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true;
  }

  return false;
});
