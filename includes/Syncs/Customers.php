<?php
Namespace Fullculqi\Syncs;

use Fullculqi\Traits\Singleton;

/**
 * Customers Class
 * @since  1.0.0
 * @package Includes / Sync / Customers
 */
class Customers extends Client {
	use Singleton;

	/**
	 * PostType
	 * @var string
	 */
	protected string $postType = 'culqi_customers';


	/**
	 * Endpoint to sync
	 * @var string
	 */
	protected string $endpoint = 'customers/';


	/**
	 * Get Customer from meta values
	 * @param  integer $wpuser_id
	 * @return bool
	 */
	public function get( int $WPUserID = 0 ): ?\stdClass {

		// Check in the WP_USERS
		$culqiCustomerID = \get_user_meta( $WPUserID, '_culqi_customer_id', true );
		$postCustomerID  = \get_user_meta( $WPUserID, '_post_customer_id', true );

		if ( ! empty( $culqiCustomerID ) && ! empty( $postCustomerID ) ) {
			return (object)[
				'wpuserID' => $WPUserID,
				'culqiID'  => $culqiCustomerID,
				'postID'   => $postCustomerID
			];
		}

		// Check in the Customer CPT
		$postID = \fullculqi_post_from_meta( 'culqi_wp_user_id', \absint( $WPUserID ) );

		if ( empty( $postID ) ) {
			return null;
		}

		$culqiCustomerID = \get_post_meta( $postID, 'culqi_id', true );

		if ( empty( $culqiCustomerID ) ) {
			return null;
		}

		return (object)[
			'wpuserID' => $WPUserID,
			'culqiID'  => $culqiCustomerID,
			'postID'   => $postID
		];
	}

	/**
	 * Get Customer by Email
	 * @param  string $email
	 * @return array
	 */
	public function getByEmail( string $email ): ?\stdClass {

		$args = [
			'post_type'	  => $this->postType,
			'post_status' => 'publish',
			'meta_query'  => [[
					'key'     => 'culqi_email',
					'value'	  => $email,
					'compare' => '=',
				]
			]
		];

		$posts = \get_posts( $args );

		if ( $posts ) {
			foreach ( $posts as $post ) {
				return (object)[
					'wpuserID' => \get_post_meta( $post->ID, 'culqi_wp_user_id', true ),
					'culqiID'  => \get_post_meta( $post->ID, 'culqi_id', true ),
					'postID'   => $post->ID,
				];
			}
		}

		return null;
	}

	
	/**
	 * Create Customer
	 * @param  integer $wpuser_id
	 * @param  array   $post_data
	 * @return mixed
	 */
	public function create( int $WPUserID = 0, array $args = [] ): \stdClass {

		$args     = \apply_filters( \sprintf( 'fullculqi/%s/create/args', $this->postType ), $args );
		$customer = $this->requestPost( $args );

		if ( ! $customer->success ) {
			return $customer;
		}


		// Create Order Post
		$postID = $this->createWPPost( $customer->data->body );
		
		// Update Meta user
		\update_post_meta( $postID, 'culqi_wp_user_id', $WPUserID );
		\update_user_meta( $WPUserID, '_culqi_customer_id', $customer->data->body->id );
		\update_user_meta( $WPUserID, '_post_customer_id', $postID );

		\do_action( \sprintf( 'fullculqi/%s/create', $this->postType ), $postID, $customer );

		return (object) \apply_filters( \sprintf( 'fullculqi/%s/create/success', $this->postType ), [
			'success' => true,
			'data'	  => (object)[
				'culqiCustomerID' => $customer->data->body->id,
				'postCustomerID'  => $postID
			]
		] );
	}


	/**
	 * Create Order Post
	 * @param  integer $post_id
	 * @param  objt $customer
	 * @return integer
	 */
	public function createWPPost( \stdClass $customer, int $postID = 0 ): int {

		if ( empty( $postID ) ) {

			// Create Post
			$args = [
				'post_title'  => $customer->id,
				'post_type'	  => 'culqi_customers',
				'post_status' => 'publish',
			];

			$postID = \wp_insert_post( $args );
		}

		$names = \sprintf( '%s %s',
			$customer->antifraud_details->first_name,
			$customer->antifraud_details->last_name
		);

		\update_post_meta( $postID, 'culqi_id', $customer->id );
		\update_post_meta( $postID, 'culqi_data', $customer );
		\update_post_meta( $postID, 'culqi_email', $customer->email );

		\update_post_meta( $postID, 'culqi_creation_date', fullculqi_convertToDate( $customer->creation_date ) );

		$basic = [
			'culqi_first_name' => $customer->antifraud_details->first_name,
			'culqi_last_name'  => $customer->antifraud_details->last_name,
			'culqi_names'      => $names,
			'culqi_address'    => $customer->antifraud_details->address,
			'culqi_city'       => $customer->antifraud_details->address_city,
			'culqi_country'    => $customer->antifraud_details->country_code,
			'culqi_phone'      => $customer->antifraud_details->phone,
		];

		\update_post_meta( $postID, 'culqi_basic', $basic );

		\do_action( \sprintf( 'fullculqi/%s/wppost', $this->postType ), $customer, $postID );

		return $postID;
	}


	/**
	 * Delete Posts
	 * @return mixed
	 */
	public function deleteWPPosts(): bool {
		global $wpdb;

		// Usermeta
		$query = \sprintf(
			'DELETE FROM
				%s
			WHERE
				meta_key IN ("culqi_id", "culqi_post_id", "_culqi_customer_id")',
			$wpdb->usermeta
		);

		$wpdb->query( $query );

		return parent::deleteWPPosts();
	}
}