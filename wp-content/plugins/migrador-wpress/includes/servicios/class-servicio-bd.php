<?php
/**
 * Servicio para exportar e importar base de datos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Operaciones SQL.
 */
class Servicio_BD {

	/**
	 * Servicio de registros.
	 *
	 * @var Servicio_Registros
	 */
	private $registros;

	/**
	 * Instancia global de wpdb.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param Servicio_Registros $registros Servicio de logs.
	 */
	public function __construct( $registros ) {
		global $wpdb;

		$this->registros = $registros;
		$this->db        = $wpdb;
	}

	/**
	 * Exporta la base de datos a un archivo .sql.
	 *
	 * @param string $ruta_destino Ruta del archivo.
	 * @return string
	 */
	public function exportar_sql( $ruta_destino ) {
		wp_mkdir_p( dirname( $ruta_destino ) );

		if ( $this->disponible_mysqldump() ) {
			$this->registros->registrar( 'Usando mysqldump para exportar' );
			$comando = $this->construir_comando_mysqldump( $ruta_destino );
			exec( $comando, $salida, $estado );
			if ( 0 === $estado && file_exists( $ruta_destino ) ) {
				return $ruta_destino;
			}
			$this->registros->registrar( 'mysqldump falló, se usa exportación alternativa' );
		}

		$this->registros->registrar( 'Usando exportación via wpdb' );
		$tablas = $this->db->get_col( 'SHOW TABLES' );

		$contenido = '';
		foreach ( $tablas as $tabla ) {
			$crear = $this->db->get_row( "SHOW CREATE TABLE `$tabla`", ARRAY_N );
			if ( isset( $crear[1] ) ) {
				$contenido .= "DROP TABLE IF EXISTS `$tabla`;\n";
				$contenido .= $crear[1] . ";\n\n";
			}

			$filas = $this->db->get_results( "SELECT * FROM `$tabla`", ARRAY_A );
			foreach ( $filas as $fila ) {
				$valores = array();
				foreach ( $fila as $valor ) {
					if ( null === $valor ) {
						$valores[] = 'NULL';
					} else {
						$valores[] = "'" . $this->db->_real_escape( $valor ) . "'";
					}
				}
				$contenido .= "INSERT INTO `$tabla` VALUES (" . implode( ',', $valores ) . ");\n";
			}
			$contenido .= "\n";
		}

		file_put_contents( $ruta_destino, $contenido );
		return $ruta_destino;
	}

	/**
	 * Importa un archivo SQL con search/replace de dominio.
	 *
	 * @param string $ruta_sql       Ruta al SQL.
	 * @param string $dominio_origen Dominio actual en el dump.
	 * @param string $dominio_nuevo  Dominio deseado.
	 * @return void
	 */
	public function importar_sql( $ruta_sql, $dominio_origen = '', $dominio_nuevo = '' ) {
		if ( ! file_exists( $ruta_sql ) ) {
			$this->registros->registrar( 'Archivo SQL no encontrado en restauración' );
			return;
		}

		$sql = file_get_contents( $ruta_sql );
		if ( $dominio_origen && $dominio_nuevo && $dominio_origen !== $dominio_nuevo ) {
			$sql = str_replace( $dominio_origen, $dominio_nuevo, $sql );
		}

		$sentencias = preg_split( '/;[\r\n]+/', $sql );
		$this->db->query( 'SET FOREIGN_KEY_CHECKS=0;' );

		foreach ( $sentencias as $sentencia ) {
			$sentencia = trim( $sentencia );
			if ( empty( $sentencia ) ) {
				continue;
			}
			$this->db->query( $sentencia );
		}

		$this->db->query( 'SET FOREIGN_KEY_CHECKS=1;' );
	}

	/**
	 * Comprueba si mysqldump está disponible.
	 *
	 * @return bool
	 */
	private function disponible_mysqldump() {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}
		$ruta = trim( (string) shell_exec( 'which mysqldump' ) );
		return ! empty( $ruta );
	}

	/**
	 * Crea el comando para mysqldump.
	 *
	 * @param string $destino Ruta destino.
	 * @return string
	 */
	private function construir_comando_mysqldump( $destino ) {
		$host   = DB_HOST;
		$puerto = '';

		if ( false !== strpos( DB_HOST, ':' ) ) {
			list( $host_base, $resto ) = explode( ':', DB_HOST, 2 );
			if ( is_numeric( $resto ) ) {
				$host   = $host_base;
				$puerto = (int) $resto;
			}
		}

		$credenciales = sprintf(
			'--user=%s --password=%s --host=%s',
			escapeshellarg( DB_USER ),
			escapeshellarg( DB_PASSWORD ),
			escapeshellarg( $host )
		);

		if ( ! empty( $puerto ) ) {
			$credenciales .= ' --port=' . escapeshellarg( (string) $puerto );
		}

		if ( defined( 'DB_NAME' ) ) {
			$credenciales .= ' ' . escapeshellarg( DB_NAME );
		}

		return sprintf(
			'mysqldump %s --skip-lock-tables --single-transaction --no-tablespaces > %s',
			$credenciales,
			escapeshellarg( $destino )
		);
	}
}
