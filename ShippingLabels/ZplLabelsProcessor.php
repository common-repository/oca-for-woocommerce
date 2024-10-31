<?php

namespace CRPlugins\Oca\ShippingLabels;

defined( 'ABSPATH' ) || exit;

class ZplLabelsProcessor extends AbstractLabelsProcessor implements LabelsProcessorInterface {

	protected function get_file_name( \WC_Order $order ): string {
		return sprintf( __( 'order-%d', 'wc-oca' ), $order->get_id() );
	}

	protected function get_file_extension(): string {
		return 'zpl';
	}

	protected function get_label_from_api( string $oca_number ): string {

		$shipping_label = $this->sdk->get_shipping_label_zpl( $oca_number );

		return empty( $shipping_label['error'] ) && isset( $shipping_label['zpl'] ) ? $shipping_label['zpl'] : '';
	}
}
