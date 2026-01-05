<?php
/**
 * Servicio de restauración.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestiona restauración desde staging.
 */
class Servicio_Restauracion {

	/**
	 * Servicio de registros.
	 *
	 * @var Servicio_Registros
	 */
	private $registros;

	/**
	 * Servicio de base de datos.
	 *
	 * @var Servicio_BD
	 */
	private $bd;

	/**
	 * Servicio de archivos.
	 *
	 * @var Servicio_Archivos
	 */
	private $archivos;

	/**
	 * Constructor.
	 *
	 * @param Servicio_Registros $registros Servicio de logs.
	 * @param Servicio_BD        $bd        Servicio BD.
	 * @param Servicio_Archivos  $archivos  Servicio de archivos.
	 */
	public function __construct( $registros, $bd, $archivos ) {
		$this->registros = $registros;
		$this->bd        = $bd;
		$this->archivos  = $archivos;
	}

	/**
	 * Lee manifest.json si existe.
	 *
	 * @param string $directorio Directorio base.
	 * @return array
	 */
	public function leer_manifesto( $directorio ) {
		$archivo = trailingslashit( $directorio ) . 'manifest.json';
		if ( ! file_exists( $archivo ) ) {
			return array();
		}

		$contenido = file_get_contents( $archivo );
		$datos     = json_decode( $contenido, true );

		return is_array( $datos ) ? $datos : array();
	}

	/**
	 * Copia archivos restaurados a wp-content.
	 *
	 * @param string $origen  Ruta en staging.
	 * @param string $destino Ruta wp-content.
	 * @return void
	 */
	public function restaurar_archivos( $origen, $destino ) {
		$this->registros->registrar( 'Copiando archivos restaurados' );
		wp_mkdir_p( $destino );

		$iterador = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $origen, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterador as $archivo ) {
			$ruta_relativa = ltrim( str_replace( $origen, '', $archivo->getPathname() ), DIRECTORY_SEPARATOR );
			$destino_actual = trailingslashit( $destino ) . $ruta_relativa;

			if ( $archivo->isDir() ) {
				wp_mkdir_p( $destino_actual );
			} else {
				wp_mkdir_p( dirname( $destino_actual ) );
				copy( $archivo->getPathname(), $destino_actual );
			}
		}
	}
}
