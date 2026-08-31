# Propósito de `mmt/jwf-laravel`

Este paquete adapta JWF a Laravel. Proporciona persistencia Eloquent, migraciones, integración con el validador, gestión configurable de archivos, Service Provider y una API de soporte para enviar formularios.

Su misión es traducir los detalles de Laravel a los contratos de `mmt/jwf-core`, no redefinir el dominio.

Las definiciones reutilizables propias del adaptador, como perfiles predeterminados de validación, viven en `Support`.
Describen políticas declarativas y tipos compatibles; la persistencia les asigna identidades y versiones, sin trasladar
detalles de Laravel al core.

La independencia de `jwf-core` respecto de Laravel se verifica en las pruebas arquitectónicas del propio core. Este adaptador verifica su integración contra el core instalado mediante Composer, sin depender de que ambos repositorios sean directorios hermanos.

La API de soporte usa `Jwf::submit($formVersionId, $formId, Request|array $input)`. El `formId` es explícito porque una versión puede contener varios formularios. Internamente delega en servicios separados de resolución, normalización, validación, archivos y persistencia.
