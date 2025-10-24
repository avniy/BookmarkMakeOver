const API_URL = 'http://localhost:8000';

let bookmarkData = null;
let organizationResult = null;

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
  const apiKey = await getStoredApiKey();

  if (apiKey) {
    showMainSection();
    await loadUserData(apiKey);
  } else {
    showLoginSection();
  }

  setupEventListeners();
});

// Event Listeners
function setupEventListeners() {
  document.getElementById('loginBtn').addEventListener('click', handleLogin);
  document.getElementById('organizeBtn').addEventListener('click', handleOrganize);
  document.getElementById('applyBtn').addEventListener('click', handleApply);
  document.getElementById('cancelBtn').addEventListener('click', handleCancel);

  document.getElementById('hideSensitive').addEventListener('change', (e) => {
    document.getElementById('hideDepth').disabled = !e.target.checked;
  });
}

// Auth
async function handleLogin() {
  const apiKey = document.getElementById('apiKey').value.trim();
  if (!apiKey) return showStatus('Enter API key', 'error');

  showStatus('Connecting...', 'info');

  try {
    const res = await fetch(`${API_URL}/auth.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'validate', apiKey })
    });

    const data = await res.json();

    if (data.success) {
      await chrome.storage.local.set({ apiKey });
      showMainSection();
      await loadUserData(apiKey);
      showStatus('Connected!', 'success');
      setTimeout(() => hideStatus(), 2000);
    } else {
      showStatus(data.error || 'Invalid API key', 'error');
    }
  } catch (err) {
    showStatus('Connection failed', 'error');
  }
}

async function loadUserData(apiKey) {
  try {
    const res = await fetch(`${API_URL}/api.php?action=user&apiKey=${apiKey}`);
    const data = await res.json();

    if (data.success) {
      document.getElementById('credits').textContent = data.credits;
      await countBookmarks();
    }
  } catch (err) {
    console.error('Failed to load user data', err);
  }
}

// Bookmark Operations
async function countBookmarks() {
  const tree = await chrome.bookmarks.getTree();
  const count = flattenBookmarks(tree).length;
  document.getElementById('bookmarkCount').textContent = `${count} bookmarks`;
  document.getElementById('costEstimate').textContent = `Cost: ${count} credits`;
}

async function handleOrganize() {
  const apiKey = await getStoredApiKey();
  if (!apiKey) return;

  const btn = document.getElementById('organizeBtn');
  btn.disabled = true;
  btn.textContent = 'Analyzing...';

  showStatus('Reading bookmarks...', 'info');

  try {
    const tree = await chrome.bookmarks.getTree();
    const bookmarks = flattenBookmarks(tree);

    showStatus(`Organizing ${bookmarks.length} bookmarks...`, 'info');

    const options = getSelectedOptions();

    const res = await fetch(`${API_URL}/api.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'organize',
        apiKey,
        bookmarks,
        options
      })
    });

    const data = await res.json();

    if (data.success) {
      organizationResult = data.result;
      showPreview(data.result);
      showStatus('Ready to apply!', 'success');
    } else {
      showStatus(data.error || 'Organization failed', 'error');
    }
  } catch (err) {
    showStatus('Error: ' + err.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Organize Bookmarks';
  }
}

async function handleApply() {
  if (!organizationResult) return;

  const btn = document.getElementById('applyBtn');
  btn.disabled = true;
  btn.textContent = 'Applying...';

  showStatus('Reorganizing bookmarks...', 'info');

  try {
    await applyOrganization(organizationResult);
    showStatus('Done! Bookmarks reorganized.', 'success');
    hidePreview();
    await loadUserData(await getStoredApiKey());
  } catch (err) {
    showStatus('Failed to apply changes', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Apply';
  }
}

function handleCancel() {
  hidePreview();
  organizationResult = null;
}

// Helper Functions
function flattenBookmarks(tree, list = []) {
  tree.forEach(node => {
    if (node.url) {
      const domain = new URL(node.url).hostname.replace('www.', '');
      list.push({
        id: list.length + 1,
        chromeId: node.id,
        title: node.title || 'Untitled',
        url: node.url,
        domain,
        dateAdded: node.dateAdded
      });
    }
    if (node.children) {
      flattenBookmarks(node.children, list);
    }
  });
  return list;
}

function getSelectedOptions() {
  return {
    iconOnly: document.getElementById('iconOnly').checked,
    foldersOnly: document.getElementById('foldersOnly').checked,
    remove404: document.getElementById('remove404').checked,
    brutalHonesty: document.getElementById('brutalHonesty').checked,
    hideSensitive: document.getElementById('hideSensitive').checked,
    hideDepth: document.getElementById('hideDepth').value
  };
}

async function applyOrganization(result) {
  // Create new folder structure
  const bookmarkBar = (await chrome.bookmarks.getTree())[0].children[0];

  // Clear existing bookmarks (optional, or move to archive)
  // For now, we'll create a new "Organized" folder
  const organizedFolder = await chrome.bookmarks.create({
    parentId: bookmarkBar.id,
    title: 'Organized'
  });

  // Recursively create folders and move bookmarks
  await createFolderStructure(result.folders, organizedFolder.id);
}

async function createFolderStructure(folders, parentId) {
  for (const [folderName, content] of Object.entries(folders)) {
    if (folderName.startsWith('_')) continue; // Skip special keys

    const folder = await chrome.bookmarks.create({
      parentId,
      title: folderName
    });

    if (Array.isArray(content)) {
      // Move bookmarks to this folder
      for (const bookmarkId of content) {
        const bookmark = bookmarkData.find(b => b.id === bookmarkId);
        if (bookmark) {
          await chrome.bookmarks.move(bookmark.chromeId, { parentId: folder.id });
        }
      }
    } else {
      // Nested folders
      await createFolderStructure(content, folder.id);
    }
  }
}

function showPreview(result) {
  const preview = document.getElementById('preview');
  const content = document.getElementById('previewContent');

  let html = '';

  if (result.analysis) {
    html += `<strong>Analysis:</strong><br>`;
    html += `Hobbies: ${result.analysis.hobbies?.join(', ') || 'N/A'}<br>`;
    html += `Career: ${result.analysis.career || 'N/A'}<br>`;
    if (result.analysis.lifeEvents?.length) {
      html += `Life Events: ${result.analysis.lifeEvents.join(', ')}<br>`;
    }
    html += `<br>`;
  }

  if (result._suggestions?.length) {
    html += `<strong>Suggestions:</strong><br>`;
    result._suggestions.forEach(s => html += `• ${s}<br>`);
    html += `<br>`;
  }

  html += `<strong>New Structure:</strong><br>`;
  html += formatFolders(result.folders);

  if (result._remove?.length) {
    html += `<br><strong>To Remove:</strong> ${result._remove.length} items`;
  }

  content.innerHTML = html;
  preview.classList.remove('hidden');
}

function formatFolders(folders, indent = 0) {
  let html = '';
  for (const [name, content] of Object.entries(folders)) {
    if (name.startsWith('_')) continue;

    const prefix = '&nbsp;'.repeat(indent * 2);

    if (Array.isArray(content)) {
      html += `${prefix}📁 ${name} (${content.length})<br>`;
    } else {
      html += `${prefix}📁 ${name}<br>`;
      html += formatFolders(content, indent + 1);
    }
  }
  return html;
}

function hidePreview() {
  document.getElementById('preview').classList.add('hidden');
}

function showMainSection() {
  document.getElementById('loginSection').classList.add('hidden');
  document.getElementById('mainSection').classList.remove('hidden');
}

function showLoginSection() {
  document.getElementById('loginSection').classList.remove('hidden');
  document.getElementById('mainSection').classList.add('hidden');
}

function showStatus(msg, type) {
  const status = document.getElementById('status');
  status.textContent = msg;
  status.className = `status ${type}`;
  status.classList.remove('hidden');
}

function hideStatus() {
  document.getElementById('status').classList.add('hidden');
}

async function getStoredApiKey() {
  const result = await chrome.storage.local.get('apiKey');
  return result.apiKey;
}
