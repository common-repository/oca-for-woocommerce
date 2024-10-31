<?php

use setasign\Fpdi\Fpdi;

require_once 'fpdf/fpdf.php';
require_once 'fpdi/autoload.php';

class OcaConcatPdf extends Fpdi {

	/**
	 * @var string[]
	 */
	public $files = array();

	/**
	 * @param string[] $files
	 */
	public function setFiles( array $files ): void {
		$this->files = $files;
	}

	public function addFile( string $file ): void {
		$this->files[] = $file;
	}

	public function concat(): void {
		foreach ( $this->files as $file ) {
			$page_count = $this->setSourceFile( $file );

			for ( $page_number = 1; $page_number <= $page_count; $page_number++ ) {
				$page_id = $this->ImportPage( $page_number );
				$size    = $this->getTemplatesize( $page_id );

				$this->AddPage( $size['orientation'], $size );
				$this->useImportedPage( $page_id );
			}
		}
	}
}
