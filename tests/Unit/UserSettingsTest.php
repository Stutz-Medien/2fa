<?php
/**
 * User Settings Test
 *
 * @package stutzmedien/2fa
 */

namespace Andromeda\TwoFactorAuth\Tests\Unit;

use Andromeda\TwoFactorAuth\UserSettings;
use Andromeda\TwoFactorAuth\TotpManager;
use Andromeda\TwoFactorAuth\QrCodeGenerator;
use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey\Functions;

class UserSettingsTest extends TestCase {

	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	private $totp_manager;
	private $qr_generator;
	private $user_settings;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->totp_manager = Mockery::mock( TotpManager::class );
		$this->qr_generator = Mockery::mock( QrCodeGenerator::class );
		$this->user_settings = new UserSettings( $this->totp_manager, $this->qr_generator );
	}

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

	public function test_has_2fa_enabled_returns_true_when_enabled() {
		$user_id = 1;
		
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '1' );

		$result = $this->user_settings->is_enabled_for_user( $user_id );

		$this->assertTrue( $result );
	}

	public function test_has_2fa_enabled_returns_false_when_disabled() {
		$user_id = 1;
		
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '' );

		$result = $this->user_settings->is_enabled_for_user( $user_id );

		$this->assertFalse( $result );
	}

	public function test_get_user_secret_returns_secret() {
		$user_id = 1;
		$secret = 'JBSWY3DPEHPK3PXP';
		
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret', true )
			->andReturn( $secret );

		$result = $this->user_settings->get_user_secret( $user_id );

		$this->assertEquals( $secret, $result );
	}

	public function test_get_user_secret_returns_empty_string_when_not_set() {
		$user_id = 1;
		
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret', true )
			->andReturn( '' );

		$result = $this->user_settings->get_user_secret( $user_id );

		$this->assertEquals( '', $result );
	}

	public function test_set_user_secret_updates_user_meta() {
		$user_id = 2;
		$secret  = 'JBSWY3DPEHPK3PXP';

		Functions\expect( 'update_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret', $secret )
			->andReturn( true );

		$result = $this->user_settings->set_user_secret( $user_id, $secret );

		$this->assertTrue( $result );
	}

	public function test_enable_for_user_sets_enabled_flag() {
		$user_id = 3;

		Functions\expect( 'update_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( true );

		$result = $this->user_settings->enable_for_user( $user_id );

		$this->assertTrue( $result );
	}

	public function test_disable_for_user_deletes_meta_and_returns_true() {
		$user_id = 4;

		Functions\expect( 'delete_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled' )
			->andReturn( true );

		Functions\expect( 'delete_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret' )
			->andReturn( true );

		$result = $this->user_settings->disable_for_user( $user_id );

		$this->assertTrue( $result );
	}

	public function test_render_user_profile_fields_shows_setup_when_disabled_and_no_secret() {
		$user_id = 10;
		$user    = (object) [ 'ID' => $user_id, 'user_email' => 'user@example.com' ];

		// WordPress helper functions stubs
		Functions\when( 'esc_html_e' )->alias( function( $text ) { echo $text; } );
		Functions\when( 'esc_attr_e' )->alias( function( $text ) { echo $text; } );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'checked' )->alias( function( $checked ) { if ( $checked ) echo 'checked="checked"'; } );

		// User meta lookups: disabled and no secret
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '' );
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret', true )
			->andReturn( '' );

		// Site info
		Functions\expect( 'get_bloginfo' )
			->once()
			->with( 'name' )
			->andReturn( 'Test Site' );

		// TOTP + QR dependencies
		$totp = \Mockery::mock();
		$totp->shouldReceive( 'getSecret' )->once()->andReturn( 'TESTSECRET' );
		$this->totp_manager->shouldReceive( 'generate_totp' )->once()->andReturn( $totp );
		$this->totp_manager->shouldReceive( 'get_provisioning_uri' )
			->once()
			->with( 'TESTSECRET', 'user@example.com', 'Test Site' )
			->andReturn( 'otpauth://totp/...TESTSECRET...' );
		$this->qr_generator->shouldReceive( 'generate_totp_qr_code' )
			->once()
			->with( 'otpauth://totp/...TESTSECRET...' )
			->andReturn( 'data:image/png;base64,AAA' );

		ob_start();
		$this->user_settings->render_user_profile_fields( $user );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="andromeda_2fa_enabled"', $html );
		$this->assertStringContainsString( 'name="andromeda_2fa_secret"', $html );
		$this->assertStringContainsString( 'value="TESTSECRET"', $html );
		$this->assertStringContainsString( 'Setup QR Code', $html );
		$this->assertStringContainsString( 'src="data:image/png;base64,AAA"', $html );
		$this->assertStringContainsString( 'Secret Key:', $html );
		$this->assertStringContainsString( '>TESTSECRET<', $html );
		$this->assertStringContainsString( 'name="andromeda_2fa_verify_code"', $html );
	}

    public function test_render_user_profile_fields_hides_setup_when_enabled() {
		$user_id = 11;
		$user    = (object) [ 'ID' => $user_id, 'user_email' => 'user2@example.com' ];

		Functions\when( 'esc_html_e' )->alias( function( $text ) { echo $text; } );
		Functions\when( 'esc_attr_e' )->alias( function( $text ) { echo $text; } );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'checked' )->alias( function( $checked ) { if ( $checked ) echo 'checked="checked"'; } );

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '1' );
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret', true )
			->andReturn( 'EXISTSECRET' );

		Functions\expect( 'get_bloginfo' )
			->once()
			->with( 'name' )
			->andReturn( 'Test Site' );

		$this->totp_manager->shouldNotReceive( 'generate_totp' );
		$this->totp_manager->shouldReceive( 'get_provisioning_uri' )
			->once()
			->with( 'EXISTSECRET', 'user2@example.com', 'Test Site' )
			->andReturn( 'otpauth://totp/...EXISTSECRET...' );
		$this->qr_generator->shouldReceive( 'generate_totp_qr_code' )
			->once()
			->with( 'otpauth://totp/...EXISTSECRET...' )
			->andReturn( 'data:image/png;base64,BBB' );

		ob_start();
		$this->user_settings->render_user_profile_fields( $user );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="andromeda_2fa_enabled"', $html );
		$this->assertStringContainsString( 'checked="checked"', $html );
		$this->assertStringContainsString( 'name="andromeda_2fa_secret"', $html );
		$this->assertStringContainsString( 'value="EXISTSECRET"', $html );
		$this->assertStringNotContainsString( 'Setup QR Code', $html );
        $this->assertStringNotContainsString( 'name="andromeda_2fa_verify_code"', $html );
    }

	public function test_save_user_profile_fields_enable_missing_code_adds_error() {
		$user_id = 20;
		$_POST['andromeda_2fa_nonce']       = 'nonce';
		$_POST['andromeda_2fa_enabled']     = '1';
		$_POST['andromeda_2fa_secret']      = 'SECRETABC';
		$_POST['andromeda_2fa_verify_code'] = '';

		Functions\when( 'wp_verify_nonce' )->alias( function () { return true; } );
		Functions\when( 'current_user_can' )->alias( function () { return true; } );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( '__' )->returnArg();

		// is_enabled_for_user -> false
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '' );

		// Capture the error callback added by handle_enable_2fa
		$captured = null;
		Functions\when( 'add_action' )->alias( function ( $hook, $callback ) use ( &$captured ) {
			if ( 'user_profile_update_errors' === $hook && is_callable( $callback ) ) {
				$captured = $callback;
			}
			return null;
		} );

		$this->user_settings->save_user_profile_fields( $user_id );

		$this->assertIsCallable( $captured );

		$errors = \Mockery::mock();
		$errors->shouldReceive( 'add' )
			->once()
			->with( '2fa_no_code', \Mockery::type( 'string' ) );

		// Invoke the captured callback to assert it adds proper error
		$captured( $errors );

		unset( $_POST['andromeda_2fa_nonce'], $_POST['andromeda_2fa_enabled'], $_POST['andromeda_2fa_secret'], $_POST['andromeda_2fa_verify_code'] );
	}

	public function test_save_user_profile_fields_enable_invalid_code_adds_error() {
		$user_id = 21;
		$_POST['andromeda_2fa_nonce']       = 'nonce';
		$_POST['andromeda_2fa_enabled']     = '1';
		$_POST['andromeda_2fa_secret']      = 'SECRETXYZ';
		$_POST['andromeda_2fa_verify_code'] = '123456';

		Functions\when( 'wp_verify_nonce' )->alias( function () { return true; } );
		Functions\when( 'current_user_can' )->alias( function () { return true; } );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( '__' )->returnArg();

		// is_enabled_for_user -> false
		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '' );

		// TOTP verification fails
		$this->totp_manager->shouldReceive( 'verify_code' )
			->once()
			->with( 'SECRETXYZ', '123456' )
			->andReturn( false );

		$captured = null;
		Functions\when( 'add_action' )->alias( function ( $hook, $callback ) use ( &$captured ) {
			if ( 'user_profile_update_errors' === $hook && is_callable( $callback ) ) {
				$captured = $callback;
			}
			return null;
		} );

		$this->user_settings->save_user_profile_fields( $user_id );

		$this->assertIsCallable( $captured );

		$errors = \Mockery::mock();
		$errors->shouldReceive( 'add' )
			->once()
			->with( '2fa_invalid_code', \Mockery::type( 'string' ) );

		$captured( $errors );

		unset( $_POST['andromeda_2fa_nonce'], $_POST['andromeda_2fa_enabled'], $_POST['andromeda_2fa_secret'], $_POST['andromeda_2fa_verify_code'] );
	}

	public function test_save_user_profile_fields_enable_valid_code_sets_meta_and_enables() {
		$user_id = 22;
		$_POST['andromeda_2fa_nonce']       = 'nonce';
		$_POST['andromeda_2fa_enabled']     = '1';
		$_POST['andromeda_2fa_secret']      = 'SECRETOK';
		$_POST['andromeda_2fa_verify_code'] = '654321';

		Functions\when( 'wp_verify_nonce' )->alias( function () { return true; } );
		Functions\when( 'current_user_can' )->alias( function () { return true; } );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '' );

		$this->totp_manager->shouldReceive( 'verify_code' )
			->once()
			->with( 'SECRETOK', '654321' )
			->andReturn( true );

		// Expect meta updates via set_user_secret and enable_for_user
		Functions\expect( 'update_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret', 'SECRETOK' )
			->andReturn( true );
		Functions\expect( 'update_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( true );

		$this->user_settings->save_user_profile_fields( $user_id );

		unset( $_POST['andromeda_2fa_nonce'], $_POST['andromeda_2fa_enabled'], $_POST['andromeda_2fa_secret'], $_POST['andromeda_2fa_verify_code'] );
	}

	public function test_save_user_profile_fields_disable_when_enabled() {
		$user_id = 23;
		$_POST['andromeda_2fa_nonce']   = 'nonce';
		// Checkbox not set -> disabled

		Functions\when( 'wp_verify_nonce' )->alias( function () { return true; } );
		Functions\when( 'current_user_can' )->alias( function () { return true; } );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '1' );

		Functions\expect( 'delete_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled' )
			->andReturn( true );
		Functions\expect( 'delete_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret' )
			->andReturn( true );

		$this->user_settings->save_user_profile_fields( $user_id );

		unset( $_POST['andromeda_2fa_nonce'] );
	}

	public function test_save_user_profile_fields_persists_secret_when_disabled_and_not_enabled() {
		$user_id = 24;
		$_POST['andromeda_2fa_nonce']  = 'nonce';
		$_POST['andromeda_2fa_secret'] = 'SECRETPersist';

		Functions\when( 'wp_verify_nonce' )->alias( function () { return true; } );
		Functions\when( 'current_user_can' )->alias( function () { return true; } );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '' );

		Functions\expect( 'update_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_secret', 'SECRETPersist' )
			->andReturn( true );

		$this->user_settings->save_user_profile_fields( $user_id );

		unset( $_POST['andromeda_2fa_nonce'], $_POST['andromeda_2fa_secret'] );
	}
}
