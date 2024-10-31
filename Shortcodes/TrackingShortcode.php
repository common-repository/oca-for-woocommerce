<?php

namespace CRPlugins\Oca\Shortcodes;

use CRPlugins\Oca\Sdk\OcaSdk;

defined( 'ABSPATH' ) || exit;

class TrackingShortcode {

	/**
	 * @var OcaSdk
	 */
	private $sdk;

	public function __construct( OcaSdk $sdk ) {
		add_shortcode( 'oca_order_tracking', array( $this, 'output' ) );

		$this->sdk = $sdk;
	}

	/**
	 * @param array{with_form?: string} $atts
	 */
	public function output( array $atts ): string {
		// phpcs:disable WordPress.Security.NonceVerification
		$output = '';

		if ( isset( $atts['with_form'] ) ) {
			$output .= $this->form();
		}

		if ( ! empty( $_GET['oca_tracking_number'] ) ) {
			$tracking_number   = sanitize_text_field( wp_unslash( $_GET['oca_tracking_number'] ) );
			$tracking_statuses = $this->sdk->get_tracking( $tracking_number );
			if ( isset( $tracking_statuses['error'] ) ) {
				$output .= $this->error();
			} else {
				$output .= $this->results_table( $tracking_statuses, $tracking_number );
			}
		}

		// phpcs:enable

		return $output;
	}

	public function form(): string {
		return '<h2 class="oca-tracking-form-title">' . __( 'Shipment number', 'wc-oca' ) . '</h2>
        <form method="get" class="oca-tracking-form">
        <input type="text" name="oca_tracking_number" style="width:40%" class="oca-tracking-form-field"><br>
        <br />
        <input name="submit_button" type="submit"  value="' . __( 'Track shipment', 'wc-oca' ) . '"  id="update_button"  class="oca-tracking-form-submit update_button" style="cursor: pointer;background-color: #4b177d;border: 1px solid #4b177d;color: white;padding: 5px 10px;display: inline-block;border-radius: 4px;font-weight: 600;margin-bottom: 10px;text-align: center;"/>
        </form>';
	}

	public function error(): string {
		return '<h3 class="oca-tracking-error">' . __( 'There was an error, please try again', 'wc-oca' ) . '</h3>';
	}

	/**
	 * @param array{status: string, reason: string, branch: string, date: string}[] $tracking_statuses
	 */
	public function results_table( array $tracking_statuses, string $tracking_number ): string {
		$output = '';
		if ( ! empty( $tracking_statuses ) ) {
			$output .= sprintf( '<h3>%s : %s</h3>', esc_html__( 'Shipment number', 'wc-oca' ), $tracking_number );
			$output .= '<table class="oca-table">';
			$output .= '<thead>';
			$output .= '<tr>';
			$output .= sprintf( '<th width="10%">%s</th>', __( 'Date', 'wc-oca' ) );
			$output .= sprintf( '<th width="25%">%s</th>', __( 'Status', 'wc-oca' ) );
			$output .= sprintf( '<th width="25%">%s</th>', __( 'Status reason', 'wc-oca' ) );
			$output .= sprintf( '<th width="30%">%s</th>', __( 'Branch office', 'wc-oca' ) );
			$output .= '</tr>';
			$output .= '</thead>';
			$output .= '<tbody>';
			foreach ( $tracking_statuses as $status ) {
				$output .= '<tr>';
				$output .= sprintf( '<td>%s</td>', $status['date'] );
				$output .= sprintf( '<td>%s</td>', $status['status'] );
				$output .= sprintf( '<td>%s</td>', $status['reason'] );
				$output .= sprintf( '<td>%s</td>', $status['date'] );
				$output .= sprintf( '<td>%s</td>', $status['branch'] );
				$output .= '</tr>';
			}
			$output .= '</tbody>';
			$output .= '</table>';
		} else {
			$output .= sprintf( '<h2>%s</h2>', esc_html__( 'Shipment without status', 'wc-oca' ) );
		}
		return $output;
	}
}
