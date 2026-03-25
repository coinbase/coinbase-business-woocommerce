<?php
/*
Plugin Name:  Coinbase Business
Plugin URI:   https://github.com/coinbase/coinbase-commerce-woocommerce/
Description:  A payment gateway that allows your customers to pay with USDC via Coinbase Business Checkouts API (https://coinbase.com/business)
Version:      2.1.0
Author:       Coinbase Business
Author URI:   https://coinbase.com/business
License:      GPLv3+
License URI:  https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:  coinbase
Domain Path:  /languages

WC requires at least: 3.0.9
WC tested up to: 8.9.1

Coinbase Business is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

Coinbase Business is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Coinbase WooCommerce. If not, see https://www.gnu.org/licenses/gpl-3.0.html.
*/

function cb_init_gateway() {
	// If WooCommerce is available, initialise WC parts.

	/** DOCBLOCK - Makes linter happy.
	 *
	 * @since today
	 */
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		require_once 'class-wc-gateway-coinbase.php';
		// Legacy: keep blockchain pending status registration for existing orders.
		add_action( 'init', 'cb_wc_register_blockchain_status' );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', 'cb_wc_status_valid_for_payment', 10, 2 );
		add_action( 'cb_check_orders', 'cb_wc_check_orders' );
		add_filter( 'woocommerce_payment_gateways', 'cb_wc_add_coinbase_class' );
		// Legacy: keep blockchain pending status in order status list for existing orders.
		add_filter( 'wc_order_statuses', 'cb_wc_add_status' );
		add_action( 'woocommerce_admin_order_data_after_order_details', 'cb_order_meta_general' );
		add_action( 'woocommerce_order_details_after_order_table', 'cb_order_meta_general' );
		add_filter( 'woocommerce_email_order_meta_fields', 'cb_custom_woocommerce_email_order_meta_fields', 10, 3 );
		// Legacy: keep email action for blockchainpending → processing transition.
		add_filter( 'woocommerce_email_actions', 'cb_register_email_action' );
		add_action( 'woocommerce_email', 'cb_add_email_triggers' );
		add_action( 'before_woocommerce_init', function() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		} );
	}
}
add_action( 'plugins_loaded', 'cb_init_gateway' );


// Setup cron job.

function cb_activation() {
	if ( ! wp_next_scheduled( 'cb_check_orders' ) ) {
		wp_schedule_event( time(), 'hourly', 'cb_check_orders' );
	}
}
register_activation_hook( __FILE__, 'cb_activation' );

function cb_deactivation() {
	wp_clear_scheduled_hook( 'cb_check_orders' );
}
register_deactivation_hook( __FILE__, 'cb_deactivation' );


// WooCommerce

function cb_wc_add_coinbase_class( $methods ) {
	$methods[] = 'WC_Gateway_Coinbase';
	return $methods;
}

function cb_wc_check_orders() {
	$gateway = WC()->payment_gateways()->payment_gateways()['coinbase'];
	return $gateway->check_orders();
}

/**
 * Legacy: Register "wc-blockchainpending" status for existing orders
 * from the previous Coinbase Commerce integration.
 */
function cb_wc_register_blockchain_status() {
	register_post_status( 'wc-blockchainpending', array(
		'label'                     => __( 'Blockchain Pending', 'coinbase' ),
		'public'                    => true,
		'show_in_admin_status_list' => true,
		/* translators: WooCommerce order count in blockchain pending. */
		'label_count'               => _n_noop( 'Blockchain pending <span class="count">(%s)</span>', 'Blockchain pending <span class="count">(%s)</span>' ),
	) );
}

/**
 * Legacy: Register wc-blockchainpending status as valid for payment.
 */
function cb_wc_status_valid_for_payment( $statuses, $order ) {
	$statuses[] = 'wc-blockchainpending';
	return $statuses;
}

/**
 * Legacy: Add registered status to list of WC Order statuses.
 * Kept for existing orders that may have this status.
 *
 * @param array $wc_statuses_arr Array of all order statuses on the website.
 */
function cb_wc_add_status( $wc_statuses_arr ) {
	$new_statuses_arr = array();

	// Add new order status after payment pending.
	foreach ( $wc_statuses_arr as $id => $label ) {
		$new_statuses_arr[ $id ] = $label;

		if ( 'wc-pending' === $id ) {  // after "Payment Pending" status.
			$new_statuses_arr['wc-blockchainpending'] = __( 'Blockchain Pending', 'coinbase' );
		}
	}

	return $new_statuses_arr;
}


/**
 * Add order Coinbase meta after General and before Billing.
 * Shows checkout ID (new), payment link ID (previous), or charge ID (legacy), whichever exists.
 *
 * @see: https://rudrastyh.com/woocommerce/customize-order-details.html
 *
 * @param WC_Order $order WC order instance
 */
function cb_order_meta_general( $order ) {
	if ($order->get_payment_method() == 'coinbase') {
		$checkout_id     = $order->get_meta( '_coinbase_checkout_id' );
		$payment_link_id = $order->get_meta( '_coinbase_payment_link_id' );
		$charge_id       = $order->get_meta( '_coinbase_charge_id' );
		$reference       = $checkout_id ? $checkout_id : ( $payment_link_id ? $payment_link_id : $charge_id );

		if ( $reference ) {
			?>

			<br class="clear"/>
			<h3>Coinbase Business Data</h3>
			<div class="">
				<p>Coinbase Reference # <?php echo esc_html( $reference ); ?></p>
			</div>

			<?php
		}
	}
}


/**
 * Add Coinbase meta to WC emails.
 * Prefers checkout ID, falls back to payment link ID, then legacy charge ID.
 *
 * @see https://docs.woocommerce.com/document/add-a-custom-field-in-an-order-to-the-emails/
 *
 * @param array    $fields indexed list of existing additional fields.
 * @param bool     $sent_to_admin If should sent to admin.
 * @param WC_Order $order WC order instance
 *
 */
function cb_custom_woocommerce_email_order_meta_fields( $fields, $sent_to_admin, $order ) {
	if ($order->get_payment_method() == 'coinbase') {
		$checkout_id     = $order->get_meta( '_coinbase_checkout_id' );
		$payment_link_id = $order->get_meta( '_coinbase_payment_link_id' );
		$charge_id       = $order->get_meta( '_coinbase_charge_id' );
		$reference       = $checkout_id ? $checkout_id : ( $payment_link_id ? $payment_link_id : $charge_id );

		if ( $reference ) {
			$fields['coinbase_reference'] = array(
				'label' => __( 'Coinbase Reference #' ),
				'value' => $reference,
			);
		}
	}

	return $fields;
}


/**
 * Legacy: Registers "woocommerce_order_status_blockchainpending_to_processing"
 * as a WooCommerce email action for existing orders.
 *
 * @param array $email_actions
 *
 * @return array
 */
function cb_register_email_action( $email_actions ) {
	$email_actions[] = 'woocommerce_order_status_blockchainpending_to_processing';

	return $email_actions;
}


/**
 * Legacy: Adds triggers for emails sent when the order status transitions
 * from blockchainpending to Processing, for existing orders.
 *
 * @param WC_Emails $wc_emails
 */
function cb_add_email_triggers( $wc_emails ) {
	$emails = $wc_emails->get_emails();

	/**
	 * A list of WooCommerce emails sent when the order status transitions to Processing.
	 *
	 * Developers can use the `cb_processing_order_emails` filter to add in their own emails.
	 *
	 * @param array $emails List of email class names.
	 *
	 * @return array
	 *
	 * @since today
	 */
	$processing_order_emails = apply_filters( 'cb_processing_order_emails', [
		'WC_Email_New_Order',
		'WC_Email_Customer_Processing_Order',
	] );

	foreach ( $processing_order_emails as $email_class ) {
		if ( isset( $emails[ $email_class ] ) ) {
			$email = $emails[ $email_class ];

			add_action(
				'woocommerce_order_status_blockchainpending_to_processing_notification',
				array( $email, 'trigger' )
			);
		}
	}
}
