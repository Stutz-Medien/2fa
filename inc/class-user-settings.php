<?php
/**
 * User Settings class - manages 2FA user settings and profile page.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 */

namespace Andromeda\TwoFactorAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * User Settings Class
 */
class UserSettings {
	/**
	 * TOTP Manager instance.
	 *
	 * @var TotpManager
	 */
	private $totp_manager;

	/**
	 * QR Code Generator instance.
	 *
	 * @var QrCodeGenerator
	 */
	private $qr_generator;

	/**
	 * Recovery Manager instance.
	 *
	 * @var RecoveryManager|null
	 */
	private $recovery_manager;

	/**
	 * User meta key for 2FA secret.
	 */
	const META_SECRET = 'andromeda_2fa_secret';

	/**
	 * User meta key for 2FA enabled status.
	 */
	const META_ENABLED = 'andromeda_2fa_enabled';

	/**
	 * Constructor.
	 *
	 * @param TotpManager          $totp_manager TOTP Manager instance.
	 * @param QrCodeGenerator      $qr_generator QR Code Generator instance.
	 * @param RecoveryManager|null $recovery_manager Recovery Manager instance (optional).
	 */
	public function __construct( TotpManager $totp_manager, QrCodeGenerator $qr_generator, ?RecoveryManager $recovery_manager = null ) {
		$this->totp_manager     = $totp_manager;
		$this->qr_generator     = $qr_generator;
		$this->recovery_manager = $recovery_manager ?? new RecoveryManager();

		add_action( 'show_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'admin_footer', array( $this, 'enqueue_profile_scripts' ) );
	}

	/**
	 * Check if 2FA is enabled for a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_enabled_for_user( $user_id ) {
		return (bool) get_user_meta( $user_id, self::META_ENABLED, true );
	}

	/**
	 * Get user's 2FA secret.
	 *
	 * @param int $user_id User ID.
	 * @return string|false
	 */
	public function get_user_secret( $user_id ) {
		return get_user_meta( $user_id, self::META_SECRET, true );
	}

	/**
	 * Set user's 2FA secret.
	 *
	 * @param int    $user_id User ID.
	 * @param string $secret  The TOTP secret.
	 * @return bool
	 */
	public function set_user_secret( $user_id, $secret ) {
		return update_user_meta( $user_id, self::META_SECRET, $secret );
	}

	/**
	 * Enable 2FA for a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function enable_for_user( $user_id ) {
		return update_user_meta( $user_id, self::META_ENABLED, true );
	}

	/**
	 * Disable 2FA for a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function disable_for_user( $user_id ) {
		delete_user_meta( $user_id, self::META_ENABLED );
		delete_user_meta( $user_id, self::META_SECRET );

		if ( $this->recovery_manager ) {
			delete_user_meta( $user_id, RecoveryManager::META_RECOVERY_CODES );
		}

		return true;
	}

	/**
	 * Render 2FA fields on user profile page.
	 *
	 * @param \WP_User $user The user object.
	 */
	public function render_user_profile_fields( $user ) {
		$is_enabled = $this->is_enabled_for_user( $user->ID );
		$secret     = $this->get_user_secret( $user->ID );

		if ( ! $secret ) {
			$totp   = $this->totp_manager->generate_totp();
			$secret = $totp->getSecret();
		}

		$site_name        = get_bloginfo( 'name' );
		$provisioning_uri = $this->totp_manager->get_provisioning_uri( $secret, $user->user_email, $site_name );
		$qr_code_data_uri = $this->qr_generator->generate_totp_qr_code( $provisioning_uri );

		wp_nonce_field( 'andromeda_2fa_settings', 'andromeda_2fa_nonce' );
		?>
		<h2><?php esc_html_e( 'Two-Factor Authentication', 'andromeda-2fa' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable 2FA', 'andromeda-2fa' ); ?></th>
				<td>
					<label>
						<input type="checkbox" 
								name="andromeda_2fa_enabled" 
								value="1" 
								<?php checked( $is_enabled ); ?> />
						<?php esc_html_e( 'Enable Two-Factor Authentication for my account', 'andromeda-2fa' ); ?>
					</label>
					<input type="hidden" name="andromeda_2fa_secret" value="<?php echo esc_attr( $secret ); ?>" />
				</td>
			</tr>
			<?php if ( ! $is_enabled ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Setup QR Code', 'andromeda-2fa' ); ?></th>
				<td>
				<p class="description">
					<?php esc_html_e( 'Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.) before enabling 2FA.', 'andromeda-2fa' ); ?>
				</p>
				<div class="andromeda-2fa-qr-code">
					<img src="<?php echo esc_attr( $qr_code_data_uri ); ?>" alt="<?php esc_attr_e( 'QR Code', 'andromeda-2fa' ); ?>" />
				</div>
				<p class="description">
					<strong><?php esc_html_e( 'Secret Key:', 'andromeda-2fa' ); ?></strong>
					<code class="andromeda-2fa-secret-key"><?php echo esc_html( $secret ); ?></code>
				</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Verify Setup', 'andromeda-2fa' ); ?></th>
				<td>
					<input type="text" 
							name="andromeda_2fa_verify_code" 
							class="regular-text" 
							maxlength="6" 
							pattern="[0-9]{6}"
							placeholder="<?php esc_attr_e( 'Enter 6-digit code', 'andromeda-2fa' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Enter the 6-digit code from your authenticator app to verify the setup before enabling.', 'andromeda-2fa' ); ?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<?php if ( $is_enabled && $this->recovery_manager ) : ?>
			<?php
			$transient_key = 'andromeda_2fa_plain_codes_' . (int) $user->ID;
			$plain_codes   = ( function_exists( 'get_transient' ) ) ? get_transient( $transient_key ) : false;
			if ( is_array( $plain_codes ) && ! empty( $plain_codes ) ) :
				$download_text = implode( "\n", array_map( 'esc_html', $plain_codes ) );
				$download_href = 'data:text/plain;charset=utf-8,' . rawurlencode( $download_text );
				?>
				<div class="andromeda-2fa-recovery-codes">
					<h3><?php esc_html_e( 'Your recovery codes', 'andromeda-2fa' ); ?></h3>
					<p class="description andromeda-2fa-warning"><?php esc_html_e( '⚠️ Store these codes in a safe place. Each code can be used once if you lose access to your authenticator app. These codes will only be shown once.', 'andromeda-2fa' ); ?></p>
					<div class="andromeda-2fa-codes-grid">
						<?php foreach ( $plain_codes as $code ) : ?>
							<code class="andromeda-2fa-code-item"><?php echo esc_html( $code ); ?></code>
						<?php endforeach; ?>
					</div>
					<p class="andromeda-2fa-actions">
						<a class="button button-primary" href="<?php echo esc_attr( $download_href ); ?>" download="andromeda-recovery-codes.txt">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Download Codes', 'andromeda-2fa' ); ?>
						</a>
						<button type="button" class="button andromeda-copy-codes" data-clipboard-text="<?php echo esc_attr( implode( "\n", $plain_codes ) ); ?>">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy to Clipboard', 'andromeda-2fa' ); ?>
						</button>
					</p>
				</div>
				<?php if ( function_exists( 'delete_transient' ) ) delete_transient( $transient_key ); ?>
			<?php else : ?>
				<p class="description">
					<?php
					$remaining = $this->recovery_manager->count_remaining_codes( (int) $user->ID );
					/* translators: %d: number of remaining recovery codes */
					echo esc_html( sprintf( __( 'Recovery codes remaining: %d', 'andromeda-2fa' ), (int) $remaining ) );
					?>
				</p>

				<?php if ( 0 === $remaining ) : ?>
					<p>
						<button type="submit" name="andromeda_2fa_regen_codes" value="1" class="button">
							<?php esc_html_e( 'Generate Recovery Codes', 'andromeda-2fa' ); ?>
						</button>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save user profile 2FA settings.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_user_profile_fields( $user_id ) {
		if ( ! isset( $_POST['andromeda_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['andromeda_2fa_nonce'] ) ), 'andromeda_2fa_settings' ) ) return;

		if ( ! current_user_can( 'edit_user', $user_id ) ) return;

		$wants_enabled = isset( $_POST['andromeda_2fa_enabled'] );
		$secret        = isset( $_POST['andromeda_2fa_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['andromeda_2fa_secret'] ) ) : '';
		$code          = isset( $_POST['andromeda_2fa_verify_code'] ) ? sanitize_text_field( wp_unslash( $_POST['andromeda_2fa_verify_code'] ) ) : '';
		$is_enabled    = $this->is_enabled_for_user( $user_id );

		if ( isset( $_POST['andromeda_2fa_regen_codes'] ) && $is_enabled && $this->recovery_manager ) {
			$codes = $this->recovery_manager->generate_recovery_codes();
			$this->recovery_manager->store_recovery_codes( (int) $user_id, $codes['hashed'] );

			if ( ! function_exists( 'set_transient' ) ) return;

			set_transient( 'andromeda_2fa_plain_codes_' . (int) $user_id, $codes['plain'], 15 * MINUTE_IN_SECONDS );

			return;
		}

		if ( $wants_enabled && ! $is_enabled ) {
			$this->handle_enable_2fa( $user_id, $secret, $code );

			return;
		}

		if ( ! $wants_enabled && $is_enabled ) {
			$this->disable_for_user( $user_id );

			return;
		}

		if ( ! $wants_enabled && ! $is_enabled && ! empty( $secret ) ) {
			$this->set_user_secret( $user_id, $secret );
		}
	}

	/**
	 * Handle enabling 2FA for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $secret  TOTP secret.
	 * @param string $code    Verification code.
	 */
	private function handle_enable_2fa( $user_id, $secret, $code ) {
		if ( empty( $code ) ) {
			add_action(
				'user_profile_update_errors',
				function ( $errors ) {
					$errors->add(
						'2fa_no_code',
						__( 'Please enter a verification code from your authenticator app to enable 2FA.', 'andromeda-2fa' )
					);
				}
			);

			return;
		}

		if ( ! $this->totp_manager->verify_code( $secret, $code ) ) {
			add_action(
				'user_profile_update_errors',
				function ( $errors ) {
					$errors->add(
						'2fa_invalid_code',
						__( 'Invalid verification code. Please try again.', 'andromeda-2fa' )
					);
				}
			);

			return;
		}

		$this->set_user_secret( $user_id, $secret );
		$this->enable_for_user( $user_id );

		if ( $this->recovery_manager ) {
			$codes = $this->recovery_manager->generate_recovery_codes();
			$this->recovery_manager->store_recovery_codes( (int) $user_id, $codes['hashed'] );

			if ( ! function_exists( 'set_transient' ) ) return;

			set_transient( 'andromeda_2fa_plain_codes_' . (int) $user_id, $codes['plain'], 15 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Enqueue scripts for user profile page.
	 */
	public function enqueue_profile_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'profile', 'user-edit' ), true ) ) return;
		?>
		<style>
			.andromeda-2fa-qr-code {
				background: white;
				padding: 20px;
				display: inline-block;
				border: 1px solid #ddd;
				margin: 10px 0;
				border-radius: 4px;
			}

			.andromeda-2fa-secret-key {
				font-size: 14px;
				padding: 5px 8px;
				background: #f0f0f0;
				border-radius: 3px;
				font-family: 'Courier New', Courier, monospace;
				letter-spacing: 1px;
			}

			.andromeda-2fa-recovery-codes {
				margin: 1.5em 0;
				padding: 20px;
				background: #fff;
				border: 2px solid #d63638;
				border-radius: 4px;
			}

			.andromeda-2fa-recovery-codes h3 {
				margin-top: 0;
				color: #d63638;
			}

			.andromeda-2fa-warning {
				padding: 12px;
				background: #fcf0f1;
				border-left: 4px solid #d63638;
				margin: 10px 0 20px 0;
				font-weight: 500;
			}

			.andromeda-2fa-codes-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 12px;
				margin: 20px 0;
			}

			.andromeda-2fa-code-item {
				background: #2c3338;
				color: #50fa7b;
				padding: 12px 16px;
				border-radius: 4px;
				font-family: monospace;
				font-size: 14px;
				font-weight: 600;
				letter-spacing: 1px;
				text-align: center;
				display: block;
				border: 1px solid #3c434a;
			}

			.andromeda-2fa-actions {
				margin-top: 20px;
				padding-top: 20px;
				border-top: 1px solid #dcdcde;
			}

			.andromeda-2fa-actions .button {
				margin-right: 10px;
			}

			.andromeda-2fa-actions .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				vertical-align: text-top;
				margin-right: 4px;
			}
		</style>

		<script>
			const copyToClipboard = (text) => {
				if (navigator.clipboard?.writeText) {
					return navigator.clipboard.writeText(text).catch(() => {
						copyToClipboardFallback(text);
					});
				}
				
				copyToClipboardFallback(text);
			};

			const copyToClipboardFallback = (text) => {
				const textarea = document.createElement('textarea');
				textarea.value = text;
				document.body.appendChild(textarea);
				textarea.select();
				
				try {
					document.execCommand('copy');
				} catch  {
					// Silent fail
				}
				
				document.body.removeChild(textarea);
			};

			const showCopySuccess = (button) => {
				const originalText = button.textContent;
				button.textContent = '✓ Copied!';
				button.disabled = true;
				
				setTimeout(() => {
					button.textContent = originalText;
					button.disabled = false;
				}, 2000);
			};

			const handleRecoveryCodes = () => {
				const button = document.querySelector('.andromeda-copy-codes');
				if (!button) return;

				const clipboardText = button.getAttribute('data-clipboard-text') || '';

				button.addEventListener('click', () => {
					copyToClipboard(clipboardText);
					showCopySuccess(button);
				});
			};

			document.addEventListener('DOMContentLoaded', handleRecoveryCodes);
		</script>
		<?php
	}
}
