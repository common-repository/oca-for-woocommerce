<?php

namespace CRPlugins\Oca\ShippingLabels;

use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Sdk\OcaSdk;
use WC_Order;

defined( 'ABSPATH' ) || exit;

abstract class AbstractLabelsProcessor {

	/**
	 * @var OcaSdk
	 */
	protected $sdk;

	abstract protected function get_file_name( WC_Order $order ): string;
	abstract protected function get_file_extension(): string;
	abstract protected function get_label_from_api( string $oca_number ): string;

	public function __construct( OcaSdk $sdk ) {
		$this->sdk = $sdk;
	}

	public function get_label_content( WC_Order $order ): string {
		if ( ! $this->label_exists( $order ) ) {
			return '';
		}

		return file_get_contents( $this->get_label_path( $order ) ); // phpcs:ignore
	}

	public function create_label( WC_Order $order, $content = null ): void {
		if ( $this->label_exists( $order ) ) {
			return;
		}

		// If no content provided, fetch it from API
		if ( ! $content ) {
			$shipping_method = Helper::get_order_shipping_method( $order );
			if ( ! $shipping_method->is_oca() ) {
				return;
			}

			$oca_number = $shipping_method->get_oca_number();
			if ( ! $oca_number ) {
				return;
			}

			$content = $this->get_label_from_api( $oca_number );
		}

		if ( empty( $content ) ) {
			return;
		}

		$file = $this->get_file_uri( $this->get_file_name( $order ) );

		$file_stream = fopen( $file, 'w' ); // phpcs:ignore
		if ( ! $file_stream ) {
			Helper::log_error( 'Could not open file ' . $file );
			return;
		}

		fwrite( $file_stream, $content ); // phpcs:ignore
		fclose( $file_stream ); // phpcs:ignore
	}

	public function get_label_path( WC_Order $order ): string {
		return $this->get_file_uri( $this->get_file_name( $order ) );
	}

	public function create_label_from_base64( string $base64_data, WC_Order $order ): void {
		$content = base64_decode( $base64_data );

		$file = $this->get_file_uri( $this->get_file_name( $order ) );

		$file_stream = fopen( $file, 'w' ); // phpcs:ignore
		if ( ! $file_stream ) {
			Helper::log_error( 'Could not open file ' . $file );
			return;
		}

		fwrite( $file_stream, $content ); // phpcs:ignore
		fclose( $file_stream ); // phpcs:ignore
	}

	public function delete_label( WC_Order $order ): void {
		if ( $this->label_exists( $order ) ) {
			unlink( $this->get_label_path( $order ) ); // phpcs:ignore
		}
	}

	public function label_exists( WC_Order $order ): bool {
		$file = $this->get_file_uri( $this->get_file_name( $order ) );
		return file_exists( $file ) && filesize( $file );
	}

	protected function get_file_uri( string $file_name ): string {
		return sprintf( '%s/%s.%s', Helper::get_labels_folder_path(), $file_name, $this->get_file_extension() );
	}
}
