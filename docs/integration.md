# Integración Laravel

El adaptador ofrece una fachada o servicio para validar y enviar una respuesta de formulario. Tanto `validate` como `submit` reciben el ID de versión y el ID del formulario concreto. `submit` debe:

1. Resolver una versión publicada del formulario.
2. Extraer exclusivamente los campos permitidos por sus inputs.
3. Convertir `UploadedFile` en `FileReference` mediante el servicio de archivos configurado.
4. Normalizar valores conforme al tipo del input.
5. Compilar reglas declarativas y perfiles versionados a reglas del Validator de Laravel.
6. Validar los datos.
7. Crear el envío y los valores en una sola transacción.

También debe existir una operación de validación sin escritura para procesos HTTP, importaciones o jobs.

Los artefactos de archivo se resuelven y eliminan mediante operaciones explícitas del paquete. El paquete no registra rutas: la aplicación integradora autoriza y expone las descargas según su política.

La configuración de un input de archivo puede restringir `maxSizeKb`, `allowedMimeTypes` y `allowedExtensions`. Estas restricciones complementan, pero nunca amplían, los límites globales del paquete.

La expresión de perfiles se almacena como reglas declarativas con nombre y parámetros. El adaptador Laravel las compila a las reglas nativas adecuadas: por ejemplo, el perfil `integer` se traduce a `integer`, no a `numeric`, que también admite decimales.
