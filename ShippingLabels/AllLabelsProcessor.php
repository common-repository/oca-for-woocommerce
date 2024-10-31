<?php

namespace CRPlugins\Oca\ShippingLabels;

use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Sdk\OcaSdk;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class AllLabelsProcessor {

	/**
	 * @var OcaSdk
	 */
	private $sdk;

	/**
	 * @var LabelsProcessorInterface
	 */
	private $pdf_a4_processor;

	/**
	 * @var LabelsProcessorInterface
	 */
	private $pdf_10x15_processor;

	/**
	 * @var LabelsProcessorInterface
	 */
	private $zpl_processor;

	public function __construct(
		OcaSdk $sdk,
		LabelsProcessorInterface $pdf_a4_processor,
		LabelsProcessorInterface $pdf_10x15_processor,
		LabelsProcessorInterface $zpl_processor
	) {
		$this->sdk                 = $sdk;
		$this->pdf_a4_processor    = $pdf_a4_processor;
		$this->pdf_10x15_processor = $pdf_10x15_processor;
		$this->zpl_processor       = $zpl_processor;
	}

	public function create_order_labels( WC_Order $order ): void {
		$shipping_method = Helper::get_order_shipping_method( $order );
		if ( $shipping_method->is_empty() ) {
			Helper::log_error( 'Tried to create labels for an order without method' );
			return;
		}

		$oca_number = $shipping_method->get_oca_number();
		if ( ! $oca_number ) {
			Helper::log_error( sprintf( __( 'Tried to create order labels but the order %d is not ready yet', 'wc-oca' ), $order->get_id() ) );
			return;
		}

		$existing_labels = array(
			'A4'    => $this->pdf_a4_processor->label_exists( $order ),
			'10x15' => $this->pdf_10x15_processor->label_exists( $order ),
			'zpl'   => $this->zpl_processor->label_exists( $order ),
		);

		if ( ! $existing_labels['A4'] && ! $existing_labels['zpl'] && ! $existing_labels['10x15'] ) {
			$labels = $this->get_all_shipping_labels( $oca_number );

			if ( ! empty( $labels['pdf-a4'] ) ) {
				$this->pdf_a4_processor->create_label( $order, base64_decode( $labels['pdf-a4'] ) );
			}
			if ( ! empty( $labels['pdf-10x15'] ) ) {
				$this->pdf_10x15_processor->create_label( $order, base64_decode( $labels['pdf-10x15'] ) );
			}
			if ( ! empty( $labels['zpl'] ) ) {
				$this->zpl_processor->create_label( $order, $labels['zpl'] );
			}
		}

		// In case previous call did not succeed with all labels, regenerate missing ones.
		$this->pdf_a4_processor->create_label( $order );
		$this->pdf_10x15_processor->create_label( $order );
		$this->zpl_processor->create_label( $order );
	}

	public function delete_all_labels( WC_Order $order ): void {
		$this->pdf_a4_processor->delete_label( $order );
		$this->pdf_10x15_processor->delete_label( $order );
		$this->zpl_processor->delete_label( $order );
	}

	protected function get_all_shipping_labels( string $oca_number ): array {
		$data     = array(
			'pdf-a4'    => '',
			'pdf-10x15' => '',
			'zpl'       => '',
		);
		$response = $this->sdk->get_all_shipping_labels( $oca_number );

		if ( isset( $response['error'] ) ) {
			return $data;
		}

		$data['pdf-a4']    = isset( $response['pdf-a4'] ) ? $response['pdf-a4'] : '';
		$data['pdf-10x15'] = isset( $response['pdf-10x15'] ) ? $response['pdf-10x15'] : '';
		$data['zpl']       = isset( $response['zpl'] ) ? $response['zpl'] : '';

		return $data;
	}
}
