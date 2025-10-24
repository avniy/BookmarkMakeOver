# BookmarkMakeOver

AI-powered Chrome bookmark organization with brutal honesty and psychological intelligence.

## Features

- **Icon-Only Bookmark Bar** - Clean, minimal aesthetic
- **Hide Sensitive Content** - 4 levels: Deep, Deeper, Deepest, Mariana Trench
- **Brutal Honesty Mode** - AI tells you the truth about your bookmark hoarding
- **Hobby Detection** - Analyzes your interests from bookmarks
- **Life Event Detection** - Identifies weddings, moving, career changes, etc.
- **Dead Link Removal** - Detects and removes 404s
- **Credit System** - 1 credit per bookmark, credits never expire

## Tech Stack

**Extension:**
- Vanilla JavaScript
- Chrome Bookmarks API

**Backend:**
- PHP 8+
- MySQL
- jQuery
- Tailwind CSS
- Claude API (Sonnet 4.5)

## Setup

### 1. Database

```bash
# Import database schema
mysql -u root -p < backend/db.sql
```

### 2. Backend Configuration

Edit `backend/config.php`:
- Set your database credentials
- Claude API key is already configured

### 3. Install Extension

1. Open `chrome://extensions`
2. Enable "Developer mode"
3. Click "Load unpacked"
4. Select the `extension` folder

### 4. Run Backend

```bash
# If using XAMPP/WAMP, place backend folder in htdocs
# Access at: http://localhost/BookmarkMakeOver/backend/
```

## Usage

1. Register at `http://localhost/BookmarkMakeOver/backend/register.php`
2. Get 100 free credits
3. Copy your API key
4. Open Chrome extension and paste API key
5. Choose organization options
6. Click "Organize Bookmarks"
7. Preview and apply changes

## Credit Pricing

- 1 credit = 1 bookmark
- 100 free credits on signup
- Credits never expire
- Additional credits: $5/100, $20/500, $35/1000

## Privacy

- Bookmarks sent to Claude API for organization only
- Not stored on our servers
- Session-based authentication
- API keys stored securely

## File Structure

```
BookmarkMakeOver/
├── extension/
│   ├── manifest.json
│   ├── popup.html
│   ├── popup.css
│   ├── popup.js
│   ├── background.js
│   └── icons/
├── backend/
│   ├── config.php
│   ├── db.sql
│   ├── auth.php
│   ├── credits.php
│   ├── claude.php
│   ├── api.php
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   └── app.php
└── README.md
```

## API Endpoints

**POST /auth.php**
- `action: register` - Create account
- `action: login` - Login
- `action: validate` - Validate API key

**GET /api.php?action=user&apiKey=XXX**
- Get user info and credits

**POST /api.php**
- `action: organize` - Organize bookmarks
- Payload: `{apiKey, bookmarks, options}`

## Development

Built with love, PHP, and brutal honesty.

## License

MIT
