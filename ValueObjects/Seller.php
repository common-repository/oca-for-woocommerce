<?php

namespace CRPlugins\Oca\ValueObjects;

class Seller {

	/**
	 * @var string
	 */
	private $name;
	/**
	 * @var string
	 */
	private $email;
	/**
	 * @var string
	 */
	private $store_name;
	/**
	 * @var int
	 */
	private $cost_branch;
	/**
	 * @var string
	 */
	private $time_frame;
	/**
	 * @var SellerOcaSettings
	 */
	private $settings;
	/**
	 * @var Address
	 */
	private $address;

	public function __construct(
		string $name,
		string $email,
		string $store_name,
		string $time_frame,
		SellerOcaSettings $settings,
		Address $address
	) {
		$this->name       = $name;
		$this->email      = $email;
		$this->store_name = $store_name;
		$this->time_frame = $time_frame;
		$this->settings   = $settings;
		$this->address    = $address;

		$this->cost_branch = 1; // Default
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_email(): string {
		return $this->email;
	}

	public function get_store_name(): string {
		return $this->store_name;
	}

	public function get_cost_branch(): int {
		return $this->cost_branch;
	}

	public function get_time_frame(): string {
		return $this->time_frame;
	}

	public function get_settings(): SellerOcaSettings {
		return $this->settings;
	}

	public function get_address(): Address {
		return $this->address;
	}
}
