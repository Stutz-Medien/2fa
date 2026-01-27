<?php
/**
 * Recovery Manager Test
 *
 * @package stutzmedien/2fa
 */

namespace Andromeda\TwoFactorAuth\Tests\Unit;

use Andromeda\TwoFactorAuth\RecoveryManager;
use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey\Functions;

class RecoveryManagerTest extends TestCase {

	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	private RecoveryManager $recovery_manager;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		$this->recovery_manager = new RecoveryManager();
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_normalize_code_normalizes_to_canonical_format() {
		$this->assertSame( 'ABCD-EFGH-IJKL', $this->recovery_manager->normalize_code( 'abcd-efgh-ijkl' ) );
		$this->assertSame( 'ABCD-EFGH-IJKL', $this->recovery_manager->normalize_code( ' a b c d  e f g h  i j k l ' ) );
		$this->assertSame( 'ABCD-EFGH-IJKL', $this->recovery_manager->normalize_code( 'a!b@c#d$e%f^g&h*i(j)k_l' ) );
	}

	public function test_normalize_code_returns_empty_string_for_invalid_length() {
		$this->assertSame( '', $this->recovery_manager->normalize_code( '' ) );
		$this->assertSame( '', $this->recovery_manager->normalize_code( 'ABC' ) );
		$this->assertSame( '', $this->recovery_manager->normalize_code( 'ABCD-EFGH' ) );
		$this->assertSame( '', $this->recovery_manager->normalize_code( 'ABCD-EFGH-IJKL-MNOP' ) );
	}

	public function test_consume_recovery_code_returns_false_when_input_cannot_be_normalized() {
		$user_id = 1;

		Functions\expect( 'get_user_meta' )->never();
		Functions\expect( 'update_user_meta' )->never();

		$this->assertFalse( $this->recovery_manager->consume_recovery_code( $user_id, '---' ) );
	}

	public function test_consume_recovery_code_returns_false_when_no_codes_stored() {
		$user_id = 2;

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, RecoveryManager::META_RECOVERY_CODES, true )
			->andReturn( array() );

		Functions\expect( 'update_user_meta' )->never();

		$this->assertFalse( $this->recovery_manager->consume_recovery_code( $user_id, 'ABCD-EFGH-IJKL' ) );
	}

	public function test_consume_recovery_code_consumes_matching_password_hash() {
		$user_id = 3;

		$code_to_consume = 'ABCD-EFGH-IJKL';
		$other_code      = 'WXYZ-1234-5678';
		$codes = array(
			password_hash( $code_to_consume, PASSWORD_DEFAULT ),
			password_hash( $other_code, PASSWORD_DEFAULT ),
		);

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, RecoveryManager::META_RECOVERY_CODES, true )
			->andReturn( $codes );

		Functions\expect( 'update_user_meta' )
			->once()
			->with( $user_id, RecoveryManager::META_RECOVERY_CODES, array( $codes[1] ) )
			->andReturn( true );

		$this->assertTrue( $this->recovery_manager->consume_recovery_code( $user_id, 'abcd efgh ijkl' ) );
	}

	public function test_consume_recovery_code_consumes_matching_wp_legacy_hash() {
		$user_id = 4;

		$legacy_hash = 'legacyhash';
		$codes       = array( $legacy_hash );

		Functions\when( 'wp_check_password' )->alias(
			function( $password, $hash ) use ( $legacy_hash ) {
				return ( 'ABCD-EFGH-IJKL' === $password && $legacy_hash === $hash );
			}
		);

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, RecoveryManager::META_RECOVERY_CODES, true )
			->andReturn( $codes );

		Functions\expect( 'update_user_meta' )
			->once()
			->with( $user_id, RecoveryManager::META_RECOVERY_CODES, array() )
			->andReturn( true );

		$this->assertTrue( $this->recovery_manager->consume_recovery_code( $user_id, 'ABCD EFGH IJKL' ) );
	}

	public function test_consume_recovery_code_returns_false_when_no_stored_hash_matches() {
		$user_id = 5;

		$non_matching_input = 'ABCD-EFGH-IJKL';
		$hashes = array(
			password_hash( 'WXYZ-1234-5678', PASSWORD_DEFAULT ),
			'legacyhash',
		);

		Functions\when( 'wp_check_password' )->alias(
			function() {
				return false;
			}
		);

		Functions\expect( 'get_user_meta' )
			->once()
			->with( $user_id, RecoveryManager::META_RECOVERY_CODES, true )
			->andReturn( $hashes );

		Functions\expect( 'update_user_meta' )->never();

		$this->assertFalse( $this->recovery_manager->consume_recovery_code( $user_id, $non_matching_input ) );
	}
}
