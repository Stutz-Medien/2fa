# Andromeda Two‑Factor Authentication (2FA)

A lightweight WordPress plugin that adds Time‑based One‑Time Password (TOTP) two‑factor authentication to user accounts. Compatible with common authenticator apps like Google Authenticator, Authy, and 1Password.

## ✨ Features

- **TOTP Authentication** – Secure time-based one-time passwords
- **User Control** – Per-user enable/disable functionality  
- **Quick Setup** – QR code provisioning for easy configuration
- **WordPress Integration** – Seamless user profile integration
- **Tested** – Comprehensive PHPUnit test suite

## 📋 Requirements

- **PHP:** 8.3 or higher
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

## 🛠️ Development

### Project Structure

```
andromeda-2fa.php          # Plugin bootstrap
inc/                       # Core plugin classes
├── class-user-settings.php
├── class-totp-manager.php  
├── class-qr-code-generator.php
└── class-login-handler.php
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

### Manual Testing

```bash
vendor/bin/phpunit -v
```

**Bootstrap:** `tests/bootstrap.php`  
**Autoloading:** PSR-4 via composer for `inc/` directory

## ⚙️ Technical Details

### Data Storage

- **Secret Key:** `andromeda_2fa_secret` (user meta)
- **Status:** `andromeda_2fa_enabled` (user meta)
- **QR Codes:** Generated as data URIs (no file system writes)

### Dependencies

- Managed via `composer.json`
- PSR-4 autoloading for clean architecture

## 🔒 Security

**Found a security issue?** Please contact us privately at **<development@stutz-medien.ch>** instead of filing a public issue.

## 📄 License

MIT License. See `LICENSE` file for details.
