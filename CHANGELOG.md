# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 26.0.1 (2026-02-23)

### Fixed

- Aligned 2FA plugin class filenames in `inc/` with PSR-4 expectations (e.g. `UserSettings.php`, `LoginHandler.php`, `Plugin.php`) so Composer no longer skips them during autoload generation.
- Updated all internal `require_once` references and test bootstrap includes to match the renamed class files.

## 26.0.0 (2026-01-27)

### Added

- Time-based one-time password (TOTP) authentication for WordPress logins.
- Per-user enable/disable controls in the user profile.
- QR code provisioning for authenticator apps.
- Recovery codes with one-time use, regeneration, and download/copy options.
- Login flow integration that challenges 2FA-enabled users.
- PHPUnit test suite for core 2FA components.
