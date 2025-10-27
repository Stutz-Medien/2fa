<?php
/**
 * Plugin main class file.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 */

namespace Andromeda\TwoFactorAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

class Plugin {
	public function __construct() {
		$this->define_constants();
		$this->initialize_components();
	}

	private function define_constants() {
		define( 'ANDROMEDA_2FA_VERSION', '26.0.0' );
		define( 'ANDROMEDA_2FA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'ANDROMEDA_2FA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'ANDROMEDA_2FA_TEXT_DOMAIN', 'andromeda-2fa' );
	}

	private function initialize_components() {
		// Initialization logic for the plugin components goes here.
		wp_die( '2FA Plugin Initialized' );
	}
}
