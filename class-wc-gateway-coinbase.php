<?php // phpcs:disable
/**
 * Coinbase Business Payment Gateway.
 *
 * Provides a Coinbase Business Payment Gateway using Payment Links API.
 *
 * @class       WC_Gateway_Coinbase
 * @extends     WC_Payment_Gateway
 * @since       2.0.0
 * @package     WooCommerce/Classes/Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Coinbase Class.
 */
class WC_Gateway_Coinbase extends WC_Payment_Gateway {

	/**
	 * Log_enabled - whether or not logging is enabled
	 *
	 * @var bool	Whether or not logging is enabled
	 */
	public static $log_enabled = false;

	/**
	 * WC_Logger Logger instance
	 *
	 * @var WC_Logger Logger instance
	 * */
	public static $log = false;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'coinbase';
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Pay with Coinbase', 'coinbase' );
		$this->method_title       = __( 'Coinbase Business', 'coinbase' );
		$this->method_description = '<p>' .
			__( 'A payment gateway that sends your customers to Coinbase Business to pay with USDC.', 'coinbase' )
			. '</p><p>' .
			sprintf(
				__( 'If you do not currently have a Coinbase Business account, you can set one up here: %s', 'coinbase' ),
				'<a target="_blank" href="https://coinbase.com/business">https://coinbase.com/business</a>'
			);

		// Timeout after 1 day. USDC confirms in seconds,
		// but allow time for the customer to complete the payment flow.
		$this->timeout = ( new WC_DateTime() )->sub( new DateInterval( 'P1D' ) );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->debug       = 'yes' === $this->get_option( 'debug', 'no' );

		self::$log_enabled = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, '_custom_query_var' ), 10, 2 );
		add_action( 'woocommerce_api_wc_gateway_coinbase', array( $this, 'handle_webhook' ) );
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'coinbase' ) );
		}
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		if ( $this->get_option( 'show_icons' ) === 'no' ) {
			return '';
		}

		$url       = WC_HTTPS::force_https_url( plugins_url( '/assets/images/usdc.png', __FILE__ ) );
		$icon_html = '<img width="26" src="' . esc_attr( $url ) . '" alt="' . esc_attr__( 'USDC', 'coinbase' ) . '" />';

		/** DOCBLOCK - Makes linter happy.
		 *
		 * @since today
		*/
		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Coinbase Business Payment', 'coinbase' ),
				'default' => 'yes',
			),
			'title'           => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'USDC via Coinbase', 'coinbase' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'Pay with USDC.', 'coinbase' ),
			),
			'cdp_key_name'    => array(
				'title'       => __( 'CDP API Key Name', 'coinbase' ),
				'type'        => 'text',
				'default'     => '',
				'description' => sprintf(
					__(
						'Your CDP API key name from the Coinbase Business dashboard: %s',
						'coinbase'
					),
					esc_url( 'https://coinbase.com/business' )
				),
			),
			'cdp_private_key' => array(
				'title'       => __( 'CDP API Private Key', 'coinbase' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Your EC private key in PEM format. Paste the full key including BEGIN/END lines.', 'coinbase' ),
				'css'         => 'font-family: monospace; height: 150px;',
			),
			'webhook_secret'  => array(
				'title'       => __( 'Webhook Secret', 'coinbase' ),
				'type'        => 'text',
				'description' =>

				__( 'Using webhooks allows Coinbase Business to send payment confirmation messages to the website. To fill this out:', 'coinbase' )

				. '<br /><br />' .

				__( '1. Follow the instructions at https://docs.cdp.coinbase.com/coinbase-business/payment-link-apis/webhooks to set up webhooks.', 'coinbase' )

				. '<br />' .

				sprintf( __( '2. Add a webhook endpoint with the following URL: %s', 'coinbase' ), add_query_arg( 'wc-api', 'WC_Gateway_Coinbase', home_url( '/', 'https' ) ) )

				. '<br />' .

				__( '3. Copy the webhook secret and paste it into the box above.', 'coinbase' ),

			),
			'show_icons'      => array(
				'title'       => __( 'Show icons', 'coinbase' ),
				'type'        => 'checkbox',
				'label'       => __( 'Display USDC icon on checkout page.', 'coinbase' ),
				'default'     => 'yes',
			),
			'debug'           => array(
				'title'       => __( 'Debug log', 'woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log Coinbase API events inside %s', 'coinbase' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'coinbase' ) . '</code>' ),
			),
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Validate USD currency requirement.
		if ( get_woocommerce_currency() !== 'USD' ) {
			wc_add_notice( __( 'Coinbase Business payments require USD store currency.', 'coinbase' ), 'error' );
			return array( 'result' => 'fail' );
		}

		// Create description for payment link based on order's products.
		try {
			$order_items = array_map( function( $item ) {
				return $item['quantity'] . ' x ' . $item['name'];
			}, $order->get_items() );

			$description = mb_substr( implode( ', ', $order_items ), 0, 500 );
		} catch ( Exception $e ) {
			$description = null;
		}

		$this->init_api();

		// Create a new payment link.
		$metadata = array(
			'order_id'  => strval( $order->get_id() ),
			'order_key' => $order->get_order_key(),
			'source'    => 'woocommerce',
		);

		$result = Coinbase_API_Handler::create_payment_link(
			$order->get_total(),
			$metadata,
			$this->get_return_url( $order ),
			$this->get_cancel_url( $order ),
			$description
		);

		if ( ! $result[0] ) {
			return array( 'result' => 'fail' );
		}

		$payment_link = $result[1];

		$order->update_meta_data( '_coinbase_payment_link_id', $payment_link['id'] );
		$order->update_meta_data( '_coinbase_payment_link_url', $payment_link['url'] );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $payment_link['url'],
		);
	}

	/**
	 * Get the cancel url.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_cancel_url( $order ) {
		$return_url = $order->get_cancel_order_url();

		if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
			$return_url = str_replace( 'http:', 'https:', $return_url );
		}

		/** DOCBLOCK - Makes linter happy.
		 *
		 * @since today
		*/
		return apply_filters( 'woocommerce_get_cancel_url', $return_url, $order );
	}

	/**
	 * Check payment statuses on orders and update order statuses.
	 */
	public function check_orders() {
		$this->init_api();

		// Check the status of non-archived Coinbase orders.
		$orders = wc_get_orders(
			array(
				'coinbase_archived' => false,
				'status' => array( 'wc-pending' ),
				'meta_query' => array(
					array(
						'key'     => '_coinbase_archived',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_coinbase_payment_link_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $orders as $order ) {
			$payment_link_id = $order->get_meta( '_coinbase_payment_link_id' );

			usleep( 300000 );  // Ensure we don't hit the rate limit.
			$result = Coinbase_API_Handler::get_payment_link( $payment_link_id );

			if ( ! $result[0] ) {
				self::log( 'Failed to fetch order updates for: ' . $order->get_id() );
				continue;
			}

			$data = $result[1];

			// Map payment link status to event type for _update_order_status().
			$event_type = isset( $data['eventType'] ) ? $data['eventType'] : null;
			if ( $event_type ) {
				$this->_update_order_status( $order, $event_type, $data );
			}
		}
	}

	/**
	 * Handle requests sent to webhook.
	 */
	public function handle_webhook() {
		include_once dirname( __FILE__ ) . '/includes/class-coinbase-constants.php';

		$payload = file_get_contents( 'php://input' );
		if ( ! empty( $payload ) && $this->validate_webhook( $payload ) ) {
			$data = json_decode( $payload, true );

			self::log( 'Webhook received event: ' . print_r( $data, true ) );

			$event_type = isset( $data['eventType'] ) ? $data['eventType'] : null;
			$metadata   = isset( $data['metadata'] ) ? $data['metadata'] : array();

			// Validate this is a WooCommerce payment.
			if ( ! isset( $metadata['source'] ) || $metadata['source'] !== 'woocommerce' ) {
				self::log( 'Ignoring webhook: not a WooCommerce payment.' );
				exit;
			}

			if ( ! isset( $metadata['order_id'] ) ) {
				self::log( 'Ignoring webhook: no order_id in metadata.' );
				exit;
			}

			$order_id = $metadata['order_id'];
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				self::log( 'Webhook error: order not found for ID ' . $order_id );
				exit;
			}

			// Verify order key if present in metadata.
			if ( isset( $metadata['order_key'] ) && $order->get_order_key() !== $metadata['order_key'] ) {
				self::log( 'Webhook error: order key mismatch for order ' . $order_id );
				exit;
			}

			$this->_update_order_status( $order, $event_type, $data );

			exit;  // 200 response for acknowledgement.
		}

		status_header( 200 );
		exit;
	}

	/**
	 * Validate Coinbase Business webhook signature.
	 *
	 * Verifies the x-hook0-signature header using HMAC-SHA256 with timestamp
	 * replay attack prevention.
	 *
	 * @param  string $payload Raw request body.
	 * @return bool
	 */
	public function validate_webhook( $payload ) {
		self::log( 'Checking Webhook response is valid' );

		$headers = array_change_key_case( getallheaders() );

		if ( ! isset( $headers[ Coinbase_Constants::SIGNATURE_HEADER ] ) ) {
			self::log( 'Missing signature header.' );
			return false;
		}

		$signature_header = $headers[ Coinbase_Constants::SIGNATURE_HEADER ];
		$secret           = $this->get_option( 'webhook_secret' );

		// Parse the signature header (format: t=timestamp,v0=sig,h=headers,v1=sig)
		$parts = array();
		foreach ( explode( ',', $signature_header ) as $part ) {
			$split = explode( '=', $part, 2 );
			if ( count( $split ) === 2 ) {
				$parts[ $split[0] ] = $split[1];
			}
		}

		$timestamp = isset( $parts['t'] ) ? $parts['t'] : null;
		$signature = isset( $parts['v0'] ) ? $parts['v0'] : null;

		if ( ! $timestamp || ! $signature ) {
			self::log( 'Invalid signature header format.' );
			return false;
		}

		// Replay attack prevention: reject if timestamp > 5 minutes old.
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			self::log( 'Webhook signature timestamp too old.' );
			return false;
		}

		// Verify: hash_hmac('sha256', "$timestamp.$payload", $secret)
		$signed_payload     = $timestamp . '.' . $payload;
		$expected_signature = hash_hmac( 'sha256', $signed_payload, $secret );

		if ( hash_equals( $expected_signature, $signature ) ) {
			return true;
		}

		self::log( 'Webhook signature mismatch.' );
		return false;
	}

	/**
	 * Init the API class and set credentials.
	 */
	protected function init_api() {
		include_once dirname( __FILE__ ) . '/includes/class-coinbase-constants.php';
		include_once dirname( __FILE__ ) . '/includes/class-coinbase-jwt-auth.php';
		include_once dirname( __FILE__ ) . '/includes/class-coinbase-api-handler.php';

		Coinbase_API_Handler::$log             = get_class( $this ) . '::log';
		Coinbase_API_Handler::$cdp_key_name    = $this->get_option( 'cdp_key_name' );
		Coinbase_API_Handler::$cdp_private_key = $this->get_option( 'cdp_private_key' );
	}

	/**
	 * Update the status of an order from a payment link event.
	 *
	 * @param  WC_Order $order      WooCommerce order.
	 * @param  string   $event_type Payment link event type.
	 * @param  array    $data       Full event data.
	 */
	public function _update_order_status( $order, $event_type, $data = array() ) {
		$prev_status = $order->get_meta( '_coinbase_status' );

		if ( $event_type === $prev_status ) {
			return;
		}

		$order->update_meta_data( '_coinbase_status', $event_type );

		if ( Coinbase_Constants::EVENT_PAYMENT_SUCCESS === $event_type ) {
			// Extract transaction ID from payment URL.
			$txn_id = ! empty( $data['url'] ) ? basename( $data['url'] ) : '';
			$order->payment_complete( $txn_id );
			$order->update_status( 'completed', __( 'Coinbase payment was successfully processed.', 'coinbase' ) );
		} elseif ( Coinbase_Constants::EVENT_PAYMENT_FAILED === $event_type ) {
			$order->update_status( 'failed', __( 'Coinbase payment failed.', 'coinbase' ) );
		} elseif ( Coinbase_Constants::EVENT_PAYMENT_EXPIRED === $event_type ) {
			if ( 'pending' === $order->get_status() ) {
				$order->update_status( 'cancelled', __( 'Coinbase payment expired.', 'coinbase' ) );
			}
		}

		// Archive if in a resolved state and idle more than timeout.
		if ( in_array( $event_type, array( Coinbase_Constants::EVENT_PAYMENT_EXPIRED, Coinbase_Constants::EVENT_PAYMENT_SUCCESS ), true ) &&
			$order->get_date_modified() < $this->timeout ) {
			self::log( 'Archiving order: ' . $order->get_order_number() );
			$order->update_meta_data( '_coinbase_archived', true );
		}

		$order->save();
	}

	/**
	 * Handle a custom 'coinbase_archived' query var to get orders
	 * paid through Coinbase with the '_coinbase_payment_link_id' meta.
	 *
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 * @return array modified $query
	 */
	public function _custom_query_var( $query, $query_vars ) {
		if ( array_key_exists( 'coinbase_archived', $query_vars ) ) {
			$query['meta_query'][] = array(
				'key'     => '_coinbase_archived',
				'compare' => $query_vars['coinbase_archived'] ? 'EXISTS' : 'NOT EXISTS',
			);
			$query['meta_query'][] = array(
				'key'     => '_coinbase_payment_link_id',
				'compare' => 'EXISTS',
			);
		}

		return $query;
	}
}
