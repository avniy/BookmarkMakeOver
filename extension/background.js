// Background service worker for bookmark monitoring and dead link detection

chrome.runtime.onInstalled.addListener(() => {
  console.log('BookmarkMakeOver installed');
});

// Listen for bookmark changes to sync with backend
chrome.bookmarks.onCreated.addListener((id, bookmark) => {
  console.log('Bookmark created:', bookmark);
});

chrome.bookmarks.onRemoved.addListener((id, removeInfo) => {
  console.log('Bookmark removed:', id);
});

// Dead link checker (optional background task)
async function checkDeadLinks(bookmarks) {
  const results = [];

  for (const bookmark of bookmarks) {
    try {
      const response = await fetch(bookmark.url, { method: 'HEAD', mode: 'no-cors' });
      results.push({
        id: bookmark.id,
        status: response.ok ? 'alive' : 'dead'
      });
    } catch (err) {
      results.push({
        id: bookmark.id,
        status: 'error'
      });
    }
  }

  return results;
}
