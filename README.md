# 📚 Sorted AI

> **AI-powered bookmark organizer** - Chrome extension + web wizard

Transform years of bookmark chaos into perfectly organized folders in seconds. No more "Unsorted Bookmarks" with 1,000+ items.

---

## ✨ Features

- 🧠 **AI Psychology** - Organizes by your hobbies, career, and life events (not alphabetically)
- 🗑️ **Smart Cleanup** - Auto-removes duplicates, 404s, and dead links
- ⚡ **One-Click Restore** - Safe backup & instant revert anytime
- 📁 **Perfect Folders** - Creates nested structures that actually make sense
- 🔥 **Brutal Honesty** - Get a productivity score and real insights
- ✏️ **Smart Renaming** - Renames "Untitled" bookmarks automatically
- 🎯 **Life Events** - Detects weddings, moves, career changes from bookmarks
- 🔄 **Monthly Reminders** - Lifetime users get nudges to re-organize
- 🌍 **Multi-Language** - Organize in any language
- ⏮️ **Version History** - Time-travel your bookmarks, restore previous states
- 📌 **Icon-Only Respect** - Preserves Chrome icon-only bookmarks
- 🎨 **6 Organization Styles** - Smart, Flat, Deep, By Type, By Domain, By Year

---

## 🔐 Privacy First

- ✅ **Only sends bookmark titles and domains** (e.g., "github.com", not full URLs)
- ✅ **No browsing history tracking**
- ✅ **Zero data storage** - we don't keep your bookmarks
- ✅ **No third parties** - never sold, shared, or analyzed

---

## 💰 Pricing

- **$9/year** - Perfect for trying it out
- **$29 lifetime** - One payment, years of organization (30 analyses/month)

No credits system. No surprises.

---

## 🚀 Quick Start

### 1. Install Extension

1. Download from Chrome Web Store *(coming soon)*
2. Or load unpacked from \`extension/\` folder

### 2. Set Up Backend

\`\`\`bash
# Clone the repo
git clone https://github.com/avniy/Sorted AI.git
cd Sorted AI

# Configure environment
cp backend/.env.example backend/.env
# Edit backend/.env with your Claude API key

# Run locally (PHP 7.4+)
cd backend
php -S localhost:8000
\`\`\`

### 3. Open Wizard

1. Click extension icon
2. Enter email + API key
3. Follow the 5-step wizard

---

## 📂 Project Structure

\`\`\`
Sorted AI/
├── extension/           # Chrome extension
│   ├── manifest.json   # Extension config
│   ├── popup.html      # Login popup
│   ├── popup.js        # Auth logic
│   ├── background.js   # Bookmark API bridge
│   └── content.js      # Page <-> extension bridge
│
├── backend/            # PHP backend
│   ├── wizard.php      # Main 5-step wizard UI
│   ├── wizard.js       # Frontend logic
│   ├── api.php         # REST endpoints
│   ├── claude.php      # Claude AI integration
│   ├── organize.php    # Organization logic
│   ├── session.php     # Session management
│   └── .env.example    # Config template
│
└── README.md           # You are here
\`\`\`

---

## 🎨 Extension Design

- **Teal/Cyan + Coral** color scheme
- **Dark theme** with glass-morphism effects
- **420x480px popup** with improved fonts
- **Production-only** URLs (localhost removed)

---

Made with ❤️ by [avniy](https://github.com/avniy) | 🤖 Generated with [Claude Code](https://claude.com/claude-code)
