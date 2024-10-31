<?php

namespace CRPlugins\Oca\ValueObjects;

class ShippingBranch {

	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var string
	 */
	public $street;

	/**
	 * @var string
	 */
	public $street_number;

	/**
	 * @var string
	 */
	public $floor;

	/**
	 * @var string
	 */
	public $apt;

	/**
	 * @var string
	 */
	public $city;

	/**
	 * @var string
	 */
	public $postcode;

	/**
	 * @var string
	 */
	public $phone;

	/**
	 * @var string
	 */
	public $hours;

	/**
	 * @var string
	 */
	public $type;


	public function __construct(
		int $id,
		string $name,
		string $street,
		string $street_number,
		string $floor,
		string $apt,
		string $city,
		string $postcode,
		string $phone,
		string $hours,
		string $type
	) {
		$this->id            = $id;
		$this->name          = ucfirst( mb_strtolower( $name, 'UTF-8' ) );
		$this->street        = ucwords( mb_strtolower( $street, 'UTF-8' ), ',. ' );
		$this->street_number = ucfirst( mb_strtolower( $street_number, 'UTF-8' ) );
		$this->floor         = $floor;
		$this->apt           = $apt;
		$this->city          = ucfirst( mb_strtolower( $city, 'UTF-8' ) );
		$this->postcode      = $postcode;
		$this->phone         = $phone;
		$this->hours         = $hours;
		$this->type          = $type;
	}

	public function get_address(): string {
		$address_bits = array( $this->get_street(), $this->get_street_number() );
		if ( $this->get_floor() ) {
			$address_bits[] = $this->get_floor();
			if ( $this->get_apt() ) {
				$address_bits[] = $this->get_apt();
			}
		}

		$address = implode( ' ', $address_bits ) . '. %s %s';
		$address = sprintf( $address, $this->get_city(), $this->get_postcode() );

		return $address;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_street(): string {
		return $this->street;
	}

	public function get_street_number(): string {
		return $this->street_number;
	}

	public function get_floor(): string {
		return $this->floor;
	}

	public function get_apt(): string {
		return $this->apt;
	}

	public function get_city(): string {
		return $this->city;
	}

	public function get_postcode(): string {
		return $this->postcode;
	}

	public function get_phone(): string {
		return $this->phone;
	}

	public function get_hours(): string {
		return $this->hours;
	}

	public function get_type(): string {
		return $this->type;
	}
}
