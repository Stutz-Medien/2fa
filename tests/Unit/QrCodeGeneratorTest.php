<?php
/**
 * QR Code Generator Test
 *
 * @package stutzmedien/2fa
 */

namespace Andromeda\TwoFactorAuth\Tests\Unit;

use Andromeda\TwoFactorAuth\QrCodeGenerator;
use PHPUnit\Framework\TestCase;

class QrCodeGeneratorTest extends TestCase {

	private $qr_generator;

	protected function setUp(): void {
		parent::setUp();
		$this->qr_generator = new QrCodeGenerator();
	}

	public function test_generate_qr_code_returns_data_uri() {
		$data = 'otpauth://totp/test@example.com?secret=JBSWY3DPEHPK3PXP&issuer=TestSite';
		$qr_code = $this->qr_generator->generate_totp_qr_code( $data );

		$this->assertStringStartsWith( 'data:image/png;base64,', $qr_code );
	}

	public function test_generate_qr_code_with_empty_data() {
		$qr_code = $this->qr_generator->generate_totp_qr_code( '' );

		$this->assertStringStartsWith( 'data:image/png;base64,', $qr_code );
	}

	public function test_generate_qr_code_produces_valid_base64() {
		$data = 'otpauth://totp/test@example.com?secret=JBSWY3DPEHPK3PXP&issuer=TestSite';
		$qr_code = $this->qr_generator->generate_totp_qr_code( $data );

		$base64_part = str_replace( 'data:image/png;base64,', '', $qr_code );
		$decoded = base64_decode( $base64_part, true );

		$this->assertNotFalse( $decoded );
		$this->assertNotEmpty( $decoded );
	}

	public function test_generate_qr_code_with_custom_size() {
		$data = 'test data';
		$qr_code = $this->qr_generator->generate_totp_qr_code( $data, 400 );

		$this->assertStringStartsWith( 'data:image/png;base64,', $qr_code );
	}

	public function test_different_data_produces_different_qr_codes() {
		$qr_code_1 = $this->qr_generator->generate_totp_qr_code( 'data1' );
		$qr_code_2 = $this->qr_generator->generate_totp_qr_code( 'data2' );

		$this->assertNotEquals( $qr_code_1, $qr_code_2 );
	}
}
