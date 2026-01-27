<?php
/**
 * PHPUnit bootstrap file
 *
 * @package stutzmedien/2fa
 */

namespace {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';

	define( 'ABSPATH', '/tmp/wordpress/' );
	define( 'ANDROMEDA_2FA_PLUGIN_FILE', dirname( __DIR__ ) . '/andromeda-2fa.php' );
	define( 'ANDROMEDA_2FA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
	define( 'ANDROMEDA_2FA_VERSION', '26.0.0' );

	if ( ! defined( 'COOKIEPATH' ) ) {
		define( 'COOKIEPATH', '/' );
	}

	if ( ! defined( 'COOKIE_DOMAIN' ) ) {
		define( 'COOKIE_DOMAIN', '' );
	}

	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}

	if ( ! class_exists( 'WP_User' ) ) {
		class WP_User {
			public $ID;

			public function __construct( $id = 0 ) {
				$this->ID = $id;
			}
		}
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			public $code;
			public $message;

			public function __construct( $code = '', $message = '' ) {
				$this->code = $code;
				$this->message = $message;
			}

			public function get_error_code() {
				return $this->code;
			}

			public function get_error_message() {
				return $this->message;
			}
		}
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
	require_once dirname( __DIR__ ) . '/inc/class-plugin.php';
}

namespace Andromeda\TwoFactorAuth {
	if ( ! function_exists( __NAMESPACE__ . '\\setcookie' ) ) {
		function setcookie() {
			return true;
		}
	}
}
