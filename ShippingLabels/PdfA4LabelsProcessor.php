<?php

namespace CRPlugins\Oca\ShippingLabels;

defined( 'ABSPATH' ) || exit;

class PdfA4LabelsProcessor extends AbstractLabelsProcessor implements LabelsProcessorInterface {

	protected function get_file_name( \WC_Order $order ): string {
		return sprintf( __( 'order-%d-A4', 'wc-oca' ), $order->get_id() );
	}

	protected function get_file_extension(): string {
		return 'pdf';
	}

	protected function get_label_from_api( string $oca_number ): string {

		$shipping_label = $this->sdk->get_shipping_label_pdf_a4( $oca_number );

		return empty( $shipping_label['error'] ) && isset( $shipping_label['pdf'] ) ? base64_decode( $shipping_label['pdf'] ) : '';
	}
}
