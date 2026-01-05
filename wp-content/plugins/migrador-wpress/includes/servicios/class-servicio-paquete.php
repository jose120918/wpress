<?php
/**
 * Servicio de empaquetado .wpress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maneja creación y extracción de paquetes.
 */
class Servicio_Paquete {

	/**
	 * Servicio de registros.
	 *
	 * @var Servicio_Registros
	 */
	private $registros;

	/**
	 * Constructor.
	 *
	 * @param Servicio_Registros $registros Servicio de logs.
	 */
	public function __construct( $registros ) {
		$this->registros = $registros;
	}

	/**
	 * Genera el paquete .wpress.
	 *
	 * @param string $directorio Directorio a empaquetar.
	 * @param string $destino    Ruta destino del archivo.
	 * @return array
	 */
	public function generar_paquete( $directorio, $destino ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $destino, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$this->registros->registrar( 'No se pudo crear el archivo .wpress' );
			return array(
				'ruta'     => '',
				'checksum' => '',
			);
		}

		$archivos = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directorio, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $archivos as $archivo ) {
			$ruta_relativa = str_replace( $directorio . '/', '', $archivo->getPathname() );
			if ( $archivo->isDir() ) {
				$zip->addEmptyDir( $ruta_relativa );
			} else {
				$zip->addFile( $archivo->getPathname(), $ruta_relativa );
			}
		}

		$zip->close();

		return array(
			'ruta'     => $destino,
			'checksum' => hash_file( 'sha256', $destino ),
		);
	}

	/**
	 * Extrae un archivo .wpress a destino.
	 *
	 * @param string $archivo  Ruta del paquete.
	 * @param string $destino  Directorio destino.
	 * @return void
	 */
	public function extraer_paquete( $archivo, $destino ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $archivo ) ) {
			$this->registros->registrar( 'No se pudo abrir el paquete para extraer' );
			return;
		}

		wp_mkdir_p( $destino );
		$zip->extractTo( $destino );
		$zip->close();
	}
}
