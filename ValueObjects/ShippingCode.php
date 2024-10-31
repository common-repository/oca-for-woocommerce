<?php

namespace CRPlugins\Oca\ValueObjects;

use CRPlugins\Oca\Helper\Helper;

class ShippingCode {

	/**
	 * @var string
	 */
	private $id;
	/**
	 * @var string
	 */
	private $name;
	/**
	 * @var string
	 */
	private $type;
	/**
	 * @var float
	 */
	private $price;

	public function __construct(
		string $id,
		string $name,
		string $type,
		float $price
	) {
		$this->id    = $id;
		$this->name  = $name;
		$this->type  = $type;
		$this->price = $price;
	}

	public function get_id(): string {
		return $this->id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_type(): string {
		return $this->type;
	}

	public function get_price(): float {
		return $this->price;
	}

	public function has_price(): bool {
		return 0.0 !== $this->price;
	}

	public function is_from_branch(): bool {
		return Helper::str_starts_with( $this->get_type(), 'S' );
	}

	public function is_from_door(): bool {
		return Helper::str_starts_with( $this->get_type(), 'P' );
	}

	public function is_to_branch(): bool {
		return Helper::str_ends_with( $this->get_type(), 'S' );
	}

	public function is_to_door(): bool {
		return Helper::str_ends_with( $this->get_type(), 'P' );
	}
}
