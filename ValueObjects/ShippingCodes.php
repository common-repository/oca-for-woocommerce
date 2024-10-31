<?php

namespace CRPlugins\Oca\ValueObjects;

class ShippingCodes {

	/**
	 * @var ShippingCode[]
	 */
	private $shipping_codes;

	/**
	 * @param ShippingCode[] $shipping_codes
	 */
	public function __construct( array $shipping_codes ) {
		$this->shipping_codes = $shipping_codes;
	}

	/**
	 * @return ShippingCode[]
	 */
	public function get(): array {
		return $this->shipping_codes;
	}

	public function is_empty(): bool {
		return empty( $this->shipping_codes );
	}

	public function to_branch(): self {
		return new self(
			array_filter(
				$this->shipping_codes,
				function ( ShippingCode $shipping_code ) {
					return $shipping_code->is_to_branch();
				}
			)
		);
	}

	public function to_door(): self {
		return new self(
			array_filter(
				$this->shipping_codes,
				function ( ShippingCode $shipping_code ) {
					return $shipping_code->is_to_door();
				}
			)
		);
	}

	public function from_branch(): self {
		return new self(
			array_filter(
				$this->shipping_codes,
				function ( ShippingCode $shipping_code ) {
					return $shipping_code->is_from_branch();
				}
			)
		);
	}

	public function from_door(): self {
		return new self(
			array_filter(
				$this->shipping_codes,
				function ( ShippingCode $shipping_code ) {
					return $shipping_code->is_from_door();
				}
			)
		);
	}

	public function get_by_id( string $id ): ?ShippingCode {
		foreach ( $this->shipping_codes as $shipping_code ) {
			if ( $id === $shipping_code->get_id() ) {
				return $shipping_code;
			}
		}

		return null;
	}

	/**
	 * @return string[]
	 */
	public function get_ids(): array {
		return array_map(
			function ( ShippingCode $shipping_code ) {
				return $shipping_code->get_id();
			},
			$this->shipping_codes
		);
	}
}
