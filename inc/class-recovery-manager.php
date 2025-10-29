<?php
/**
 * Recovery Manager class - handles recovery code generation and management.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 * @license GPL-2.0-or-later
 */

namespace Andromeda\TwoFactorAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Recovery Manager Class
 */
class RecoveryManager {
	/**
	 * User meta key for recovery codes (array of hashed strings).
	 */
	public const META_RECOVERY_CODES = 'andromeda_2fa_recovery_codes';

	/**
	 * Generate a set of human-friendly one-time recovery codes.
	 * Returns an associative array with plaintext codes (to show once)
	 * and hashed codes (to store).
	 *
	 * @param int $count Number of codes to generate.
	 * @return array{plain: string[], hashed: string[]}
	 */
	public function generate_recovery_codes( int $count = 10 ): array {
		$plain_codes = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$seg           = strtoupper( bin2hex( random_bytes( 6 ) ) );
			$plain_codes[] = substr( $seg, 0, 4 ) . '-' . substr( $seg, 4, 4 ) . '-' . substr( $seg, 8, 4 );
		}

		$hashed_codes = array_map(
			static fn( $c ) => password_hash( (string) $c, PASSWORD_DEFAULT ),
			$plain_codes
		);

		return [
			'plain'  => $plain_codes,
			'hashed' => $hashed_codes,
		];
	}

	/**
	 * Store hashed recovery codes for a user (overwrites existing list).
	 *
	 * @param int   $user_id       User ID.
	 * @param array $hashed_codes  Array of hashed code strings.
	 * @return void
	 */
	public function store_recovery_codes( int $user_id, array $hashed_codes ): void {
		update_user_meta( $user_id, self::META_RECOVERY_CODES, array_values( $hashed_codes ) );
	}

	/**
	 * Try to consume a recovery code for the given user.
	 * If the input matches one of the stored hashes, that code is removed
	 * and the method returns true. Otherwise returns false.
	 *
	 * Accepts inputs with or without dashes/spaces (normalizes internally).
	 * Supports both password_hash() hashes and wp_hash_password() legacy hashes.
	 *
	 * @param int    $user_id User ID.
	 * @param string $input   User-entered code.
	 * @return bool
	 */
	public function consume_recovery_code( int $user_id, string $input ): bool {
		$normalized = $this->normalize_code( $input );
		if ( '' === $normalized ) {
			return false;
		}

		$list = (array) get_user_meta( $user_id, self::META_RECOVERY_CODES, true );
		if ( empty( $list ) ) {
			return false;
		}

		foreach ( $list as $code => $hash ) {
			$matched = false;

			if ( is_string( $hash ) && password_verify( $normalized, $hash ) ) {
				$matched = true;
			}

			if ( ! $matched && function_exists( 'wp_check_password' ) && is_string( $hash ) ) {
				$matched = wp_check_password( $normalized, $hash, 0 );
			}

			if ( $matched ) {
				unset( $list[ $code ] );
				update_user_meta( $user_id, self::META_RECOVERY_CODES, array_values( $list ) );

				return true;
			}
		}

		return false;
	}

	/**
	 * Count how many unused recovery codes remain for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_remaining_codes( int $user_id ): int {
		$list = (array) get_user_meta( $user_id, self::META_RECOVERY_CODES, true );

		return count( $list );
	}

	/**
	 * Normalize user-entered code: strip non-alphanumerics, uppercase, and
	 * reformat to canonical XXXX-XXXX-XXXX if length is 12. Returns empty string
	 * if the input cannot be normalized to the expected length.
	 *
	 * @param string $code Raw user input.
	 * @return string Normalized code or empty string on failure.
	 */
	public function normalize_code( string $code ): string {
		$code = strtoupper( trim( $code ) );
		$code = preg_replace( '/[^A-Z0-9]/', '', $code );

		if ( strlen( $code ) !== 12 ) {
			return '';
		}

		return substr( $code, 0, 4 ) . '-' . substr( $code, 4, 4 ) . '-' . substr( $code, 8, 4 );
	}
}
