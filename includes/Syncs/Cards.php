<?php
Namespace Fullculqi\Syncs;

use Fullculqi\Traits\Singleton;

/**
 * Customers Class
 * @since  1.0.0
 * @package Includes / Sync / Customers
 */
class Cards extends Client {
	use Singleton;

	/**
	 * Endpoint to sync
	 * @var string
	 */
	protected string $endpoint = 'cards/';
	
	/**
	 * Create Card
	 * @param  array  $args
	 * @return array
	 */
	public function create( array $args ): \stdClass {


		$args = \apply_filters( 'fullculqi/culqi_cards/create/args', $args );
		$card = $this->requestPost( $args );

		if ( ! $card->success ) {
			return $card;
		}

		// If it need a review. Apply 3Ds
		if( ! empty( $card->data->action_code ) && $card->data->action_code === 'REVIEW' ) {
			return (object)[
				'success' => true,
				'data'    => (object)[ 'needs3Ds' => true ]
			];
		}

		\do_action( \sprintf( 'fullculqi/%s/create', $this->postType ), $card );

		return (object) \apply_filters( \sprintf( 'fullculqi/%s/create/success', $this->postType ), [
			'success' => true,
			'data'    => (object) [
				'culqiCardID'   => $card->data->body->id,
				'culqiCardData' => $card->data->body,
				'needs3Ds'      => false,
			]
		] );
	}


	/**
	 * Get Culqi Card ID
	 * @param  string $card_id
	 * @return array
	 */
	public function get( string $culqiCardID ): \stdClass {
		global $culqi;

		$culqiCardID = \apply_filters(
			\sprintf( 'fullculqi/%s/get/id', $this->postType ), $culqiCardID
		);

		$card = $this->requestGet( $culqiCardID );

		if ( ! $card->success ) {
			return $card;
		}

		\do_action( \sprintf( 'fullculqi/%s/get/after', $this->postType ) , $card );

		return (object) \apply_filters( \sprintf( 'fullculqi/%s/get/success', $this->postType ), [
			'success' => true,
			'data'    => (object)[
				'culqiCardID'   => $card->id,
				'culqiCardData' => $card
			]
		] );
	}
}