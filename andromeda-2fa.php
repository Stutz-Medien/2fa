<?php
/**
 *  * Andromeda Two‑Factor Authentication
 *
 * @package       stutzmedien/2fa
 * @author        Stutz Medien AG
 * @license       GPL-2.0-or-later
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

define( 'ANDROMEDA_2FA_PLUGIN_FILE', __FILE__ );
define( 'ANDROMEDA_2FA_PLUGIN_DIR', plugin_dir_path( ANDROMEDA_2FA_PLUGIN_FILE ) );
define( 'ANDROMEDA_2FA_VERSION', '26.0.0' );

$autoload_path = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload_path ) ) {
	require_once $autoload_path;
}

require_once __DIR__ . '/inc/Plugin.php';

add_action(
	'plugins_loaded',
	function () {
		new \Andromeda\TwoFactorAuth\Plugin();
	}
);
