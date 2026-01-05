<?php
/**
 * Clase principal del plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MIGRADOR_WPRESS_RUTA . 'includes/servicios/class-servicio-registros.php';
require_once MIGRADOR_WPRESS_RUTA . 'includes/servicios/class-servicio-bd.php';
require_once MIGRADOR_WPRESS_RUTA . 'includes/servicios/class-servicio-archivos.php';
require_once MIGRADOR_WPRESS_RUTA . 'includes/servicios/class-servicio-paquete.php';
require_once MIGRADOR_WPRESS_RUTA . 'includes/servicios/class-servicio-restauracion.php';
require_once MIGRADOR_WPRESS_RUTA . 'includes/class-migrador-wpress-admin.php';
require_once MIGRADOR_WPRESS_RUTA . 'includes/class-migrador-wpress-api.php';

/**
 * Gestiona dependencias y procesos de backup/restauración.
 */
class Migrador_WPress {

	/**
	 * Instancia singleton.
	 *
	 * @var Migrador_WPress
	 */
	private static $instancia = null;

	/**
	 * Ruta de trabajo en uploads.
	 *
	 * @var string
	 */
	private $ruta_trabajo;

	/**
	 * Ruta de staging temporal.
	 *
	 * @var string
	 */
	private $ruta_staging;

	/**
	 * Ruta del archivo de registros.
	 *
	 * @var string
	 */
	private $ruta_registro;

	/**
	 * Servicio de registros.
	 *
	 * @var Servicio_Registros
	 */
	public $servicio_registros;

	/**
	 * Servicio de base de datos.
	 *
	 * @var Servicio_BD
	 */
	public $servicio_bd;

	/**
	 * Servicio de archivos.
	 *
	 * @var Servicio_Archivos
	 */
	public $servicio_archivos;

	/**
	 * Servicio de empaquetado.
	 *
	 * @var Servicio_Paquete
	 */
	public $servicio_paquete;

	/**
	 * Servicio de restauración.
	 *
	 * @var Servicio_Restauracion
	 */
	public $servicio_restauracion;

	/**
	 * Instancia administrativa.
	 *
	 * @var Migrador_WPress_Admin
	 */
	private $admin;

	/**
	 * Instancia API.
	 *
	 * @var Migrador_WPress_Api
	 */
	private $api;

	/**
	 * Devuelve la instancia única.
	 *
	 * @return Migrador_WPress
	 */
	public static function instancia() {
		if ( null === self::$instancia ) {
			self::$instancia = new self();
		}

		return self::$instancia;
	}

	/**
	 * Constructor protegido para el patrón singleton.
	 */
	private function __construct() {
		$this->definir_rutas();
		$this->instanciar_servicios();
		$this->registrar_componentes();
		$this->registrar_cli();
	}

	/**
	 * Prepara rutas principales.
	 */
	private function definir_rutas() {
		$uploads          = wp_upload_dir();
		$this->ruta_trabajo  = trailingslashit( $uploads['basedir'] ) . 'migrador-wpress/';
		$this->ruta_staging  = trailingslashit( $this->ruta_trabajo ) . 'staging/';
		$this->ruta_registro = trailingslashit( $this->ruta_trabajo ) . 'migrador.log';

		wp_mkdir_p( $this->ruta_trabajo );
		wp_mkdir_p( $this->ruta_staging );
	}

	/**
	 * Instancia servicios internos.
	 */
	private function instanciar_servicios() {
		$this->servicio_registros    = new Servicio_Registros( $this->ruta_registro );
		$this->servicio_bd           = new Servicio_BD( $this->servicio_registros );
		$this->servicio_archivos     = new Servicio_Archivos( $this->servicio_registros );
		$this->servicio_paquete      = new Servicio_Paquete( $this->servicio_registros );
		$this->servicio_restauracion = new Servicio_Restauracion( $this->servicio_registros, $this->servicio_bd, $this->servicio_archivos );
	}

	/**
	 * Carga administradores y API.
	 */
	private function registrar_componentes() {
		$this->admin = new Migrador_WPress_Admin( $this );
		$this->api   = new Migrador_WPress_Api( $this );
	}

	/**
	 * Registra comandos WP-CLI si aplica.
	 */
	public function registrar_cli() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once MIGRADOR_WPRESS_RUTA . 'includes/class-migrador-wpress-cli.php';
			WP_CLI::add_command( 'migrador-wpress', new Migrador_WPress_CLI( $this ) );
		}
	}

	/**
	 * Genera un respaldo completo.
	 *
	 * @param array $exclusiones Listado de rutas relativas a excluir.
	 *
	 * @return array
	 */
	public function generar_respaldo( $exclusiones = array() ) {
		$marca_tiempo = gmdate( 'Ymd_His' );
		$directorio   = trailingslashit( $this->ruta_staging ) . 'respaldo_' . $marca_tiempo;
		$destino      = trailingslashit( $this->ruta_trabajo ) . 'respaldo-' . $marca_tiempo . '.wpress';

		wp_mkdir_p( $directorio );

		$this->servicio_registros->registrar( 'Iniciando exportación de base de datos' );
		$sql = $this->servicio_bd->exportar_sql( $directorio . '/database.sql' );

		$this->servicio_registros->registrar( 'Copiando wp-content con exclusiones' );
		$this->servicio_archivos->copiar_contenido_wp( WP_CONTENT_DIR, $directorio . '/wp-content', $exclusiones );

		$dominio_actual = home_url();
		$manifesto      = array(
			'dominio'   => $dominio_actual,
			'creado_en' => current_time( 'mysql' ),
			'exclusiones' => $exclusiones,
		);

		file_put_contents( $directorio . '/manifest.json', wp_json_encode( $manifesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

		$this->servicio_registros->registrar( 'Generando archivo .wpress' );
		$paquete = $this->servicio_paquete->generar_paquete( $directorio, $destino );

		$this->servicio_registros->registrar( 'Limpieza de temporales' );
		$this->servicio_archivos->eliminar_directorio( $directorio );

		return array(
			'paquete'  => $paquete['ruta'],
			'checksum' => $paquete['checksum'],
			'log'      => $this->servicio_registros->leer_registro(),
			'sql'      => $sql,
		);
	}

	/**
	 * Ejecuta restauración completa.
	 *
	 * @param string $archivo_paquete Ruta al archivo .wpress.
	 * @param string $dominio_nuevo   Dominio destino para search/replace.
	 *
	 * @return array
	 */
	public function restaurar_desde_paquete( $archivo_paquete, $dominio_nuevo = '' ) {
		$directorio_destino = trailingslashit( $this->ruta_staging ) . 'restauracion_' . gmdate( 'Ymd_His' );
		wp_mkdir_p( $directorio_destino );

		$this->servicio_registros->registrar( 'Extrayendo paquete' );
		$this->servicio_paquete->extraer_paquete( $archivo_paquete, $directorio_destino );

		$manifesto     = $this->servicio_restauracion->leer_manifesto( $directorio_destino );
		$dominio_origen = isset( $manifesto['dominio'] ) ? $manifesto['dominio'] : '';
		if ( empty( $dominio_nuevo ) ) {
			$dominio_nuevo = home_url();
		}

		$sql = $directorio_destino . '/database.sql';
		if ( file_exists( $sql ) ) {
			$this->servicio_registros->registrar( 'Importando base de datos con search/replace' );
			$this->servicio_bd->importar_sql( $sql, $dominio_origen, $dominio_nuevo );
		}

		if ( file_exists( $directorio_destino . '/wp-content' ) ) {
			$this->servicio_registros->registrar( 'Restaurando wp-content' );
			$this->servicio_restauracion->restaurar_archivos( $directorio_destino . '/wp-content', WP_CONTENT_DIR );
		}

		$this->servicio_registros->registrar( 'Limpieza de temporales de restauración' );
		$this->servicio_archivos->eliminar_directorio( $directorio_destino );

		return array(
			'log'           => $this->servicio_registros->leer_registro(),
			'dominio_origen' => $dominio_origen,
			'dominio_destino' => $dominio_nuevo,
		);
	}

	/**
	 * Devuelve la ruta base de trabajo.
	 *
	 * @return string
	 */
	public function obtener_ruta_trabajo() {
		return $this->ruta_trabajo;
	}

	/**
	 * Devuelve la ruta de staging.
	 *
	 * @return string
	 */
	public function obtener_ruta_staging() {
		return $this->ruta_staging;
	}
}
