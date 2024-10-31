<?php

namespace CRPlugins\Oca\ValueObjects;

class Item {

	/**
	 * @var float
	 */
	private $height;
	/**
	 * @var float
	 */
	private $width;
	/**
	 * @var float
	 */
	private $length;
	/**
	 * @var float
	 */
	private $weight;
	/**
	 * @var float
	 */
	private $price;
	/**
	 * @var string
	 */
	private $name;
	/**
	 * @var int
	 */
	private $quantity;
	/**
	 * @var int
	 */
	private $id;

	public function __construct(
		float $height,
		float $width,
		float $length,
		float $weight,
		float $price,
		string $name,
		int $quantity,
		int $id
	) {
		$this->height   = $height;
		$this->width    = $width;
		$this->length   = $length;
		$this->weight   = $weight;
		$this->price    = $price;
		$this->name     = $name;
		$this->quantity = $quantity;
		$this->id       = $id;
	}

	public function set_price( float $price ): void {
		$this->price = $price;
	}

	public function get_height(): float {
		return $this->height;
	}

	public function get_width(): float {
		return $this->width;
	}

	public function get_length(): float {
		return $this->length;
	}

	public function get_weight(): float {
		return $this->weight;
	}

	public function get_price(): float {
		return $this->price;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_quantity(): int {
		return $this->quantity;
	}

	public function get_id(): int {
		return $this->id;
	}
}
