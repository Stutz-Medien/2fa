<?php
/**
 * QR Code Generator class - handles QR code generation for TOTP setup.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 * @license GPL-2.0-or-later
 */

namespace Andromeda\TwoFactorAuth;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * QR Code Generator Class
 */
class QrCodeGenerator {
	/**
	 * Generate QR code as data URI.
	 *
	 * @param string $data The data to encode in the QR code.
	 * @return string Data URI of the QR code image.
	 */
	public function generate_data_uri( $data ) {
		$qr_code = new QrCode( $data );
		$writer  = new PngWriter();
		$result  = $writer->write( $qr_code );

		return $result->getDataUri();
	}

	/**
	 * Generate QR code for TOTP provisioning URI.
	 *
	 * @param string $provisioning_uri The TOTP provisioning URI.
	 * @return string Data URI of the QR code image.
	 */
	public function generate_totp_qr_code( $provisioning_uri ) {
		return $this->generate_data_uri( $provisioning_uri );
	}
}
