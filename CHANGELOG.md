# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [1.0.0] - 2026-01-02

### 🎉 Lanzamiento Inicial

- API RESTful completa con Slim Framework 4.x
- Búsqueda optimizada de palabras válidas con algoritmo O(n)
- Base de datos SQLite con +50,000 palabras indexadas
- Endpoints: /buscar, /crear, /actualizar, /eliminar, /health
- Documentación OpenAPI 3.0 completa con ejemplos interactivos
- Docker & Docker Compose para desarrollo local
- Suite de tests PHPUnit con 17 tests y 43 assertions
- Análisis estático con PHPStan nivel 8
- Estilo de código PSR-12 con PHP CS Fixer
- CI/CD con GitHub Actions (tests, calidad, seguridad)
- Endpoint de monitoreo /health para producción
- Seguridad empresarial: CORS, validación de entradas, SQL injection protection
- Estándares PSR: PSR-4, PSR-7, PSR-15

[1.0.0]: https://github.com/angelcafe/bpwordzee-api/releases/tag/v1.0.0
