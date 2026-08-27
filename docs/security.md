# Seguridad de `mmt/jwf-laravel`

- Verificar autorización antes de administrar plantillas, publicar versiones, acceder a una definición o crear un envío. El paquete debe exponer puntos claros para que la aplicación aplique sus policies/guards.
- Aplicar listas permitidas de campos: nunca persistir directamente todo el contenido de un `Request`.
- Ejecutar normalización, validación y persistencia dentro de un flujo consistente; el envío y todos sus valores se confirman o revierten juntos.
- Los atributos de nodos no se interpretan como HTML seguro. Las vistas y recursos deben escapar contenido y restringir atributos peligrosos conforme a la política de la aplicación.
- La gestión de archivos debe validar tamaño, cantidad y MIME/extensión permitidos; generar identificadores no predecibles; guardar con un disco configurado; y devolver una `FileReference` en vez de usar el nombre original como identidad.
- No exponer rutas privadas o metadatos sensibles de archivos al serializar formularios o respuestas.
- Las reglas Laravel generadas desde perfiles se validan al publicar la versión. No ejecutar reglas que permitan callbacks o código persistido desde la base de datos.
- Los archivos nunca se eliminan automáticamente. Al eliminar un envío, sus artefactos quedan disponibles para inspección y eliminación manual explícita por la aplicación integradora.
- La privacidad, retención efectiva y anonimización son responsabilidad de la aplicación integradora; el paquete aporta operaciones autorizables de consulta y eliminación, no políticas automáticas.
