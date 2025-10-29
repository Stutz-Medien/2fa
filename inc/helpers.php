<?php
/**
 * Global helper functions.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'andromeda_2fa_verify_nonce' ) ) {
	/**
	 * Verify a nonce from a request field.
	 *
	 * @param string $field  The request field name containing the nonce.
	 * @param string $action The nonce action string.
	 * @return bool True if valid, false otherwise.
	 */
	function andromeda_2fa_verify_nonce( $field, $action ) {
		if ( ! isset( $_POST[ $field ] ) ) return false;

		return (bool) wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST[ $field ] ) ),
			$action
		);
	}
}
