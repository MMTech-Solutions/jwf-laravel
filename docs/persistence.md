# Persistencia y versionado

JWF persiste definiciones de forma relacional, no como un documento JSON completo. JSON queda reservado para atributos/configuración flexible y valores serializados.

## Agregados persistidos

- Plantilla de formulario: identidad editable y estable.
- Versión de formulario: borrador, publicada o archivada; una publicada es inmutable.
- Nodo de formulario: pertenece a una versión, tiene `parent_id`, posición, clase de nodo y configuración.
- Opción de input: pertenece a un input de selección y tiene identidad/valor estable.
- Perfil y versión de perfil de validación: el perfil identifica la política; cada versión contiene la expresión declarativa de reglas. Los formularios publicados referencian versiones, no el perfil mutable.
- Envío: respuesta concreta asociada a la versión publicada del formulario.
- Valor de envío: asociación entre el envío y el ID del input; el valor se guarda en `LONGTEXT` como JSON canónico.

El uso de `LONGTEXT` permite representar valores simples, arreglos y objetos sin perder una convención única. El tipo y las reglas del nodo validan y normalizan antes de persistir; la columna no infiere el tipo.

El adaptador mantiene perfiles internos para las garantías de tipos de input. Al persistir un borrador con un input
`email` o `url`, añade una referencia a la versión predeterminada de `jwf.default.email` o `jwf.default.url`; la
referencia queda congelada en la versión de formulario. Estos perfiles no requieren configuración ni creación por la
aplicación host.

Las definiciones internas se resuelven por nombre y se versionan igual que cualquier otro perfil: si cambian sus tipos
compatibles o reglas declarativas, el adaptador crea una nueva versión para los borradores posteriores. Las versiones ya
referenciadas no se modifican.

Para cargar un formulario se recuperan todos los nodos de la versión en una consulta ordenada y se reconstruye el árbol en memoria mediante `parent_id`. No se hace una consulta por hijo.

## Copias y eliminación

Una versión nueva se crea como copia fiel de otra versión de la misma plantilla. Conserva los IDs lógicos y todo el contenido de la definición, incluidas las referencias exactas a perfiles. Solo cambia el ID de versión, se asigna el siguiente número y nace como borrador.

Una plantilla o versión con envíos no puede eliminarse. Sin envíos, sus nodos, opciones y asociaciones internas se eliminan en cascada. Eliminar un envío elimina sus valores, pero conserva los artefactos y archivos para que la aplicación integradora decida posteriormente qué hacer con ellos.
