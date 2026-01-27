<?php
/**
 * Login Handler class - integrates 2FA into WordPress login flow.
 *
 * @package stutzmedien/2fa
 * @since   26.0.0
 * @license GPL-2.0-or-later
 */

namespace Andromeda\TwoFactorAuth;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Login Handler Class
 */
class LoginHandler {
	/**
	 * TOTP Manager instance.
	 *
	 * @var TotpManager
	 */
	private $totp_manager;

	/**
	 * User Settings instance.
	 *
	 * @var UserSettings
	 */
	private $user_settings;

	/**
	 * Recovery Manager instance.
	 *
	 * @var RecoveryManager|null
	 */
	private $recovery_manager;

	/**
	 * Transient key prefix for storing authentication data.
	 */
	const TRANSIENT_PREFIX = 'andromeda_2fa_auth_';

	/**
	 * Cookie name for 2FA session.
	 */
	const COOKIE_NAME = 'andromeda_2fa_token';

	/**
	 * Flag to track if 2FA was verified in this request.
	 *
	 * @var bool
	 */
	private $verified_this_request = false;

	/**
	 * Constructor.
	 *
	 * @param TotpManager          $totp_manager  TOTP Manager instance.
	 * @param UserSettings         $user_settings User Settings instance.
	 * @param RecoveryManager|null $recovery_manager Recovery Manager instance (optional).
	 */
	public function __construct( TotpManager $totp_manager, UserSettings $user_settings, ?RecoveryManager $recovery_manager = null ) {
		$this->totp_manager     = $totp_manager;
		$this->user_settings    = $user_settings;
		$this->recovery_manager = $recovery_manager ?? new RecoveryManager();

		add_filter( 'authenticate', array( $this, 'handle_2fa_verification' ), 10, 3 );
		add_filter( 'authenticate', array( $this, 'check_2fa_required' ), 30, 3 );
		add_action( 'login_form', array( $this, 'render_2fa_field' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_scripts' ) );
	}

	/**
	 * Handle 2FA verification before WordPress credential check.
	 *
	 * @param \WP_User|\WP_Error|null $user     User object or error.
	 *
	 * @return \WP_User|\WP_Error|null
	 */
	public function handle_2fa_verification( $user ) {
		$auth_data = $this->get_auth_data();

		if ( ! $auth_data ) {
			return $user;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_2fa_code.
		if ( ! isset( $_POST['andromeda_2fa_code'] ) ) {
			return $user;
		}

		return $this->verify_2fa_code( $auth_data );
	}

	/**
	 * Get or create a unique token for the current 2FA session.
	 *
	 * @return string
	 */
	private function get_session_token() {
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		}

		$token = wp_generate_password( 32, false );
		setcookie( self::COOKIE_NAME, $token, time() + 600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		return $token;
	}

	/**
	 * Store authentication data for 2FA verification.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $username Username.
	 */
	private function store_auth_data( $user_id, $username ) {
		$token = $this->get_session_token();
		$data  = array(
			'user_id'  => $user_id,
			'username' => $username,
		);
		set_transient( self::TRANSIENT_PREFIX . $token, $data, 600 );
	}

	/**
	 * Get stored authentication data.
	 *
	 * @return array|false
	 */
	private function get_auth_data() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return false;
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		$data  = get_transient( self::TRANSIENT_PREFIX . $token );

		return is_array( $data ) ? $data : false;
	}

	/**
	 * Clear stored authentication data.
	 */
	private function clear_auth_data() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		delete_transient( self::TRANSIENT_PREFIX . $token );
		setcookie( self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Check if we're in 2FA mode.
	 *
	 * @return bool
	 */
	private function is_2fa_mode() {
		return (bool) $this->get_auth_data();
	}

	/**
	 * Check if 2FA is required and handle verification.
	 *
	 * @param \WP_User|\WP_Error|null $user     User object or error.
	 * @param string                  $username Username.
	 * @return \WP_User|\WP_Error
	 */
	public function check_2fa_required( $user, $username ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( ! $user instanceof \WP_User ) {
			return $user;
		}

		if ( $this->verified_this_request ) {
			return $user;
		}

		if ( $this->get_auth_data() ) {
			return $user;
		}

		if ( ! $this->user_settings->is_enabled_for_user( $user->ID ) ) {
			return $user;
		}

		$this->store_auth_data( $user->ID, $username );

		wp_safe_redirect( add_query_arg( 'andromeda_2fa', '1', wp_login_url() ) );
		exit; // @codeCoverageIgnore
	}

	/**
	 * Verify 2FA code.
	 *
	 * @param array $auth_data Stored authentication data.
	 * @return \WP_User|\WP_Error
	 */
	private function verify_2fa_code( $auth_data ) {
		if ( ! \andromeda_2fa_verify_nonce( 'andromeda_2fa_nonce', 'andromeda_2fa_verify' ) ) {
			return new \WP_Error(
				'2fa_invalid_nonce',
				sprintf(
					'<strong>%s</strong><br>%s',
					__( 'Nonce Error', 'andromeda-2fa' ),
					__( 'Invalid security token. Please try again.', 'andromeda-2fa' )
				)
			);
		}

		$code = isset( $_POST['andromeda_2fa_code'] ) ? sanitize_text_field( wp_unslash( $_POST['andromeda_2fa_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via andromeda_2fa_verify_nonce above

		if ( empty( $code ) ) {
			return new \WP_Error(
				'2fa_code_required',
				sprintf(
					'<strong>%s</strong><br>%s',
					__( 'Two-Factor Authentication', 'andromeda-2fa' ),
					__( 'Please enter your authentication code.', 'andromeda-2fa' )
				)
			);
		}

		$user_id = $auth_data['user_id'];
		$secret  = $this->user_settings->get_user_secret( $user_id );

		if ( $this->totp_manager->verify_code( $secret, $code ) ) {
			$this->clear_auth_data();
			$this->verified_this_request = true;

			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return new \WP_Error( 'invalid_user', __( 'Invalid user.', 'andromeda-2fa' ) );
			}

			return $user;
		}

		if ( $this->recovery_manager && $this->recovery_manager->consume_recovery_code( (int) $user_id, (string) $code ) ) {
			$this->clear_auth_data();
			$this->verified_this_request = true;

			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return new \WP_Error( 'invalid_user', __( 'Invalid user.', 'andromeda-2fa' ) );
			}

			return $user;
		}

		return new \WP_Error(
			'2fa_invalid_code',
			sprintf(
				'<strong>%s</strong><br>%s',
				__( 'Invalid Code', 'andromeda-2fa' ),
				__( 'The authentication code is incorrect.', 'andromeda-2fa' )
			)
		);
	}

	/**
	 * Render 2FA code input field on login form.
	 */
	public function render_2fa_field() {
		if ( ! $this->is_2fa_mode() ) return;

		$auth_data = $this->get_auth_data();
		?>
		<input type="hidden" name="log" value="<?php echo esc_attr( $auth_data['username'] ); ?>" autocomplete="username" />
		<?php wp_nonce_field( 'andromeda_2fa_verify', 'andromeda_2fa_nonce' ); ?>
		
		<p class="andromeda-2fa-info">
			<strong><?php esc_html_e( 'Two-Factor Authentication', 'andromeda-2fa' ); ?></strong><br>
			<?php esc_html_e( 'Please enter your authentication code or a recovery code.', 'andromeda-2fa' ); ?>
		</p>
		
		<p class="andromeda-2fa-code-field">
			<label for="andromeda_2fa_code">
				<?php esc_html_e( 'Authentication Code', 'andromeda-2fa' ); ?>
			</label>
			<input type="text" 
				name="andromeda_2fa_code" 
				id="andromeda_2fa_code" 
				class="input" 
				maxlength="36" 
				pattern="(\\d{6})|([A-Za-z0-9-]{12,36})"
				autocomplete="one-time-code"
				inputmode="text"
				placeholder="<?php esc_attr_e( '000000', 'andromeda-2fa' ); ?>"
				autofocus
				required />
		</p>
		<?php
	}

	/**
	 * Enqueue scripts and styles for login page.
	 */
	public function enqueue_login_scripts() {
		if ( ! $this->is_2fa_mode() ) return;

		wp_enqueue_style(
			'andromeda-2fa-style',
			plugins_url( 'src/css/login-style.css', ANDROMEDA_2FA_PLUGIN_FILE ),
			array(),
			ANDROMEDA_2FA_VERSION
		);

		wp_enqueue_script(
			'andromeda-2fa-script',
			plugins_url( 'src/js/login-script.js', ANDROMEDA_2FA_PLUGIN_FILE ),
			array(),
			ANDROMEDA_2FA_VERSION,
			true
		);
	}
}
