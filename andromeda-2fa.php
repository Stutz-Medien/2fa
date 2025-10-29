<?php
/**
 *  * Andromeda Two‑Factor Authentication
 *
 * @package       stutzmedien/2fa
 * @author        Stutz Medien AG
 *
 * @wordpress-plugin
 * Plugin Name:   Andromeda Two‑Factor Authentication
 * Plugin URI:    https://github.com/Stutz-Medien/2fa
 * Description:   Adds Two Factor Authentication (2FA) to your WordPress login process to enhance security.
 * Version:       26.0.0
 * Author:        Stutz Medien AG
 * Author URI:    https://stutz-medien.ch/
 * Text Domain:   andromeda-2fa
 * Domain Path:   /lang
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$autoload_path = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload_path ) ) {
	require_once $autoload_path;
}

require_once __DIR__ . '/inc/class-plugin.php';

add_action(
	'plugins_loaded',
	function () {
		new \Andromeda\TwoFactorAuth\Plugin();
	}
);
