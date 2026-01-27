<?php
/**
 * PHPUnit bootstrap file
 *
 * @package stutzmedien/2fa
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'ANDROMEDA_2FA_PLUGIN_FILE', dirname( __DIR__ ) . '/andromeda-2fa.php' );
define( 'ANDROMEDA_2FA_PLUGIN_DIR', dirname( __DIR__ ) . '/inc/' );
define( 'ANDROMEDA_2FA_VERSION', '26.0.0' );

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		$GLOBALS['__andromeda_test_transient_calls'][] = array(
			'type' => 'get',
			'key'  => $key,
		);

		return $GLOBALS['__andromeda_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['__andromeda_test_transient_calls'][] = array(
			'type'       => 'set',
			'key'        => $key,
			'value'      => $value,
			'expiration' => $expiration,
		);

		$GLOBALS['__andromeda_test_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		$GLOBALS['__andromeda_test_transient_calls'][] = array(
			'type' => 'delete',
			'key'  => $key,
		);

		unset( $GLOBALS['__andromeda_test_transients'][ $key ] );

		return true;
	}
}

require_once dirname( __DIR__ ) . '/inc/helpers.php';
require_once dirname( __DIR__ ) . '/inc/class-totp-manager.php';
require_once dirname( __DIR__ ) . '/inc/class-qr-code-generator.php';
require_once dirname( __DIR__ ) . '/inc/class-recovery-manager.php';
require_once dirname( __DIR__ ) . '/inc/class-user-settings.php';
require_once dirname( __DIR__ ) . '/inc/class-login-handler.php';
