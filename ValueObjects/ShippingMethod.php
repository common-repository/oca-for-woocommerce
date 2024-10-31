<?php

namespace CRPlugins\Oca\ValueObjects;

use WC_Order_Item_Shipping;

class ShippingMethod {

	/**
	 * @var ?WC_Order_Item_Shipping
	 */
	private $method;

	public function __construct( ?WC_Order_Item_Shipping $method ) {
		$this->method = $method;
	}

	public function is_empty(): bool {
		return null === $this->method;
	}

	public function is_oca(): bool {
		return $this->method && 'oca' === $this->method->get_method_id();
	}

	public function is_canceled(): bool {
		return $this->method && ! empty( $this->method->get_meta( 'oca_canceled' ) );
	}

	public function is_processed(): bool {
		return $this->method && ! empty( $this->method->get_meta( 'tracking_number' ) );
	}

	public function get_packages_quantity(): int {
		if ( ! $this->method ) {
			return 0;
		}

		// BW Compat - To remove later
		if ( $this->method->get_meta( 'oca_packages' ) ) {
			return $this->method->get_meta( 'oca_packages' );
		}

		return $this->method->get_meta( 'packages_quantity' );
	}

	public function get_tracking_number(): string {
		if ( ! $this->method ) {
			return '';
		}

		return $this->method->get_meta( 'tracking_number' );
	}

	public function get_oca_number(): string {
		if ( ! $this->method ) {
			return '';
		}

		return $this->method->get_meta( 'oca_number' );
	}

	public function get_hidden_price(): ?float {
		if ( ! $this->method ) {
			return null;
		}

		return (float) $this->method->get_meta( 'hidden_price' );
	}

	public function set_tracking_information( string $tracking_number, string $oca_number ): void {
		if ( ! $this->method ) {
			return;
		}

		$this->method->update_meta_data( 'tracking_number', $tracking_number );
		$this->method->update_meta_data( 'oca_number', $oca_number );
		$this->method->update_meta_data( 'oca_canceled', false );
		$this->method->save();
	}

	public function set_packages_quantity( int $packages_quantity ): void {
		if ( ! $this->method ) {
			return;
		}

		$this->method->update_meta_data( 'packages_quantity', $packages_quantity );
		$this->method->save();
	}

	public function get_shipping_code(): ?ShippingCode {
		if ( ! $this->method ) {
			return null;
		}

		$shipping_code = $this->method->get_meta( 'shipping_code' );
		if ( ! $shipping_code ) {
			return null;
		}

		// BW Compat - To remove later
		if ( ! empty( $shipping_code['code'] ) ) {
			return new ShippingCode( $shipping_code['code'], '', $shipping_code['type'], 0 );
		}

		/** @var array{id: string, name: string, type: string} $shipping_code */
		return new ShippingCode( $shipping_code['id'], $shipping_code['name'], $shipping_code['type'], 0 );
	}

	public function get_shipping_branch_id(): ?int {
		if ( ! $this->method ) {
			return null;
		}

		// BW Compat - To remove later
		$branch = $this->method->get_meta( 'destination_branch' );
		if ( $branch && ! empty( $branch['id'] ) ) {
			return (int) $branch['id'];
		}

		$branch = $this->method->get_meta( 'shipping_branch' );
		if ( ! $branch ) {
			return null;
		}

		/** @var array{id: string, name: string, type: string} $shipping_code */
		return (int) $branch;
	}

	public function cancel(): void {
		if ( ! $this->method ) {
			return;
		}

		$this->method->delete_meta_data( 'tracking_number' );
		$this->method->delete_meta_data( 'oca_number' );
		$this->method->update_meta_data( 'oca_canceled', true );
		$this->method->save();
	}
}
