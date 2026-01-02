# 🎯 BpWordzee API

<div align="center">

[![CI/CD Pipeline](https://github.com/angelcafe/bpwordzee-api/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/angelcafe/bpwordzee-api/actions/workflows/tests.yml)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://phpstan.org/)
[![PHP 8.3|8.4](https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777BB4?logo=php)](https://www.php.net/)
[![Production](https://img.shields.io/badge/Status-Production-success)](https://acf.alwaysdata.net/api)
[![License](https://img.shields.io/badge/License-Proprietary-red)](LICENSE)

**API RESTful de alto rendimiento para búsqueda de palabras válidas en juegos tipo Wordzee**

[🔗 Demo Live](https://acf.alwaysdata.net/api/bpwordzee?letras=A,R,B,O,L,E,S) • [📚 Documentación](https://acf.alwaysdata.net/api/docs.html)

</div>

---

## 🚀 Stack Tecnológico

```
PHP 8.4 + Slim Framework 4.x + SQLite 3 + Docker
```

- **Backend**: PHP 8.4 con tipado estricto, PSR-4/7/15
- **Framework**: Slim 4.x (microframework RESTful)
- **Base de Datos**: SQLite con +50k palabras indexadas
- **Testing**: PHPUnit 11.5 con 17 tests (43 assertions)
- **Code Quality**: PHPStan nivel 8 + PHP CS Fixer (PSR-12)
- **DevOps**: Docker + Docker Compose, CI/CD con GitHub Actions
- **Documentación**: OpenAPI 3.0 completa

## ⚡ Arquitectura Cliente-Servidor

```
┌──────────────┐    HTTP/JSON     ┌────────────┐
│   CLIENTE    │ ◄──────────────► │  SERVIDOR  │
│  JavaScript  │   <100ms avg     │    PHP     │
├──────────────┤                  ├────────────┤
│ • Puntuación │                  │ • Filtrado │
│ • Bonus      │                  │ • SQLite   │
│ • Sorting    │                  │ • CORS     │
└──────────────┘                  └────────────┘
```

**Beneficios**: Rendimiento optimizado • Escalabilidad • Separación de responsabilidades

## 💡 Características Destacadas

### 🏗️ Código Profesional
```php
declare(strict_types=1);

/**
 * @param array<int, string> $letras_disponibles
 * @return array<int, string>
 * @throws InvalidArgumentException
 */
public static function buscarPalabras(array $letras_disponibles): array
{
    if (count($letras_disponibles) !== self::REQUIRED_LETTERS) {
        throw new InvalidArgumentException('Requeridas 7 letras exactamente.');
    }
    // Algoritmo O(n) optimizado...
}
```

### 🔒 Seguridad Real
- CORS restrictivo (solo `https://angelcastro.es`)
- API Key SHA-256 para endpoints administrativos
- Validación y sanitización completa de inputs
- SQLite en modo READONLY para consultas
- Prepared statements anti-SQL injection

### ⚡ Alto Rendimiento
- **< 100ms** tiempo de respuesta promedio
- **O(n)** complejidad del algoritmo de búsqueda
- **+500 req/s** capacidad de throughput
- Índices DB optimizados, sin ORM pesado

## 🧪 Testing y Calidad

```bash
composer quality  # PHPStan + CS Fixer + Tests
```

- ✅ **17 tests** cubriendo casos normales, límite y errores
- ✅ **PHPStan nivel 8** (máxima strictness)
- ✅ **PSR-12** code style con PHP CS Fixer
- ✅ **CI/CD** automático en GitHub Actions (PHP 8.3/8.4)

## 📦 Instalación y Demo

### Con Docker (Recomendado)
```bash
docker-compose up -d
# API disponible en http://localhost:8080/api
```

### Sin Docker
```bash
composer install
php -S localhost:8080 -t www/
```

### Demo en Producción
```bash
curl "https://acf.alwaysdata.net/api/bpwordzee?letras=A,R,B,O,L,E,S"
```

**Respuesta:**
```json
{
  "success": true,
  "data": ["ARBOL", "ROBA", "SABLE", "..."],
  "total": 156,
  "time_ms": 48
}
```

## 📚 Documentación API

### Endpoints Principales

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/bpwordzee?letras=A,B,C,...` | Buscar palabras válidas |
| `GET` | `/health` | Health check para monitorización |
| `POST` | `/bpwordzee` | Crear palabra (requiere API Key) |
| `PUT` | `/bpwordzee/{palabra}` | Actualizar palabra (requiere API Key) |
| `DELETE` | `/bpwordzee/{palabra}` | Eliminar palabra (requiere API Key) |

#### Health Check Endpoint

Endpoint de monitorización para producción. Verifica estado del servicio y dependencias:

```bash
curl https://acf.alwaysdata.net/api/health
```

**Respuesta (200 OK):**
```json
{
  "status": "healthy",
  "timestamp": "2026-01-02T16:00:50+01:00",
  "service": "BpWordzee API",
  "version": "1.0.0",
  "response_time_ms": 0.1,
  "checks": {
    "database": {"status": "ok", "message": "SQLite accessible"},
    "memory": {"status": "ok", "usage": "4 MB", "limit": "128M", "percent": "3.13%"},
    "php": {"status": "ok", "version": "8.4.16", "sapi": "apache2handler"}
  }
}
```

Útil para: Load balancers, contenedores Docker/Kubernetes, sistemas de monitoreo, alertas automáticas.

📖 [Documentación OpenAPI completa](https://acf.alwaysdata.net/api/docs.html)

## 🎓 Skills Demostradas

### Backend Development
✅ PHP moderno (8.4+) con tipado estricto  
✅ API RESTful design & implementation  
✅ Framework MVC (Slim)  
✅ Estándares PSR-4/7/15

### Database & Performance
✅ Optimización SQL con índices  
✅ Algoritmos eficientes (O(n))  
✅ Caché strategies (ETag, Cache-Control)

### Security
✅ CORS configuration  
✅ API Key authentication (SHA-256)  
✅ Input validation & sanitization  
✅ SQL injection prevention

### DevOps & Testing
✅ Docker containerization  
✅ PHPUnit test suite (17 tests)  
✅ Static analysis (PHPStan nivel 8)  
✅ CI/CD pipeline (GitHub Actions)

### Architecture & Best Practices
✅ Clean code principles  
✅ Separation of concerns  
✅ Middleware pattern  
✅ OpenAPI 3.0 documentation

## 📊 Métricas de Rendimiento

| Métrica | Valor |
|---------|-------|
| Tiempo de respuesta | < 100ms promedio |
| Throughput | 500+ req/s |
| Palabras indexadas | +50,000 |
| Complejidad algoritmo | O(n) |
| Tests passing | 17/17 ✅ |
| PHPStan errors | 0 ✅ |

## 📂 Estructura del Proyecto

```
bpwordzee/
├── clases/
│   └── BpWordzee.php        # Core: Algoritmo búsqueda + CRUD
├── www/api/
│   ├── index.php            # Slim Framework setup + routing
│   ├── openapi.yaml         # Especificación API completa
│   └── docs.html            # Documentación interactiva
├── tests/
│   └── BpWordzeeTest.php    # 17 tests PHPUnit
├── docker-compose.yml       # Orquestación containers
├── Dockerfile               # PHP 8.4-apache custom image
└── composer.json            # Dependencies + scripts
```

## 💼 Valor Profesional

Este proyecto demuestra capacidad para:

✨ Diseñar arquitecturas escalables y mantenibles  
✨ Escribir código limpio con estándares modernos  
✨ Implementar seguridad real (no solo demos)  
✨ Optimizar rendimiento y considerar escalabilidad  
✨ Documentar técnicamente de forma profesional  
✨ Aplicar DevOps (Docker, CI/CD)  
✨ Entregar software funcional en producción

## 🤝 Contacto Profesional

**Ángel Miguel Castro Fernández**  
Desarrollador Backend especializado en PHP, APIs REST y arquitecturas escalables

- 🌐 Portfolio: [angelcastro.es](https://angelcastro.es)
- 🚀 API Demo: [acf.alwaysdata.net/api](https://acf.alwaysdata.net/api)
- 💼 LinkedIn: [linkedin.com/in/angcas](https://www.linkedin.com/in/angcas/)
- 📧 Email: angelcafn@gmail.com

**Disponibilidad**: Remoto  
**Ubicación**: España (San Sebastián de los Reyes - Madrid)

---

## 📜 Licencia

**Copyright © 2025 Ángel Miguel Castro Fernández. Todos los derechos reservados.**

Código disponible para:
- ✅ Evaluación técnica en procesos de selección
- ✅ Demostración de habilidades profesionales

**Uso comercial, distribución o modificación requieren autorización expresa.**

---

<div align="center">

**¿Te gustaría este talento en tu equipo?**

[📧 Contactar para Oportunidades](mailto:angelcafn@gmail.com)

</div>
