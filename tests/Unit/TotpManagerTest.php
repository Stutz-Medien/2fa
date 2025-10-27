<?php
/**
 * TOTP Manager Test
 *
 * @package stutzmedien/2fa
 */

namespace Andromeda\TwoFactorAuth\Tests\Unit;

use Andromeda\TwoFactorAuth\TotpManager;
use PHPUnit\Framework\TestCase;
use OTPHP\TOTP;

class TotpManagerTest extends TestCase {

	private $totp_manager;

	protected function setUp(): void {
		parent::setUp();
		$this->totp_manager = new TotpManager();
	}

	public function test_generate_totp_returns_totp_instance() {
		$totp = $this->totp_manager->generate_totp();
		
		$this->assertInstanceOf( TOTP::class, $totp );
		$this->assertNotEmpty( $totp->getSecret() );
	}

	public function test_create_from_secret() {
		$secret = 'JBSWY3DPEHPK3PXP';
		$totp   = $this->totp_manager->create_from_secret( $secret );
		
		$this->assertInstanceOf( TOTP::class, $totp );
		$this->assertEquals( $secret, $totp->getSecret() );
	}

	public function test_get_provisioning_uri() {
		$secret = 'JBSWY3DPEHPK3PXP';
		$email  = 'test@example.com';
		$issuer = 'Test Site';

		$uri = $this->totp_manager->get_provisioning_uri( $secret, $email, $issuer );

		$this->assertStringContainsString( 'otpauth://totp/', $uri );
		$this->assertStringContainsString( urlencode( $email ), $uri );
		$this->assertStringContainsString( $secret, $uri );
	}

	public function test_verify_code_with_valid_code() {
		$secret = 'JBSWY3DPEHPK3PXP';
		$totp   = $this->totp_manager->create_from_secret( $secret );
		$code   = $totp->now();

		$result = $this->totp_manager->verify_code( $secret, $code );

		$this->assertTrue( $result );
	}

	public function test_verify_code_with_invalid_code() {
		$secret = 'JBSWY3DPEHPK3PXP';
		$code   = '000000';

		$result = $this->totp_manager->verify_code( $secret, $code );

		$this->assertFalse( $result );
	}

	public function test_verify_code_with_empty_secret() {
		$result = $this->totp_manager->verify_code( '', '123456' );

		$this->assertFalse( $result );
	}

	public function test_verify_code_with_empty_code() {
		$secret = 'JBSWY3DPEHPK3PXP';
		$result = $this->totp_manager->verify_code( $secret, '' );

		$this->assertFalse( $result );
	}

	public function test_get_current_code() {
		$secret = 'JBSWY3DPEHPK3PXP';
		$code   = $this->totp_manager->get_current_code( $secret );

		$this->assertMatchesRegularExpression( '/^\d{6}$/', $code );
	}

	public function test_verify_code_accepts_codes_within_window() {
		$secret = 'JBSWY3DPEHPK3PXP';
		$totp   = $this->totp_manager->create_from_secret( $secret );
		
		// Get code for current time
		$code = $totp->at( time() );

		// Verify with window of 1 (±30 seconds)
		$result = $this->totp_manager->verify_code( $secret, $code, 1 );

		$this->assertTrue( $result );
	}
}
