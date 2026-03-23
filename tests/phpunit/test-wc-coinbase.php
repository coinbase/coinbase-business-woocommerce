<?php

class WC_Coinbase_Test extends WP_UnitTestCase {
	/**
	 * Test that Coinbase payment gateway has loaded.
	 */
	public function test_gateway_loaded() {
		$this->assertArrayHasKey( 'coinbase', WC()->payment_gateways()->payment_gateways() );
	}

	/**
	 * Create $product_count simple products and store them in $this->products.
	 *
	 * @param int $product_count Number of products to create.
	 */
	protected function create_products( $product_count = 30 ) {
		$this->products = array();
		for ( $i = 0; $i < $product_count; $i++ ) {
			$product = WC_Helper_Product::create_simple_product();
			$product->set_name( 'Dummy Product ' . $i );
			$this->products[] = $product;

		}

		// To test limit length properly, we need a product with a name that is shorter than 127 chars,
		// but longer than 127 chars when URL-encoded.
		if ( array_key_exists( 0, $this->products ) ) {
			$this->products[0]->set_name( 'Dummy Product 😎😎😎😎😎😎😎😎😎😎😎' );
		}

	}

	/**
	 * Add products from $this->products to $order as items, clearing existing order items.
	 *
	 * @param WC_Order $order Order to which the products should be added.
	 * @param array    $prices Array of prices to use for created products. Leave empty for default prices.
	 */
	protected function add_products_to_order( $order, $prices = array() ) {
		// Remove previous items.
		foreach ( $order->get_items() as $item ) {
			$order->remove_item( $item->get_id() );
		}

		// Add new products.
		$prod_count = 0;
		foreach ( $this->products as $product ) {
			$item = new WC_Order_Item_Product();
			$item->set_props( array(
				'product'  => $product,
				'quantity' => 3,
				'subtotal' => $prices ? $prices[ $prod_count ] : wc_get_price_excluding_tax( $product, array( 'qty' => 3 ) ),
				'total'    => $prices ? $prices[ $prod_count ] : wc_get_price_excluding_tax( $product, array( 'qty' => 3 ) ),
			) );

			$item->save();
			$order->add_item( $item );

			$prod_count++;
		}

	}

	/**
	 * Test USDC icon is displayed.
	 */
	public function test_usdc_icon() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];

		$icons   = $payment_gateway->get_icon();
		$matches = array();
		$num     = preg_match_all( '/<img .*? src="(.*?)".*?\/>/', $icons, $matches );

		$this->assertSame( 1, $num );
		$this->assertStringEndsWith( 'usdc.png', $matches[1][0] );
	}

	/**
	 * Test an order checkout.
	 */
	public function test_order_checkout() {
		// User set up.
		$this->user = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		wp_set_current_user( $this->user );

		// Create products.
		$this->create_products( 5 );

		$this->order = WC_Helper_Order::create_order( $this->user );
		$this->add_products_to_order( $this->order, array() );

		// Set payment method to Coinbase.
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];
		$this->order->set_payment_method( $payment_gateway );

		$this->order->calculate_shipping();
		$this->order->calculate_totals();

		// Set store currency to USD for the test.
		update_option( 'woocommerce_currency', 'USD' );

		add_filter( 'pre_http_request', array( $this, 'pre_http_request_checkout_success' ) );
		$result = $payment_gateway->process_payment( $this->order->get_id() );
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request_checkout_success' ) );

		$this->assertSame( 'success', $result['result'] );

		// Verify the new meta key is stored.
		$order = wc_get_order( $this->order->get_id() );
		$this->assertNotEmpty( $order->get_meta( '_coinbase_checkout_id' ) );

		// Remove order and created products.
		WC_Helper_Order::delete_order( $this->order->get_id() );
	}

	/**
	 * Test that non-USD currency returns failure.
	 */
	public function test_currency_validation() {
		// User set up.
		$this->user = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		wp_set_current_user( $this->user );

		$this->create_products( 1 );
		$this->order = WC_Helper_Order::create_order( $this->user );
		$this->add_products_to_order( $this->order, array() );

		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];
		$this->order->set_payment_method( $payment_gateway );
		$this->order->calculate_totals();

		// Set store currency to EUR.
		update_option( 'woocommerce_currency', 'EUR' );

		$result = $payment_gateway->process_payment( $this->order->get_id() );

		$this->assertSame( 'fail', $result['result'] );

		// Restore USD.
		update_option( 'woocommerce_currency', 'USD' );

		// Cleanup.
		WC_Helper_Order::delete_order( $this->order->get_id() );
	}

	/**
	 * Test webhook signature validation with timestamp-based HMAC.
	 */
	public function test_webhook_signature_validation() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];

		// Set a known webhook secret.
		$payment_gateway->update_option( 'webhook_secret', 'test_secret_123' );

		$payload   = '{"eventType":"checkout.payment.success","metadata":{"order_id":"1"}}';
		$timestamp = time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $payload, 'test_secret_123' );

		// Simulate the x-hook0-signature header.
		$_SERVER['HTTP_X_HOOK0_SIGNATURE'] = 't=' . $timestamp . ',v0=' . $signature;

		// Need to include constants for the header name.
		include_once dirname( dirname( __DIR__ ) ) . '/includes/class-coinbase-constants.php';

		$result = $payment_gateway->validate_webhook( $payload );
		$this->assertTrue( $result );

		// Test with wrong signature.
		$_SERVER['HTTP_X_HOOK0_SIGNATURE'] = 't=' . $timestamp . ',v0=invalidsignature';
		$result = $payment_gateway->validate_webhook( $payload );
		$this->assertFalse( $result );

		// Clean up.
		unset( $_SERVER['HTTP_X_HOOK0_SIGNATURE'] );
	}

	/**
	 * Test that an expired timestamp is rejected by webhook validation.
	 */
	public function test_webhook_signature_rejects_expired_timestamp() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];
		$payment_gateway->update_option( 'webhook_secret', 'test_secret_123' );

		$payload   = '{"eventType":"checkout.payment.success","metadata":{"order_id":"1"}}';
		$timestamp = time() - 400; // >300s old.
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $payload, 'test_secret_123' );

		$_SERVER['HTTP_X_HOOK0_SIGNATURE'] = 't=' . $timestamp . ',v0=' . $signature;

		include_once dirname( dirname( __DIR__ ) ) . '/includes/class-coinbase-constants.php';

		$result = $payment_gateway->validate_webhook( $payload );
		$this->assertFalse( $result );

		unset( $_SERVER['HTTP_X_HOOK0_SIGNATURE'] );
	}

	/**
	 * Test that malformed or missing signature header fields are rejected.
	 */
	public function test_webhook_signature_rejects_malformed_header() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];
		$payment_gateway->update_option( 'webhook_secret', 'test_secret_123' );

		$payload = '{"eventType":"checkout.payment.success","metadata":{"order_id":"1"}}';

		include_once dirname( dirname( __DIR__ ) ) . '/includes/class-coinbase-constants.php';

		// Missing t= (only v0=...).
		$_SERVER['HTTP_X_HOOK0_SIGNATURE'] = 'v0=somesignature';
		$this->assertFalse( $payment_gateway->validate_webhook( $payload ) );

		// Missing v0= (only t=...).
		$_SERVER['HTTP_X_HOOK0_SIGNATURE'] = 't=' . time();
		$this->assertFalse( $payment_gateway->validate_webhook( $payload ) );

		// Completely absent header.
		unset( $_SERVER['HTTP_X_HOOK0_SIGNATURE'] );
		$this->assertFalse( $payment_gateway->validate_webhook( $payload ) );
	}

	/**
	 * Test get_checkout returns success on 200 response.
	 */
	public function test_get_checkout_success() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];

		// Init the API so the handler class is loaded.
		$init_api = new ReflectionMethod( $payment_gateway, 'init_api' );
		$init_api->setAccessible( true );
		$init_api->invoke( $payment_gateway );

		add_filter( 'pre_http_request', array( $this, 'pre_http_request_get_checkout_success' ) );
		$result = Coinbase_API_Handler::get_checkout( 'pl_ABC123' );
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request_get_checkout_success' ) );

		$this->assertTrue( $result[0] );
		$this->assertSame( 'pl_ABC123', $result[1]['id'] );
	}

	/**
	 * Test get_checkout returns failure on error response.
	 */
	public function test_get_checkout_failure() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];

		$init_api = new ReflectionMethod( $payment_gateway, 'init_api' );
		$init_api->setAccessible( true );
		$init_api->invoke( $payment_gateway );

		add_filter( 'pre_http_request', array( $this, 'pre_http_request_get_checkout_failure' ) );
		$result = Coinbase_API_Handler::get_checkout( 'pl_INVALID' );
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request_get_checkout_failure' ) );

		$this->assertFalse( $result[0] );
	}

	/**
	 * Return successful result for checkout creation.
	 */
	public function pre_http_request_checkout_success() {
		return array(
			'response' => array( 'code' => 201 ),
			'body'     => wp_json_encode( array(
				'id'  => 'pl_ABC123',
				'url' => 'https://business.coinbase.com/pay/test',
			) ),
		);
	}

	/**
	 * Return successful result for get_checkout.
	 */
	public function pre_http_request_get_checkout_success() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array(
				'id'     => 'pl_ABC123',
				'url'    => 'https://business.coinbase.com/pay/test',
				'status' => 'COMPLETED',
			) ),
		);
	}

	/**
	 * Return error result for get_checkout with new error format.
	 */
	public function pre_http_request_get_checkout_failure() {
		return array(
			'response' => array( 'code' => 400 ),
			'body'     => wp_json_encode( array(
				'errorMessage' => 'Checkout not found',
			) ),
		);
	}

	/**
	 * Test that sandbox mode returns sandbox API path.
	 */
	public function test_sandbox_mode_uses_sandbox_api_path() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];

		$init_api = new ReflectionMethod( $payment_gateway, 'init_api' );
		$init_api->setAccessible( true );
		$init_api->invoke( $payment_gateway );

		Coinbase_API_Handler::$sandbox_mode = true;
		$this->assertSame( '/sandbox/api/v1/checkouts', Coinbase_API_Handler::get_api_path() );

		// Clean up.
		Coinbase_API_Handler::$sandbox_mode = false;
	}

	/**
	 * Test that production mode returns production API path.
	 */
	public function test_production_mode_uses_production_api_path() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];

		$init_api = new ReflectionMethod( $payment_gateway, 'init_api' );
		$init_api->setAccessible( true );
		$init_api->invoke( $payment_gateway );

		Coinbase_API_Handler::$sandbox_mode = false;
		$this->assertSame( '/api/v1/checkouts', Coinbase_API_Handler::get_api_path() );
	}

	/**
	 * Test webhook uses sandbox secret when sandbox mode is enabled.
	 */
	public function test_webhook_uses_sandbox_secret_in_sandbox_mode() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];

		$payment_gateway->update_option( 'sandbox_mode', 'yes' );
		$payment_gateway->update_option( 'webhook_secret', 'production_secret' );
		$payment_gateway->update_option( 'sandbox_webhook_secret', 'sandbox_secret' );

		$payload   = '{"eventType":"checkout.payment.success","metadata":{"order_id":"1"}}';
		$timestamp = time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $payload, 'sandbox_secret' );

		$_SERVER['HTTP_X_HOOK0_SIGNATURE'] = 't=' . $timestamp . ',v0=' . $signature;

		include_once dirname( dirname( __DIR__ ) ) . '/includes/class-coinbase-constants.php';

		$result = $payment_gateway->validate_webhook( $payload );
		$this->assertTrue( $result );

		// Clean up.
		$payment_gateway->update_option( 'sandbox_mode', 'no' );
		unset( $_SERVER['HTTP_X_HOOK0_SIGNATURE'] );
	}

	/**
	 * Test webhook uses production secret when sandbox mode is disabled.
	 */
	public function test_webhook_uses_production_secret_in_production_mode() {
		$payment_gateway = WC()->payment_gateways->payment_gateways()['coinbase'];

		$payment_gateway->update_option( 'sandbox_mode', 'no' );
		$payment_gateway->update_option( 'webhook_secret', 'production_secret' );
		$payment_gateway->update_option( 'sandbox_webhook_secret', 'sandbox_secret' );

		$payload   = '{"eventType":"checkout.payment.success","metadata":{"order_id":"1"}}';
		$timestamp = time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $payload, 'production_secret' );

		$_SERVER['HTTP_X_HOOK0_SIGNATURE'] = 't=' . $timestamp . ',v0=' . $signature;

		include_once dirname( dirname( __DIR__ ) ) . '/includes/class-coinbase-constants.php';

		$result = $payment_gateway->validate_webhook( $payload );
		$this->assertTrue( $result );

		// Clean up.
		unset( $_SERVER['HTTP_X_HOOK0_SIGNATURE'] );
	}

	/**
	 * Test that legacy payment_link.* webhook events are normalized to checkout.* events.
	 */
	public function test_legacy_webhook_event_normalization() {
		include_once dirname( dirname( __DIR__ ) ) . '/includes/class-coinbase-constants.php';

		// Legacy events should be normalized.
		$this->assertSame( 'checkout.payment.success', Coinbase_Constants::normalize_event_type( 'payment_link.payment.success' ) );
		$this->assertSame( 'checkout.payment.failed', Coinbase_Constants::normalize_event_type( 'payment_link.payment.failed' ) );
		$this->assertSame( 'checkout.payment.expired', Coinbase_Constants::normalize_event_type( 'payment_link.payment.expired' ) );

		// New events should pass through unchanged.
		$this->assertSame( 'checkout.payment.success', Coinbase_Constants::normalize_event_type( 'checkout.payment.success' ) );
		$this->assertSame( 'checkout.payment.failed', Coinbase_Constants::normalize_event_type( 'checkout.payment.failed' ) );
		$this->assertSame( 'checkout.payment.expired', Coinbase_Constants::normalize_event_type( 'checkout.payment.expired' ) );
	}

	/**
	 * Test that GET response statuses are correctly mapped to event types.
	 */
	public function test_status_to_event_type_mapping() {
		include_once dirname( dirname( __DIR__ ) ) . '/includes/class-coinbase-constants.php';

		// Terminal statuses should map to event types.
		$this->assertSame( 'checkout.payment.success', Coinbase_Constants::status_to_event_type( 'COMPLETED' ) );
		$this->assertSame( 'checkout.payment.failed', Coinbase_Constants::status_to_event_type( 'FAILED' ) );
		$this->assertSame( 'checkout.payment.expired', Coinbase_Constants::status_to_event_type( 'EXPIRED' ) );

		// Non-terminal statuses should return null.
		$this->assertNull( Coinbase_Constants::status_to_event_type( 'ACTIVE' ) );
		$this->assertNull( Coinbase_Constants::status_to_event_type( 'PROCESSING' ) );
	}
}
