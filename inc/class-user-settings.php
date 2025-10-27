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
	 * @param TotpManager     $totp_manager TOTP Manager instance.
	 * @param QrCodeGenerator $qr_generator QR Code Generator instance.
	 */
	public function __construct( TotpManager $totp_manager, QrCodeGenerator $qr_generator ) {
		$this->totp_manager = $totp_manager;
		$this->qr_generator = $qr_generator;

		add_action( 'show_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
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
					<div style="background: white; padding: 20px; display: inline-block; border: 1px solid #ddd; margin: 10px 0;">
						<img src="<?php echo esc_attr( $qr_code_data_uri ); ?>" alt="<?php esc_attr_e( 'QR Code', 'andromeda-2fa' ); ?>" />
					</div>
					<p class="description">
						<strong><?php esc_html_e( 'Secret Key:', 'andromeda-2fa' ); ?></strong>
						<code style="font-size: 14px; padding: 5px; background: #f0f0f0;"><?php echo esc_html( $secret ); ?></code>
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
	}
}
