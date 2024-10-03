<?php

use Fullculqi\Syncs\Charges;
use Fullculqi\Syncs\Orders;
use Fullculqi\Syncs\Customers;
use Fullculqi\Syncs\Refunds;

/**
 * Ajax Class
 * @since  1.0.0
 * @package Includes / Ajax
 */
class FullCulqi_Ajax {

	public function __construct() {

		// Create a refund
		add_action( 'wp_ajax_create_culqi_refund', [ $this, 'create_refund' ] );

		// Delete All Charges
		add_action( 'wp_ajax_delete_culqi_charges', [ $this, 'deleteCharges' ] );

		// Delete All Orders
		add_action( 'wp_ajax_delete_culqi_orders', [ $this, 'deleteOrders' ] );

		// Delete All Customers
		add_action( 'wp_ajax_delete_culqi_customers', [ $this, 'deleteCustomers' ] );

		// Sync Charges from the admin
		add_action( 'wp_ajax_sync_culqi_charges', [ $this, 'syncCharges' ] );

		// Sync Orders from the admin
		add_action( 'wp_ajax_sync_culqi_orders', [ $this, 'syncOrders' ] );

		// Sync Customers from the admin
		add_action( 'wp_ajax_sync_culqi_customers', [ $this, 'syncCustomers' ] );

	}

	/**
	 * Sync Charges from Admin
	 * @return json
	 */
	public function syncCharges(): mixed {

		// Run a security check.
		\check_ajax_referer( 'fullculqi-wpnonce', 'wpnonce' );

		// Check the permissions
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \esc_html__( 'You do not have permission.', 'fullculqi' ) );
		}

		$record  = isset( $_POST['record'] ) ? \intval( $_POST['record'] ) : 100;
		$afterID = isset( $_POST['after_id'] ) ? \esc_html( $_POST['after_id'] ) : '';

		$charges = Charges::getInstance()->sync( $record, $afterID );

		if( ! $charges->success ) {
			\wp_send_json_error( $charges->data );
		}

		\wp_send_json_success( $charges->data );
	}

	/**
	 * Sync Charges from Admin
	 * @return json
	 */
	public function syncOrders(): mixed {

		// Run a security check.
		\check_ajax_referer( 'fullculqi-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \esc_html__( 'You do not have permission.', 'fullculqi' ) );
		}

		$record  = isset( $_POST['record'] ) ? \intval( $_POST['record'] ) : 100;
		$afterID = isset( $_POST['after_id'] ) ? \esc_html( $_POST['after_id'] ) : '';

		$orders = Orders::getInstance()->sync( $record, $afterID );

		if ( ! $orders->success ) {
			\wp_send_json_error( $orders->data );
		}

		\wp_send_json_success( $orders->data );
	}


	/**
	 * Sync Customer from Admin
	 * @return json
	 */
	public function syncCustomers(): mixed {
		
		// Run a security check.
		\check_ajax_referer( 'fullculqi-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! \current_user_can( 'manage_options' ) )
			\wp_send_json_error( \esc_html__( 'You do not have permission.', 'fullculqi' ) );

		$record  = isset( $_POST['record'] ) ? \intval( $_POST['record'] ) : 100;
		$afterID = isset( $_POST['after_id'] ) ? \esc_html( $_POST['after_id'] ) : '';

		$customers = Customers::getInstance()->sync( $record, $afterID );

		if ( ! $customers->success ) {
			\wp_send_json_error( $customers->data );
		}
			
		\wp_send_json_success( $customers->data );
	}

	/**
	 * Delete all the charges posts
	 * @return mixed
	 */
	public function deleteCharges(): mixed {

		// Run a security check.
		\check_ajax_referer( 'fullculqi-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \esc_html__( 'You do not have permission.', 'fullculqi' ) );
		}

		$isDeleted = Charges::getInstance()->deleteWPPosts();
		
		if( ! $isDeleted ) {
			\wp_send_json_error();
		}

		\wp_send_json_success();	
	}

	/**
	 * Delete all the orders posts
	 * @return mixed
	 */
	public function deleteOrders(): mixed {

		// Run a security check.
		\check_ajax_referer( 'fullculqi-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \esc_html__( 'You do not have permission.', 'fullculqi' ) );
		}

		$isDeleted = Orders::getInstance()->deleteWPPosts();
		
		if( ! $isDeleted ) {
			\wp_send_json_error();
		}

		\wp_send_json_success();	
	}

	/**
	 * Delete all the customers posts
	 * @return mixed
	 */
	public function deleteCustomers(): mixed {

		// Run a security check.
		\check_ajax_referer( 'fullculqi-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \esc_html__( 'You do not have permission.', 'fullculqi' ) );
		}

		$isDeleted = Customers::getInstance()->deleteWPPosts();
		
		if( ! $isDeleted ) {
			\wp_send_json_error();
		}

		\wp_send_json_success();	
	}


	/**
	 * Create Refund from CPT
	 * @return mixed
	 */
	public function create_refund() {

		// Run a security check.
		\check_ajax_referer( 'fullculqi-wpnonce', 'wpnonce' );

		// Check the permissions
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \esc_html__( 'You do not have permission.', 'fullculqi' ) );
		}

		// Check if the post exists
		if ( ! isset( $_POST['post_id'] ) || empty( $_POST['post_id'] ) ) {
			\wp_send_json_error();
		}

		// Charge Post ID
		$postChargeID = \absint( $_POST['post_id'] );


		$refund = new \stdClass();

		// 3rd-party
		$refund = \apply_filters( 'fullculqi/ajax/refund/process', $refund, $postChargeID );

		if ( empty( $refund ) ) {

			// Meta Basic from Charges
			$chargeBasic = \get_post_meta( $postChargeID, 'culqi_basic', true );
			$amount      = \floatval( $chargeBasic['culqi_amount'] ) - floatval( $chargeBasic['culqi_amount_refunded'] );

			// Culqi Charge ID
			$culqiChargeID = \get_post_meta( $postChargeID, 'culqi_id', true );

			$args = [
				'amount'	=> \round( $amount*100, 0 ),
				'charge_id'	=> $culqiChargeID,
				'reason'	=> 'solicitud_comprador',
				'metadata'	=> [
					'post_id'	=> $postChargeID,
				],
			];

			$refund = Refunds::getInstance()->create( $postChargeID, $args );
		}

		\do_action( 'fullculqi/ajax/refund/create', $refund );

		if ( $refund && $refund->success ) {
			\wp_send_json_success();
		}

		\wp_send_json_error( $refund->data->message ?? '' );
	}
}

new FullCulqi_Ajax();
?>