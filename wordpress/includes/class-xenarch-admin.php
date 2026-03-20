<?php
/**
 * Xenarch admin settings page.
 *
 * Renders Settings -> Xenarch in the WordPress admin and handles
 * registration, site setup, and configuration forms.
 *
 * @package Xenarch
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Admin
 */
class Xenarch_Admin {

	/**
	 * API client instance.
	 *
	 * @var Xenarch_Api
	 */
	private $api;

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		$this->api = new Xenarch_Api();

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
	}

	/**
	 * Register the settings page under Settings.
	 */
	public function add_menu_page() {
		add_options_page(
			__( 'Xenarch Settings', 'xenarch' ),
			__( 'Xenarch', 'xenarch' ),
			'manage_options',
			'xenarch',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin CSS on the settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( 'settings_page_xenarch' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'xenarch-admin',
			XENARCH_PLUGIN_URL . 'assets/admin.css',
			array(),
			XENARCH_VERSION
		);
	}

	// ------------------------------------------------------------------
	// Form handling
	// ------------------------------------------------------------------

	/**
	 * Process form submissions on admin_init.
	 */
	public function handle_form_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['xenarch_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['xenarch_action'] ) );

			switch ( $action ) {
				case 'register':
					$this->handle_register();
					break;
				case 'add_site':
					$this->handle_add_site();
					break;
				case 'save_config':
					$this->handle_save_config();
					break;
			}
		}
	}

	/**
	 * Handle publisher registration.
	 */
	private function handle_register() {
		check_admin_referer( 'xenarch_register' );

		$email    = isset( $_POST['xenarch_email'] ) ? sanitize_email( wp_unslash( $_POST['xenarch_email'] ) ) : '';
		$password = isset( $_POST['xenarch_password'] ) ? sanitize_text_field( wp_unslash( $_POST['xenarch_password'] ) ) : '';

		if ( empty( $email ) || empty( $password ) ) {
			add_settings_error( 'xenarch', 'missing_fields', __( 'Email and password are required.', 'xenarch' ) );
			return;
		}

		$result = $this->api->register( $email, $password );

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'xenarch', 'register_error', $result->get_error_message() );
			return;
		}

		if ( ! empty( $result['api_key'] ) ) {
			update_option( 'xenarch_api_key', sanitize_text_field( $result['api_key'] ) );
			update_option( 'xenarch_email', $email );
			add_settings_error( 'xenarch', 'register_success', __( 'Registration successful! Your API key has been saved.', 'xenarch' ), 'updated' );
		} else {
			add_settings_error( 'xenarch', 'register_error', __( 'Registration succeeded but no API key was returned.', 'xenarch' ) );
		}
	}

	/**
	 * Handle site addition.
	 */
	private function handle_add_site() {
		check_admin_referer( 'xenarch_add_site' );

		$site_url = get_site_url();
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );

		if ( empty( $domain ) ) {
			add_settings_error( 'xenarch', 'domain_error', __( 'Could not detect site domain.', 'xenarch' ) );
			return;
		}

		$result = $this->api->add_site( $domain );

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'xenarch', 'site_error', $result->get_error_message() );
			return;
		}

		if ( ! empty( $result['id'] ) ) {
			update_option( 'xenarch_site_id', sanitize_text_field( $result['id'] ) );
		}
		if ( ! empty( $result['site_token'] ) ) {
			update_option( 'xenarch_site_token', sanitize_text_field( $result['site_token'] ) );
		}

		add_settings_error( 'xenarch', 'site_success', __( 'Site added successfully! l.js will now load on your frontend.', 'xenarch' ), 'updated' );
	}

	/**
	 * Handle configuration save (pricing + payout).
	 */
	private function handle_save_config() {
		check_admin_referer( 'xenarch_save_config' );

		$price  = isset( $_POST['xenarch_default_price'] ) ? sanitize_text_field( wp_unslash( $_POST['xenarch_default_price'] ) ) : '';
		$wallet = isset( $_POST['xenarch_payout_wallet'] ) ? sanitize_text_field( wp_unslash( $_POST['xenarch_payout_wallet'] ) ) : '';

		// Update local options regardless of API result.
		if ( '' !== $price ) {
			update_option( 'xenarch_default_price', $price );
		}
		if ( '' !== $wallet ) {
			update_option( 'xenarch_payout_wallet', $wallet );
		}

		$site_id = get_option( 'xenarch_site_id', '' );

		// Push pricing to API if site is registered.
		if ( ! empty( $site_id ) && '' !== $price ) {
			$pricing_result = $this->api->update_pricing( $site_id, (float) $price );

			if ( is_wp_error( $pricing_result ) ) {
				add_settings_error( 'xenarch', 'pricing_error', $pricing_result->get_error_message() );
				return;
			}
		}

		// Push payout wallet to API if provided.
		if ( ! empty( $wallet ) ) {
			$password = isset( $_POST['xenarch_confirm_password'] ) ? sanitize_text_field( wp_unslash( $_POST['xenarch_confirm_password'] ) ) : '';

			if ( ! empty( $password ) ) {
				$payout_result = $this->api->update_payout( $wallet, $password );

				if ( is_wp_error( $payout_result ) ) {
					add_settings_error( 'xenarch', 'payout_error', $payout_result->get_error_message() );
					return;
				}
			}
		}

		add_settings_error( 'xenarch', 'config_success', __( 'Settings saved.', 'xenarch' ), 'updated' );
	}

	// ------------------------------------------------------------------
	// Settings page rendering
	// ------------------------------------------------------------------

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key    = get_option( 'xenarch_api_key', '' );
		$site_id    = get_option( 'xenarch_site_id', '' );
		$site_token = get_option( 'xenarch_site_token', '' );
		$email      = get_option( 'xenarch_email', '' );
		$price      = get_option( 'xenarch_default_price', '0.003' );
		$wallet     = get_option( 'xenarch_payout_wallet', '' );

		$is_registered = ! empty( $api_key );
		$has_site      = ! empty( $site_id ) && ! empty( $site_token );
		?>
		<div class="wrap xenarch-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'xenarch' ); ?>

			<!-- Status Display -->
			<div class="xenarch-status-card">
				<h2><?php esc_html_e( 'Status', 'xenarch' ); ?></h2>
				<table class="xenarch-status-table">
					<tr>
						<td><?php esc_html_e( 'Connection', 'xenarch' ); ?></td>
						<td>
							<?php if ( $is_registered ) : ?>
								<span class="xenarch-status-dot xenarch-status-green"></span>
								<?php esc_html_e( 'Connected', 'xenarch' ); ?>
							<?php else : ?>
								<span class="xenarch-status-dot xenarch-status-red"></span>
								<?php esc_html_e( 'Not connected', 'xenarch' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $is_registered ) : ?>
						<tr>
							<td><?php esc_html_e( 'Email', 'xenarch' ); ?></td>
							<td><?php echo esc_html( $email ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $has_site ) : ?>
						<tr>
							<td><?php esc_html_e( 'Site Token', 'xenarch' ); ?></td>
							<td>
								<code><?php echo esc_html( substr( $site_token, 0, 8 ) . '...' . substr( $site_token, -4 ) ); ?></code>
							</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'l.js', 'xenarch' ); ?></td>
							<td>
								<span class="xenarch-status-dot xenarch-status-green"></span>
								<?php esc_html_e( 'Loading on frontend', 'xenarch' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<tr>
							<td><?php esc_html_e( 'l.js', 'xenarch' ); ?></td>
							<td>
								<span class="xenarch-status-dot xenarch-status-red"></span>
								<?php esc_html_e( 'Not active — add your site first', 'xenarch' ); ?>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<td><?php esc_html_e( 'Server-side gating', 'xenarch' ); ?></td>
						<td>
							<?php if ( $has_site ) : ?>
								<span class="xenarch-status-dot xenarch-status-green"></span>
								<?php
								printf(
									/* translators: %d: number of bot signatures */
									esc_html__( 'Active — blocking %d bot signatures', 'xenarch' ),
									count( Xenarch_Bot_Detect::get_signatures() ) + count( Xenarch_Bot_Detect::get_fetcher_signatures() )
								);
								?>
							<?php else : ?>
								<span class="xenarch-status-dot xenarch-status-red"></span>
								<?php esc_html_e( 'Inactive — add your site first', 'xenarch' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'pay.json', 'xenarch' ); ?></td>
						<td>
							<?php if ( $has_site ) : ?>
								<a href="<?php echo esc_url( get_site_url() . '/.well-known/pay.json' ); ?>" target="_blank">
									<?php echo esc_html( get_site_url() . '/.well-known/pay.json' ); ?>
								</a>
							<?php else : ?>
								<span class="xenarch-status-dot xenarch-status-red"></span>
								<?php esc_html_e( 'Not available', 'xenarch' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'xenarch.md', 'xenarch' ); ?></td>
						<td>
							<?php if ( $has_site ) : ?>
								<a href="<?php echo esc_url( get_site_url() . '/.well-known/xenarch.md' ); ?>" target="_blank">
									<?php echo esc_html( get_site_url() . '/.well-known/xenarch.md' ); ?>
								</a>
							<?php else : ?>
								<span class="xenarch-status-dot xenarch-status-red"></span>
								<?php esc_html_e( 'Not available', 'xenarch' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<?php if ( ! $is_registered ) : ?>
				<!-- Registration Section -->
				<div class="xenarch-card">
					<h2><?php esc_html_e( 'Step 1: Register', 'xenarch' ); ?></h2>
					<p><?php esc_html_e( 'Create a Xenarch publisher account to get started.', 'xenarch' ); ?></p>
					<form method="post" action="">
						<?php wp_nonce_field( 'xenarch_register' ); ?>
						<input type="hidden" name="xenarch_action" value="register" />
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="xenarch_email"><?php esc_html_e( 'Email', 'xenarch' ); ?></label>
								</th>
								<td>
									<input
										type="email"
										id="xenarch_email"
										name="xenarch_email"
										class="regular-text"
										required
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="xenarch_password"><?php esc_html_e( 'Password', 'xenarch' ); ?></label>
								</th>
								<td>
									<input
										type="password"
										id="xenarch_password"
										name="xenarch_password"
										class="regular-text"
										required
									/>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Register', 'xenarch' ) ); ?>
					</form>
				</div>

			<?php elseif ( ! $has_site ) : ?>
				<!-- Site Setup Section -->
				<div class="xenarch-card">
					<h2><?php esc_html_e( 'Step 2: Add Your Site', 'xenarch' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: detected domain */
							esc_html__( 'We detected your domain as %s. Click below to register it with Xenarch.', 'xenarch' ),
							'<strong>' . esc_html( wp_parse_url( get_site_url(), PHP_URL_HOST ) ) . '</strong>'
						);
						?>
					</p>
					<form method="post" action="">
						<?php wp_nonce_field( 'xenarch_add_site' ); ?>
						<input type="hidden" name="xenarch_action" value="add_site" />
						<?php submit_button( __( 'Add Site', 'xenarch' ) ); ?>
					</form>
				</div>

			<?php else : ?>
				<!-- Configuration Section -->
				<div class="xenarch-card">
					<h2><?php esc_html_e( 'Configuration', 'xenarch' ); ?></h2>
					<p><?php esc_html_e( 'Configure pricing and payout settings for your site.', 'xenarch' ); ?></p>
					<form method="post" action="">
						<?php wp_nonce_field( 'xenarch_save_config' ); ?>
						<input type="hidden" name="xenarch_action" value="save_config" />
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="xenarch_default_price"><?php esc_html_e( 'Default Price (USD)', 'xenarch' ); ?></label>
								</th>
								<td>
									<input
										type="number"
										id="xenarch_default_price"
										name="xenarch_default_price"
										value="<?php echo esc_attr( $price ); ?>"
										class="regular-text"
										step="0.001"
										min="0"
										max="1"
									/>
									<p class="description">
										<?php esc_html_e( 'Amount in USD charged per AI agent page view (max $1.00).', 'xenarch' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="xenarch_payout_wallet"><?php esc_html_e( 'Payout Wallet', 'xenarch' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										id="xenarch_payout_wallet"
										name="xenarch_payout_wallet"
										value="<?php echo esc_attr( $wallet ); ?>"
										class="regular-text"
										placeholder="0x..."
									/>
									<p class="description">
										<?php esc_html_e( 'Your USDC wallet address on Base network.', 'xenarch' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="xenarch_confirm_password"><?php esc_html_e( 'Confirm Password', 'xenarch' ); ?></label>
								</th>
								<td>
									<input
										type="password"
										id="xenarch_confirm_password"
										name="xenarch_confirm_password"
										class="regular-text"
									/>
									<p class="description">
										<?php esc_html_e( 'Required when changing payout wallet. Leave blank if only updating price.', 'xenarch' ); ?>
									</p>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Save Settings', 'xenarch' ) ); ?>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
