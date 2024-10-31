<?php

namespace CRPlugins\Oca\ValueObjects;

class Items {

	/** @var Item[] */
	private $items;

	/**
	 * @var ItemsTotals
	 */
	private $totals;

	/**
	 * @param Item[] $items
	 */
	public function __construct( array $items ) {
		$this->items  = $items;
		$this->totals = new ItemsTotals();
		$this->calculate_totals();
	}

	public function calculate_totals(): void {
		$totals = new ItemsTotals();
		foreach ( $this->items as $item ) {
			$volume = $item->get_height() * $item->get_width() * $item->get_length();

			$totals->add_weight( $item->get_weight() * $item->get_quantity() );
			$totals->add_volume( $volume * $item->get_quantity() );
			$totals->add_price( $item->get_price() * $item->get_quantity() );
			$totals->add_quantity( $item->get_quantity() );
		}

		$this->totals = $totals;
	}

	public function get_totals(): ItemsTotals {
		return $this->totals;
	}

	/**
	 * @return Item[]
	 */
	public function get(): array {
		return $this->items;
	}

	public function set_items_price( float $price ): void {
		$new_items = array_map(
			function ( Item $item ) use ( $price ) {
				$item->set_price( $price );
				return $item;
			},
			$this->items
		);

		$this->set_items( $new_items );
	}

	/**
	 * @param Item[] $items
	 */
	private function set_items( array $items ): void {
		$this->items = $items;
		$this->calculate_totals();
	}

	/**
	 * @param array{} $dimensions
	 */
	public function set_custom_items_quantity(
		int $quantity,
		float $height,
		float $width,
		float $length
	): void {
		$price  = 0;
		$weight = 0;
		$names  = array();
		foreach ( $this->items as $item ) {
			$names[] = sprintf( '%sx %s', $item->get_quantity(), $item->get_name() );
			$price  += ( $item->get_price() * $item->get_quantity() );
			$weight += ( $item->get_weight() * $item->get_quantity() );
		}

		$new_item = new Item(
			$height,
			$width,
			$length,
			$weight,
			$price,
			implode( ', ', $names ),
			$quantity,
			0
		);
		$this->set_items( array( $new_item ) );
	}

	/**
	 * @return int[]
	 */
	public function get_invalid_products(): array {
		$invalid_products = array();
		foreach ( $this->items as $item ) {
			if ( empty( $item->get_height() ) || empty( $item->get_length() ) || empty( $item->get_width() ) ) {
				$invalid_products[] = $item->get_id();
			}
		}

		return $invalid_products;
	}
}
