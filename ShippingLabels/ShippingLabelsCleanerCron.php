<?php

namespace CRPlugins\Oca\ShippingLabels;

use CRPlugins\Oca\Helper\Helper;

defined( 'ABSPATH' ) || exit;

class ShippingLabelsCleanerCron {

	private const CRON_NAME = 'oca_remove_old_labels_cron';

	public function __construct() {
		add_action( self::CRON_NAME, array( $this, 'remove_old_labels_cron_func' ) );

		$this->create_cron();
	}

	public function remove_old_labels_cron_func(): void {

		$time = (int) Helper::get_option( 'label_delete_cron_time', 604800 ); // 7 days in secs

		$total_deleted  = 0;
		$total_deleted += $this->delete_in_labels_folder( '/*.pdf', $time );
		$total_deleted += $this->delete_in_labels_folder( '/*.zpl', $time );
		$total_deleted += $this->delete_in_labels_folder( '/*.zip', 1 ); // Don't preserve zips

		Helper::log_info( sprintf( 'Cron ran and deleted %d labels older than %s secs', $total_deleted, $time ) );
	}

	protected function delete_in_labels_folder( string $pattern, int $older_than ): int {
		$now   = time();
		$i     = 0;
		$files = glob( Helper::get_labels_folder_path() . $pattern );

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				if ( $now - filemtime( $file ) >= $older_than ) {
					unlink( $file ); // phpcs:ignore
					++$i;
				}
			}
		}

		return $i;
	}

	public function create_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_NAME ) ) {
			wp_schedule_event( ( new \DateTime() )->modify( '+24 hours' )->getTimestamp(), 'daily', self::CRON_NAME );
		}
	}
}
