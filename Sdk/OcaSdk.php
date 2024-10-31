<?php

namespace CRPlugins\Oca\Sdk;

use CRPlugins\Oca\Api\OcaApi;
use CRPlugins\Oca\Api\OcaFilesApi;
use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\ValueObjects\Customer;
use CRPlugins\Oca\ValueObjects\Items;
use CRPlugins\Oca\ValueObjects\ItemsTotals;
use CRPlugins\Oca\ValueObjects\Seller;
use CRPlugins\Oca\ValueObjects\SellerOcaSettings;
use CRPlugins\Oca\ValueObjects\ShippingCode;
use CRPlugins\Oca\ValueObjects\ShippingCodes;
use DateTimeInterface;
use Exception;
use WC_Order;

/**
 * @psalm-type Request = array{method: string, headers: array<string,string>, body?: string}
 */
class OcaSdk {

	/**
	 * @var OcaApi
	 */
	private $api;

	/**
	 * @var ?SellerOcaSettings
	 */
	private $settings;

	public function __construct( OcaApi $api, ?SellerOcaSettings $settings ) {
		$this->api      = $api;
		$this->settings = $settings;
	}

	public function is_valid(): bool {
		return null !== $this->settings;
	}

	/**
	 * @param array{request: mixed, response: array<string,mixed>|never[]} $response
	 * @return array{error: string}|array<string,mixed>
	 */
	protected function handle_response( array $response, string $function_name ): array {
		Helper::log_debug( sprintf( __( '%1$s - Data sent to Oca: %2$s', 'wc-oca' ), $function_name, wc_print_r( $response['request'], true ) ) );
		Helper::log_debug( sprintf( __( '%1$s - Data received from Oca: %2$s', 'wc-oca' ), $function_name, wc_print_r( $response['response'], true ) ) );

		if ( 'process_order' === $function_name ) {
			Helper::log_info( sprintf( __( '%1$s - Data sent to Oca: %2$s', 'wc-oca' ), $function_name, wc_print_r( $response['request'], true ) ) );
			Helper::log_info( sprintf( __( '%1$s - Data received from Oca: %2$s', 'wc-oca' ), $function_name, wc_print_r( $response['response'], true ) ) );
		}

		if ( empty( $response['response'] ) ) {
			Helper::log_warning( $function_name . ': ' . __( 'No response from OCA server', 'wc-oca' ) );
			return array( 'error' => __( 'No response from OCA server', 'wc-oca' ) );
		}

		if ( ! empty( $response['response']['error'] ) ) {
			Helper::log_error( $function_name . ': ' . $response['response']['error'] );
			Helper::log_error( 'Data sent: ' . wc_print_r( $response['request'], true ) );
		}

		return $response['response'];
	}

	/**
	 * @return never[]|array{error: string}|array{apiKey: string, domain: string, type: string, status: string, expiration_date: string}
	 */
	public function get_store(): array {
		try {
			$res = $this->api->get( '/stores/mine' );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return array{error: string}|array<string, array{price: float, days: int}>
	 */
	public function get_quotes( ItemsTotals $totals, string $from_postcode, string $to_postcode, ShippingCodes $shipping_codes ): array {
		$data_to_send = array(
			'totalWeight' => $totals->get_weight(),
			'totalVolume' => $totals->get_volume(),
			'price'       => $totals->get_price(),
			'qty'         => $totals->get_quantity(),
			'from'        => array(
				'postcode' => $from_postcode,
			),
			'to'          => array(
				'postcode' => $to_postcode,
			),
			'settings'    => array(
				'username'      => $this->settings->get_username(),
				'password'      => $this->settings->get_password(),
				'accountNumber' => $this->settings->get_account_number(),
				'cuit'          => $this->settings->get_cuit(),
				'shippingCodes' => array(),
			),
		);

		foreach ( $shipping_codes->get() as $shipping_code ) {
			$data_to_send['settings']['shippingCodes'][] = $shipping_code->get_id();
		}
		try {
			$res = $this->api->post( '/oca/quotes', $data_to_send );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{oca_number: string, tracking_number: string}
	 */
	public function process_order(
		Seller $seller,
		Customer $to,
		Items $items,
		WC_Order $order,
		ShippingCode $shipping_code,
		DateTimeInterface $dispatch_day,
		bool $force_process = false
	): array {
		$seller_address   = $seller->get_address();
		$customer_address = $to->get_address();
		$data_to_send     = array(
			'amount'       => $order->get_total(),
			'orderId'      => $order->get_id(),
			'forceProcess' => $force_process,
			'from'         => array(
				'contact'        => $seller->get_name(),
				'email'          => $seller->get_email(),
				'storeName'      => $seller->get_store_name(),
				'costBranch'     => $seller->get_cost_branch(),
				'timeFrame'      => $seller->get_time_frame(),
				'street'         => $seller_address->get_street(),
				'streetNumber'   => $seller_address->get_number(),
				'floor'          => $seller_address->get_floor(),
				'apt'            => $seller_address->get_apartment(),
				'postcode'       => $seller_address->get_postcode(),
				'city'           => $seller_address->get_city(),
				'state'          => $seller_address->get_state(),
				'observations'   => $seller_address->get_extra_info(),
				'shippingBranch' => $seller_address->get_shipping_branch(),
				'date'           => $dispatch_day->format( 'Ymd' ),
			),
			'to'           => array(
				'firstName'      => $to->get_first_name(),
				'lastName'       => $to->get_last_name(),
				'street'         => $customer_address->get_street(),
				'streetNumber'   => $customer_address->get_number(),
				'floor'          => $customer_address->get_floor(),
				'apt'            => $customer_address->get_apartment(),
				'city'           => $customer_address->get_city(),
				'state'          => $customer_address->get_state(),
				'postcode'       => $customer_address->get_postcode(),
				'phone'          => $to->get_phone(),
				'email'          => $to->get_email(),
				'shippingBranch' => $customer_address->get_shipping_branch(),
				'observations'   => $customer_address->get_extra_info(),
			),
			'items'        => array(),
			'settings'     => array(
				'username'      => $this->settings->get_username(),
				'password'      => $this->settings->get_password(),
				'accountNumber' => $this->settings->get_account_number(),
				'cuit'          => $this->settings->get_cuit(),
				'shippingCode'  => $shipping_code->get_id(),
			),
		);
		foreach ( $items->get() as $item ) {
			$data_to_send['items'][] = array(
				'height' => $item->get_height(),
				'width'  => $item->get_width(),
				'length' => $item->get_length(),
				'weight' => $item->get_weight(),
				'price'  => $item->get_price(),
				'name'   => $item->get_name(),
				'qty'    => $item->get_quantity(),
				'id'     => $item->get_id(),
			);
		}
		try {
			$res = $this->api->post( '/oca/orders', $data_to_send );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{id: string, message: string}
	 */
	public function cancel_order( string $oca_number ): array {
		$data_to_send = array(
			'ocaNumber' => $oca_number,
			'settings'  => array(
				'username'      => $this->settings->get_username(),
				'password'      => $this->settings->get_password(),
				'accountNumber' => $this->settings->get_account_number(),
				'cuit'          => $this->settings->get_cuit(),
			),
		);
		try {
			$res = $this->api->post( '/oca/orders/cancel', $data_to_send );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{pdf: string}
	 */
	public function get_shipping_label_pdf_a4( string $oca_number ): array {
		$body = array(
			'format' => 'pdf',
			'number' => $oca_number,
			'size'   => 'A4',
		);

		try {
			$res = $this->api->get( '/oca/labels', $body );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{pdf: string}
	 */
	public function get_shipping_label_pdf_10x15( string $oca_number ): array {
		$body = array(
			'format' => 'pdf',
			'number' => $oca_number,
			'size'   => '10x15',
		);

		try {
			$res = $this->api->get( '/oca/labels', $body );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{zpl: string}
	 */
	public function get_shipping_label_zpl( string $oca_number ): array {
		$body = array(
			'format' => 'zpl',
			'number' => $oca_number,
			'size'   => '10x15',
		);

		try {
			$res = $this->api->get( '/oca/labels', $body );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{pdf-a4: string, pdf-10x15: string, zpl: string}
	 */
	public function get_all_shipping_labels( string $oca_number ): array {
		$body = array(
			'format' => 'all',
			'number' => $oca_number,
		);

		try {
			$res = $this->api->get( '/oca/labels', $body );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{status: string, reason: string, branch: string, date: string}[]
	 */
	public function get_tracking( string $tracking_number ): array {
		$body = array( 'number' => $tracking_number );

		try {
			$res = $this->api->get( '/oca/tracking', $body );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{id: int, name: string, street: string, streetNumber: string, floor: string, apt: string, city: string, postcode: string, phone: string, hours: string, type: string}[]
	 */
	public function get_shipping_branches_to( string $postcode ): array {
		$body = array( 'number' => $postcode );

		try {
			$res = $this->api->get( '/oca/branches/to', $body );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}|array{id: int, name: string, street: string, streetNumber: string, floor: string, apt: string, city: string, postcode: string, phone: string, hours: string, type: string}[]
	 */
	public function get_shipping_branches_from( string $postcode ): array {
		$body = array( 'number' => $postcode );

		try {
			$res = $this->api->get( '/oca/branches/from', $body );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}
		return $this->handle_response( $res, __FUNCTION__ );
	}

	/**
	 * @return never[]|array{error: string}
	 */
	public function send_report( string $report_uri ): array {
		$api = new OcaFilesApi( $this->api->get_api_key() );
		try {
			$res = $api->post( '/oca/reports', array( 'report' => $report_uri ) );
		} catch ( Exception $e ) {
			Helper::log_error( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}

		return $this->handle_response( $res, __FUNCTION__ );
	}
}
