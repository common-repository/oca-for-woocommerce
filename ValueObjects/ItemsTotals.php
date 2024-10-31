<?php

namespace CRPlugins\Oca\ValueObjects;

class ItemsTotals {

	/**
	 * @var int
	 */
	private $quantity;
	/**
	 * @var float
	 */
	private $price;
	/**
	 * @var float
	 */
	private $volume;
	/**
	 * @var float
	 */
	private $weight;

	public function __construct() {
		$this->quantity = 0;
		$this->price    = 0;
		$this->volume   = 0;
		$this->weight   = 0;
	}

	public function set_price( float $price ): void {
		$this->price = $price;
	}

	public function add_weight( float $weight ): void {
		$this->weight += $weight;
	}

	public function add_price( float $price ): void {
		$this->price += $price;
	}

	public function add_volume( float $volume ): void {
		$this->volume += $volume;
	}

	public function add_quantity( int $quantity ): void {
		$this->quantity += $quantity;
	}

	public function get_weight(): float {
		return $this->weight;
	}

	public function get_volume(): float {
		return $this->volume;
	}

	public function get_price(): float {
		return $this->price;
	}

	public function get_quantity(): int {
		return $this->quantity;
	}
}
