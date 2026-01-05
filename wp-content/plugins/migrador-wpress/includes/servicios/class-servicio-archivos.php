<?php
/**
 * Servicio de archivos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copia y limpia rutas.
 */
class Servicio_Archivos {

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
	 * Copia wp-content a un staging respetando exclusiones.
	 *
	 * @param string $origen       Directorio de origen.
	 * @param string $destino      Directorio destino.
	 * @param array  $exclusiones  Lista de rutas relativas a omitir.
	 * @return void
	 */
	public function copiar_contenido_wp( $origen, $destino, $exclusiones = array() ) {
		wp_mkdir_p( $destino );
		$iterador = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $origen, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterador as $archivo ) {
			$ruta_relativa = ltrim( str_replace( $origen, '', $archivo->getPathname() ), DIRECTORY_SEPARATOR );
			if ( $this->es_excluida( $ruta_relativa, $exclusiones ) ) {
				continue;
			}

			$destino_actual = trailingslashit( $destino ) . $ruta_relativa;

			if ( $archivo->isDir() ) {
				wp_mkdir_p( $destino_actual );
			} else {
				wp_mkdir_p( dirname( $destino_actual ) );
				copy( $archivo->getPathname(), $destino_actual );
			}
		}
	}

	/**
	 * Determina si la ruta debe excluirse.
	 *
	 * @param string $ruta_relativa Ruta relativa.
	 * @param array  $exclusiones   Lista de exclusiones.
	 * @return bool
	 */
	private function es_excluida( $ruta_relativa, $exclusiones ) {
		foreach ( $exclusiones as $exclusion ) {
			$exclusion = trim( $exclusion );
			if ( empty( $exclusion ) ) {
				continue;
			}

			$exclusion = trim( $exclusion, '/\\' );
			if ( 0 === strpos( $ruta_relativa, $exclusion ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Elimina recursivamente un directorio.
	 *
	 * @param string $ruta Ruta a eliminar.
	 * @return void
	 */
	public function eliminar_directorio( $ruta ) {
		if ( ! file_exists( $ruta ) ) {
			return;
		}

		$archivos = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $ruta, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $archivos as $archivo ) {
			if ( $archivo->isDir() ) {
				rmdir( $archivo->getPathname() );
			} else {
				unlink( $archivo->getPathname() );
			}
		}

		rmdir( $ruta );
	}
}
