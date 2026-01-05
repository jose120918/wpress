# Migrador WPress

Plugin de WordPress para generar y restaurar respaldos en formato `.wpress`, con interfaz de administración, endpoints REST/AJAX y comandos WP-CLI reutilizando servicios internos en PHP.

## Resumen ejecutivo
- Crea paquetes `.wpress` que incluyen `database.sql`, `wp-content` (con exclusiones configurables) y un `manifest.json` con dominio y metadatos.
- Permite restaurar paquetes realizando search/replace de dominio antes de importar el SQL y copiando archivos a `wp-content`.
- Expone UI en Herramientas, endpoints protegidos con nonce/capability y comandos WP-CLI para automatización.

## Requisitos
- WordPress 6.0+ con PHP 7.4+.
- Extensión `ZipArchive` habilitada.
- `mysqldump` disponible en el servidor para exportación rápida (si no existe, se usa exportación con `wpdb`).
- Espacio en disco suficiente en `wp-content/uploads/migrador-wpress` para staging, logs y paquetes generados.
- (Opcional) WP-CLI para uso por consola.

## Instalación
1. Copia el directorio `wp-content/plugins/migrador-wpress/` en tu instalación de WordPress.
2. Activa el plugin desde _Plugins_ o mediante `wp plugin activate migrador-wpress`.

## Uso en la interfaz de administración
1. Ve a _Herramientas → Migrador WPress_.
2. **Crear backup**:
   - Marca las carpetas a excluir (cache, uploads, plugins, themes, mu-plugins) y añade rutas adicionales (una por línea) si lo necesitas.
   - Pulsa **Generar .wpress** y espera el mensaje de confirmación con ruta y checksum.
3. **Restaurar**:
   - Selecciona un archivo `.wpress` y escribe el dominio destino (usado en search/replace antes de importar la base de datos).
   - Pulsa **Restaurar** y espera el mensaje de finalización.

Los mensajes de estado se muestran bajo cada formulario; los errores y pasos quedan registrados en `wp-content/uploads/migrador-wpress/migrador.log`.

## Comandos WP-CLI
- Crear backup excluyendo carpetas:
  ```
  wp migrador-wpress backup --excluir=uploads,cache
  ```
- Restaurar usando dominio nuevo:
  ```
  wp migrador-wpress restore /ruta/al/archivo.wpress --dominio=https://nuevo-ejemplo.com
  ```

## Endpoints REST
- Backup: `POST /wp-json/migrador-wpress/v1/backup`
  - Headers: `X-WP-Nonce: <nonce wp_rest>`.
  - Body: `exclusiones[]` (opcional), `exclusiones_personalizadas` (texto).
- Restore: `POST /wp-json/migrador-wpress/v1/restore`
  - Headers: `X-WP-Nonce: <nonce wp_rest>`.
  - Body: `archivo_wpress` (multipart, obligatorio), `dominio_nuevo` (texto).

Los permisos requieren `manage_options` y nonces válidos. Si prefieres AJAX, usa `admin-ajax.php` con las acciones `migrador_wpress_backup` y `migrador_wpress_restore` más el nonce `migrador_wpress_acciones`.

## Flujo interno
1. **Backup**
   - Exporta la base de datos a `database.sql` (mysqldump si está disponible, si no, exportación con `wpdb`).
   - Copia `wp-content` a staging respetando exclusiones.
   - Genera `manifest.json` con dominio y fecha.
   - Empaqueta staging en `.wpress` y calcula checksum SHA-256.
   - Limpia temporales.
2. **Restauración**
   - Extrae el `.wpress` a staging.
   - Lee `manifest.json` para detectar dominio original.
   - Importa `database.sql` aplicando search/replace de dominio.
   - Copia `wp-content` extraído sobre el actual.
   - Limpia temporales.

## Límites conocidos
- El fallback de exportación con `wpdb` puede ser más lento en bases de datos grandes.
- El search/replace es textual; para sitios con datos serializados complejos se recomienda validar manualmente.
- Es necesario contar con espacio libre suficiente para staging y el paquete final.

## Validación en staging recomendada
- Instala el plugin en un entorno de staging, genera un backup y restaura en otro sitio con dominio distinto usando la UI o WP-CLI.
- Verifica que las URLs en la base de datos apunten al dominio nuevo y que los archivos se hayan copiado correctamente antes de hacer cambios en producción.

## Pruebas
- No se ejecutaron pruebas automáticas en este entorno al no contar con una instancia de WordPress preconfigurada. Se recomienda validar el flujo completo en staging siguiendo la sección anterior.
