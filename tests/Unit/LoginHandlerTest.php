<?php
/**
 * Login Handler Test
 *
 * @package stutzmedien/2fa
 */

namespace Andromeda\TwoFactorAuth\Tests\Unit;

use Andromeda\TwoFactorAuth\LoginHandler;
use Andromeda\TwoFactorAuth\RecoveryManager;
use Andromeda\TwoFactorAuth\TotpManager;
use Andromeda\TwoFactorAuth\UserSettings;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

class LoginHandlerTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	private $totp_manager;
	private $user_settings;
	private $recovery_manager;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$_COOKIE = array();
		$_POST = array();
		$GLOBALS['__andromeda_test_transients'] = array();
		$GLOBALS['__andromeda_test_transient_calls'] = array();

		$this->totp_manager = Mockery::mock( TotpManager::class );
		$this->user_settings = Mockery::mock( UserSettings::class );
		$this->recovery_manager = Mockery::mock( RecoveryManager::class );

		Functions\when( 'add_filter' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'is_ssl' )->justReturn( false );
		Functions\when( '__' )->returnArg();
		Functions\when( 'is_wp_error' )->alias( function( $value ) {
			return $value instanceof \WP_Error;
		} );
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	private function make_handler(): LoginHandler {
		return new LoginHandler( $this->totp_manager, $this->user_settings, $this->recovery_manager );
	}

	private function set_auth_cookie_and_transient( $token, $user_id, $username ) {
		$_COOKIE[ LoginHandler::COOKIE_NAME ] = $token;
		$GLOBALS['__andromeda_test_transients'][ LoginHandler::TRANSIENT_PREFIX . $token ] = array(
			'user_id'  => $user_id,
			'username' => $username,
		);
	}

	private function invoke_private( $instance, $method, array $args = array() ) {
		$caller = function( $method, array $args ) {
			return $this->$method( ...$args );
		};
		$caller = $caller->bindTo( $instance, get_class( $instance ) );
		return $caller( $method, $args );
	}

	private function set_private_property( $instance, $property, $value ) {
		$setter = function( $property, $value ) {
			$this->$property = $value;
		};
		$setter = $setter->bindTo( $instance, get_class( $instance ) );
		$setter( $property, $value );
	}

	public function test_handle_2fa_verification_returns_user_when_no_auth_data() {
		$handler = $this->make_handler();
		$user = new \WP_User( 1 );

		$this->assertSame( $user, $handler->handle_2fa_verification( $user ) );
	}

	public function test_handle_2fa_verification_returns_user_when_code_missing() {
		$handler = $this->make_handler();
		$user = new \WP_User( 1 );

		$this->set_auth_cookie_and_transient( 'token1', 1, 'jane' );

		$this->assertSame( $user, $handler->handle_2fa_verification( $user ) );
	}

	public function test_handle_2fa_verification_returns_error_for_invalid_nonce() {
		$handler = $this->make_handler();
		$user = new \WP_User( 1 );

		$this->set_auth_cookie_and_transient( 'token2', 1, 'jane' );
		$_POST['andromeda_2fa_code'] = '123456';
		$_POST['andromeda_2fa_nonce'] = 'bad-nonce';

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'bad-nonce', 'andromeda_2fa_verify' )
			->andReturn( false );

		$result = $handler->handle_2fa_verification( $user );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( '2fa_invalid_nonce', $result->get_error_code() );
	}

	public function test_handle_2fa_verification_returns_error_when_code_empty() {
		$handler = $this->make_handler();
		$user = new \WP_User( 1 );

		$this->set_auth_cookie_and_transient( 'token3', 1, 'jane' );
		$_POST['andromeda_2fa_code'] = '';
		$_POST['andromeda_2fa_nonce'] = 'good-nonce';

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'good-nonce', 'andromeda_2fa_verify' )
			->andReturn( true );

		$result = $handler->handle_2fa_verification( $user );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( '2fa_code_required', $result->get_error_code() );
	}

	public function test_handle_2fa_verification_returns_user_when_totp_valid() {
		$handler = $this->make_handler();
		$user_id = 12;
		$user = new \WP_User( $user_id );

		$this->set_auth_cookie_and_transient( 'token4', $user_id, 'jane' );
		$_POST['andromeda_2fa_code'] = '123456';
		$_POST['andromeda_2fa_nonce'] = 'good-nonce';

		Functions\expect( 'wp_verify_nonce' )->once()->with( 'good-nonce', 'andromeda_2fa_verify' )->andReturn( true );
		Functions\expect( 'get_user_by' )->once()->with( 'id', $user_id )->andReturn( $user );

		$this->user_settings
			->shouldReceive( 'get_user_secret' )
			->once()
			->with( $user_id )
			->andReturn( 'SECRET' );

		$this->totp_manager
			->shouldReceive( 'verify_code' )
			->once()
			->with( 'SECRET', '123456' )
			->andReturn( true );

		$this->recovery_manager
			->shouldReceive( 'consume_recovery_code' )
			->never();

		$result = $handler->handle_2fa_verification( $user );

		$this->assertSame( $user, $result );
		$this->assertArrayNotHasKey( LoginHandler::TRANSIENT_PREFIX . 'token4', $GLOBALS['__andromeda_test_transients'] );

		$this->user_settings->shouldNotReceive( 'is_enabled_for_user' );

		$this->assertSame( $user, $handler->check_2fa_required( $user, 'jane' ) );
	}

	public function test_handle_2fa_verification_returns_user_when_recovery_code_valid() {
		$handler = $this->make_handler();
		$user_id = 22;
		$user = new \WP_User( $user_id );

		$this->set_auth_cookie_and_transient( 'token5', $user_id, 'jane' );
		$_POST['andromeda_2fa_code'] = 'RECOVERY-CODE';
		$_POST['andromeda_2fa_nonce'] = 'good-nonce';

		Functions\expect( 'wp_verify_nonce' )->once()->with( 'good-nonce', 'andromeda_2fa_verify' )->andReturn( true );
		Functions\expect( 'get_user_by' )->once()->with( 'id', $user_id )->andReturn( $user );

		$this->user_settings
			->shouldReceive( 'get_user_secret' )
			->once()
			->with( $user_id )
			->andReturn( 'SECRET' );

		$this->totp_manager
			->shouldReceive( 'verify_code' )
			->once()
			->with( 'SECRET', 'RECOVERY-CODE' )
			->andReturn( false );

		$this->recovery_manager
			->shouldReceive( 'consume_recovery_code' )
			->once()
			->with( $user_id, 'RECOVERY-CODE' )
			->andReturn( true );

		$result = $handler->handle_2fa_verification( $user );

		$this->assertSame( $user, $result );
		$this->assertArrayNotHasKey( LoginHandler::TRANSIENT_PREFIX . 'token5', $GLOBALS['__andromeda_test_transients'] );
	}

	public function test_handle_2fa_verification_returns_error_when_recovery_user_invalid() {
		$handler = $this->make_handler();
		$user_id = 23;

		$this->set_auth_cookie_and_transient( 'token5b', $user_id, 'jane' );
		$_POST['andromeda_2fa_code'] = 'RECOVERY-CODE';
		$_POST['andromeda_2fa_nonce'] = 'good-nonce';

		Functions\expect( 'wp_verify_nonce' )->once()->with( 'good-nonce', 'andromeda_2fa_verify' )->andReturn( true );
		Functions\expect( 'get_user_by' )->once()->with( 'id', $user_id )->andReturn( false );

		$this->user_settings
			->shouldReceive( 'get_user_secret' )
			->once()
			->with( $user_id )
			->andReturn( 'SECRET' );

		$this->totp_manager
			->shouldReceive( 'verify_code' )
			->once()
			->with( 'SECRET', 'RECOVERY-CODE' )
			->andReturn( false );

		$this->recovery_manager
			->shouldReceive( 'consume_recovery_code' )
			->once()
			->with( $user_id, 'RECOVERY-CODE' )
			->andReturn( true );

		$result = $handler->handle_2fa_verification( null );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_user', $result->get_error_code() );
	}

	public function test_handle_2fa_verification_returns_error_when_user_invalid() {
		$handler = $this->make_handler();
		$user_id = 33;

		$this->set_auth_cookie_and_transient( 'token6', $user_id, 'jane' );
		$_POST['andromeda_2fa_code'] = '123456';
		$_POST['andromeda_2fa_nonce'] = 'good-nonce';

		Functions\expect( 'wp_verify_nonce' )->once()->with( 'good-nonce', 'andromeda_2fa_verify' )->andReturn( true );
		Functions\expect( 'get_user_by' )->once()->with( 'id', $user_id )->andReturn( false );

		$this->user_settings
			->shouldReceive( 'get_user_secret' )
			->once()
			->with( $user_id )
			->andReturn( 'SECRET' );

		$this->totp_manager
			->shouldReceive( 'verify_code' )
			->once()
			->with( 'SECRET', '123456' )
			->andReturn( true );

		$result = $handler->handle_2fa_verification( null );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_user', $result->get_error_code() );
	}

	public function test_handle_2fa_verification_returns_error_when_code_invalid() {
		$handler = $this->make_handler();
		$user_id = 44;
		$user = new \WP_User( $user_id );

		$this->set_auth_cookie_and_transient( 'token7', $user_id, 'jane' );
		$_POST['andromeda_2fa_code'] = 'BADCODE';
		$_POST['andromeda_2fa_nonce'] = 'good-nonce';

		Functions\expect( 'wp_verify_nonce' )->once()->with( 'good-nonce', 'andromeda_2fa_verify' )->andReturn( true );

		$this->user_settings
			->shouldReceive( 'get_user_secret' )
			->once()
			->with( $user_id )
			->andReturn( 'SECRET' );

		$this->totp_manager
			->shouldReceive( 'verify_code' )
			->once()
			->with( 'SECRET', 'BADCODE' )
			->andReturn( false );

		$this->recovery_manager
			->shouldReceive( 'consume_recovery_code' )
			->once()
			->with( $user_id, 'BADCODE' )
			->andReturn( false );

		$result = $handler->handle_2fa_verification( $user );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( '2fa_invalid_code', $result->get_error_code() );
	}

	public function test_get_session_token_returns_existing_cookie() {
		$handler = $this->make_handler();

		$_COOKIE[ LoginHandler::COOKIE_NAME ] = 'existing-token';

		Functions\expect( 'wp_generate_password' )->never();

		$token = $this->invoke_private( $handler, 'get_session_token' );

		$this->assertSame( 'existing-token', $token );
	}

	public function test_get_session_token_generates_new_token_when_missing_cookie() {
		$handler = $this->make_handler();

		Functions\expect( 'wp_generate_password' )
			->once()
			->with( 32, false )
			->andReturn( 'new-token' );

		$token = $this->invoke_private( $handler, 'get_session_token' );

		$this->assertSame( 'new-token', $token );
	}

	public function test_store_auth_data_sets_transient() {
		$handler = $this->make_handler();

		Functions\expect( 'wp_generate_password' )
			->once()
			->with( 32, false )
			->andReturn( 'store-token' );

		$this->invoke_private( $handler, 'store_auth_data', array( 7, 'marie' ) );

		$this->assertArrayHasKey( LoginHandler::TRANSIENT_PREFIX . 'store-token', $GLOBALS['__andromeda_test_transients'] );
		$this->assertSame(
			array( 'user_id' => 7, 'username' => 'marie' ),
			$GLOBALS['__andromeda_test_transients'][ LoginHandler::TRANSIENT_PREFIX . 'store-token' ]
		);
	}

	public function test_get_auth_data_returns_false_when_no_cookie() {
		$handler = $this->make_handler();

		$this->assertFalse( $this->invoke_private( $handler, 'get_auth_data' ) );
	}

	public function test_get_auth_data_returns_false_when_transient_not_array() {
		$handler = $this->make_handler();

		$_COOKIE[ LoginHandler::COOKIE_NAME ] = 'token-non-array';
		$GLOBALS['__andromeda_test_transients'][ LoginHandler::TRANSIENT_PREFIX . 'token-non-array' ] = 'not-array';

		$this->assertFalse( $this->invoke_private( $handler, 'get_auth_data' ) );
	}

	public function test_clear_auth_data_does_nothing_without_cookie() {
		$handler = $this->make_handler();

		$this->invoke_private( $handler, 'clear_auth_data' );

		$this->assertSame( array(), $GLOBALS['__andromeda_test_transient_calls'] );
	}

	public function test_clear_auth_data_deletes_transient() {
		$handler = $this->make_handler();

		$_COOKIE[ LoginHandler::COOKIE_NAME ] = 'token-clear';
		$GLOBALS['__andromeda_test_transients'][ LoginHandler::TRANSIENT_PREFIX . 'token-clear' ] = array( 'user_id' => 9 );

		$this->invoke_private( $handler, 'clear_auth_data' );

		$this->assertArrayNotHasKey( LoginHandler::TRANSIENT_PREFIX . 'token-clear', $GLOBALS['__andromeda_test_transients'] );
	}

	public function test_is_2fa_mode_returns_false_when_no_auth_data() {
		$handler = $this->make_handler();

		$this->assertFalse( $this->invoke_private( $handler, 'is_2fa_mode' ) );
	}

	public function test_is_2fa_mode_returns_true_when_auth_data_present() {
		$handler = $this->make_handler();

		$this->set_auth_cookie_and_transient( 'token-mode', 3, 'mode-user' );

		$this->assertTrue( $this->invoke_private( $handler, 'is_2fa_mode' ) );
	}

	public function test_check_2fa_required_returns_wp_error_untouched() {
		$handler = $this->make_handler();

		$error = new \WP_Error( 'test', 'error' );

		$this->assertSame( $error, $handler->check_2fa_required( $error, 'jane' ) );
	}

	public function test_check_2fa_required_returns_non_user_untouched() {
		$handler = $this->make_handler();

		$this->assertSame( 'not-user', $handler->check_2fa_required( 'not-user', 'jane' ) );
	}

	public function test_check_2fa_required_returns_when_verified_this_request() {
		$handler = $this->make_handler();
		$user = new \WP_User( 2 );

		$this->set_private_property( $handler, 'verified_this_request', true );
		$this->user_settings->shouldNotReceive( 'is_enabled_for_user' );

		$this->assertSame( $user, $handler->check_2fa_required( $user, 'jane' ) );
	}

	public function test_check_2fa_required_returns_when_auth_data_present() {
		$handler = $this->make_handler();
		$user = new \WP_User( 5 );

		$this->set_auth_cookie_and_transient( 'token-auth', 5, 'jane' );
		$this->user_settings->shouldNotReceive( 'is_enabled_for_user' );

		$this->assertSame( $user, $handler->check_2fa_required( $user, 'jane' ) );
	}

	public function test_check_2fa_required_returns_when_not_enabled() {
		$handler = $this->make_handler();
		$user = new \WP_User( 6 );

		$this->user_settings
			->shouldReceive( 'is_enabled_for_user' )
			->once()
			->with( 6 )
			->andReturn( false );

		$this->assertSame( $user, $handler->check_2fa_required( $user, 'jane' ) );
	}

	public function test_check_2fa_required_redirects_when_enabled() {
		$handler = $this->make_handler();
		$user = new \WP_User( 8 );

		$this->user_settings
			->shouldReceive( 'is_enabled_for_user' )
			->once()
			->with( 8 )
			->andReturn( true );

		Functions\expect( 'wp_generate_password' )
			->once()
			->with( 32, false )
			->andReturn( 'store-token' );

		Functions\expect( 'wp_login_url' )->once()->andReturn( 'login-url' );
		Functions\expect( 'add_query_arg' )
			->once()
			->with( 'andromeda_2fa', '1', 'login-url' )
			->andReturn( 'login-url?andromeda_2fa=1' );

		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'login-url?andromeda_2fa=1' )
			->andReturnUsing( function() {
				throw new \RuntimeException( 'redirect' );
			} );

		try {
			$handler->check_2fa_required( $user, 'jane' );
			$this->fail( 'Expected redirect exception.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'redirect', $exception->getMessage() );
		}

		$this->assertArrayHasKey( LoginHandler::TRANSIENT_PREFIX . 'store-token', $GLOBALS['__andromeda_test_transients'] );
	}

	public function test_render_2fa_field_outputs_nothing_when_not_in_2fa_mode() {
		$handler = $this->make_handler();

		ob_start();
		$handler->render_2fa_field();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}

	public function test_render_2fa_field_outputs_fields_when_in_2fa_mode() {
		$handler = $this->make_handler();

		$this->set_auth_cookie_and_transient( 'token-render', 1, 'jane' );

		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_attr_e' )->alias( function( $text ) { echo $text; } );
		Functions\when( 'esc_html_e' )->alias( function( $text ) { echo $text; } );
		Functions\when( 'wp_nonce_field' )->alias( function() { echo 'nonce-field'; } );

		ob_start();
		$handler->render_2fa_field();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="log"', $html );
		$this->assertStringContainsString( 'value="jane"', $html );
		$this->assertStringContainsString( 'andromeda_2fa_code', $html );
		$this->assertStringContainsString( 'nonce-field', $html );
	}

	public function test_enqueue_login_scripts_skips_when_not_in_2fa_mode() {
		$handler = $this->make_handler();

		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();

		$handler->enqueue_login_scripts();
	}

	public function test_enqueue_login_scripts_enqueues_assets_when_in_2fa_mode() {
		$handler = $this->make_handler();

		$this->set_auth_cookie_and_transient( 'token-assets', 1, 'jane' );

		Functions\expect( 'plugins_url' )
			->twice()
			->andReturnUsing( function( $path ) {
				return 'https://example.test/' . ltrim( $path, '/' );
			} );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with( 'andromeda-2fa-style', 'https://example.test/src/css/login-style.css', array(), ANDROMEDA_2FA_VERSION );

		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with( 'andromeda-2fa-script', 'https://example.test/src/js/login-script.js', array(), ANDROMEDA_2FA_VERSION, true );

		$handler->enqueue_login_scripts();
	}
}
