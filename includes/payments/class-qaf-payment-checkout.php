<?php
/**
 * The payment field on a published form, and the checkout script.
 *
 * The field itself collects no card details. It says what will be charged
 * (a fixed amount, the order total, or an amount the giver types) and, for a
 * donation, holds the one input the giver fills in. The checkout appears
 * after the form is sent and the site has saved the answers and worked out
 * the amount; the provider's own fields (Stripe's Payment Element, PayPal's
 * buttons) take the payment details inside it.
 *
 * Accessibility, briefly: every input has a visible label; hints and errors
 * are tied to it with aria-describedby; errors say what to do in words, not
 * colour; the checkout heading takes focus when it appears; progress is
 * announced from one polite status region and failures from one alert; and
 * every step works from the keyboard alone.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Checkout class.
 */
class QAF_Payment_Checkout {

	/**
	 * The payment field's markup.
	 *
	 * @param array $field Model payment field (from the stored builder config,
	 *                     which carries the same id, label and help).
	 * @param array $model Form model.
	 * @return string HTML.
	 */
	public static function render_field( $field, $model ) {
		$config   = QAF_Payment_Config::get( $model );
		$item     = QAF_Payment_Config::payment_field( $model );
		$id       = (string) ( $item['id'] ?? ( $field['id'] ?? 'payment' ) );
		$props    = is_array( $item['props'] ?? null ) ? $item['props'] : array();
		$amount   = $props['amount'] ?? null;
		$currency = $config['currency'];
		$code     = strtoupper( $currency );
		$html_id  = 'qaf-pay-' . sanitize_html_class( $id );
		$label    = (string) ( $field['label'] ?? ( $item['label'] ?? __( 'Payment', 'quill-accessible-forms' ) ) );
		$help     = (string) ( $field['description'] ?? ( $item['help'] ?? '' ) );
		$hint_id  = $html_id . '-hint';
		$err_id   = $html_id . '-error';
		$names    = array();
		foreach ( $config['providers'] as $p ) {
			$names[] = $p === 'stripe' ? __( 'card (through Stripe)', 'quill-accessible-forms' ) : 'PayPal';
		}
		/* translators: %s: list of ways to pay, such as "card (through Stripe) or PayPal". */
		$how = sprintf( __( 'You pay by %s after you send the form.', 'quill-accessible-forms' ), implode( __( ' or ', 'quill-accessible-forms' ), $names ) );

		$out  = '<div class="qaf-field qaf-field-payment qaf-payment-v1" data-field-id="' . esc_attr( $id ) . '" data-field-type="payment">';
		if ( $amount === 'donation' ) {
			$hint = array( $how );
			$low  = isset( $props['donation_min'] ) ? QAF_Payment_Money::to_minor( $props['donation_min'], $currency ) : null;
			$high = isset( $props['donation_max'] ) ? QAF_Payment_Money::to_minor( $props['donation_max'], $currency ) : null;
			if ( $low !== null && $high !== null ) {
				/* translators: 1: smallest amount, 2: largest amount, each with currency. */
				array_unshift( $hint, sprintf( __( 'Give from %1$s to %2$s.', 'quill-accessible-forms' ), QAF_Payment_Money::spoken( $low, $currency ), QAF_Payment_Money::spoken( $high, $currency ) ) );
			} elseif ( $low !== null ) {
				/* translators: %s: smallest amount with currency. */
				array_unshift( $hint, sprintf( __( 'The smallest gift is %s.', 'quill-accessible-forms' ), QAF_Payment_Money::spoken( $low, $currency ) ) );
			} elseif ( $high !== null ) {
				/* translators: %s: largest amount with currency. */
				array_unshift( $hint, sprintf( __( 'The largest gift is %s.', 'quill-accessible-forms' ), QAF_Payment_Money::spoken( $high, $currency ) ) );
			}
			if ( $help !== '' ) {
				array_unshift( $hint, $help );
			}
			$exp = QAF_Payment_Money::exponent( $currency );
			/* translators: 1: field label, 2: currency code. */
			$out .= '<label class="qaf-label" for="' . esc_attr( $html_id ) . '">' . esc_html( sprintf( __( '%1$s, in %2$s', 'quill-accessible-forms' ), $label, $code ) ) . ' <span class="qaf-required-text">' . esc_html__( '(required)', 'quill-accessible-forms' ) . '</span></label>';
			$out .= '<input type="text" inputmode="decimal" autocomplete="off" class="qaf-input qaf-payment-donation" id="' . esc_attr( $html_id ) . '" name="fields[' . esc_attr( $id ) . ']" required aria-required="true"';
			$out .= ' data-currency="' . esc_attr( $code ) . '" data-decimals="' . esc_attr( $exp ) . '"';
			if ( $low !== null ) {
				$out .= ' data-min="' . esc_attr( QAF_Payment_Money::to_major( $low, $currency ) ) . '"';
			}
			if ( $high !== null ) {
				$out .= ' data-max="' . esc_attr( QAF_Payment_Money::to_major( $high, $currency ) ) . '"';
			}
			$out .= ' aria-describedby="' . esc_attr( $hint_id . ' ' . $err_id ) . '" />';
			$out .= '<p class="qaf-field-description" id="' . esc_attr( $hint_id ) . '">' . esc_html( implode( ' ', $hint ) ) . '</p>';
		} else {
			if ( QAF_Payment_Config::positive_number( $amount ) ) {
				$minor = (int) QAF_Payment_Money::to_minor( $amount, $currency );
				/* translators: %s: amount with currency. */
				$what = sprintf( __( 'Amount due: %s.', 'quill-accessible-forms' ), QAF_Payment_Money::spoken( $minor, $currency ) );
			} else {
				$ref    = is_string( $amount ) ? substr( $amount, 1 ) : '';
				$labels = QAF_Form_Model::labels( $model );
				/* translators: 1: the label of the total field, 2: currency code. */
				$what = sprintf( __( 'You pay the amount in "%1$s", in %2$s. The site works it out again when you send the form.', 'quill-accessible-forms' ), $labels[ $ref ] ?? $ref, $code );
			}
			$out .= '<p class="qaf-label qaf-payment-label" id="' . esc_attr( $html_id ) . '-label"><strong>' . esc_html( $label ) . '</strong></p>';
			$text = array( $what, $how );
			if ( $help !== '' ) {
				array_unshift( $text, $help );
			}
			$out .= '<p class="qaf-field-description" id="' . esc_attr( $hint_id ) . '">' . esc_html( implode( ' ', $text ) ) . '</p>';
		}
		if ( $config['test_mode'] ) {
			$out .= '<p class="qaf-payment-test-note">' . esc_html__( 'Test mode: no real money is taken. Use a test card from the provider, never a real one.', 'quill-accessible-forms' ) . '</p>';
		}
		$out .= '<noscript><p>' . esc_html__( 'Paying needs JavaScript. Your answers can still be sent, and the site owner can arrange payment with you.', 'quill-accessible-forms' ) . '</p></noscript>';
		$out .= '<div class="qaf-field-error" id="' . esc_attr( $err_id ) . '" aria-live="polite"></div>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * A random key for one page load of a paid form, so that sending the same
	 * answers twice reuses the first entry and its payment.
	 *
	 * @return string Hidden input.
	 */
	public static function submission_input() {
		return '<input type="hidden" name="qaf_submission" value="' . esc_attr( bin2hex( random_bytes( 16 ) ) ) . '" />';
	}

	/**
	 * Load the checkout script and styles, and Stripe.js when the form offers
	 * Stripe. Stripe requires Stripe.js to load from js.stripe.com itself;
	 * PayPal's script is loaded by the checkout only when the payer picks it,
	 * because its address names the client ID, currency and intent.
	 *
	 * @param array $model Form model.
	 */
	public static function enqueue( $model ) {
		$config = QAF_Payment_Config::get( $model );
		$deps   = array( 'quill-accessible-forms' );
		if ( in_array( 'stripe', $config['providers'], true ) ) {
			wp_enqueue_script( 'qaf-stripe-js', 'https://js.stripe.com/v3/', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			$deps[] = 'qaf-stripe-js';
		}
		wp_enqueue_style( 'qaf-payments', QAF_PLUGIN_URL . 'public/css/qaf-payments.css', array(), QAF_VERSION );
		wp_enqueue_script( 'qaf-payments', QAF_PLUGIN_URL . 'public/js/qaf-payments.js', $deps, QAF_VERSION, true );
		wp_localize_script( 'qaf-payments', 'qafPayments', array(
			'restUrl' => rest_url( 'qaf/v1/payments/' ),
			'i18n'    => self::strings(),
		) );
	}

	/**
	 * Every sentence the checkout says, translatable.
	 *
	 * @return array Strings.
	 */
	public static function strings() {
		return array(
			/* translators: %s: amount with currency. */
			'heading'      => __( 'Pay %s', 'quill-accessible-forms' ),
			'intro'        => __( 'Your answers are saved. Your payment is not taken until you finish here.', 'quill-accessible-forms' ),
			'testMode'     => __( 'Test mode: no real money is taken. Use the provider\'s test card or sandbox account, never a real card.', 'quill-accessible-forms' ),
			'choose'       => __( 'How do you want to pay?', 'quill-accessible-forms' ),
			'card'         => __( 'Card, through Stripe', 'quill-accessible-forms' ),
			'paypal'       => __( 'PayPal', 'quill-accessible-forms' ),
			'cardDetails'  => __( 'Card details', 'quill-accessible-forms' ),
			/* translators: %s: amount with currency. */
			'payButton'    => __( 'Pay %s', 'quill-accessible-forms' ),
			'loading'      => __( 'Loading the payment form.', 'quill-accessible-forms' ),
			'loaded'       => __( 'The payment form is ready.', 'quill-accessible-forms' ),
			'paypalReady'  => __( 'The PayPal button is ready. It opens PayPal in a new window.', 'quill-accessible-forms' ),
			'working'      => __( 'Sending your payment. Please wait.', 'quill-accessible-forms' ),
			'checking'     => __( 'Checking your payment with the site.', 'quill-accessible-forms' ),
			'paypalClosed' => __( 'PayPal was closed before the payment finished. You can try again.', 'quill-accessible-forms' ),
			'tryAgain'     => __( 'Try again', 'quill-accessible-forms' ),
			'checkAgain'   => __( 'Check payment again', 'quill-accessible-forms' ),
			'receipt'      => __( 'View your receipt', 'quill-accessible-forms' ),
			'network'      => __( 'The site could not be reached. Check your connection, then try again.', 'quill-accessible-forms' ),
			'noScript'     => __( 'The payment provider\'s script did not load, so payment cannot be taken on this page. Please try again later.', 'quill-accessible-forms' ),
			'returned'     => __( 'Welcome back. Checking your payment.', 'quill-accessible-forms' ),
		);
	}
}
