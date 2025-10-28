<?php
/**
 * Main Plugin class - initializes the plugin components.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 */

namespace Andromeda\TwoFactorAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Plugin Class
 */
class Plugin {
	/**
	 * TOTP Manager instance.
	 *
	 * @var TotpManager
	 */
	private $totp_manager;

	/**
	 * QR Code Generator instance.
	 *
	 * @var QrCodeGenerator
	 */
	private $qr_generator;

	/**
	 * Recovery Manager instance.
	 *
	 * @var RecoveryManager
	 */
	private $recovery_manager;

	/**
	 * User Settings instance.
	 *
	 * @var UserSettings
	 */
	private $user_settings;

	/**
	 * Login Handler instance.
	 *
	 * @var LoginHandler
	 */
	private $login_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->load_dependencies();
		$this->initialize_components();
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'ANDROMEDA_2FA_VERSION', '26.0.0' );
		define( 'ANDROMEDA_2FA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'ANDROMEDA_2FA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'ANDROMEDA_2FA_TEXT_DOMAIN', 'andromeda-2fa' );
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'class-totp-manager.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'class-qr-code-generator.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'class-recovery-manager.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'class-user-settings.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'class-login-handler.php';
	}

	/**
	 * Initialize plugin components.
	 */
	private function initialize_components() {
		$this->totp_manager     = new TotpManager();
		$this->qr_generator     = new QrCodeGenerator();
		$this->recovery_manager = new RecoveryManager();

		$this->user_settings = new UserSettings( $this->totp_manager, $this->qr_generator, $this->recovery_manager );
		$this->login_handler = new LoginHandler( $this->totp_manager, $this->user_settings, $this->recovery_manager );
	}
}
