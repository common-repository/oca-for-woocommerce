<?php

namespace CRPlugins\Oca\ValueObjects;

class Customer {

	/**
	 * @var string
	 */
	private $first_name;
	/**
	 * @var string
	 */
	private $last_name;
	/**
	 * @var Address
	 */
	private $address;
	/**
	 * @var string
	 */
	private $email;
	/**
	 * @var string
	 */
	private $phone;

	public function __construct(
		string $first_name,
		string $last_name,
		Address $address,
		string $email = '',
		string $phone = ''
	) {
		$this->first_name = $first_name;
		$this->last_name  = $last_name;
		$this->address    = $address;
		$this->email      = $email;
		$this->phone      = $phone;
	}

	public function set_email( string $email ): void {
		$this->email = $email;
	}

	public function set_phone( string $phone ): void {
		$this->phone = $phone;
	}

	public function get_first_name(): string {
		return $this->first_name;
	}

	public function get_last_name(): string {
		return $this->last_name;
	}

	public function get_full_name(): string {
		return trim(
			sprintf(
				'%s %s',
				$this->get_first_name(),
				$this->get_last_name()
			)
		);
	}

	public function get_address(): Address {
		return $this->address;
	}

	public function get_email(): string {
		return $this->email;
	}

	public function get_phone(): string {
		return $this->phone;
	}
}
