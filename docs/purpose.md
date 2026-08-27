# Propósito de `mmt/jwf-laravel`

Este paquete adapta JWF a Laravel. Proporciona persistencia Eloquent, migraciones, integración con el validador, gestión configurable de archivos, Service Provider y una API de soporte para enviar formularios.

Su misión es traducir los detalles de Laravel a los contratos de `mmt/jwf-core`, no redefinir el dominio.

La API de soporte usa `Jwf::submit($formVersionId, $formId, Request|array $input)`. El `formId` es explícito porque una versión puede contener varios formularios. Internamente delega en servicios separados de resolución, normalización, validación, archivos y persistencia.
