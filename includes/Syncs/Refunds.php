<?php
Namespace Fullculqi\Syncs;

use Fullculqi\Traits\Singleton;

/**
 * Refunds Class
 * @since  1.0.0
 * @package Includes / Sync / Refunds
 */
class Refunds extends Client {
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
	protected string $endpoint = 'refunds/';

	
	/**
	 * Create Refund
	 * @param  string $charge_id
	 * @param  integer $post_id
	 * @param  float  $amount
	 * @return bool
	 */
	public function create( int $postChargeID, array $args ): \stdClass {

		$args   = \apply_filters( 'fullculqi/culqi_refunds/create/args', $args, $postChargeID );
		$refund = $this->requestPost( $args );

		if ( ! $refund->success ) {
			return $refund;
		}

		$data = $refund->data->body;

		\update_post_meta( $postChargeID, 'culqi_data', $data );
		\update_post_meta( $postChargeID, 'culqi_status', 'refunded' );

		// Save Refund
		$basic      = \get_post_meta( $postChargeID, 'culqi_basic', true );
		$refundsIDs = \get_post_meta( $postChargeID, 'culqi_ids_refunded', true ) ?: [];
		
		$refundsIDs[ $data->id ] = \number_format( $data->amount / 100, 2, '.', '' );
		
		$basic['culqi_amount_refunded'] = \array_sum( $refundsIDs );

		\update_post_meta( $postChargeID, 'culqi_basic', $basic );
		\update_post_meta( $postChargeID, 'culqi_ids_refunded', $refundsIDs );

		\do_action( 'fullculqi/culqi_refunds/create', $postChargeID, $refund );

		return (object) \apply_filters( 'fullculqi/culqi_refunds/create/success', [
			'success' => true,
			'data'    => (object)[
				'culqiRefundID' => $data->id,
				'postChargeID'  => $postChargeID
			]
		] );
	}

}