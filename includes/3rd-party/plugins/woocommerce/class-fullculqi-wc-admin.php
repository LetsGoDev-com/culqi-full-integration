<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * WooCommerce Class
 * @since  1.0.0
 * @package Includes / 3rd-party / plugins / WooCommerce
 */
class FullCulqi_WC_Admin {

	public function __construct() {
		// Metaboxes to Shop Order CPT
		add_action( 'add_meta_boxes', [ $this, 'metaboxes'], 10, 1 );

		// Metaboxes Charges columns
		add_filter( 'fullculqi/charges/column_name', [ $this, 'column_name' ], 10, 1 );
		add_filter( 'fullculqi/charges/column_value', [ $this, 'column_value' ], 10, 3 );
		add_filter( 'fullculqi/orders/column_name', [ $this, 'column_name' ], 10, 1 );
		add_filter( 'fullculqi/orders/column_value', [ $this, 'column_value' ], 10, 3 );

		// Metaboxes Charges Edit
		add_action(  'fullculqi/charges/basic/print_data', [ $this, 'basic_print_order' ] );
		add_action(  'fullculqi/orders/basic/print_data', [ $this, 'basic_print_order' ] );

		// Create WPPost
		add_action( 'fullculqi/culqi_charges/sync/loop', [ $this, 'link_to_wc_orders' ], 10, 2 );

		// Ajax Refund
		add_filter( 'fullculqi/ajax/refund/is_external', '__return_true' );
		add_filter( 'fullculqi/ajax/refund/process_external', [ $this, 'createRefundProcess' ] );
	}

	/**
	 * Add Meta Boxes to Shop Order CPT
	 * @param  WP_POST $post
	 * @return mixed
	 */
	public function metaboxes( $post ) {

		$orderScreen = \wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? \wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'fullculqi_payment_log',
			esc_html__( 'FullCulqi Logs', 'fullculqi' ),
			[ $this, 'metabox_log' ],
			$orderScreen,
			'normal',
			'core'
		);
	}


	/**
	 * Metaboxes Log
	 * @param  WP_POST $post
	 * @return mixed
	 */
	public function metabox_log( $queriedObject ) {

		$order = ( $queriedObject instanceof \WP_Post ) ? \wc_get_order( $queriedObject->ID ) : $queriedObject;

		$args = [
			'logs' => $order->get_meta('culqi_log'),
		];

		fullculqi_get_template( 'layouts/order_log.php', $args, FULLCULQI_WC_DIR );
	}


	/**
	 * Charges Column Name
	 * @param  array $newCols]
	 * @param  [type] $cols
	 * @return array
	 */
	public function column_name( $newCols = [] ) {

		if( ! class_exists( 'WooCommerce' ) ) {
			return $newCols;
		}

		$newCols['culqi_wc_order_id']	= esc_html__( 'WC Order', 'fullculqi' );

		return $newCols;
	}


	/**
	 * Charge Column Value
	 * @param  string  $value
	 * @param  string  $col
	 * @param  integer $post_id
	 * @return mixed
	 */
	public function column_value( $value = '', $col = '', $post_id = 0 ) {
		if( $col != 'culqi_wc_order_id' )
			return $value;

		$value = '';
		$order_id = get_post_meta( $post_id, 'culqi_wc_order_id', true );

		if( ! empty( $order_id ) ) {
			$value = sprintf(
				'<a target="_blank" href="%s">%s</a>',
				get_edit_post_link( $order_id ), $order_id
			);
		}

		return $value;
	}


	/**
	 * Print WC Order in Metaboxes Basic
	 * @param  integer $post_id
	 * @return html
	 */
	public function basic_print_order( $post_id = 0 ) {

		if( empty( $post_id ) )
			return;

		$args = [
			'order_id' => get_post_meta( $post_id, 'culqi_wc_order_id', true ),
		];
		
		fullculqi_get_template( 'layouts/charge_basic.php', $args, FULLCULQI_WC_DIR );
	}


	/**
	 * Link Charge to WC Orders
	 * @param  Culqi Object  $charge
	 * @param  integer $post_id
	 * @return mixed
	 */
	public function link_to_wc_orders( stdClass $charge, int $postID = 0 ) {

		if ( empty( $charge ) || empty( $postID ) ) {
			return;
		}

		$orderID = fullculqi_post_from_meta( '_culqi_charge_id', $charge->id );
		$order   = wc_get_order( $orderID );

		if( ! $order instanceof WC_Order ) {
			return;
		}

		// WC Order Meta - Customer
		$culqiCustomerID = get_post_meta( $postID, 'culqi_customer_id', true );

		if( ! empty( $culqiCustomerID ) ) {
			$postCustomerID = fullculqi_post_from_meta( 'culqi_id', $culqiCustomerID );

			// WC Order - Charge
			$order->update_meta_data( '_culqi_customer_id', $culqiCustomerID );
			$order->update_meta_data( '_post_customer_id', $postCustomerID );
		}

		// Update WC Order in Charge CPT
		update_post_meta( $postID, 'culqi_wc_order_id', $orderID );

		// WC Order - Charge
		$order->update_meta_data( '_culqi_charge_id', $charge->id );
		$order->update_meta_data( '_post_charge_id', $postID );

		$order->save_meta_data();

		return true;
	}


	/**
	 * Create Refund to WC
	 * @param  integer $post_charge_id
	 * @return mixed
	 */
	public function createRefundProcess( int $postChargeID ): bool {

		// WC Order ID
		$orderID = get_post_meta( $postChargeID, 'culqi_wc_order_id', true );
		$order 	 = wc_get_order( $orderID );

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		// WC Refund
		$basic = get_post_meta( $postChargeID, 'culqi_basic', true );
	
		$wcRefund = wc_create_refund( [
			'amount'         => wc_format_decimal( $basic['culqi_current_amount'] ),
			'reason'         => \esc_html__( 'Refund from Charge CPT', 'fullculqi' ),
			'order_id'       => $orderID,
			'line_items'     => [],
			'refund_payment' => true,
			'restock_items'  => true,
		] );

		return true;
	}
}

new FullCulqi_WC_Admin();