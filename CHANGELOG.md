# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0] - 2024

### 🎉 Major Features

#### Media Upload & Sending
- Added `file_url` column to `messages` table
- Upload form support for images, videos, and PDFs
- Media file validation (MIME type, file size max 10MB, extension)
- Integrated media sending via Fonnte API `url` parameter
- File preview in message edit form
- Option to delete existing media files

#### Security Enhancements
- XSS protection using `htmlspecialchars()` for all outputs
- SQL Injection prevention using PDO prepared statements
- WhatsApp number validation for Indonesian format (08xx, 628xx, +628xx)
- Email validation using `filter_var()`
- Upload folder security with `.htaccess` (prevents PHP execution)
- File upload validation:
  - MIME type checking using `finfo_file()`
  - File extension whitelist
  - File size limit (10MB default)
  - Unique filename generation

#### Helper Functions Library
- Created `admin/helpers.php` with reusable functions:
  - `sanitize_input()` - XSS protection
  - `validate_whatsapp_number()` - Phone validation
  - `normalize_phone()` - Format phone to 62xxx
  - `validate_file_upload()` - File upload validation
  - `upload_file()` - Handle file uploads
  - `delete_file()` - Clean up old files
  - `generate_pagination()` - Create pagination HTML
  - `format_bytes()` - Human-readable file size
  - `e()` - Escape output shorthand

#### Pagination System
- Added pagination to `subscribers.php` (20 items per page)
- Added pagination to `logs.php` (50 items per page)
- Custom pagination function with prev/next and page numbers
- Status filter preserved in pagination URLs
- Beautiful pagination UI with hover effects

#### Improved Logging
- Enhanced error logging in `message_logs` table
- Better error message capture from Fonnte API
- HTTP response code logging
- Response snippet logging for debugging

### 🎨 UI/UX Improvements

#### Form Enhancements
- Added file input with custom styling (dashed border)
- File type indicators (🖼️ 🎥 📄) in messages table
- Emoji icons in buttons and labels for better UX
- Hover effects on file inputs
- Helper text and tips for better guidance
- Preview of uploaded media in edit form

#### Table & Display
- Added "Media" column in messages table
- Better status badges with gradients
- Improved table hover effects
- Enhanced filter section styling
- Better responsive design

#### Navigation & Feedback
- Consistent navigation across all pages
- Better success/error message display
- Loading states and visual feedback
- Improved button styling with gradients

### 📁 New Files & Folders

```
uploads/
├── .htaccess          # Security rules
├── index.php          # Prevent directory listing
└── messages/          # Media files storage

admin/
└── helpers.php        # Helper functions library

migration_add_file_url.sql  # Database migration
CRON_SETUP.md              # Cron job documentation
CHANGELOG.md               # This file
.gitignore                 # Git ignore rules
```

### 🔧 Technical Improvements

#### Code Quality
- Separated validation logic into helpers
- Consistent error handling
- Better code organization
- Inline documentation
- DRY principle implementation

#### Database
- Added `file_url` column to messages table
- Maintained database integrity with constraints
- Optimized queries with proper indexes

#### API Integration
- Enhanced Fonnte API integration
- Support for media URL parameter
- Better response parsing
- Multiple status flag detection
- Improved error handling

### 🐛 Bug Fixes
- Fixed phone number normalization
- Fixed status detection for Fonnte API responses
- Improved SQL query binding for pagination
- Fixed file cleanup on upload errors

### 📝 Documentation
- Updated README.md with v2 features
- Added CRON_SETUP.md for automation guide
- Added inline comments in critical sections
- Migration guide for v1 to v2 upgrade
- Security best practices documented

### ⚙️ Configuration
- Added `BASE_URL` constant for CLI/cron support
- Better configuration comments
- Environment-specific settings guide

### 🔄 Breaking Changes
- Database schema update required (run migration)
- `admin/helpers.php` must be included where needed
- File upload requires writable `uploads/` directory

### 🚀 Deployment Notes
1. Run `migration_add_file_url.sql` on existing databases
2. Create and set permissions for `uploads/` folder:
   ```bash
   mkdir -p uploads/messages
   chmod 755 uploads
   ```
3. Update `BASE_URL` in `admin/db.php` for production
4. Verify `.htaccess` is working in uploads folder
5. Test file upload functionality
6. Setup cron job if needed (see CRON_SETUP.md)

---

## [1.0.0] - Initial Release

### Features
- Basic subscriber registration form
- Admin login and dashboard
- Message management (CRUD)
- Automated message sending based on delay
- Message logs with status tracking
- Manual trigger for message sending
- Fonnte API integration
- Basic security with session management
