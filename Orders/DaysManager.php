<?php

namespace CRPlugins\Oca\Orders;

use DateTime;
use DateTimeInterface;

class DaysManager {

	/**
	 * @param string[] $allowed_days ['0', '1', ...]
	 */
	public function get_soonest_available_date(
		DateTimeInterface $date,
		array $allowed_days,
		string $time_frame,
		int $extra_days
	): DateTimeInterface {
		$allowed_hours = $this->get_hours_from_time_frame( $time_frame );

		$new_date = $this->create_date_time_from_interface( $date );

		if ( $extra_days ) {
			$new_date->modify( sprintf( '+%s days', $extra_days ) );
			$new_date->setTime( 0, 0 );
		}

		if ( ! $this->is_date_within_time_frame( $new_date, $allowed_hours ) ) {
			$new_date->modify( '+1 day' );
		}

		if ( ! $this->is_day_allowed( $new_date, $allowed_days ) ) {
			$new_date = $this->get_next_allowed_day( $new_date, $allowed_days );
		}

		return $new_date;
	}

	/**
	 * @param string[] $allowed_days ['0', '1', ...]
	 */
	private function get_next_allowed_day( DateTimeInterface $date, array $allowed_days ): DateTime {
		$new_date = $this->create_date_time_from_interface( $date );

		while ( ! $this->is_day_allowed( $new_date, $allowed_days ) ) {
			$new_date->modify( '+1 day' );
		}

		return $new_date;
	}

	/**
	 * @param string[] $allowed_days ['0', '1', ...]
	 */
	private function is_day_allowed( DateTimeInterface $date, array $allowed_days ): bool {
		return in_array( $date->format( 'w' ), $allowed_days, true );
	}

	/**
	 * @param array{from: int, to: int} $allowed_hours
	 */
	private function is_date_within_time_frame( DateTimeInterface $date, array $allowed_hours ): bool {
		$time_frame_to = $this->create_date_time_from_interface( $date )->setTime( $allowed_hours['to'], 0 );

		$interval = $date->diff( $time_frame_to );

		$total_minutes = ( ( $interval->days * 24 * 60 ) + ( $interval->h * 60 ) + $interval->i ) * ( $interval->invert ? 1 : -1 );

		$minutes_treshold = apply_filters( 'wc_oca_door_dispatch_minutes_treshold', 0 );
		if ( $minutes_treshold > 0 ) {
			$minutes_treshold *= -1; // treshold must be negative for the calculation
		}

		return $total_minutes < $minutes_treshold;
	}

	/**
	 * @return array{from: int, to: int}
	 */
	private function get_hours_from_time_frame( string $time_frame ): array {
		switch ( $time_frame ) {
			default:
			case '1':
				return array(
					'from' => 8,
					'to'   => 17,
				);
			case '2':
				return array(
					'from' => 8,
					'to'   => 12,
				);
			case '3':
				return array(
					'from' => 14,
					'to'   => 17,
				);
		}
	}

	/**
	 * Polyfill, function exists only on PHP >= 8
	 */
	private function create_date_time_from_interface( DateTimeInterface $date_time_interface ): DateTime {
		$timezone  = $date_time_interface->getTimezone();
		$date_time = new DateTime( $date_time_interface->format( 'Y-m-d H:i:s' ), $timezone ? $timezone : null );

		return $date_time;
	}
}
