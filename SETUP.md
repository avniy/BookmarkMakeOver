# Quick Setup Guide

## 1. Create Database

```bash
# Open MySQL (adjust path for your system)
C:\wamp64\bin\mysql\mysql8.3.0\bin\mysql.exe -u root -p

# Or if WAMP/XAMPP is running:
mysql -u root -p

# Then:
source C:\Users\avniy\OneDrive\Desktop\Code\Sorted AI\backend\db.sql
```

## 2. Configure Backend

The `backend/config.php` is already configured with:
- Database: `localhost/bookmark_makeover`
- User: `root`
- Password: `` (empty)
- Claude API Key: Already set from your CLAUDE.md

If you need different DB credentials, edit `backend/config.php`.

## 3. Place Backend in Web Server

If using WAMP:
```bash
# Copy backend folder to:
C:\wamp64\www\Sorted AI\backend\

# Access at:
http://localhost/Sorted AI/backend/
```

## 4. Update Extension API URL

Edit `extension/popup.js` line 1:
```javascript
const API_URL = 'http://localhost/Sorted AI/backend';
```

Change to your actual backend URL if different.

## 5. Create Extension Icons (Temporary)

For now, create simple placeholder images or use any PNG files:
- `extension/icons/icon16.png` (16x16)
- `extension/icons/icon48.png` (48x48)
- `extension/icons/icon128.png` (128x128)

Or download a bookmark icon from the web and resize it.

## 6. Load Extension in Chrome

1. Open Chrome
2. Go to `chrome://extensions`
3. Enable "Developer mode" (top right)
4. Click "Load unpacked"
5. Select: `C:\Users\avniy\OneDrive\Desktop\Code\Sorted AI\extension`

## 7. Register & Test

1. Go to `http://localhost/Sorted AI/backend/register.php`
2. Create account (you get 100 free credits)
3. Copy your API key
4. Open Chrome extension (click icon in toolbar)
5. Paste API key
6. Choose options
7. Click "Organize Bookmarks"

## Troubleshooting

**CORS Errors:**
- Make sure `backend/config.php` is loaded (it sets CORS headers)

**Database Connection Failed:**
- Check MySQL is running
- Verify credentials in `config.php`
- Ensure database `bookmark_makeover` exists

**Extension Not Loading:**
- Check for JavaScript errors in Chrome DevTools
- Ensure `manifest.json` is valid
- Verify icon files exist (or comment out icon paths temporarily)

**Claude API Errors:**
- Verify API key in `backend/config.php`
- Check Claude API quota/limits

## Next Steps

1. Add extension icons (use Figma, Canva, or AI generation)
2. Test with real bookmarks
3. Adjust Claude prompt in `backend/claude.php` if needed
4. Add payment integration (Stripe) for credit purchases
5. Deploy to production server
6. Publish extension to Chrome Web Store

## Security Notes

Before production:
- Change database password
- Use environment variables for API keys
- Add rate limiting
- Implement CSRF protection
- Use HTTPS only
- Add proper session management
