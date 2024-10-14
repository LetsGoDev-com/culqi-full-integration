<?php
Namespace Fullculqi\Syncs;

use Fullculqi\Traits\Singleton;

/**
 * Orders Class
 * @since  1.0.0
 * @package Includes / Sync / Orders
 */
class Orders extends Client {
	use Singleton;

	/**
	 * PostType
	 * @var string
	 */
	protected string $postType = 'culqi_orders';


	/**
	 * Endpoint to sync
	 * @var string
	 */
	protected string $endpoint = 'orders/';
	

	/**
	 * Create Order
	 * @param  array $args_order
	 * @return array
	 */
	public function create( array $args ): \stdClass {

		$args  = \apply_filters( \sprintf( 'fullculqi/%s/create/args', $this->postType ), $args );
		$order = $this->requestPost( $args );

		if ( ! $order->success ) {
			return $order;
		}

		\do_action( \sprintf( 'fullculqi/%s/create', $this->postType ), $order->data->body );

		return (object) \apply_filters( \sprintf( 'fullculqi/%s/create/success', $this->postType ), [
			'success' => true,
			'data'	  => (object) [ 'culqiOrderID' => $order->data->body->id ]
		] );
	}

	/**
	 * Save metadata in Order
	 * @param  array  $args
	 * @return mixed
	 */
	public function afterConfirm( string $culqiOrderID, array $metadata ): \stdClass {

		$order = $this->requestPost( [ 'metadata' => $metadata ], $culqiOrderID );

		if ( ! $order->success ) {
			return $order;
		}

		// Update post
		$postID = $this->createWPPost( $order->data->body );

		\do_action(
			\sprintf( 'fullculqi/%s/after_confirm', $this->postType ), $postID, $order->data->body
		);

		return (object) \apply_filters(
			\sprintf( 'fullculqi/%s/after_confirm/success', $this->postType ), [
				'success' => true,
				'data'    => (object)[
					'culqiOrderID' => $order->data->body->id,
					'postOrderID'  => $postID
				]
			]
		);
	}

	/**
	 * Update Order from webhook
	 * @param  object $culqi_order
	 * @return mixed
	 */
	public function update( \stdClass $order ) {
		
		if( ! isset( $order->id ) ) {
			return;
		}

		//$cip_code = trim( $culqi_order->payment_code );
		$postID = fullculqi_post_from_meta( 'culqi_id', $order->id );

		if( ! empty( $postID ) ) {
			$postID = $this->createWPPost( $order, $postID );
		}

		\do_action( \sprintf( 'fullculqi/%s/update', $this->postType ), $order );
	}

	/**
	 * Create Order Post
	 * @param  integer $post_id
	 * @param  objt $culqi_order
	 * @param  integer $post_customer_id
	 * @return integer
	 */
	public function createWPPost( \stdClass $order, int $postID = 0 ) {

		if( empty( $postID ) ) {

			// Create Post
			$args = [
				'post_title'	=> $order->id,
				'post_type'		=> 'culqi_orders',
				'post_status'	=> 'publish',
			];

			$postID = \wp_insert_post( $args );
		}

		$amount = \round( $order->amount/100, 2 );

		\update_post_meta( $postID, 'culqi_id', $order->id );
		\update_post_meta( $postID, 'culqi_qr', $order->qr ?? '' );
		\update_post_meta( $postID, 'culqi_data', $order );
		\update_post_meta( $postID, 'culqi_status', $order->state );
		\update_post_meta( $postID, 'culqi_status_date', date('Y-m-d H:i:s') );

		// CIP CODE
		$cip = '';
		
		if( ! empty( $order->payment_code ) ) {
			$cip = $order->payment_code;
		} elseif( isset( $order->metadata->cip_code ) ) {
			$cip = $order->metadata->cip_code;
		}

		\update_post_meta( $postID, 'culqi_cip', $cip );

		\update_post_meta( $postID, 'culqi_creation_date', \fullculqi_convertToDate( $order->creation_date ) );

		$basic = [
			'culqi_expiration'		=> \fullculqi_convertToDate( $order->expiration_date ),
			'culqi_amount'			=> $amount,
			'culqi_currency'		=> $order->currency_code,
		];

		\update_post_meta( $postID, 'culqi_basic', $basic );

		// Metavalues
		if ( isset( $order->metadata ) && ! empty( $order->metadata ) ) {
			\update_post_meta( $postID, 'culqi_metadata', $order->metadata );
		}

		// Customers
		$customer = [
			'post_id'          => 0,
			'culqi_email'      => '',
			'culqi_first_name' => '',
			'culqi_last_name'  => '',
			'culqi_city'       => '',
			'culqi_country'    => '',
			'culqi_phone'      => '',
		];

		// Save customer
		if( isset( $order->metadata->post_customer_id ) ) {
			$customer[ 'post_id' ] = $order->metadata->post_customer_id;
		}

		if( isset( $order->metadata->customer_email ) ) {
			$customer[ 'culqi_email' ] = $order->metadata->customer_email;
		}

		if( isset( $order->metadata->customer_first ) ) {
			$customer[ 'culqi_first_name' ] = $order->metadata->customer_first;
		}

		if( isset( $order->metadata->customer_last ) ) {
			$customer[ 'culqi_last_name' ] = $order->metadata->customer_last;
		}

		if( isset( $order->metadata->customer_city ) ) {
			$customer[ 'culqi_city' ] = $order->metadata->customer_city;
		}

		if( isset( $order->metadata->customer_country ) ) {
			$customer[ 'culqi_country' ] = $order->metadata->customer_country;
		}

		if( isset( $order->metadata->customer_phone ) ) {
			$customer[ 'culqi_phone' ] = $order->metadata->customer_phone;
		}

		// Customer
		\update_post_meta( $postID, 'culqi_customer', $customer );


		\do_action( \sprintf( 'fullculqi/%s/wppost', $this->postType ), $order, $postID );

		return $postID;
	}
}