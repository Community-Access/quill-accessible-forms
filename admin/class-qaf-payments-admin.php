<?php
/**
 * QAF Forms > Payments: keys, webhooks, test and live mode, and a report.
 *
 * Built for the keyboard and a screen reader first: native form controls,
 * each with a visible label and its hint tied to it; fieldsets with legends
 * for each provider and environment; results announced in a notice that
 * takes focus; readiness written as words in a captioned table, never only
 * as a colour or an icon. Secrets are write-only: the screen says whether
 * one is saved, and never shows it, any part of it, or its length.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payments_Admin class.
 */
class QAF_Payments_Admin {

	const PAGE       = 'qaf-payments';
	const CAPABILITY = 'qaf_manage_payments';

	/**
	 * Hook in.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_qaf_payments_save', array( $this, 'save' ) );
		add_action( 'admin_post_qaf_payments_action', array( $this, 'action' ) );
	}

	/**
	 * Whether the current user may manage payments.
	 *
	 * @return bool Allowed.
	 */
	public static function allowed() {
		return current_user_can( 'manage_options' ) || current_user_can( self::CAPABILITY );
	}

	/**
	 * Add the page under QAF Forms.
	 */
	public function menu() {
		add_submenu_page(
			'qaf-forms',
			__( 'Payments', 'quill-accessible-forms' ),
			__( 'Payments', 'quill-accessible-forms' ),
			current_user_can( 'manage_options' ) ? 'manage_options' : self::CAPABILITY,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Labels for the fields, per provider.
	 *
	 * @return array Fields.
	 */
	private static function fields() {
		return array(
			'stripe' => array(
				'publishable_key' => array( __( 'Publishable key', 'quill-accessible-forms' ), false, __( 'Starts with pk_test_ or pk_live_. This one is not secret: the payment page uses it.', 'quill-accessible-forms' ) ),
				'secret_key'      => array( __( 'Secret key', 'quill-accessible-forms' ), true, __( 'Starts with sk_ or, for a restricted key, rk_. Keep it secret.', 'quill-accessible-forms' ) ),
				'webhook_secret'  => array( __( 'Webhook signing secret', 'quill-accessible-forms' ), true, __( 'Starts with whsec_. Create Webhook fills this in for you; or paste the one Stripe shows for your endpoint.', 'quill-accessible-forms' ) ),
			),
			'paypal' => array(
				'client_id'  => array( __( 'Client ID', 'quill-accessible-forms' ), false, __( 'From your PayPal app. This one is not secret: the payment page uses it.', 'quill-accessible-forms' ) ),
				'secret'     => array( __( 'Secret', 'quill-accessible-forms' ), true, __( 'From the same PayPal app. Keep it secret.', 'quill-accessible-forms' ) ),
				'webhook_id' => array( __( 'Webhook ID', 'quill-accessible-forms' ), true, __( 'Create Webhook fills this in for you; or paste the ID PayPal shows for your webhook.', 'quill-accessible-forms' ) ),
			),
		);
	}

	/**
	 * An environment's name for a provider.
	 *
	 * @param string $provider Provider.
	 * @param string $env      Environment.
	 * @return string Name.
	 */
	private static function env_name( $provider, $env ) {
		if ( $env === 'live' ) {
			return __( 'live', 'quill-accessible-forms' );
		}
		return $provider === 'paypal' ? __( 'sandbox', 'quill-accessible-forms' ) : __( 'test', 'quill-accessible-forms' );
	}

	/**
	 * Leave a notice for the next page load.
	 *
	 * @param string $text Text.
	 * @param bool   $ok   Success (else a problem).
	 */
	private static function notice( $text, $ok ) {
		set_transient( 'qaf_payments_notice_' . get_current_user_id(), array( 'text' => $text, 'ok' => $ok ), 60 );
	}

	/**
	 * Back to the page.
	 */
	private static function back() {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * A submitted key, cleaned: no tags, no spaces anywhere (keys never have them).
	 *
	 * @param string $name Field name.
	 * @return string|null Value, or null when the field was not sent.
	 */
	private static function posted( $name ) {
		if ( ! isset( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in save().
			return null;
		}
		return preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Save the form.
	 */
	public function save() {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'You do not have permission to change payment settings.', 'quill-accessible-forms' ), 403 );
		}
		check_admin_referer( 'qaf_payments_save' );

		$mode = self::posted( 'qaf_mode' ) === 'live' ? 'live' : 'test';
		if ( $mode === 'live' && QAF_Payment_Settings::mode() !== 'live' && self::posted( 'qaf_confirm_live' ) !== '1' ) {
			self::notice( __( 'Nothing was saved. To take real payments, check "I have finished testing" as well as choosing Live.', 'quill-accessible-forms' ), false );
			self::back();
		}
		QAF_Payment_Settings::set_mode( $mode );

		foreach ( self::fields() as $provider => $fields ) {
			foreach ( QAF_Payment_Settings::ENVIRONMENTS as $env ) {
				foreach ( $fields as $name => $info ) {
					$key   = "qaf_{$provider}_{$env}_{$name}";
					$value = self::posted( $key );
					if ( ! $info[1] ) {
						if ( $value !== null ) {
							QAF_Payment_Settings::set_public( $provider, $env, $name, $value );
						}
						continue;
					}
					// Secrets are write-only: blank keeps the saved one; the
					// Remove check box is the only way to clear it.
					if ( self::posted( $key . '_remove' ) === '1' ) {
						QAF_Payment_Settings::set_secret( $provider, $env, $name, '' );
					} elseif ( $value !== null && $value !== '' ) {
						QAF_Payment_Settings::set_secret( $provider, $env, $name, $value );
					}
				}
			}
		}
		$problems = array();
		foreach ( QAF_Payment_Config::PROVIDERS as $provider ) {
			$problem = QAF_Payment_Settings::shape_problem( $provider, $mode );
			$any     = QAF_Payment_Settings::get_public( $provider, $mode, $provider === 'stripe' ? 'publishable_key' : 'client_id' ) !== '';
			if ( $problem !== '' && $any ) {
				$problems[] = QAF_Payment_Config::provider_name( $provider ) . ': ' . $problem;
			}
		}
		$text = $mode === 'live'
			? __( 'Saved. The site is in live mode: published payment forms in live mode take real money.', 'quill-accessible-forms' )
			: __( 'Saved. The site is in test mode: no real money is taken.', 'quill-accessible-forms' );
		if ( $problems ) {
			$text .= ' ' . implode( ' ', $problems );
		}
		self::notice( $text . ' ' . __( 'Use Check Connection after changing keys.', 'quill-accessible-forms' ), ! $problems );
		self::back();
	}

	/**
	 * Run Check Connection or Create Webhook.
	 */
	public function action() {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'You do not have permission to change payment settings.', 'quill-accessible-forms' ), 403 );
		}
		check_admin_referer( 'qaf_payments_action' );
		$provider = self::posted( 'qaf_provider' );
		$env      = self::posted( 'qaf_env' );
		$what     = self::posted( 'qaf_do' );
		if ( ! in_array( $provider, QAF_Payment_Config::PROVIDERS, true ) || ! in_array( $env, QAF_Payment_Settings::ENVIRONMENTS, true ) || ! in_array( $what, array( 'check', 'webhook' ), true ) ) {
			self::back();
		}
		$gateway = QAF_Payment_Service::instance()->gateway( $provider );
		$name    = QAF_Payment_Config::provider_name( $provider ) . ' (' . self::env_name( $provider, $env ) . ')';
		if ( $what === 'webhook' ) {
			if ( ! QAF_Payment_Settings::configured( $provider, $env ) ) {
				self::notice( $name . ': ' . __( 'Check the connection first; a webhook needs working keys.', 'quill-accessible-forms' ), false );
				self::back();
			}
			$result = $gateway->create_webhook( $env );
			if ( $result['ok'] ) {
				QAF_Payment_Settings::record_check( $provider, $env, 'webhook', true, $result['message'] );
			}
			self::notice( $name . ': ' . $result['message'], $result['ok'] );
			self::back();
		}
		$result = $gateway->verify_credentials( $env );
		QAF_Payment_Settings::record_check( $provider, $env, 'credentials', $result['ok'], $result['message'] );
		$text = $name . ': ' . $result['message'];
		$ok   = $result['ok'];
		if ( $ok && $provider === 'paypal' && QAF_Payment_Settings::has_secret( 'paypal', $env, 'webhook_id' ) ) {
			$hook = $gateway->verify_webhook( $env );
			QAF_Payment_Settings::record_check( 'paypal', $env, 'webhook', $hook['ok'], $hook['message'] );
			$text .= ' ' . $hook['message'];
			$ok    = $ok && $hook['ok'];
		}
		if ( $ok && $provider === 'stripe' && ! QAF_Payment_Settings::webhook_ready( 'stripe', $env ) ) {
			$text .= ' ' . __( 'The webhook is not ready yet: use Create Webhook, or paste the signing secret and send a test event from Stripe so the site can verify it.', 'quill-accessible-forms' );
		}
		self::notice( $text, $ok );
		self::back();
	}

	/**
	 * Draw the page.
	 */
	public function render() {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'You do not have permission to see payment settings.', 'quill-accessible-forms' ), 403 );
		}
		$mode   = QAF_Payment_Settings::mode();
		$notice = get_transient( 'qaf_payments_notice_' . get_current_user_id() );
		delete_transient( 'qaf_payments_notice_' . get_current_user_id() );

		echo '<div class="wrap qaf-payments-admin">';
		echo '<h1>' . esc_html__( 'Payments', 'quill-accessible-forms' ) . '</h1>';
		if ( is_array( $notice ) ) {
			$class = $notice['ok'] ? 'notice-success' : 'notice-error';
			$role  = $notice['ok'] ? 'status' : 'alert';
			echo '<div id="qaf-payments-notice" class="notice ' . esc_attr( $class ) . '" role="' . esc_attr( $role ) . '" tabindex="-1"><p>' . esc_html( $notice['text'] ) . '</p></div>';
			echo '<script>document.getElementById("qaf-payments-notice").focus();</script>';
		}
		echo '<p>' . esc_html__( 'Forms take payment through Stripe, PayPal, or both. Keys stay on this site: QUILL and the payment page never see the secret ones. Start in test mode, try every step with test cards and sandbox accounts, then switch to live.', 'quill-accessible-forms' ) . '</p>';

		$this->render_readiness( $mode );
		$this->render_settings( $mode );
		$this->render_actions();
		$this->render_report();
		echo '</div>';
	}

	/**
	 * The readiness table.
	 *
	 * @param string $mode Current mode.
	 */
	private function render_readiness( $mode ) {
		echo '<h2>' . esc_html__( 'Readiness', 'quill-accessible-forms' ) . '</h2>';
		/* translators: %s: test or live. */
		echo '<p>' . esc_html( sprintf( __( 'The site is in %s mode. QUILL publishes a payment form only when the form\'s mode matches this and every provider it uses is ready.', 'quill-accessible-forms' ), $mode === 'live' ? __( 'live', 'quill-accessible-forms' ) : __( 'test', 'quill-accessible-forms' ) ) ) . '</p>';
		echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Payment readiness by provider, in the current mode', 'quill-accessible-forms' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Provider', 'quill-accessible-forms' ) . '</th><th scope="col">' . esc_html__( 'Keys', 'quill-accessible-forms' ) . '</th><th scope="col">' . esc_html__( 'Webhook', 'quill-accessible-forms' ) . '</th></tr></thead><tbody>';
		foreach ( QAF_Payment_Config::PROVIDERS as $p ) {
			$keys = QAF_Payment_Settings::configured( $p, $mode );
			$hook = QAF_Payment_Settings::webhook_ready( $p, $mode );
			$kc   = QAF_Payment_Settings::last_check( $p, $mode, 'credentials' );
			$hc   = QAF_Payment_Settings::last_check( $p, $mode, 'webhook' );
			$kt   = $keys ? __( 'Ready: checked with the provider.', 'quill-accessible-forms' ) : ( QAF_Payment_Settings::shape_problem( $p, $mode ) ?: ( $kc && ! $kc['ok'] ? $kc['message'] : __( 'Not checked since the keys were saved.', 'quill-accessible-forms' ) ) );
			$ht   = $hook ? __( 'Ready.', 'quill-accessible-forms' ) : ( $hc && ! $hc['ok'] ? $hc['message'] : __( 'Not set up.', 'quill-accessible-forms' ) );
			echo '<tr><th scope="row">' . esc_html( QAF_Payment_Config::provider_name( $p ) ) . '</th><td>' . esc_html( $kt ) . '</td><td>' . esc_html( $ht ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * The settings form.
	 *
	 * @param string $mode Current mode.
	 */
	private function render_settings( $mode ) {
		echo '<h2>' . esc_html__( 'Settings', 'quill-accessible-forms' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'qaf_payments_save' );
		echo '<input type="hidden" name="action" value="qaf_payments_save" />';

		echo '<fieldset class="qaf-pay-mode"><legend><strong>' . esc_html__( 'Mode', 'quill-accessible-forms' ) . '</strong></legend>';
		foreach ( array( 'test' => __( 'Test: no real money moves', 'quill-accessible-forms' ), 'live' => __( 'Live: real payments', 'quill-accessible-forms' ) ) as $value => $label ) {
			echo '<p><input type="radio" name="qaf_mode" id="qaf_mode_' . esc_attr( $value ) . '" value="' . esc_attr( $value ) . '"' . checked( $mode, $value, false ) . ' /> ';
			echo '<label for="qaf_mode_' . esc_attr( $value ) . '">' . esc_html( $label ) . '</label></p>';
		}
		if ( $mode !== 'live' ) {
			echo '<p><input type="checkbox" name="qaf_confirm_live" id="qaf_confirm_live" value="1" aria-describedby="qaf_confirm_live_hint" /> ';
			echo '<label for="qaf_confirm_live">' . esc_html__( 'I have finished testing and want this site to take real payments', 'quill-accessible-forms' ) . '</label></p>';
			echo '<p class="description" id="qaf_confirm_live_hint">' . esc_html__( 'Needed only when switching to Live.', 'quill-accessible-forms' ) . '</p>';
		}
		echo '</fieldset>';

		foreach ( self::fields() as $provider => $fields ) {
			foreach ( QAF_Payment_Settings::ENVIRONMENTS as $env ) {
				$legend = QAF_Payment_Config::provider_name( $provider ) . ', ' . self::env_name( $provider, $env ) . ' ' . __( 'keys', 'quill-accessible-forms' );
				echo '<fieldset class="qaf-pay-keys"><legend><strong>' . esc_html( $legend ) . '</strong></legend><table class="form-table" role="presentation"><tbody>';
				foreach ( $fields as $name => $info ) {
					list( $label, $secret, $hint ) = $info;
					$id      = "qaf_{$provider}_{$env}_{$name}";
					$hint_id = $id . '_hint';
					echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
					if ( ! $secret ) {
						echo '<input type="text" class="regular-text code" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( QAF_Payment_Settings::get_public( $provider, $env, $name ) ) . '" autocomplete="off" spellcheck="false" aria-describedby="' . esc_attr( $hint_id ) . '" />';
						echo '<p class="description" id="' . esc_attr( $hint_id ) . '">' . esc_html( $hint ) . '</p>';
					} elseif ( QAF_Payment_Settings::secret_from_constant( $provider, $env, $name ) ) {
						/* translators: %s: constant name. */
						echo '<p id="' . esc_attr( $id ) . '">' . esc_html( sprintf( __( 'Set in wp-config.php (%s). Change it there.', 'quill-accessible-forms' ), QAF_Payment_Settings::constant_name( $provider, $env, $name ) ) ) . '</p>';
					} else {
						$saved  = QAF_Payment_Settings::has_secret( $provider, $env, $name );
						$status = $saved ? __( 'Saved. Leave blank to keep it.', 'quill-accessible-forms' ) : __( 'Not saved.', 'quill-accessible-forms' );
						echo '<input type="password" class="regular-text code" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="" autocomplete="new-password" spellcheck="false" aria-describedby="' . esc_attr( $hint_id ) . '" />';
						echo '<p class="description" id="' . esc_attr( $hint_id ) . '">' . esc_html( $status . ' ' . $hint ) . '</p>';
						if ( $saved ) {
							echo '<p><input type="checkbox" id="' . esc_attr( $id . '_remove' ) . '" name="' . esc_attr( $id . '_remove' ) . '" value="1" /> ';
							/* translators: %s: what is removed, such as Secret key. */
							echo '<label for="' . esc_attr( $id . '_remove' ) . '">' . esc_html( sprintf( __( 'Remove the saved %s', 'quill-accessible-forms' ), strtolower( $label ) ) ) . '</label></p>';
						}
					}
					echo '</td></tr>';
				}
				echo '</tbody></table></fieldset>';
			}
		}
		submit_button( __( 'Save Payment Settings', 'quill-accessible-forms' ) );
		echo '</form>';
	}

	/**
	 * Check Connection and Create Webhook, one small form per button.
	 */
	private function render_actions() {
		echo '<h2>' . esc_html__( 'Check and connect', 'quill-accessible-forms' ) . '</h2>';
		echo '<p>' . esc_html__( 'Check Connection asks the provider whether it accepts the saved keys in that environment. Create Webhook registers this site\'s address with the provider so it can report payments, refunds and failures.', 'quill-accessible-forms' ) . '</p>';
		foreach ( QAF_Payment_Config::PROVIDERS as $provider ) {
			echo '<h3>' . esc_html( QAF_Payment_Config::provider_name( $provider ) ) . '</h3>';
			echo '<p><label for="qaf_hook_url_' . esc_attr( $provider ) . '">' . esc_html__( 'Webhook address', 'quill-accessible-forms' ) . '</label><br />';
			echo '<input type="text" readonly class="large-text code" id="qaf_hook_url_' . esc_attr( $provider ) . '" value="' . esc_attr( QAF_Payment_Settings::webhook_url( $provider ) ) . '" /></p>';
			echo '<ul>';
			foreach ( QAF_Payment_Settings::ENVIRONMENTS as $env ) {
				foreach ( array( 'check' => __( 'Check Connection', 'quill-accessible-forms' ), 'webhook' => __( 'Create Webhook', 'quill-accessible-forms' ) ) as $do => $verb ) {
					echo '<li><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="qaf-pay-action">';
					wp_nonce_field( 'qaf_payments_action' );
					echo '<input type="hidden" name="action" value="qaf_payments_action" />';
					echo '<input type="hidden" name="qaf_provider" value="' . esc_attr( $provider ) . '" />';
					echo '<input type="hidden" name="qaf_env" value="' . esc_attr( $env ) . '" />';
					echo '<input type="hidden" name="qaf_do" value="' . esc_attr( $do ) . '" />';
					/* translators: 1: Check Connection or Create Webhook, 2: provider, 3: environment. */
					echo '<button type="submit" class="button">' . esc_html( sprintf( __( '%1$s: %2$s %3$s', 'quill-accessible-forms' ), $verb, QAF_Payment_Config::provider_name( $provider ), self::env_name( $provider, $env ) ) ) . '</button>';
					echo '</form></li>';
				}
			}
			echo '</ul>';
		}
	}

	/**
	 * Recent payments and totals.
	 */
	private function render_report() {
		$status = isset( $_GET['qaf_status'] ) ? sanitize_key( wp_unslash( $_GET['qaf_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only filter.
		$form   = isset( $_GET['qaf_form'] ) ? absint( $_GET['qaf_form'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows   = QAF_Payment_Service::instance()->store()->recent( array( 'status' => $status, 'form_id' => $form, 'limit' => 100 ) );

		echo '<h2>' . esc_html__( 'Recent payments', 'quill-accessible-forms' ) . '</h2>';
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '" />';
		echo '<label for="qaf_status">' . esc_html__( 'Show', 'quill-accessible-forms' ) . '</label> <select id="qaf_status" name="qaf_status"><option value="">' . esc_html__( 'Every status', 'quill-accessible-forms' ) . '</option>';
		foreach ( QAF_Payment_States::ALL as $s ) {
			echo '<option value="' . esc_attr( $s ) . '"' . selected( $status, $s, false ) . '>' . esc_html( QAF_Payment_States::label( $s ) ) . '</option>';
		}
		echo '</select> <label for="qaf_form">' . esc_html__( 'Form ID', 'quill-accessible-forms' ) . '</label> <input type="number" min="0" class="small-text" id="qaf_form" name="qaf_form" value="' . esc_attr( $form ? $form : '' ) . '" /> ';
		submit_button( __( 'Filter', 'quill-accessible-forms' ), 'secondary', '', false );
		echo '</form>';

		$totals = self::totals( $rows );
		if ( $totals ) {
			echo '<h3>' . esc_html__( 'Totals for the payments shown', 'quill-accessible-forms' ) . '</h3><ul>';
			foreach ( $totals as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul>';
		}
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No payments yet.', 'quill-accessible-forms' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Recent payments, newest first', 'quill-accessible-forms' ) . '</caption><thead><tr>';
		foreach ( array( __( 'Entry', 'quill-accessible-forms' ), __( 'Form', 'quill-accessible-forms' ), __( 'Amount', 'quill-accessible-forms' ), __( 'Status', 'quill-accessible-forms' ), __( 'Provider', 'quill-accessible-forms' ), __( 'Mode', 'quill-accessible-forms' ), __( 'Transaction', 'quill-accessible-forms' ), __( 'Updated (UTC)', 'quill-accessible-forms' ) ) as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$amount = QAF_Payment_Money::spoken( $r['amount_minor'], $r['currency'] );
			if ( (int) $r['refunded_minor'] > 0 ) {
				/* translators: %s: amount refunded with currency. */
				$amount .= ' ' . sprintf( __( '(%s refunded)', 'quill-accessible-forms' ), QAF_Payment_Money::spoken( $r['refunded_minor'], $r['currency'] ) );
			}
			$ref = $r['provider'] === 'paypal' && $r['capture_ref'] !== '' ? $r['capture_ref'] : $r['provider_ref'];
			echo '<tr><th scope="row">' . esc_html( $r['entry_id'] ) . '</th><td>' . esc_html( $r['form_id'] ) . '</td><td>' . esc_html( $amount ) . '</td><td>' . esc_html( QAF_Payment_States::label( $r['status'] ) ) . '</td>';
			echo '<td>' . esc_html( $r['provider'] === '' ? __( 'Not chosen yet', 'quill-accessible-forms' ) : QAF_Payment_Config::provider_name( $r['provider'] ) ) . '</td><td>' . esc_html( $r['environment'] ) . '</td><td><code>' . esc_html( $ref ) . '</code></td><td>' . esc_html( $r['updated_at'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Totals by currency: what was taken, and what was refunded.
	 *
	 * @param array $rows Attempts.
	 * @return array Lines.
	 */
	public static function totals( $rows ) {
		$by = array();
		foreach ( $rows as $r ) {
			$c = strtolower( $r['currency'] );
			if ( ! isset( $by[ $c ] ) ) {
				$by[ $c ] = array( 'taken' => 0, 'refunded' => 0, 'count' => 0, 'authorized' => 0 );
			}
			if ( in_array( $r['status'], array( 'paid', 'partially_refunded', 'refunded' ), true ) ) {
				$by[ $c ]['taken']    += (int) $r['amount_minor'];
				$by[ $c ]['refunded'] += (int) $r['refunded_minor'];
				$by[ $c ]['count']++;
			} elseif ( $r['status'] === 'authorized' ) {
				$by[ $c ]['authorized'] += (int) $r['amount_minor'];
			}
		}
		ksort( $by );
		$out = array();
		foreach ( $by as $c => $t ) {
			/* translators: 1: number of payments, 2: amount taken, 3: amount refunded, 4: amount net, 5: amount authorized and not captured. */
			$out[] = sprintf( __( '%1$d paid: %2$s taken, %3$s refunded, %4$s kept; %5$s authorized and not yet captured.', 'quill-accessible-forms' ), $t['count'], QAF_Payment_Money::spoken( $t['taken'], $c ), QAF_Payment_Money::spoken( $t['refunded'], $c ), QAF_Payment_Money::spoken( $t['taken'] - $t['refunded'], $c ), QAF_Payment_Money::spoken( $t['authorized'], $c ) );
		}
		return $out;
	}
}
