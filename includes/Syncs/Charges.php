<?php
Namespace Fullculqi\Syncs;

use Fullculqi\Traits\Singleton;

/**
 * Charges Class
 * @since  1.0.0
 * @package Includes / Sync / Charges
 */
class Charges extends Client {
	use Singleton;

	/**
	 * PostType
	 * @var string
	 */
	protected string $postType = 'culqi_charges';

	/**
	 * Endpoint to sync
	 * @var string
	 */
	protected string $endpoint = 'charges/';


	/**
	 * Update Charge
	 * @param  OBJ $charge
	 * @return mixed
	 */
	public function update( $charge ) {

		$post_id = fullculqi_post_from_meta( 'culqi_id', $charge->id );

		if( ! empty( $post_id ) )
			$post_id = self::create_wppost( $charge, $post_id );

		do_action( 'fullculqi/charges/update', $charge );
	}


	/**
	 * Create a charge
	 * @param  array  $post_data
	 * @return bool
	 */
	public function create( array $args ): \stdClass {

		$args   = \apply_filters( \sprintf( 'fullculqi/%s/create/args', $this->postType ), $args );
		$charge = $this->requestPost( $args );

		if ( ! $charge->success ) {
			return $charge;
		}

		// If it need a review. Apply 3Ds
		if( ! empty( $charge->data->body->action_code ) && $charge->data->body->action_code === 'REVIEW' ) {
			return (object)[
				'success' => true,
				'data'    => (object)[ 'needs3Ds' => true ]
			];
		}

		// Create wppost
		$postID = $this->createWPPost( $charge->data->body );

		\do_action( \sprintf( 'fullculqi/%s/create', $this->postType ), $postID, $charge );
		
		return (object) \apply_filters( \sprintf( 'fullculqi/%s/create/success', $this->postType ), [
			'success' => true,
			'data'    => (object)[
				'needs3Ds'      => false,
				'culqiChargeID' => $charge->data->body->id,
				'postChargeID'  => $postID
			]
		] );
	}


	/**
	 * Create WPPosts
	 * @param  object  $charge  
	 * @param  integer $post_id 
	 * @return mixed
	 */
	public function createWPPost( \stdClass $charge, ?int $postID = null ): int {

		if ( empty( $postID ) ) {
			
			$args = [
				'post_title'  => $charge->id,
				'post_type'   => 'culqi_charges',
				'post_status' => 'publish'
			];

			$postID = \wp_insert_post( $args );
		}

		$amount = \round( $charge->amount/100, 2 );
		$refund = \round( $charge->amount_refunded/100, 2 );

		\update_post_meta( $postID, 'culqi_data', $charge );
		\update_post_meta( $postID, 'culqi_id', $charge->id );
		\update_post_meta( $postID, 'culqi_capture', $charge->capture );
		\update_post_meta( $postID, 'culqi_capture_date',
			fullculqi_convertToDate( $charge->capture_date )
		);

		// If it use customer process
		if( isset( $charge->source->object ) && $charge->source->object == 'card' ) {
			\update_post_meta( $postID, 'culqi_customer_id', $charge->source->customer_id  );
			\update_post_meta( $postID, 'culqi_ip', $charge->source->source->client->ip );
		} else {
			\update_post_meta( $postID, 'culqi_ip', $charge->source->client->ip );
		}

		// Token type ( card or yape )
		$isYape = $charge->source->object == 'token' && \fullculqi_is_token_yape( $charge->source->id );
		\update_post_meta( $postID, 'culqi_charge_type', $isYape ? 'yape' : 'charge' );

		// Status
		$status = $charge->capture ? 'captured' : 'authorized';
		\update_post_meta( $postID, 'culqi_status', $status );

		// Creation Date
		\update_post_meta( $postID, 'culqi_creation_date',
			fullculqi_convertToDate( $charge->creation_date )
		);

		// Meta Values
		if( isset( $charge->metadata ) && ! empty( $charge->metadata ) ) {
			\update_post_meta( $postID, 'culqi_metadata', $charge->metadata );
		}

		$basic = [
			'culqi_amount'			=> $amount,
			'culqi_amount_refunded'	=> $refund,
			'culqi_currency'		=> $charge->currency_code,
		];

		\update_post_meta( $postID, 'culqi_basic', \array_map( 'esc_html', $basic ) );

		$customer = [
			'culqi_email'		=> $charge->email,
			'culqi_first_name'	=> '',
			'culqi_last_name'	=> '',
			'culqi_city'		=> '',
			'culqi_country'		=> '',
			'culqi_phone'		=> '',
		];

		// First Name
		if( isset( $charge->antifraud_details->first_name ) ) {
			$customer[ 'culqi_first_name' ] = $charge->antifraud_details->first_name;
		}

		// Last Name
		if( isset( $charge->antifraud_details->last_name ) ) {
			$customer[ 'culqi_last_name' ] = $charge->antifraud_details->last_name;
		}

		// Address City
		if( isset( $charge->antifraud_details->address_city ) ) {
			$customer[ 'culqi_city' ] = $charge->antifraud_details->address_city;
		}

		// Country Code
		if( isset( $charge->antifraud_details->country_code ) ) {
			$customer[ 'culqi_country' ] = $charge->antifraud_details->country_code;
		}

		// Phone
		if( isset( $charge->antifraud_details->phone ) ) {
			$customer[ 'culqi_phone' ] = $charge->antifraud_details->phone;
		}

		\update_post_meta( $postID, 'culqi_customer', \array_map( 'esc_html', $customer ) );

		\do_action( \sprintf( 'fullculqi/%s/wppost_create', $this->postType ), $charge, $postID );

		return $postID;
	}
}