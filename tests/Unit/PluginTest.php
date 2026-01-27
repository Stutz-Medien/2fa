<?php
/**
 * Plugin Test
 *
 * @package stutzmedien/2fa
 */

namespace Andromeda\TwoFactorAuth\Tests\Unit;

use Andromeda\TwoFactorAuth\LoginHandler;
use Andromeda\TwoFactorAuth\Plugin;
use Andromeda\TwoFactorAuth\QrCodeGenerator;
use Andromeda\TwoFactorAuth\RecoveryManager;
use Andromeda\TwoFactorAuth\TotpManager;
use Andromeda\TwoFactorAuth\UserSettings;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'add_filter' )->justReturn( null );
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constructor_initializes_components() {
		$plugin = new Plugin();

		$reflection = new \ReflectionClass( $plugin );

		$totp_manager = $reflection->getProperty( 'totp_manager' );
		$this->assertInstanceOf( TotpManager::class, $totp_manager->getValue( $plugin ) );

		$qr_generator = $reflection->getProperty( 'qr_generator' );
		$this->assertInstanceOf( QrCodeGenerator::class, $qr_generator->getValue( $plugin ) );

		$recovery_manager = $reflection->getProperty( 'recovery_manager' );
		$this->assertInstanceOf( RecoveryManager::class, $recovery_manager->getValue( $plugin ) );

		$user_settings = $reflection->getProperty( 'user_settings' );
		$this->assertInstanceOf( UserSettings::class, $user_settings->getValue( $plugin ) );

		$login_handler = $reflection->getProperty( 'login_handler' );
		$this->assertInstanceOf( LoginHandler::class, $login_handler->getValue( $plugin ) );
	}
}
