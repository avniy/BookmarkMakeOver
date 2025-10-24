const API_URL = window.location.origin;
const CREDIT_PRICE = 0.018;
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

// Get user data from PHP-rendered page
const USER_DATA = {
    apiKey: window.USER_API_KEY || null,
    credits: parseInt(document.getElementById('creditsDisplay')?.textContent.replace(/,/g, '') || '0')
};

// State management
let state = {
    currentStep: 1,
    bookmarks: [],
    bookmarkTree: null,
    options: {},
    aiResults: null,
    versions: [],
    extensionReady: false,
    comparisonBuilt: false,
    userActions: {} // Track user decisions per bookmark: {bookmarkId: {action: 'keep'|'remove'|'rename', newName: '...'}}
};

// Extension communication
function sendMessageToExtension(action, data = null) {
    return new Promise((resolve, reject) => {
        const listener = (event) => {
            if (event.origin !== window.location.origin) return;
            const response = event.data;
            if (response.action === `${action}_RESPONSE`) {
                window.removeEventListener('message', listener);
                resolve(response.data);
            } else if (response.action === `${action}_ERROR`) {
                window.removeEventListener('message', listener);
                reject(new Error(response.error));
            }
        };
        window.addEventListener('message', listener);
        window.postMessage({ action, data }, '*');
        setTimeout(() => {
            window.removeEventListener('message', listener);
            reject(new Error('Extension timeout'));
        }, 10000);
    });
}

// Wait for extension
window.addEventListener('message', (event) => {
    if (event.data.action === 'EXTENSION_READY') {
        state.extensionReady = true;
        console.log('✅ Extension ready');
    }
});

window.postMessage({ action: 'PAGE_READY' }, '*');

// Navigation
function goToStep(stepNum) {
    // Hide all steps
    document.querySelectorAll('.step').forEach(step => {
        step.classList.remove('active');
    });

    // Show target step
    document.getElementById(`step${stepNum}`).classList.add('active');

    // Update progress bar
    const progress = ((stepNum - 1) / 4) * 100;
    document.getElementById('progressFill').style.width = `${progress}%`;

    // Update step indicators
    for (let i = 1; i <= 5; i++) {
        const indicator = document.getElementById(`stepIndicator${i}`);
        const circle = indicator.querySelector('div');
        const text = indicator.querySelector('span');

        if (i < stepNum) {
            circle.className = 'w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center font-bold';
            circle.textContent = '✓';
            indicator.classList.remove('opacity-50');
            text.className = 'font-semibold text-green-700';
        } else if (i === stepNum) {
            circle.className = 'w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold';
            circle.textContent = i;
            indicator.classList.remove('opacity-50');
            text.className = 'font-semibold text-gray-700';
        } else {
            circle.className = 'w-10 h-10 rounded-full bg-gray-300 text-white flex items-center justify-center font-bold';
            circle.textContent = i;
            indicator.classList.add('opacity-50');
            text.className = 'font-semibold text-gray-500';
        }
    }

    state.currentStep = stepNum;

    // Handle step-specific logic
    if (stepNum === 3) {
        // Only start AI analysis if we don't have results yet
        if (!state.aiResults) {
            startAIAnalysis();
        }
    } else if (stepNum === 5) {
        showApplyReady();
    }
}

// Step 1: Load Bookmarks
document.getElementById('loadBtn').addEventListener('click', async () => {
    const btn = document.getElementById('loadBtn');
    btn.disabled = true;
    document.getElementById('loadingState').classList.remove('hidden');

    try {
        const tree = await sendMessageToExtension('GET_BOOKMARKS');
        state.bookmarkTree = tree;

        // Detect current bookmark bar order preference
        detectBookmarkBarOrder(tree);

        state.bookmarks = flattenBookmarks(tree);

        const count = state.bookmarks.length;
        const creditsNeeded = count;
        const cost = (creditsNeeded * CREDIT_PRICE).toFixed(2);

        document.getElementById('bookmarkCount').textContent = count;
        document.getElementById('creditsNeeded').textContent = creditsNeeded;
        document.getElementById('costEstimate').textContent = cost;

        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('loadedState').classList.remove('hidden');

        setTimeout(() => goToStep(2), 1500);
    } catch (err) {
        alert('Failed to load bookmarks. Please refresh and try again.');
        btn.disabled = false;
        document.getElementById('loadingState').classList.add('hidden');
    }
});

function detectBookmarkBarOrder(tree) {
    // Find bookmark bar in tree
    let bookmarkBar = null;
    if (tree && tree.length > 0 && tree[0].children) {
        bookmarkBar = tree[0].children.find(node =>
            node.title === 'Bookmarks bar' ||
            node.title === 'Bookmarks Bar' ||
            node.title === 'Bookmark bar' ||
            node.title === 'Bookmark Bar' ||
            node.id === '1'
        );
    }

    if (!bookmarkBar || !bookmarkBar.children) {
        state.currentBookmarkBarOrder = 'icons-first'; // default
        return;
    }

    // Analyze first few items to detect pattern
    const firstItems = bookmarkBar.children.slice(0, 10);
    let firstIconOnlyIndex = -1;
    let firstFolderIndex = -1;

    firstItems.forEach((item, index) => {
        const isFolder = item.children && item.children.length > 0;
        const isIconOnly = item.url && (!item.title || item.title.trim() === '');

        if (isIconOnly && firstIconOnlyIndex === -1) {
            firstIconOnlyIndex = index;
        }
        if (isFolder && firstFolderIndex === -1) {
            firstFolderIndex = index;
        }
    });

    // Determine order preference
    if (firstIconOnlyIndex !== -1 && firstFolderIndex !== -1) {
        if (firstIconOnlyIndex < firstFolderIndex) {
            state.currentBookmarkBarOrder = 'icons-first';
        } else {
            state.currentBookmarkBarOrder = 'folders-first';
        }
    } else if (firstIconOnlyIndex !== -1) {
        state.currentBookmarkBarOrder = 'icons-first';
    } else if (firstFolderIndex !== -1) {
        state.currentBookmarkBarOrder = 'folders-first';
    } else {
        state.currentBookmarkBarOrder = 'icons-first'; // default
    }

    console.log('Detected bookmark bar order:', state.currentBookmarkBarOrder);
}

function flattenBookmarks(tree, list = []) {
    tree.forEach(node => {
        if (node.url) {
            const hasTitle = node.title && node.title.trim().length > 0;
            list.push({
                id: list.length + 1,
                chromeId: node.id,
                title: hasTitle ? node.title : 'Untitled',
                url: node.url,
                domain: new URL(node.url).hostname.replace('www.', ''),
                dateAdded: node.dateAdded,
                isIconOnly: !hasTitle // Track if this was originally icon-only
            });
        }
        if (node.children) {
            flattenBookmarks(node.children, list);
        }
    });
    return list;
}

// Step 2: Options
document.getElementById('backToStep1').addEventListener('click', () => goToStep(1));
document.getElementById('continueToStep3').addEventListener('click', () => {
    // Collect all options from radio buttons and inputs
    state.options = {
        // Language
        outputLanguage: document.getElementById('outputLanguage').value,

        // Organization Structure
        structureStyle: document.querySelector('input[name="structureStyle"]:checked')?.value || 'smart',
        structurePreferences: document.getElementById('structureInput')?.value || '',

        // Folder Display
        folderDisplay: document.querySelector('input[name="folderDisplay"]:checked')?.value || 'emoji',

        // Sorting
        sorting: document.querySelector('input[name="sorting"]:checked')?.value || 'smart',

        // Bookmark Bar Style (Advanced)
        bookmarkBarStyle: document.querySelector('input[name="bookmarkBarStyle"]:checked')?.value || 'full',
        keepIconOnlyAtRoot: document.getElementById('keepIconOnlyAtRoot')?.checked || false,
        bookmarkBarPreferences: document.getElementById('bookmarkBarInput')?.value || '',

        // Duplicate Handling (Advanced)
        duplicates: document.querySelector('input[name="duplicates"]:checked')?.value || 'remove',

        // Bookmark Naming (Advanced)
        naming: document.querySelector('input[name="naming"]:checked')?.value || 'renameUntitled',

        // Shorten Names (Advanced)
        shortenNames: document.querySelector('input[name="shortenNames"]:checked')?.value || 'off',
        shortenWordLimit: document.querySelector('input[name="shortenNames"]:checked')?.value === 'on' ?
                         (document.getElementById('shortenWordLimit')?.value || '5') : null,

        // Old Bookmarks (Advanced)
        oldBookmarks: document.querySelector('input[name="oldBookmarks"]:checked')?.value || 'keep',
        oldBookmarksYears: document.querySelector('input[name="oldBookmarks"]:checked')?.value === 'archive' ?
                          (document.getElementById('oldBookmarksYears')?.value || '2') : null,

        // Sensitive Content (Advanced)
        sensitiveContent: document.querySelector('input[name="sensitiveContent"]:checked')?.value || 'normal',
        hideDepth: document.querySelector('input[name="sensitiveContent"]:checked')?.value === 'hide' ?
                   (document.getElementById('hideDepth')?.value || 'deep') : null
    };

    console.log('Selected options:', state.options);

    goToStep(3);
});

// Live preview of folder depth
const hideDepthElement = document.getElementById('hideDepth');
if (hideDepthElement) {
    hideDepthElement.addEventListener('change', updateDepthPreview);
}

function updateDepthPreview() {
    const depthSelect = document.getElementById('hideDepth');
    const depthPreview = document.getElementById('depthPreview');

    if (!depthSelect || !depthPreview) return;

    const depth = depthSelect.value;
    const examples = {
        deep: '"Personal" → Your bookmarks',
        deeper: '"Personal" → "Files" → "Documents" → Your bookmarks',
        deepest: '"Personal" → "Files" → "Documents" → "Archive" → "Old" → Your bookmarks',
        mariana: '"Personal" → "Files" → "Documents" → "Archive" → "Old" → "Misc" → "Data" → "Storage" → "Hidden" → "Private" → Your bookmarks'
    };

    depthPreview.innerHTML = `💡 Example: ${examples[depth]}`;
}

// Note: Advanced options toggle is handled in wizard.php inline script
// Old checkbox-based event listeners removed - now using radio buttons

// Step 3: AI Analysis
async function startAIAnalysis() {
    document.getElementById('analysisLoading').classList.remove('hidden');
    document.getElementById('analysisResults').classList.add('hidden');

    try {
        // Add detected order preference to options
        const optionsWithOrder = {
            ...state.options,
            currentBookmarkBarOrder: state.currentBookmarkBarOrder || 'icons-first'
        };

        const res = await fetch(`${API_URL}/api`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'organize',
                apiKey: USER_DATA.apiKey,
                bookmarks: state.bookmarks,
                options: optionsWithOrder
            })
        });

        const text = await res.text();
        console.log('API Response:', text);

        const data = JSON.parse(text);

        if (data.success) {
            state.aiResults = data.result;
            state.versions.push({
                timestamp: Date.now(),
                result: data.result,
                prompt: 'Initial analysis'
            });

            // Update credits
            if (data.creditsRemaining !== undefined) {
                document.getElementById('creditsDisplay').textContent = data.creditsRemaining.toLocaleString();
            }

            // Display token usage and pricing
            if (data.tokenUsage && data.pricing) {
                displayTokenUsage(data.tokenUsage, data.pricing);
            }

            displayAIResults(data.result);

            document.getElementById('analysisLoading').classList.add('hidden');
            document.getElementById('analysisResults').classList.remove('hidden');
        } else {
            throw new Error(data.error || 'Analysis failed');
        }
    } catch (err) {
        alert('AI analysis failed: ' + err.message);
        goToStep(2);
    }
}

function displayTokenUsage(tokenUsage, pricing) {
    const card = document.getElementById('tokenUsageCard');
    if (!card) return;

    // Update token counts
    document.getElementById('inputTokens').textContent = tokenUsage.input_tokens.toLocaleString();
    document.getElementById('outputTokens').textContent = tokenUsage.output_tokens.toLocaleString();
    document.getElementById('cacheWriteTokens').textContent = tokenUsage.cache_creation_input_tokens.toLocaleString();
    document.getElementById('cacheReadTokens').textContent = tokenUsage.cache_read_input_tokens.toLocaleString();

    // Update costs (format to 6 decimal places for precision)
    document.getElementById('inputCost').textContent = pricing.input_cost.toFixed(6);
    document.getElementById('outputCost').textContent = pricing.output_cost.toFixed(6);
    document.getElementById('cacheWriteCost').textContent = pricing.cache_write_cost.toFixed(6);
    document.getElementById('cacheReadCost').textContent = pricing.cache_read_cost.toFixed(6);
    document.getElementById('totalCost').textContent = pricing.total_cost.toFixed(6);

    // Show the card
    card.classList.remove('hidden');

    console.log('Token usage displayed:', {
        tokens: tokenUsage,
        pricing: pricing
    });
}

function displayAIResults(result) {
    // Calculate statistics
    const stats = {
        total: state.bookmarks.length,
        removed: result._remove?.length || 0,
        duplicates: result._duplicates?.length || 0,
        sensitive: result._sensitive?.length || 0,
        renamed: Object.keys(result._renamed || {}).length,
        old: result._old?.length || 0
    };

    // Display stats summary only
    const insightsHtml = [];
    insightsHtml.push(`
        <div class="card bg-gradient-to-r from-green-50 to-emerald-100 border-green-200 mb-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">📊 Organization Summary</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600">${stats.total}</div>
                    <div class="text-sm text-gray-600">Total Bookmarks</div>
                </div>
                ${stats.removed > 0 ? `
                <div class="text-center">
                    <div class="text-3xl font-bold text-red-600">${stats.removed}</div>
                    <div class="text-sm text-gray-600">Will be Removed</div>
                </div>
                ` : ''}
                ${stats.duplicates > 0 ? `
                <div class="text-center">
                    <div class="text-3xl font-bold text-yellow-600">${stats.duplicates}</div>
                    <div class="text-sm text-gray-600">Duplicates Found</div>
                </div>
                ` : ''}
                ${stats.renamed > 0 ? `
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600">${stats.renamed}</div>
                    <div class="text-sm text-gray-600">Will be Renamed</div>
                </div>
                ` : ''}
                ${stats.sensitive > 0 ? `
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-600">${stats.sensitive}</div>
                    <div class="text-sm text-gray-600">Sensitive Items</div>
                </div>
                ` : ''}
                ${stats.old > 0 ? `
                <div class="text-center">
                    <div class="text-3xl font-bold text-orange-600">${stats.old}</div>
                    <div class="text-sm text-gray-600">Old Bookmarks</div>
                </div>
                ` : ''}
            </div>
        </div>
    `);

    document.getElementById('insightsSection').innerHTML = insightsHtml.join('');

    // Generate floating insight cards automatically
    generateInsightCards(result);

    // Display tree
    displayTree(result.folders);

    // Build comparison view immediately
    buildComparisonView();
    state.comparisonBuilt = true;

    // Show revert button if we have versions
    if (state.versions.length > 1) {
        document.getElementById('revertVersion').classList.remove('hidden');
    }
}

// Generate floating insight cards
function generateInsightCards(result) {
    const cards = [];

    // Card 1: Interests/Hobbies
    if (result.analysis?.hobbies?.length) {
        cards.push({
            type: 'interests',
            icon: '🎯',
            title: 'Your Interests',
            content: result.analysis.hobbies.join(', '),
            gradient: 'from-pink-400 to-rose-500'
        });
    }

    // Card 2: Career Analysis
    if (result.analysis?.career) {
        cards.push({
            type: 'career',
            icon: '💼',
            title: 'Career Profile',
            content: result.analysis.career,
            gradient: 'from-blue-400 to-indigo-500'
        });
    }

    // Card 3: Productivity Score (Brutal Honesty)
    if (result.analysis?.productivityScore) {
        const isBrutal = result.analysis.productivityScore.includes('/10') && parseInt(result.analysis.productivityScore) <= 5;
        cards.push({
            type: 'productivity',
            icon: isBrutal ? '🔥' : '📊',
            title: isBrutal ? 'Brutal Truth Alert' : 'Productivity Analysis',
            content: result.analysis.productivityScore,
            gradient: isBrutal ? 'from-orange-400 to-red-500' : 'from-green-400 to-emerald-500'
        });
    }

    // Card 4-N: Suggestions (top 3 most important)
    if (result._suggestions?.length) {
        result._suggestions.slice(0, 3).forEach((suggestion, index) => {
            cards.push({
                type: 'suggestion',
                icon: '💡',
                title: `Tip #${index + 1}`,
                content: suggestion,
                gradient: 'from-purple-400 to-indigo-500'
            });
        });
    }

    if (cards.length > 0) {
        showInsightDeck(cards);
    }
}

// Show the floating insight trigger and setup modal carousel
let insightCards = [];
let currentCardIndex = 0;
let insightListenersSetup = false;

function showInsightDeck(cards) {
    insightCards = cards;
    currentCardIndex = 0;

    const trigger = document.getElementById('insightsTrigger');
    const badge = document.getElementById('insightBadge');
    const modal = document.getElementById('insightsModal');

    if (!trigger || !badge || !modal) {
        console.error('Insights elements not found');
        return;
    }

    // Update badge count
    badge.textContent = cards.length;

    // Show trigger with animation
    trigger.classList.remove('hidden');

    // Setup dots
    setupInsightsDots();

    // Render first card
    renderInsightCard(0);

    // Setup event listeners only once
    if (!insightListenersSetup) {
        // Trigger click handler
        trigger.addEventListener('click', () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });

        // Close button handler
        document.getElementById('closeInsights').addEventListener('click', () => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        });

        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }
        });

        // Navigation buttons
        document.getElementById('prevCard').addEventListener('click', () => {
            if (currentCardIndex > 0) {
                currentCardIndex--;
                renderInsightCard(currentCardIndex);
                updateNavigationButtons();
                updateDots();
            }
        });

        document.getElementById('nextCard').addEventListener('click', () => {
            if (currentCardIndex < insightCards.length - 1) {
                currentCardIndex++;
                renderInsightCard(currentCardIndex);
                updateNavigationButtons();
                updateDots();
            }
        });

        insightListenersSetup = true;
    }

    updateNavigationButtons();
}

function setupInsightsDots() {
    const dotsContainer = document.getElementById('insightsDots');
    dotsContainer.innerHTML = '';

    insightCards.forEach((card, index) => {
        const dot = document.createElement('button');
        // Mini card style instead of dots
        dot.className = 'w-8 h-10 rounded-md shadow-md transition-all transform hover:scale-110';
        dot.style.background = `linear-gradient(to bottom right, ${getGradientColors(card.gradient).from}, ${getGradientColors(card.gradient).to})`;
        dot.style.opacity = index === 0 ? '1' : '0.4';
        dot.style.transform = index === 0 ? 'rotate(0deg) scale(1.2)' : `rotate(${(index % 3 - 1) * 8}deg)`;

        dot.addEventListener('click', () => {
            currentCardIndex = index;
            renderInsightCard(index);
            updateNavigationButtons();
            updateDots();
        });
        dotsContainer.appendChild(dot);
    });
}

function getGradientColors(gradientClass) {
    const gradients = {
        'from-pink-400 to-rose-500': { from: '#f472b6', to: '#f43f5e' },
        'from-blue-400 to-indigo-500': { from: '#60a5fa', to: '#6366f1' },
        'from-orange-400 to-red-500': { from: '#fb923c', to: '#ef4444' },
        'from-green-400 to-emerald-500': { from: '#4ade80', to: '#10b981' },
        'from-purple-400 to-indigo-500': { from: '#c084fc', to: '#6366f1' }
    };
    return gradients[gradientClass] || { from: '#6366f1', to: '#8b5cf6' };
}

function updateDots() {
    const dots = document.getElementById('insightsDots').children;
    Array.from(dots).forEach((dot, index) => {
        if (index === currentCardIndex) {
            dot.style.opacity = '1';
            dot.style.transform = 'rotate(0deg) scale(1.2)';
        } else {
            dot.style.opacity = '0.4';
            dot.style.transform = `rotate(${(index % 3 - 1) * 8}deg) scale(1)`;
        }
    });
}

function getCardColor(type) {
    const colors = {
        'interests': '#ec4899', // pink
        'career': '#3b82f6', // blue
        'productivity': '#f97316', // orange
        'suggestion': '#8b5cf6' // purple
    };
    return colors[type] || '#6366f1'; // default indigo
}

function renderInsightCard(index) {
    const carousel = document.getElementById('insightsCarousel');
    carousel.innerHTML = '';

    // Render all cards in a piled style
    insightCards.forEach((card, i) => {
        const distance = Math.abs(i - index);
        const isCurrent = i === index;

        // Calculate rotation and position based on distance from current card
        let rotation = 0;
        let zIndex = 0;
        let opacity = 0.3;
        let translateY = 0;
        let translateX = 0;
        let scale = 0.85;

        if (isCurrent) {
            rotation = 0;
            zIndex = 30;
            opacity = 1;
            scale = 1;
        } else if (i < index) {
            // Cards behind (left)
            rotation = -12 - (distance * 4);
            zIndex = 30 - distance;
            translateX = -(distance * 30);
            translateY = distance * 10;
            opacity = Math.max(0.2, 0.7 - (distance * 0.2));
        } else {
            // Cards ahead (right)
            rotation = 12 + (distance * 4);
            zIndex = 30 - distance;
            translateX = distance * 30;
            translateY = distance * 10;
            opacity = Math.max(0.2, 0.7 - (distance * 0.2));
        }

        const cardEl = document.createElement('div');
        cardEl.className = 'absolute transition-all duration-500 ease-out';
        cardEl.style.width = '400px';
        cardEl.style.height = '500px';
        cardEl.style.transform = `translate(${translateX}px, ${translateY}px) rotate(${rotation}deg) scale(${scale})`;
        cardEl.style.zIndex = zIndex;
        cardEl.style.opacity = opacity;
        cardEl.style.pointerEvents = isCurrent ? 'auto' : 'none';

        cardEl.innerHTML = `
            <div class="w-full h-full bg-gradient-to-br ${card.gradient} rounded-2xl shadow-2xl p-8 flex flex-col items-center justify-center text-white transform-gpu">
                <div class="text-7xl mb-6">${card.icon}</div>
                <h3 class="text-3xl font-bold mb-4 text-center">${card.title}</h3>
                <p class="text-lg leading-relaxed text-center opacity-95">${card.content}</p>
            </div>
        `;

        carousel.appendChild(cardEl);
    });
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevCard');
    const nextBtn = document.getElementById('nextCard');

    prevBtn.disabled = currentCardIndex === 0;
    nextBtn.disabled = currentCardIndex === insightCards.length - 1;
}

function displayTree(folders, bookmarks = state.bookmarks) {
    let treeHtml = '';

    console.log('displayTree called:', {
        keepIconOnlyAtRoot: state.options.keepIconOnlyAtRoot,
        totalBookmarks: bookmarks.length,
        iconOnlyCount: bookmarks.filter(b => b.isIconOnly).length
    });

    // Build bookmark bar demo
    buildBookmarkBarDemo(folders, bookmarks);

    // Build integrated tree with icon-only bookmarks at top level
    treeHtml += buildTreeHTML(folders, bookmarks, 0, true);

    document.getElementById('treePreview').innerHTML = treeHtml;

    // Add click handlers for tree items
    document.querySelectorAll('.tree-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            const children = item.nextElementSibling;
            if (children && children.classList.contains('tree-children')) {
                children.classList.toggle('open');
                const toggle = item.querySelector('.tree-toggle');
                if (toggle) {
                    toggle.classList.toggle('open');
                }
            }
        });
    });
}

function buildBookmarkBarDemo(folders, bookmarks) {
    const demoContainer = document.getElementById('bookmarkBarDemo');
    const overflowMenu = document.getElementById('overflowMenu');

    if (!demoContainer) return;

    demoContainer.innerHTML = '';
    overflowMenu.innerHTML = '';

    const MAX_VISIBLE_ITEMS = 8; // Approximate items that fit before overflow
    let itemCount = 0;
    let visibleItems = [];
    let overflowItems = [];

    // Determine order based on user's current preference
    const iconsFirst = state.currentBookmarkBarOrder === 'icons-first';

    // Collect icon-only bookmarks
    const iconOnlyBookmarks = (state.options.keepIconOnlyAtRoot) ? bookmarks.filter(b => b.isIconOnly) : [];

    // Collect folders
    const folderItems = [];
    for (const [folderName, content] of Object.entries(folders)) {
        if (folderName.startsWith('_')) continue;
        folderItems.push({ type: 'folder', folderName, content });
    }

    // Add items in correct order based on user preference
    if (iconsFirst) {
        // Icons first, then folders
        iconOnlyBookmarks.forEach(bookmark => {
            const item = { type: 'bookmark', bookmark };
            if (itemCount < MAX_VISIBLE_ITEMS) {
                visibleItems.push(item);
            } else {
                overflowItems.push(item);
            }
            itemCount++;
        });

        folderItems.forEach(item => {
            if (itemCount < MAX_VISIBLE_ITEMS) {
                visibleItems.push(item);
            } else {
                overflowItems.push(item);
            }
            itemCount++;
        });
    } else {
        // Folders first, then icons
        folderItems.forEach(item => {
            if (itemCount < MAX_VISIBLE_ITEMS) {
                visibleItems.push(item);
            } else {
                overflowItems.push(item);
            }
            itemCount++;
        });

        iconOnlyBookmarks.forEach(bookmark => {
            const item = { type: 'bookmark', bookmark };
            if (itemCount < MAX_VISIBLE_ITEMS) {
                visibleItems.push(item);
            } else {
                overflowItems.push(item);
            }
            itemCount++;
        });
    }

    // Render visible items
    visibleItems.forEach(item => {
        if (item.type === 'bookmark') {
            demoContainer.appendChild(createBookmarkBarItem(item.bookmark));
        } else {
            demoContainer.appendChild(createBookmarkBarFolder(item.folderName, item.content, bookmarks));
        }
    });

    // Add overflow button if needed
    if (overflowItems.length > 0) {
        const overflowBtn = document.createElement('button');
        overflowBtn.className = 'px-2 py-1 text-gray-600 hover:bg-gray-100 rounded text-sm font-bold';
        overflowBtn.textContent = '>>';
        overflowBtn.title = `${overflowItems.length} more items`;

        overflowBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            overflowMenu.classList.toggle('hidden');
        });

        demoContainer.appendChild(overflowBtn);

        // Populate overflow menu
        overflowItems.forEach(item => {
            if (item.type === 'bookmark') {
                overflowMenu.appendChild(createOverflowBookmarkItem(item.bookmark));
            } else {
                overflowMenu.appendChild(createOverflowFolderItem(item.folderName, item.content, bookmarks));
            }
        });
    }

    // Close overflow menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!overflowMenu.contains(e.target) && !e.target.closest('#bookmarkBarDemo')) {
            overflowMenu.classList.add('hidden');
        }
    });
}

function createBookmarkBarItem(bookmark) {
    const item = document.createElement('div');
    const isDark = document.documentElement.classList.contains('dark');

    // Icon-only bookmarks: just show favicon, no text
    if (bookmark.isIconOnly) {
        item.className = `flex items-center px-1 py-0.5 ${isDark ? 'hover:bg-gray-700' : 'hover:bg-gray-200'} rounded cursor-pointer`;
        const favicon = `https://www.google.com/s2/favicons?domain=${bookmark.domain}&sz=16`;
        item.innerHTML = `
            <img src="${favicon}" class="w-4 h-4" title="${escapeHtml(bookmark.domain)}" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">
        `;
    } else {
        // Regular bookmarks: show favicon + text (smaller font like Chrome)
        item.className = `flex items-center gap-1 px-1.5 py-0.5 ${isDark ? 'hover:bg-gray-700' : 'hover:bg-gray-200'} rounded cursor-pointer whitespace-nowrap`;
        const favicon = `https://www.google.com/s2/favicons?domain=${bookmark.domain}&sz=16`;
        item.innerHTML = `
            <img src="${favicon}" class="w-4 h-4" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">
            <span class="${isDark ? 'text-gray-200' : 'text-gray-800'} whitespace-nowrap" style="font-size: 11px; line-height: 20px;">${escapeHtml(bookmark.title || bookmark.domain)}</span>
        `;
    }

    return item;
}

function createBookmarkBarFolder(folderName, content, bookmarks) {
    const folder = document.createElement('div');
    folder.className = 'relative bookmark-bar-folder';
    const isDark = document.documentElement.classList.contains('dark');

    const button = document.createElement('button');
    button.className = `flex items-center gap-1 px-1.5 py-0.5 ${isDark ? 'hover:bg-gray-700 text-gray-200' : 'hover:bg-gray-200 text-gray-800'} rounded font-medium whitespace-nowrap`;
    button.style.fontSize = '11px';
    button.style.lineHeight = '20px';
    button.innerHTML = `📁 ${escapeHtml(folderName)} ▾`;

    const dropdown = document.createElement('div');
    dropdown.className = `hidden absolute top-full left-0 mt-1 ${isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} rounded-lg shadow-xl border py-2 z-50`;
    dropdown.style.minWidth = '200px';
    dropdown.style.maxHeight = '400px';
    dropdown.style.overflowY = 'auto';

    // Build dropdown content
    buildFolderDropdown(dropdown, content, bookmarks);

    button.addEventListener('mouseenter', () => {
        dropdown.classList.remove('hidden');
    });

    folder.addEventListener('mouseleave', () => {
        dropdown.classList.add('hidden');
    });

    folder.appendChild(button);
    folder.appendChild(dropdown);

    return folder;
}

function buildFolderDropdown(dropdown, content, bookmarks) {
    const isDark = document.documentElement.classList.contains('dark');

    if (Array.isArray(content)) {
        // Leaf folder - show bookmarks
        content.forEach(bookmarkId => {
            const bookmark = bookmarks.find(b => b.id === bookmarkId);
            if (bookmark) {
                const item = document.createElement('div');
                item.className = `px-3 py-2 ${isDark ? 'hover:bg-gray-700' : 'hover:bg-gray-100'} cursor-pointer flex items-center gap-2 whitespace-nowrap`;

                const favicon = `https://www.google.com/s2/favicons?domain=${bookmark.domain}&sz=16`;
                const title = state.aiResults?._renamed?.[bookmarkId] || bookmark.title;

                item.innerHTML = `
                    <img src="${favicon}" class="w-4 h-4" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">
                    <span class="${isDark ? 'text-gray-200' : 'text-gray-800'}" style="font-size: 11px;">${escapeHtml(title)}</span>
                `;

                dropdown.appendChild(item);
            }
        });
    } else {
        // Nested folders
        for (const [subFolderName, subContent] of Object.entries(content)) {
            if (subFolderName.startsWith('_')) continue;

            const item = document.createElement('div');
            item.className = `px-3 py-2 ${isDark ? 'hover:bg-gray-700 text-gray-200' : 'hover:bg-gray-100 text-gray-800'} cursor-pointer flex items-center gap-2 font-medium relative subfolder-item whitespace-nowrap`;
            item.style.fontSize = '11px';
            item.innerHTML = `📁 ${escapeHtml(subFolderName)} ▸`;

            const subDropdown = document.createElement('div');
            subDropdown.className = `hidden absolute left-full top-0 ml-1 ${isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} rounded-lg shadow-xl border py-2 z-50`;
            subDropdown.style.minWidth = '200px';
            subDropdown.style.maxHeight = '400px';
            subDropdown.style.overflowY = 'auto';

            buildFolderDropdown(subDropdown, subContent, bookmarks);

            item.addEventListener('mouseenter', () => {
                subDropdown.classList.remove('hidden');
            });

            item.addEventListener('mouseleave', () => {
                setTimeout(() => {
                    if (!subDropdown.matches(':hover')) {
                        subDropdown.classList.add('hidden');
                    }
                }, 100);
            });

            item.appendChild(subDropdown);
            dropdown.appendChild(item);
        }
    }
}

function createOverflowBookmarkItem(bookmark) {
    const item = document.createElement('div');
    const favicon = `https://www.google.com/s2/favicons?domain=${bookmark.domain}&sz=16`;
    const isDark = document.documentElement.classList.contains('dark');

    // Icon-only bookmarks: just show favicon in overflow too
    if (bookmark.isIconOnly) {
        item.className = `px-3 py-2 ${isDark ? 'hover:bg-gray-700' : 'hover:bg-gray-100'} cursor-pointer flex items-center justify-center`;
        item.innerHTML = `
            <img src="${favicon}" class="w-4 h-4" title="${escapeHtml(bookmark.domain)}" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">
        `;
    } else {
        item.className = `px-3 py-2 ${isDark ? 'hover:bg-gray-700' : 'hover:bg-gray-100'} cursor-pointer flex items-center gap-2 whitespace-nowrap`;
        item.innerHTML = `
            <img src="${favicon}" class="w-4 h-4" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">
            <span class="${isDark ? 'text-gray-200' : 'text-gray-800'}" style="font-size: 11px;">${escapeHtml(bookmark.title || bookmark.domain)}</span>
        `;
    }

    return item;
}

function createOverflowFolderItem(folderName, content, bookmarks) {
    const item = document.createElement('div');
    const isDark = document.documentElement.classList.contains('dark');
    item.className = `px-3 py-2 ${isDark ? 'hover:bg-gray-700 text-gray-200' : 'hover:bg-gray-100 text-gray-800'} cursor-pointer font-medium relative subfolder-item whitespace-nowrap`;
    item.style.fontSize = '11px';
    item.innerHTML = `📁 ${escapeHtml(folderName)} ▸`;

    const subDropdown = document.createElement('div');
    subDropdown.className = `hidden absolute left-full top-0 ml-1 ${isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} rounded-lg shadow-xl border py-2 z-50`;
    subDropdown.style.minWidth = '200px';
    subDropdown.style.maxHeight = '400px';
    subDropdown.style.overflowY = 'auto';

    buildFolderDropdown(subDropdown, content, bookmarks);

    item.addEventListener('mouseenter', () => {
        subDropdown.classList.remove('hidden');
    });

    item.addEventListener('mouseleave', () => {
        setTimeout(() => {
            if (!subDropdown.matches(':hover')) {
                subDropdown.classList.add('hidden');
            }
        }, 100);
    });

    item.appendChild(subDropdown);

    return item;
}

function buildTreeHTML(folders, bookmarks, level = 0, isTopLevel = false) {
    let html = '';
    const indent = level * 8; // Reduced from 20 to 8 for less padding

    // Get lists of special items from AI results
    const sensitiveIds = state.aiResults?._sensitive || [];
    const duplicateIds = state.aiResults?._duplicates || [];
    const removeIds = state.aiResults?._remove || [];
    const renamedItems = state.aiResults?._renamed || {};
    const oldIds = state.aiResults?._old || [];

    // Show icon-only bookmarks first at top level if keepIconOnlyAtRoot is enabled
    if (isTopLevel && state.options.keepIconOnlyAtRoot) {
        const iconOnlyBookmarks = bookmarks.filter(b => b.isIconOnly);
        console.log('Displaying icon-only bookmarks at top level:', iconOnlyBookmarks);

        iconOnlyBookmarks.forEach(bookmark => {
            const favicon = `https://www.google.com/s2/favicons?domain=${bookmark.domain}&sz=16`;
            html += `
                <div class="tree-item p-2 rounded bg-yellow-50 border border-yellow-200 mb-1">
                    <div class="flex items-center gap-2">
                        <img src="${favicon}" class="favicon" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">
                        <span class="text-gray-500 text-sm flex-1">${bookmark.domain}</span>
                        <span class="text-xs bg-yellow-200 text-yellow-800 px-2 py-1 rounded">📌 Icon-Only</span>
                    </div>
                </div>
            `;
        });
    }

    for (const [folderName, content] of Object.entries(folders)) {
        if (folderName.startsWith('_')) continue;

        html += `<div style="margin-left: ${indent}px;">`;

        // Check if this is a sensitive folder
        const isSensitiveFolder = folderName.toLowerCase().includes('personal') ||
                                 folderName.toLowerCase().includes('private') ||
                                 folderName.toLowerCase().includes('archive');

        if (Array.isArray(content)) {
            // Leaf folder with bookmark IDs - show collapsed with counter
            const folderBadges = [];
            if (isSensitiveFolder) {
                folderBadges.push('<span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded ml-2" title="Contains sensitive content">🔒 Private</span>');
            }

            // Count special items in this folder
            let sensitiveCount = 0;
            let duplicateCount = 0;
            let removeCount = 0;
            let oldCount = 0;
            let renameCount = 0;

            content.forEach(bookmarkId => {
                if (sensitiveIds.includes(bookmarkId)) sensitiveCount++;
                if (duplicateIds.includes(bookmarkId)) duplicateCount++;
                if (removeIds.includes(bookmarkId)) removeCount++;
                if (oldIds.includes(bookmarkId)) oldCount++;
                if (renamedItems[bookmarkId]) renameCount++;
            });

            // Build summary badges
            const summaryBadges = [];
            if (sensitiveCount > 0) summaryBadges.push(`<span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded" title="${sensitiveCount} sensitive item(s)">🔞 ${sensitiveCount}</span>`);
            if (removeCount > 0) summaryBadges.push(`<span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded" title="${removeCount} will be removed">🗑️ ${removeCount}</span>`);
            if (duplicateCount > 0) summaryBadges.push(`<span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded" title="${duplicateCount} duplicate(s)">📋 ${duplicateCount}</span>`);
            if (renameCount > 0) summaryBadges.push(`<span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded" title="${renameCount} will be renamed">✏️ ${renameCount}</span>`);
            if (oldCount > 0) summaryBadges.push(`<span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded" title="${oldCount} old bookmark(s)">📆 ${oldCount}</span>`);

            html += `
                <div class="tree-item cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <span class="tree-toggle">▶</span>
                    📁 <strong>${folderName}</strong>
                    <span class="text-blue-600 font-semibold text-sm ml-2">(${content.length} items)</span>
                    ${folderBadges.join('')}
                    ${summaryBadges.length > 0 ? '<div class="ml-6 mt-1 flex gap-1 flex-wrap">' + summaryBadges.join(' ') + '</div>' : ''}
                </div>
                <div class="tree-children">
            `;

            // Show individual bookmarks when expanded
            content.forEach(bookmarkId => {
                const bookmark = bookmarks.find(b => b.id === bookmarkId);
                if (bookmark) {
                    const favicon = `https://www.google.com/s2/favicons?domain=${bookmark.domain}&sz=16`;

                    // Build badges for this bookmark
                    const badges = [];
                    const isSensitive = sensitiveIds.includes(bookmarkId);
                    const isDuplicate = duplicateIds.includes(bookmarkId);
                    const willBeRemoved = removeIds.includes(bookmarkId);
                    const isOld = oldIds.includes(bookmarkId);
                    const isRenamed = renamedItems[bookmarkId];

                    if (isSensitive) {
                        badges.push('<span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded" title="Adult/Sensitive content">🔞</span>');
                    }
                    if (willBeRemoved) {
                        badges.push('<span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded line-through" title="Will be removed">🗑️</span>');
                    }
                    if (isDuplicate && !willBeRemoved) {
                        badges.push('<span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded" title="Duplicate (extras will be removed)">📋</span>');
                    }
                    if (isOld) {
                        badges.push('<span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded" title="Old bookmark (2+ years)">📆</span>');
                    }
                    if (isRenamed) {
                        badges.push(`<span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded" title="Will be renamed from: ${escapeHtml(bookmark.title)}">✏️ New name</span>`);
                    }

                    const titleToShow = isRenamed ? renamedItems[bookmarkId] : bookmark.title;
                    const itemClasses = willBeRemoved ? 'opacity-50 line-through' : '';

                    html += `
                        <div class="tree-item pl-3 text-sm flex items-start gap-2 ${itemClasses} py-1">
                            <img src="${favicon}" class="favicon mt-1" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-gray-700 font-medium">${escapeHtml(titleToShow)}</span>
                                    ${badges.join(' ')}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5 truncate" title="${escapeHtml(bookmark.url)}">${escapeHtml(bookmark.url)}</div>
                            </div>
                        </div>
                    `;
                }
            });

            html += `</div>`;
        } else {
            // Nested folder - count total items recursively
            const totalItems = countItemsInFolder(content);

            const nestedBadges = [];
            if (isSensitiveFolder) {
                nestedBadges.push('<span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded ml-2" title="Contains sensitive content">🔒 Private</span>');
            }

            html += `
                <div class="tree-item cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <span class="tree-toggle">▶</span>
                    📁 <strong>${folderName}</strong>
                    <span class="text-blue-600 font-semibold text-sm ml-2">(${totalItems} items)</span>
                    ${nestedBadges.join('')}
                </div>
                <div class="tree-children">
            `;
            html += buildTreeHTML(content, bookmarks, level + 1);
            html += `</div>`;
        }

        html += `</div>`;
    }

    return html;
}

// Count total items in a folder recursively
function countItemsInFolder(folders) {
    let count = 0;

    for (const [key, value] of Object.entries(folders)) {
        if (key.startsWith('_')) continue; // Skip special keys

        if (Array.isArray(value)) {
            // Leaf folder with bookmarks
            count += value.length;
        } else {
            // Nested folder
            count += countItemsInFolder(value);
        }
    }

    return count;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Expand/Collapse all
document.getElementById('expandAll').addEventListener('click', () => {
    document.querySelectorAll('.tree-children').forEach(el => el.classList.add('open'));
    document.querySelectorAll('.tree-toggle').forEach(el => el.classList.add('open'));
});

document.getElementById('collapseAll').addEventListener('click', () => {
    document.querySelectorAll('.tree-children').forEach(el => el.classList.remove('open'));
    document.querySelectorAll('.tree-toggle').forEach(el => el.classList.remove('open'));
});

document.getElementById('backToStep2').addEventListener('click', () => goToStep(2));
document.getElementById('continueToStep4').addEventListener('click', () => goToStep(4));

// View toggle buttons
document.getElementById('viewTree').addEventListener('click', () => {
    document.getElementById('treeViewContainer').classList.remove('hidden');
    document.getElementById('comparisonViewContainer').classList.add('hidden');
    document.getElementById('viewTree').className = 'btn bg-indigo-600 text-white hover:bg-indigo-700';
    document.getElementById('viewComparison').className = 'btn bg-white border-2 border-indigo-300 text-indigo-700 hover:bg-indigo-50';
});

document.getElementById('viewComparison').addEventListener('click', () => {
    document.getElementById('treeViewContainer').classList.add('hidden');
    document.getElementById('comparisonViewContainer').classList.remove('hidden');
    document.getElementById('viewComparison').className = 'btn bg-indigo-600 text-white hover:bg-indigo-700';
    document.getElementById('viewTree').className = 'btn bg-white border-2 border-indigo-300 text-indigo-700 hover:bg-indigo-50';

    // Build comparison view if not already built
    if (!state.comparisonBuilt) {
        buildComparisonView();
        state.comparisonBuilt = true;
    }
});

// Build comparison view
function buildComparisonView() {
    const container = document.getElementById('comparisonContent');

    if (!container) {
        console.error('comparisonContent element not found');
        return;
    }

    const result = state.aiResults;
    if (!result) {
        console.error('No AI results available');
        container.innerHTML = '<div class="p-8 text-center text-gray-500">No AI results available</div>';
        return;
    }

    if (!state.bookmarks || state.bookmarks.length === 0) {
        console.error('No bookmarks in state');
        container.innerHTML = '<div class="p-8 text-center text-gray-500">No bookmarks to display</div>';
        return;
    }

    console.log('Building comparison view for', state.bookmarks.length, 'bookmarks');
    console.log('AI Results:', result);

    const removeIds = result._remove || [];
    const renamedItems = result._renamed || {};
    const sensitiveIds = result._sensitive || [];
    const duplicateIds = result._duplicates || [];

    let html = '<div class="p-4">';

    // Group bookmarks by their fate
    state.bookmarks.forEach(bookmark => {
        const willBeRemoved = removeIds.includes(bookmark.id);
        const isRenamed = renamedItems[bookmark.id];
        const isSensitive = sensitiveIds.includes(bookmark.id);
        const isDuplicate = duplicateIds.includes(bookmark.id);

        // Find where this bookmark will be placed
        const newLocation = findBookmarkLocation(bookmark.id, result.folders);

        // Build the comparison row
        html += `<div class="grid grid-cols-2 gap-4 mb-3 border-b border-gray-200 pb-3" data-bookmark-id="${bookmark.id}">`;

        // BEFORE (Current)
        html += `<div class="bg-white p-3 rounded-lg border-2 border-gray-200">`;
        html += `<div class="flex items-center gap-2 mb-2">`;
        const favicon = `https://www.google.com/s2/favicons?domain=${bookmark.domain}&sz=16`;
        html += `<img src="${favicon}" class="favicon" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">`;
        html += `<span class="text-sm font-semibold text-gray-900">${escapeHtml(bookmark.title)}</span>`;
        html += `</div>`;
        html += `<div class="text-xs text-gray-500">${bookmark.domain}</div>`;
        html += `<div class="text-xs text-gray-400 mt-1">📂 Current Location: Root</div>`;
        html += `</div>`;

        // AFTER (AI Suggestion)
        html += `<div class="bg-white p-3 rounded-lg border-2 ${willBeRemoved ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50'}">`;

        if (willBeRemoved) {
            html += `<div class="flex items-center gap-2 mb-2">`;
            html += `<span class="text-red-700 font-bold">🗑️ REMOVE</span>`;
            if (isDuplicate) {
                html += `<span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">Duplicate</span>`;
            }
            html += `</div>`;
            html += `<div class="text-xs text-red-600">AI suggests removing this bookmark</div>`;
        } else {
            html += `<div class="flex items-center gap-2 mb-2">`;
            const newTitle = isRenamed ? renamedItems[bookmark.id] : bookmark.title;
            html += `<img src="${favicon}" class="favicon" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><text y=%2212%22 font-size=%2212%22>📄</text></svg>'">`;
            html += `<span class="text-sm font-semibold text-gray-900">${escapeHtml(newTitle)}</span>`;
            if (isRenamed) {
                html += `<span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Renamed</span>`;
            }
            if (isSensitive) {
                html += `<span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded">🔞</span>`;
            }
            html += `</div>`;
            html += `<div class="text-xs text-gray-500">${bookmark.domain}</div>`;
            html += `<div class="text-xs text-green-700 mt-1">📂 New Location: ${newLocation}</div>`;
        }

        html += `</div>`;

        html += `</div>`;

        // Action buttons row
        html += `<div class="grid grid-cols-2 gap-4 mb-4">`;
        html += `<div></div>`; // Empty left column
        html += `<div class="flex gap-2">`;

        if (willBeRemoved) {
            html += `<button class="action-btn keep-btn text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700" data-id="${bookmark.id}">✓ Keep</button>`;
            html += `<button class="action-btn remove-btn text-xs bg-gray-300 text-gray-600 px-3 py-1 rounded-lg" data-id="${bookmark.id}" disabled>✗ Remove (AI)</button>`;
        } else {
            html += `<button class="action-btn keep-btn text-xs bg-gray-300 text-gray-600 px-3 py-1 rounded-lg" data-id="${bookmark.id}" disabled>✓ Keep (AI)</button>`;
            html += `<button class="action-btn remove-btn text-xs bg-red-600 text-white px-3 py-1 rounded-lg hover:bg-red-700" data-id="${bookmark.id}">✗ Remove</button>`;
        }

        if (isRenamed) {
            html += `<button class="action-btn revert-name-btn text-xs bg-yellow-600 text-white px-3 py-1 rounded-lg hover:bg-yellow-700" data-id="${bookmark.id}" data-original="${escapeHtml(bookmark.title)}">↶ Original Name</button>`;
        } else {
            html += `<button class="action-btn rename-btn text-xs bg-blue-600 text-white px-3 py-1 rounded-lg hover:bg-blue-700" data-id="${bookmark.id}">✏️ Custom Name</button>`;
        }

        html += `</div>`;
        html += `</div>`;
    });

    html += '</div>';

    container.innerHTML = html;

    // Add event listeners to action buttons
    attachComparisonEventListeners();
}

function findBookmarkLocation(bookmarkId, folders, path = '') {
    for (const [folderName, content] of Object.entries(folders)) {
        if (folderName.startsWith('_')) continue;

        const currentPath = path ? `${path} → ${folderName}` : folderName;

        if (Array.isArray(content)) {
            if (content.includes(bookmarkId)) {
                return currentPath;
            }
        } else {
            const found = findBookmarkLocation(bookmarkId, content, currentPath);
            if (found) return found;
        }
    }
    return null;
}

function attachComparisonEventListeners() {
    // Keep buttons
    document.querySelectorAll('.keep-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.id);
            state.userActions[id] = { action: 'keep' };
            // Update UI - make this button look selected
            updateButtonStates(id, 'keep');
        });
    });

    // Remove buttons
    document.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.id);
            state.userActions[id] = { action: 'remove' };
            updateButtonStates(id, 'remove');
        });
    });

    // Rename buttons
    document.querySelectorAll('.rename-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.id);
            const bookmark = state.bookmarks.find(b => b.id === id);
            const newName = prompt('Enter new name for bookmark:', bookmark.title);
            if (newName && newName !== bookmark.title) {
                state.userActions[id] = { action: 'rename', newName };
                // Update UI
                const row = document.querySelector(`[data-bookmark-id="${id}"]`);
                const afterTitle = row.querySelectorAll('.font-semibold')[1];
                if (afterTitle) {
                    afterTitle.textContent = newName;
                }
            }
        });
    });

    // Revert name buttons
    document.querySelectorAll('.revert-name-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.id);
            const originalName = btn.dataset.original;
            state.userActions[id] = { action: 'revertName', originalName };
            // Update UI
            const row = document.querySelector(`[data-bookmark-id="${id}"]`);
            const afterTitle = row.querySelectorAll('.font-semibold')[1];
            if (afterTitle) {
                afterTitle.textContent = originalName;
            }
        });
    });
}

function updateButtonStates(bookmarkId, action) {
    const row = document.querySelector(`[data-bookmark-id="${bookmarkId}"]`);
    if (!row) return;

    const keepBtn = row.querySelector('.keep-btn');
    const removeBtn = row.querySelector('.remove-btn');

    if (action === 'keep') {
        keepBtn.className = 'action-btn keep-btn text-xs bg-gray-300 text-gray-600 px-3 py-1 rounded-lg';
        keepBtn.disabled = true;
        removeBtn.className = 'action-btn remove-btn text-xs bg-red-600 text-white px-3 py-1 rounded-lg hover:bg-red-700';
        removeBtn.disabled = false;
    } else if (action === 'remove') {
        removeBtn.className = 'action-btn remove-btn text-xs bg-gray-300 text-gray-600 px-3 py-1 rounded-lg';
        removeBtn.disabled = true;
        keepBtn.className = 'action-btn keep-btn text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700';
        keepBtn.disabled = false;
    }
}

// Accept/Reject all changes
document.getElementById('acceptAllChanges').addEventListener('click', () => {
    // Accept all AI suggestions
    state.userActions = {};
    alert('All AI suggestions accepted!');
});

document.getElementById('rejectAllChanges').addEventListener('click', () => {
    // Reject all changes - keep everything as is
    state.bookmarks.forEach(bookmark => {
        state.userActions[bookmark.id] = { action: 'keep' };
    });
    alert('All changes rejected - bookmarks will stay as they are.');
});

// Revert to previous version
document.getElementById('revertVersion').addEventListener('click', () => {
    if (state.versions.length > 1) {
        state.versions.pop(); // Remove current
        const previousVersion = state.versions[state.versions.length - 1];
        state.aiResults = previousVersion.result;
        displayAIResults(previousVersion.result);

        if (state.versions.length === 1) {
            document.getElementById('revertVersion').classList.add('hidden');
        }
    }
});

// Step 4: Customize
document.getElementById('backToStep3').addEventListener('click', () => goToStep(3));
document.getElementById('skipToStep5').addEventListener('click', () => goToStep(5));

document.getElementById('refineWithPrompt').addEventListener('click', async () => {
    const customPrompt = document.getElementById('customPrompt').value.trim();
    if (!customPrompt) {
        alert('Please enter some instructions for the AI');
        return;
    }

    const btn = document.getElementById('refineWithPrompt');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner mx-auto" style="width: 20px; height: 20px;"></div> Refining...';

    try {
        const res = await fetch(`${API_URL}/api`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'organize',
                apiKey: USER_DATA.apiKey,
                bookmarks: state.bookmarks,
                options: {
                    ...state.options,
                    customPrompt: customPrompt
                }
            })
        });

        const data = await res.json();

        if (data.success) {
            state.aiResults = data.result;
            state.versions.push({
                timestamp: Date.now(),
                result: data.result,
                prompt: customPrompt
            });

            // Update credits
            if (data.creditsRemaining !== undefined) {
                document.getElementById('creditsDisplay').textContent = data.creditsRemaining.toLocaleString();
            }

            // Show version history
            updateVersionHistory();

            // Go back to step 3 to show new results
            goToStep(3);
        } else {
            throw new Error(data.error || 'Refinement failed');
        }
    } catch (err) {
        alert('Failed to refine: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '🔄 Refine with AI';
    }
});

function updateVersionHistory() {
    const historyHtml = state.versions.map((version, index) => {
        const date = new Date(version.timestamp).toLocaleTimeString();
        return `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                    <div class="font-semibold text-gray-900">Version ${index + 1}</div>
                    <div class="text-sm text-gray-600">${version.prompt} - ${date}</div>
                </div>
                <button class="restore-version bg-blue-100 text-blue-700 px-3 py-1 rounded-lg hover:bg-blue-200 text-sm" data-index="${index}">
                    Restore
                </button>
            </div>
        `;
    }).join('');

    document.getElementById('versionList').innerHTML = historyHtml;
    document.getElementById('versionHistory').classList.remove('hidden');

    // Add restore handlers
    document.querySelectorAll('.restore-version').forEach(btn => {
        btn.addEventListener('click', () => {
            const index = parseInt(btn.dataset.index);
            state.aiResults = state.versions[index].result;
            goToStep(3);
        });
    });
}

// Step 5: Apply
document.getElementById('backToStep4').addEventListener('click', () => goToStep(4));

function showApplyReady() {
    document.getElementById('applyReady').classList.remove('hidden');
    document.getElementById('applyProgress').classList.add('hidden');
    document.getElementById('applyComplete').classList.add('hidden');
    document.getElementById('applyError').classList.add('hidden');
}

document.getElementById('downloadBackup').addEventListener('click', async () => {
    const btn = document.getElementById('downloadBackup');
    btn.disabled = true;
    btn.textContent = '📥 Creating backup...';

    try {
        await sendMessageToExtension('DOWNLOAD_BACKUP');
        btn.textContent = '✅ Backup saved!';
        setTimeout(() => {
            btn.disabled = false;
            btn.textContent = '📥 Download Backup Now';
        }, 2000);
    } catch (err) {
        alert('Backup failed: ' + err.message);
        btn.disabled = false;
        btn.textContent = '📥 Download Backup Now';
    }
});

document.getElementById('applyChanges').addEventListener('click', applyChangesToBookmarks);
document.getElementById('retryApply').addEventListener('click', applyChangesToBookmarks);

async function applyChangesToBookmarks() {
    document.getElementById('applyReady').classList.add('hidden');
    document.getElementById('applyProgress').classList.remove('hidden');
    document.getElementById('applyError').classList.add('hidden');

    try {
        const result = state.aiResults;
        if (!result || !result.folders) {
            throw new Error('No organization data available');
        }

        // Step 1: Get bookmark bar
        document.getElementById('applyStatus').textContent = 'Finding bookmark bar...';
        const tree = await sendMessageToExtension('GET_BOOKMARKS');

        console.log('Bookmark tree structure:', tree);
        console.log('Looking for bookmark bar...');

        // Chrome bookmarks.getTree() returns [{ id: "0", children: [...] }]
        // We need to look inside the root node's children
        let bookmarkBar = null;

        if (tree && tree.length > 0 && tree[0].children) {
            // Look for bookmark bar in root children
            bookmarkBar = tree[0].children.find(node =>
                node.title === 'Bookmarks bar' ||
                node.title === 'Bookmarks Bar' ||
                node.title === 'Bookmark bar' ||
                node.title === 'Bookmark Bar' ||
                node.id === '1' // Chrome typically uses ID "1" for bookmark bar
            );
        }

        if (!bookmarkBar) {
            const availableNodes = tree[0]?.children?.map(n => `"${n.title}" (id: ${n.id})`).join(', ') || 'none';
            console.error('Available bookmark nodes:', availableNodes);
            console.error('Full tree:', JSON.stringify(tree, null, 2));
            throw new Error('Could not find bookmark bar. Available nodes: ' + availableNodes);
        }

        console.log('Found bookmark bar:', bookmarkBar);

        // Step 2: COMPLETELY CLEAR bookmark bar
        document.getElementById('applyStatus').textContent = 'Clearing bookmark bar...';
        await clearBookmarkBar(bookmarkBar);

        // Step 3: Create new structure
        document.getElementById('applyStatus').textContent = 'Creating new folders...';
        await createFolderStructure(bookmarkBar.id, result.folders, state.bookmarks);

        // Step 4: Remove items marked for deletion
        if (result._remove?.length) {
            document.getElementById('applyStatus').textContent = `Removing ${result._remove.length} items...`;
            for (const bookmarkId of result._remove) {
                const bookmark = state.bookmarks.find(b => b.id === bookmarkId);
                if (bookmark?.chromeId) {
                    try {
                        await sendMessageToExtension('REMOVE_BOOKMARK', { id: bookmark.chromeId });
                    } catch (err) {
                        console.warn('Failed to remove bookmark:', bookmark.chromeId, err);
                    }
                }
            }
        }

        // Done!
        document.getElementById('applyProgress').classList.add('hidden');
        document.getElementById('applyComplete').classList.remove('hidden');

    } catch (err) {
        console.error('Apply failed:', err);
        document.getElementById('applyProgress').classList.add('hidden');
        document.getElementById('applyError').classList.remove('hidden');
        document.getElementById('errorMessage').textContent = err.message;
    }
}

async function createFolderStructure(parentId, folders, bookmarks) {
    for (const [folderName, content] of Object.entries(folders)) {
        if (folderName.startsWith('_')) continue;

        // Create folder
        const folder = await sendMessageToExtension('CREATE_FOLDER', {
            parentId: parentId,
            title: folderName
        });

        if (Array.isArray(content)) {
            // Add bookmarks to this folder
            for (const bookmarkId of content) {
                const bookmark = bookmarks.find(b => b.id === bookmarkId);
                if (bookmark) {
                    try {
                        // Move existing bookmark
                        if (bookmark.chromeId) {
                            await sendMessageToExtension('MOVE_BOOKMARK', {
                                id: bookmark.chromeId,
                                destination: { parentId: folder.id }
                            });
                        }
                    } catch (err) {
                        console.warn('Failed to move bookmark:', bookmark, err);
                    }
                }
            }
        } else {
            // Recursively create subfolders
            await createFolderStructure(folder.id, content, bookmarks);
        }
    }
}

async function clearBookmarkBar(bookmarkBar) {
    console.log('Clearing bookmark bar completely...', bookmarkBar);

    if (!bookmarkBar.children || bookmarkBar.children.length === 0) {
        console.log('Bookmark bar already empty');
        return;
    }

    // Remove all children (folders and bookmarks)
    for (const child of bookmarkBar.children) {
        try {
            if (child.children) {
                // It's a folder - use removeTree to delete it and all contents
                await sendMessageToExtension('REMOVE_TREE', { id: child.id });
                console.log('Removed folder:', child.title);
            } else {
                // It's a bookmark - remove it
                await sendMessageToExtension('REMOVE_BOOKMARK', { id: child.id });
                console.log('Removed bookmark:', child.title);
            }
        } catch (err) {
            console.warn('Failed to remove item:', child.title, err);
        }
    }

    console.log('Bookmark bar cleared successfully');
}

// Restore from backup button
document.getElementById('restoreFromBackup').addEventListener('click', async () => {
    if (!confirm('This will restore your bookmarks from the last backup file you downloaded. Make sure you have the backup file ready. Continue?')) {
        return;
    }

    // Create file input
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';

    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        try {
            const text = await file.text();
            const backupData = JSON.parse(text);

            document.getElementById('applyComplete').classList.add('hidden');
            document.getElementById('applyProgress').classList.remove('hidden');
            document.getElementById('applyStatus').textContent = 'Restoring from backup...';

            // Get current bookmark bar
            const tree = await sendMessageToExtension('GET_BOOKMARKS');
            let bookmarkBar = null;

            if (tree && tree.length > 0 && tree[0].children) {
                bookmarkBar = tree[0].children.find(node =>
                    node.title === 'Bookmarks bar' ||
                    node.title === 'Bookmarks Bar' ||
                    node.title === 'Bookmark bar' ||
                    node.title === 'Bookmark Bar' ||
                    node.id === '1'
                );
            }

            if (!bookmarkBar) {
                throw new Error('Could not find bookmark bar');
            }

            // Clear current bookmark bar
            document.getElementById('applyStatus').textContent = 'Clearing current bookmarks...';
            await clearBookmarkBar(bookmarkBar);

            // Restore from backup
            document.getElementById('applyStatus').textContent = 'Restoring bookmarks...';
            const backupBookmarkBar = backupData[0]?.children?.find(n => n.id === '1' || n.title?.includes('Bookmark'));

            if (backupBookmarkBar && backupBookmarkBar.children) {
                await restoreBookmarkTree(bookmarkBar.id, backupBookmarkBar.children);
            }

            document.getElementById('applyProgress').classList.add('hidden');
            alert('✅ Bookmarks restored successfully!');
            window.close();
        } catch (err) {
            document.getElementById('applyProgress').classList.add('hidden');
            document.getElementById('applyComplete').classList.remove('hidden');
            alert('Failed to restore: ' + err.message);
        }
    };

    input.click();
});

async function restoreBookmarkTree(parentId, items) {
    for (const item of items) {
        try {
            if (item.children) {
                // It's a folder
                const folder = await sendMessageToExtension('CREATE_FOLDER', {
                    parentId: parentId,
                    title: item.title || 'Untitled Folder'
                });

                // Recursively restore children
                await restoreBookmarkTree(folder.id, item.children);
            } else if (item.url) {
                // It's a bookmark
                await sendMessageToExtension('CREATE_BOOKMARK', {
                    parentId: parentId,
                    title: item.title || '',
                    url: item.url
                });
            }
        } catch (err) {
            console.warn('Failed to restore item:', item.title, err);
        }
    }
}
