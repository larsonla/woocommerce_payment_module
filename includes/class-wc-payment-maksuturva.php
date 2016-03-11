<?php
/**
 * WooCommerce Maksuturva Payment Gateway
 *
 * @package WooCommerce Maksuturva Payment Gateway
 */

/**
 * Maksuturva Payment Gateway Plugin for WooCommerce 2.x
 * Plugin developed for Maksuturva
 * Last update: 08/03/2016
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * [GNU LGPL v. 2.1 @gnu.org] (https://www.gnu.org/licenses/lgpl-2.1.html)
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'class-wc-payment-validator-maksuturva.php';

/**
 * Class WC_Payment_Maksuturva.
 *
 * Handles the saving and loading payment related data. Keeps track of payments and their statuses.
 *
 * @since 2.0.0
 */
class WC_Payment_Maksuturva {

	/**
	 * The queue table name.
	 *
	 * @var string TABLE_NAME
	 */
	const TABLE_NAME = 'maksuturva_queue';

	/**
	 * Payment "cancelled".
	 *
	 * @var string STATUS_CANCELLED
	 */
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Payment "completed".
	 *
	 * @var string STATUS_COMPLETED
	 */
	const STATUS_COMPLETED = 'completed';

	/**
	 * Payment "on-hold".
	 *
	 * @var string STATUS_ON_HOLD
	 */
	const STATUS_ON_HOLD = 'on-hold';

	/**
	 * Payment "processing".
	 *
	 * @var string STATUS_PROCESSING
	 */
	const STATUS_PROCESSING = 'processing';

	/**
	 * Payment "pending".
	 *
	 * @var string STATUS_PENDING
	 */
	const STATUS_PENDING = 'pending';

	/**
	 * Payment "refunded".
	 *
	 * @var string STATUS_REFUNDED
	 */
	const STATUS_REFUNDED = 'refunded';

	/**
	 * Payment "failed".
	 *
	 * @var string STATUS_FAILED
	 */
	const STATUS_FAILED = 'failed';

	/**
	 * Payment "delayed".
	 *
	 * @var string STATUS_DELAYED
	 */
	const STATUS_DELAYED = 'delayed';

	/**
	 * Payment "error".
	 *
	 * @var string STATUS_ERROR
	 */
	const STATUS_ERROR = 'error';

	/**
	 * Order id.
	 *
	 * @var int $order_id The order id.
	 */
	protected $order_id;

	/**
	 * Payment id.
	 *
	 * @var string $payment_id The Maksuturva payment id.
	 */
	protected $payment_id;

	/**
	 * Payment status.
	 *
	 * @var string $status The status of the payment.
	 */
	protected $status;

	/**
	 * Data sent.
	 *
	 * @var array $data_sent Data sent to the payment gateway.
	 */
	protected $data_sent = array();

	/**
	 * Data received.
	 *
	 * @var array $data_received The data received from the payment gateway.
	 */
	protected $data_received = array();

	/**
	 * Date added.
	 *
	 * @var string $date_added The date when the record was created.
	 */
	protected $date_added;

	/**
	 * Date updated.
	 *
	 * @var string $date_updated The date when the record was updated.
	 */
	protected $date_updated;

	/**
	 * WC_Payment_Maksuturva constructor.
	 *
	 * If the order id is given, the model will be loaded from the database.
	 *
	 * @param int|null $order_id The order id to load.
	 *
	 * @since 2.0.0
	 */
	public function __construct( $order_id = null ) {
		if ( (int) $order_id > 0 ) {
			$this->load( $order_id );
		}
	}

	/**
	 * Create a new payment record.
	 *
	 * Creates the payment record in the database based on given data.
	 *
	 * @param array $data The data to save.
	 *
	 * @since 2.0.0
	 *
	 * @return WC_Payment_Maksuturva
	 * @throws WC_Gateway_Maksuturva_Exception If creating fails.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$result = $wpdb->insert( $wpdb->prefix . self::TABLE_NAME, array(
			'order_id'      => (int) $data['order_id'],
			'payment_id'    => $data['payment_id'],
			'status'        => $data['status'],
			'data_sent'     => wp_json_encode( $data['data_sent'] ),
			'data_received' => wp_json_encode( $data['data_received'] ),
			'date_added'    => date( 'Y-m-d H:i:s' ),
		) ); // Db call ok.

		if ( false === $result ) {
			throw new WC_Gateway_Maksuturva_Exception( 'Failed to create Maksuturva payment.' );
		}

		return new self( (int) $data['order_id'] );
	}

	/**
	 * Get status.
	 *
	 * Returns the payment status.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Set data received.
	 *
	 * Sets the received data property with given data.
	 *
	 * @param array $data The data to update.
	 *
	 * @since 2.0.0
	 *
	 * @throws WC_Gateway_Maksuturva_Exception If update fails.
	 */
	public function set_data_received( array $data ) {
		$this->data_received = $data;
		$this->update();
	}

	/**
	 * Complete payment.
	 *
	 * Completes the payment by setting the status to "completed".
	 *
	 * @since 2.0.0
	 *
	 * @throws WC_Gateway_Maksuturva_Exception If update fails.
	 */
	public function complete() {
		$this->status = self::STATUS_COMPLETED;
		$this->update();
	}

	/**
	 * Cancel payment.
	 *
	 * Cancels the payment by setting the status to "cancelled".
	 *
	 * @since 2.0.0
	 *
	 * @throws WC_Gateway_Maksuturva_Exception If update fails.
	 */
	public function cancel() {
		$this->status = self::STATUS_CANCELLED;
		$this->update();
	}

	/**
	 * Payment error.
	 *
	 * Update the status of the payment to "error", if something went wrong.
	 *
	 * @since 2.0.0
	 *
	 * @throws WC_Gateway_Maksuturva_Exception If update fails.
	 */
	public function error() {
		$this->status = self::STATUS_ERROR;
		$this->update();
	}

	/**
	 * Payment delayed.
	 *
	 * Updates the status of the payment to "delayed".
	 *
	 * @since 2.0.0
	 *
	 * @throws WC_Gateway_Maksuturva_Exception If update fails.
	 */
	public function delayed() {
		$this->status = self::STATUS_DELAYED;
		$this->update();
	}

	/**
	 * Payment pending.
	 *
	 * Updates the status of the payment to "pending".
	 *
	 * @since 2.0.0
	 *
	 * @throws WC_Gateway_Maksuturva_Exception If update fails.
	 */
	public function pending() {
		$this->status = self::STATUS_PENDING;
		$this->update();
	}

	/**
	 * Get surcharge.
	 *
	 * Returns the monetary amount for the payments surcharge if it was included, zero otherwise.
	 *
	 * @since 2.0.0
	 *
	 * @return float|int
	 */
	public function get_surcharge() {
		if ( isset( $this->data_sent['pmt_sellercosts'], $this->data_received['pmt_sellercosts'] ) ) {
			$sent_seller_cost     = str_replace( ',', '.', $this->data_sent['pmt_sellercosts'] );
			$received_seller_cost = str_replace( ',', '.', $this->data_received['pmt_sellercosts'] );
			if ( $received_seller_cost > $sent_seller_cost ) {
				return number_format( $received_seller_cost - $sent_seller_cost, 2, '.', '' );
			}
		}

		return 0;
	}

	/**
	 * Includes surcharge.
	 *
	 * Checks if the payment includes surcharges, i.e. if the used payment method charged an additional fee.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function includes_surcharge() {
		return ( $this->get_surcharge() > 0 );
	}

	/**
	 * Load data.
	 *
	 * Loads the data for the given order id from the database to the model.
	 *
	 * @param int $order_id The order id to load.
	 *
	 * @since 2.0.0
	 *
	 * @throws WC_Gateway_Maksuturva_Exception If load fails.
	 */
	protected function load( $order_id ) {
		global $wpdb;

		$query = $wpdb->prepare( 'SELECT order_id, payment_id, status, data_sent, data_received, date_added, date_updated FROM `'
		. $wpdb->prefix . self::TABLE_NAME . '` WHERE `order_id` = %d LIMIT 1', $order_id );

		$data = $wpdb->get_results( $query ); // Db call ok; No-cache ok.

		if ( ! ( is_array( $data ) && count( $data ) === 1 ) ) {
			throw new WC_Gateway_Maksuturva_Exception( 'Failed to load Maksuturva payment!' );
		}

		$this->order_id      = (int) $data[0]->order_id;
		$this->payment_id    = $data[0]->payment_id;
		$this->status        = $data[0]->status;
		$this->data_sent     = (array) json_decode( $data[0]->data_sent );
		$this->data_received = (array) json_decode( $data[0]->data_received );
		$this->date_added    = $data[0]->date_added;
		$this->date_updated  = $data[0]->date_updated;
	}

	/**
	 * Update model.
	 *
	 * Updates the payment model in the database with all the properties there is.
	 *
	 * @since 2.0.0
	 *
	 * @throws WC_Gateway_Maksuturva_Exception If update fails.
	 */
	protected function update() {
		global $wpdb;

		$data = array(
			'status'        => $this->status,
			'data_received' => wp_json_encode( $this->data_received ),
			'date_updated'  => date( 'Y-m-d H:i:s' ),
		);

		$result = $wpdb->update( $wpdb->prefix . self::TABLE_NAME, $data,
		array( 'order_id' => $this->order_id, 'payment_id' => $this->payment_id ) ); // Db call ok; No-cache ok.

		if ( false === $result ) {
			throw new WC_Gateway_Maksuturva_Exception( 'Failed to update Maksuturva payment!' );
		}
	}
}