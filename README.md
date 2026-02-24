# Andromeda Two‑Factor Authentication (2FA)

A lightweight WordPress plugin that adds Time‑based One‑Time Password (TOTP) two‑factor authentication to user accounts. Compatible with common authenticator apps like Google Authenticator, Authy, and 1Password.

## ✨ Features

- **TOTP Authentication** – Secure time-based one-time passwords
- **Recovery Codes** – One-time fallback codes with regenerate and download options
- **User Control** – Per-user enable/disable functionality
- **Quick Setup** – QR code provisioning for easy configuration
- **Login Flow Integration** – 2FA challenge injected into wp-login
- **Tested** – Comprehensive PHPUnit test suite

## 📋 Requirements

- **PHP:** 8.4 or higher
- **WordPress:** 6.8 or higher  
- **Composer:** For dependency management

## 🚀 Quick Start

### Installation

```bash
composer require stutzmedien/2fa
```

### Activation

1. Navigate to **wp-admin → Plugins**
2. Find "Andromeda Two‑Factor Authentication"
3. Click **Activate**

### User Setup

1. Go to **Users → Your Profile**
2. Find the "Two‑Factor Authentication" section
3. **Scan the QR code** with your authenticator app
4. **Enter the 6-digit code** to verify setup
5. **Check "Enable 2FA"** and save your profile
6. **Store your recovery codes** in a safe place
7. Use **Generate Recovery Codes** when you run out

## 🛠️ Development

### Project Structure

```text
andromeda-2fa.php          # Plugin bootstrap
inc/                       # Core plugin classes
├── helpers.php
├── UserSettings.php
├── TotpManager.php  
├── QrCodeGenerator.php
├── RecoveryManager.php
├── LoginHandler.php
└── Plugin.php
src/                       # Admin/login assets
├── css/
└── js/
tests/                     # PHPUnit tests
└── Unit/                  # Test suites
```

### Available Scripts

| Command | Description |
|---------|-------------|
| `composer test` | Run test suite |
| `composer test:coverage` | Run tests with HTML coverage report |
| `composer lint` | Check code style |
| `composer lint:fix` | Auto-fix code style issues |

### Code Coverage

- Requires Xdebug installed and enabled.
- The coverage script sets `XDEBUG_MODE=coverage` automatically.
- After `composer test:coverage`, open the HTML report in the `coverage/` directory.

## ⚙️ Technical Details

### Login Flow

- A 2FA challenge is triggered after primary credential validation for users with 2FA enabled.
- The login form accepts either a 6-digit TOTP or a recovery code.
- Challenge state is tracked via a short-lived cookie (`andromeda_2fa_token`) and transient (`andromeda_2fa_auth_{token}`).

### Data Storage

- **Secret Key:** `andromeda_2fa_secret` (user meta)
- **Status:** `andromeda_2fa_enabled` (user meta)
- **Recovery Codes:** `andromeda_2fa_recovery_codes` (user meta, hashed)
- **QR Codes:** Generated as data URIs (no file system writes)
- **Recovery Codes Preview:** transient `andromeda_2fa_plain_codes_{user_id}` (shown once)

### Dependencies

- Managed via `composer.json`
- PSR-4 autoloading for clean architecture

## 🔒 Security

**Found a security issue?** Please contact us privately at **<development@stutz-medien.ch>** instead of filing a public issue.

## 📄 License

This project is licensed under the GNU General Public License v2.0 - see the [LICENSE](LICENSE) file for details.
