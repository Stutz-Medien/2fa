<?php
/**
 * Main Plugin class - initializes the plugin components.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 * @license GPL-2.0-or-later
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
		$this->load_dependencies();
		$this->initialize_components();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'inc/helpers.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'inc/TotpManager.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'inc/QrCodeGenerator.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'inc/RecoveryManager.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'inc/UserSettings.php';
		require_once ANDROMEDA_2FA_PLUGIN_DIR . 'inc/LoginHandler.php';
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
