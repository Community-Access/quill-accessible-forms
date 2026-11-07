<?php
/**
 * Payments: loads the parts and hooks them in.
 *
 * Contract version 1 with QUILL is in docs/payments-admin-guide.md and in
 * QUILL's docs/design/forms-payments-plugin-contract.md.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-qaf-payment-money.php';
require_once __DIR__ . '/class-qaf-payment-config.php';
require_once __DIR__ . '/class-qaf-payment-pricing.php';
require_once __DIR__ . '/class-qaf-payment-states.php';
require_once __DIR__ . '/class-qaf-payment-settings.php';
require_once __DIR__ . '/class-qaf-payment-http.php';
require_once __DIR__ . '/class-qaf-payment-store.php';
require_once __DIR__ . '/class-qaf-payment-stripe.php';
require_once __DIR__ . '/class-qaf-payment-paypal.php';
require_once __DIR__ . '/class-qaf-payment-service.php';
require_once __DIR__ . '/class-qaf-payment-rest.php';
require_once __DIR__ . '/class-qaf-payment-checkout.php';

/**
 * QAF_Payments class.
 */
class QAF_Payments {

	/**
	 * Hook everything in. Called once from the main plugin class.
	 */
	public static function init() {
		$rest = new QAF_Payment_REST();
		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
		add_action( 'admin_init', array( __CLASS__, 'install' ) );
		if ( is_admin() ) {
			require_once QAF_PLUGIN_DIR . 'admin/class-qaf-payments-admin.php';
			( new QAF_Payments_Admin() )->register();
		}
	}

	/**
	 * Tables and the payment capability, for new sites and for sites whose
	 * files were replaced without activating the plugin again.
	 */
	public static function install() {
		QAF_Payment_DB_Store::maybe_install();
		$admin = function_exists( 'get_role' ) ? get_role( 'administrator' ) : null;
		if ( $admin && ! $admin->has_cap( 'qaf_manage_payments' ) ) {
			$admin->add_cap( 'qaf_manage_payments' );
		}
	}
}
