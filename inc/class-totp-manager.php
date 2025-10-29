<?php
/**
 * TOTP Manager class - handles TOTP generation and verification.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 * @license GPL-2.0-or-later
 */

namespace Andromeda\TwoFactorAuth;

use OTPHP\TOTP;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TOTP Manager Class
 */
class TotpManager {
	/**
	 * Generate a new TOTP instance with a random secret.
	 *
	 * @return TOTP
	 */
	public function generate_totp() {
		return TOTP::generate();
	}

	/**
	 * Create TOTP instance from existing secret.
	 *
	 * @param string $secret The TOTP secret.
	 * @return TOTP
	 */
	public function create_from_secret( $secret ) {
		return TOTP::createFromSecret( $secret );
	}

	/**
	 * Get the provisioning URI for a user.
	 *
	 * @param string $secret The TOTP secret.
	 * @param string $email  User email.
	 * @param string $issuer Site name/issuer.
	 * @return string
	 */
	public function get_provisioning_uri( $secret, $email, $issuer ) {
		$totp = $this->create_from_secret( $secret );
		$totp->setLabel( $email );
		$totp->setIssuer( $issuer );

		return $totp->getProvisioningUri();
	}

	/**
	 * Verify a TOTP code.
	 *
	 * @param string $secret The TOTP secret.
	 * @param string $code   The code to verify.
	 * @param int    $window Time window for verification (default 1 = ±30 seconds).
	 * @return bool
	 */
	public function verify_code( $secret, $code, $window = 1 ) {
		if ( empty( $secret ) || empty( $code ) ) {
			return false;
		}

		$totp = $this->create_from_secret( $secret );

		return $totp->verify( $code, null, $window );
	}

	/**
	 * Get the current TOTP code for a secret (for testing purposes).
	 *
	 * @param string $secret The TOTP secret.
	 * @return string
	 */
	public function get_current_code( $secret ) {
		$totp = $this->create_from_secret( $secret );

		return $totp->now();
	}
}
