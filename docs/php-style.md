# Estilo PHP

Todo código PHP de `mmt/jwf-laravel` debe cumplir estrictamente con [PSR-12](https://www.php-fig.org/psr/psr-12/).

## Reglas obligatorias

- Archivos UTF-8 sin BOM, declaración `strict_types=1` y salto de línea final.
- Un archivo declara símbolos o ejecuta efectos de lado, nunca ambos.
- Cuatro espacios de indentación; no tabs. Líneas de hasta 120 caracteres.
- Namespace, imports y declaración de clase separados por una línea en blanco. Imports ordenados alfabéticamente y sin imports no usados.
- Clases, interfaces, traits y enums en `PascalCase`; métodos y propiedades en `camelCase`; constantes en `UPPER_SNAKE_CASE`.
- Declarar tipos en parámetros, retornos y propiedades. Usar `readonly` para objetos de valor e inmutables.
- Usar `new ClassName()` siempre con paréntesis.
- Abrir llaves en la misma línea de la declaración y cerrar en una nueva línea. Aplicar la misma regla a controles de flujo.
- Usar trailing comma en listas multilínea de argumentos, parámetros, arrays y `match`.
- No usar `mixed`, arrays sin tipo PHPDoc, `@phpstan-ignore` ni supresiones de análisis para ocultar errores.

## Verificación

Antes de entregar cambios PHP, ejecutar las pruebas y análisis estático definidos por el paquete. Si se incorpora Laravel Pint, debe usar un preset PSR-12 y no sustituye estas reglas.
