# mmt/jwf-laravel

Adaptador Laravel para `mmt/jwf-core`. Proporciona persistencia relacional, administración de versiones, Validator, archivos privados y envíos transaccionales. No registra rutas ni decide las políticas de la aplicación host.

## Instalación

Instala el paquete mediante Composer y ejecuta las migraciones. La configuración se puede publicar con el tag `jwf-config`.

La aplicación debe enlazar obligatoriamente `Mmt\JwfLaravel\Contracts\JwfAuthorizer` con una implementación propia. No existe un autorizador permisivo por defecto.

```php
$this->app->bind(JwfAuthorizer::class, ApplicationJwfAuthorizer::class);
```

## Uso

La facade delega en servicios inyectables:

```php
$outcome = Jwf::validate($versionId, $formId, $request);
$result = Jwf::submit($versionId, $formId, $request);
$copy = Jwf::forms()->cloneVersion($versionId);
```

Una copia conserva el árbol, IDs lógicos, opciones, configuración y referencias exactas a perfiles; solo cambia la identidad/número de versión y nace como borrador.

`Jwf::artifacts()` permite obtener una respuesta de descarga, listar artefactos sin referencia y solicitar una eliminación explícita. El paquete nunca elimina archivos automáticamente al borrar envíos o definiciones.

Los inputs de archivo pueden declarar `maxSizeKb`, `allowedMimeTypes` y `allowedExtensions` en su configuración. Los límites del input solo pueden restringir los límites globales de `config/jwf.php`.

La aplicación host es responsable de autorización, exposición HTTP, retención, privacidad y anonimización.
