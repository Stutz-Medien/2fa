<?php
/**
 * User Settings (Recovery Codes) Test
 *
 * @package stutzmedien/2fa
 */

namespace Andromeda\TwoFactorAuth\Tests\Unit;

use Andromeda\TwoFactorAuth\UserSettings;
use Andromeda\TwoFactorAuth\TotpManager;
use Andromeda\TwoFactorAuth\QrCodeGenerator;
use Andromeda\TwoFactorAuth\RecoveryManager;
use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey\Functions;

class UserSettingsRecoveryCodesTest extends TestCase {

	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	private $totp_manager;
	private $qr_generator;
	private $user_settings;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$GLOBALS['__andromeda_test_transients']      = array();
		$GLOBALS['__andromeda_test_transient_calls'] = array();

		$this->totp_manager  = Mockery::mock( TotpManager::class );
		$this->qr_generator  = Mockery::mock( QrCodeGenerator::class );
		$this->user_settings = new UserSettings( $this->totp_manager, $this->qr_generator );
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_render_user_profile_fields_shows_recovery_codes_when_transient_present() {
		$user_id = 12;
		$user    = (object) [ 'ID' => $user_id, 'user_email' => 'user3@example.com' ];
		$plain_codes = array( 'abc<def', 'ghi&jkl' );

		Functions\when( 'esc_html_e' )->alias( function( $text ) { echo $text; } );
		Functions\when( 'esc_attr_e' )->alias( function( $text ) { echo $text; } );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->alias( function( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } );
		Functions\when( 'esc_attr' )->alias( function( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } );
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
			->with( 'EXISTSECRET', 'user3@example.com', 'Test Site' )
			->andReturn( 'otpauth://totp/...EXISTSECRET...' );
		$this->qr_generator->shouldReceive( 'generate_totp_qr_code' )
			->once()
			->with( 'otpauth://totp/...EXISTSECRET...' )
			->andReturn( 'data:image/png;base64,CCC' );

		$transient_key = 'andromeda_2fa_plain_codes_' . (int) $user_id;
		$GLOBALS['__andromeda_test_transients'][ $transient_key ] = $plain_codes;

		$escaped_codes = array_map(
			function( $code ) {
				return htmlspecialchars( (string) $code, ENT_QUOTES, 'UTF-8' );
			},
			$plain_codes
		);
		$expected_download_text = implode( "\n", $escaped_codes );
		$expected_download_href = 'data:text/plain;charset=utf-8,' . rawurlencode( $expected_download_text );
		$expected_clipboard_attr = htmlspecialchars( implode( "\n", $plain_codes ), ENT_QUOTES, 'UTF-8' );

		ob_start();
		$this->user_settings->render_user_profile_fields( $user );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'andromeda-2fa-recovery-codes', $html );
		$this->assertStringContainsString( $expected_download_href, $html );
		$this->assertStringContainsString( 'abc&lt;def', $html );
		$this->assertStringContainsString( 'ghi&amp;jkl', $html );
		$this->assertStringContainsString( 'data-clipboard-text="' . $expected_clipboard_attr . '"', $html );
		$this->assertStringNotContainsString( 'Recovery codes remaining:', $html );

		$this->assertArrayNotHasKey( $transient_key, $GLOBALS['__andromeda_test_transients'] );
		$this->assertContains(
			array( 'type' => 'get', 'key' => $transient_key ),
			$GLOBALS['__andromeda_test_transient_calls']
		);
		$this->assertContains(
			array( 'type' => 'delete', 'key' => $transient_key ),
			$GLOBALS['__andromeda_test_transient_calls']
		);
	}

	public function test_save_user_profile_fields_regen_codes_stores_codes_and_sets_plain_transient() {
		$user_id = 25;
		$_POST['andromeda_2fa_nonce']       = 'nonce';
		$_POST['andromeda_2fa_enabled']     = '1';
		$_POST['andromeda_2fa_secret']      = 'EXISTSECRET';
		$_POST['andromeda_2fa_regen_codes'] = '1';

		$recovery_manager = Mockery::mock( RecoveryManager::class );
		$this->user_settings = new UserSettings( $this->totp_manager, $this->qr_generator, $recovery_manager );

		Functions\when( 'wp_verify_nonce' )->alias( function () { return true; } );
		Functions\when( 'current_user_can' )->alias( function () { return true; } );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, 'andromeda_2fa_enabled', true )
			->andReturn( '1' );

		$codes = array(
			'plain'  => array( 'CODE1', 'CODE2' ),
			'hashed' => array( 'HASH1', 'HASH2' ),
		);

		$recovery_manager->shouldReceive( 'generate_recovery_codes' )
			->once()
			->andReturn( $codes );
		$recovery_manager->shouldReceive( 'store_recovery_codes' )
			->once()
			->with( (int) $user_id, $codes['hashed'] );

		$this->totp_manager->shouldNotReceive( 'verify_code' );

		$this->user_settings->save_user_profile_fields( $user_id );

		$this->assertSame(
			array(
				'type'       => 'set',
				'key'        => 'andromeda_2fa_plain_codes_' . (int) $user_id,
				'value'      => $codes['plain'],
				'expiration' => 15 * MINUTE_IN_SECONDS,
			),
			$GLOBALS['__andromeda_test_transient_calls'][0]
		);

		unset( $_POST['andromeda_2fa_nonce'], $_POST['andromeda_2fa_enabled'], $_POST['andromeda_2fa_secret'], $_POST['andromeda_2fa_regen_codes'] );
	}
}

