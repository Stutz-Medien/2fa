<?php
/**
 * PHPUnit bootstrap file
 *
 * @package stutzmedien/2fa
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', '/tmp/wordpress/' );

require_once dirname( __DIR__ ) . '/inc/helpers.php';
require_once dirname( __DIR__ ) . '/inc/class-totp-manager.php';
require_once dirname( __DIR__ ) . '/inc/class-qr-code-generator.php';
require_once dirname( __DIR__ ) . '/inc/class-recovery-manager.php';
require_once dirname( __DIR__ ) . '/inc/class-user-settings.php';
require_once dirname( __DIR__ ) . '/inc/class-login-handler.php';
