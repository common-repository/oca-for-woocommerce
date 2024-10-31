<?php

namespace CRPlugins\Oca\ShippingLabels;

use CRPlugins\Oca\Helper\Helper;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

class LabelFilesManager {

	/**
	 * @param string[] $files
	 */
	public static function create_zip( string $zip_name, array $files ): void {
		$zip      = new ZipArchive();
		$zip_name = sprintf( '%s/%s.zip', Helper::get_labels_folder_path(), $zip_name );

		// Override old zip
		if ( file_exists( $zip_name ) ) {
			unlink( $zip_name ); // phpcs:ignore
		}

		$zip->open( $zip_name, ZipArchive::CREATE );
		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				continue;
			}

			$zip->addFile( $file, basename( $file ) );
		}

		$zip->close();
	}

	public static function get_zip_url( string $zip_name ): string {
		if ( ! file_exists(
			sprintf(
				'%s/%s.zip',
				Helper::get_labels_folder_path(),
				$zip_name
			)
		) ) {
			return '';
		}

		return sprintf(
			'%s/%s.zip',
			Helper::get_labels_folder_url(),
			$zip_name
		);
	}

	/**
	 * @param string[] $pdfs_names
	 */
	public static function create_merged_pdf( array $pdfs_names ): string {
		/** @psalm-suppress UnresolvableInclude */
		require_once Helper::get_libs_folder_path() . '/OcaConcatPdf.php';
		$pdf_merger = new \OcaConcatPdf();

		foreach ( $pdfs_names as $pdf_name ) {
			$pdf_merger->addFile( sprintf( '%s/%s', Helper::get_labels_folder_path(), $pdf_name ) );
		}
		$pdf_merger->concat();

		$now             = new \DateTime();
		$final_file_name = sprintf( 'merge-%s', $now->format( 'U' ) );
		$final_path      = sprintf( '%s/%s.pdf', Helper::get_labels_folder_path(), $final_file_name );

		$pdf_merger->Output( $final_path, 'F' );
		$content = file_get_contents( $final_path ); // phpcs:ignore
		unlink( $final_path ); // phpcs:ignore

		return $content;
	}
}
