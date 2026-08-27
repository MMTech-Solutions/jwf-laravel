# JWF Laravel

`mmt/jwf-laravel` es el adaptador e integración de Laravel para `mmt/jwf-core`. Puede depender de Laravel y del core; nunca debe mover detalles de Laravel al core.

## Lectura selectiva de documentación

No cargues toda la documentación de forma preventiva. Consulta solo lo que corresponda:

| Si la tarea trata sobre | Leer antes |
| --- | --- |
| Alcance, responsabilidades o dependencia con el core | `docs/purpose.md` |
| Crear o modificar PHP | `docs/php-style.md` |
| Eloquent, migraciones, perfiles, versiones, envíos o valores | `docs/persistence.md` |
| Facade, Request, Validator, Storage o el flujo de envío | `docs/integration.md` |
| Autorización, archivos, atributos, datos sensibles o transacciones | `docs/security.md` |
| Contrato de dominio, serialización o tipos de nodo | `../jwf-core/docs/architecture.md` y, si aplica, `../jwf-core/docs/contract.md` |
| Reglas de negocio de formularios, nodos, perfiles o envíos | `../../../.cursor/bds/jwf.bds.md` |

## Reglas obligatorias

- Implementar los contratos de `mmt/jwf-core`; no duplicar ni relajar sus invariantes en modelos Eloquent.
- No permitir que una edición administrativa modifique una versión publicada de formulario o perfil.
- Toda operación de envío persiste su cabecera y valores en una transacción.
- El adaptador Laravel procesa archivos y devuelve `FileReference`; el core nunca recibe `UploadedFile`.
- La autorización para editar, publicar, visualizar y enviar formularios pertenece a la aplicación integradora y debe verificarse antes de la operación.
- Antes de implementar, actualizar la documentación aplicable; actualizar el BDS si cambia una regla de negocio.
