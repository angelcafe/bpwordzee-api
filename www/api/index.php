<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../clases/BpWordzee.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

// Configurar base path porque el .htaccess redirige desde /www
$app->setBasePath('/api');

/**
 * Lista blanca de orígenes permitidos para CORS.
 *
 * Solo los orígenes en esta lista pueden hacer peticiones AJAX a la API.
 * Las peticiones directas (curl, Postman) no están afectadas por CORS.
 *
 * @var array<int, string>
 */
const ALLOWED_ORIGINS = [
    'https://angelcastro.es',
];

// API Key dinámica: cambia automáticamente cada mes para mayor seguridad
// Formato: prefijo fijo + hash SHA256 del mes actual
define('API_KEY', 'Cambiar_Esta_Clave' . hash('sha256', 'wordzee_admin_' . date('Y-m')));

/**
 * Duración del cache en segundos (24 horas).
 *
 * Tiempo que los clientes deben cachear las respuestas de la API.
 *
 * @var int
 */
const CACHE_DURATION_SECONDS = 86400;

/**
 * Middleware para headers JSON y CORS.
 *
 * Implementa seguridad CORS con lista blanca de orígenes permitidos.
 * Solo las peticiones desde angelcastro.es pueden acceder a la API desde el navegador.
 */
$app->add(function (Request $request, $handler): Response {
    $origin = $request->getHeaderLine('Origin');

    // Validar si el origen de la petición está en la lista blanca
    $isAllowed = false;
    if ($origin) {
        foreach (ALLOWED_ORIGINS as $allowedOrigin) {
            if ($origin === $allowedOrigin) {
                $isAllowed = true;
                break;
            }
        }
    }

    // Responder a preflight requests (peticiones OPTIONS previas a CORS)
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();

        return aplicarHeadersCors($response, $isAllowed, $origin);
    }

    $response = $handler->handle($request);
    $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');

    return aplicarHeadersCors($response, $isAllowed, $origin);
});

/**
 * Middleware de autenticación con API Key.
 *
 * Protege los endpoints administrativos (POST, PUT, DELETE).
 * Solo se aplica a rutas que coincidan con el patrón.
 */
$authMiddleware = function (Request $request, $handler): Response {
    if (!validarApiKey($request)) {
        return respuestaError(new \Slim\Psr7\Response(), 'No autorizado', 401);
    }

    return $handler->handle($request);
};

/**
 * Middleware de manejo de errores.
 *
 * Configuración:
 * - displayErrorDetails: false (no mostrar detalles en producción)
 * - logErrors: true (registrar errores en logs)
 * - logErrorDetails: false (no registrar stack traces completos)
 *
 * @var \Slim\Middleware\ErrorMiddleware
 */
$errorMiddleware = $app->addErrorMiddleware(false, true, false);

/**
 * Handler para redirección a documentación.
 */
$docsHandler = function (Request $request, Response $response): Response {
    return $response
        ->withHeader('Location', '/api/docs.html')
        ->withStatus(302);
};

$app->get('/', $docsHandler);
$app->get('/docs', $docsHandler);

/**
 * Handler para búsqueda de palabras.
 *
 * GET /bpwordzee - Encuentra las palabras que coinciden con las letras disponibles
 *
 * El servidor solo filtra palabras válidas. El cliente calcula puntuaciones y aplica bonus.
 * Esta separación de responsabilidades optimiza el rendimiento del servidor.
 *
 * Query params:
 * @param string letras Letras disponibles separadas por comas (exactamente 7 letras)
 *
 * @var callable(Request, Response): Response
 */
$bpwordzeeHandler = function (Request $request, Response $response): Response {
    $params = $request->getQueryParams();

    if (empty($params)) {
        return $response
            ->withHeader('Location', '/api/docs.html')
            ->withStatus(302);
    }

    // Validar parámetros requeridos
    $validacion = validarParametros($params);
    if ($validacion !== null) {
        return respuestaError($response, $validacion, 400);
    }

    return ejecutarConManejoErrores($response, function () use ($params) {
        // Convertir el string de letras en array
        $letras = explode(',', $params['letras']);
        $letras = array_map('trim', $letras);

        return BpWordzee::buscarPalabras($letras);
    }, 'Error en buscarPalabras');
};

// Registrar la ruta con y sin barra final
$app->get('/bpwordzee', $bpwordzeeHandler);
$app->get('/bpwordzee/', $bpwordzeeHandler);

/**
 * GET /health - Health check endpoint para monitorización.
 *
 * Verifica el estado del servicio y sus dependencias.
 * Útil para load balancers, contenedores y sistemas de monitoreo.
 */
$app->get('/health', function (Request $request, Response $response): Response {
    $startTime = microtime(true);
    $checks = [];
    $overallStatus = 'healthy';

    // Check 1: Base de datos SQLite
    try {
        $timestamp = BpWordzee::obtenerTimestampModificacion();
        $checks['database'] = [
            'status' => 'ok',
            'message' => 'SQLite accessible',
        ];
    } catch (Exception $e) {
        $checks['database'] = [
            'status' => 'error',
            'message' => $e->getMessage(),
        ];
        $overallStatus = 'unhealthy';
    }

    // Check 2: Memoria disponible
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    $memoryLimitBytes = convertToBytes($memoryLimit);
    $memoryPercent = ($memoryUsage / $memoryLimitBytes) * 100;

    $checks['memory'] = [
        'status' => $memoryPercent < 90 ? 'ok' : 'warning',
        'usage' => round($memoryUsage / 1024 / 1024, 2) . ' MB',
        'limit' => $memoryLimit,
        'percent' => round($memoryPercent, 2) . '%',
    ];

    if ($memoryPercent >= 90) {
        $overallStatus = 'degraded';
    }

    // Check 3: Uptime del proceso (aproximado)
    $checks['php'] = [
        'status' => 'ok',
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
    ];

    $responseTime = round((microtime(true) - $startTime) * 1000, 2);

    $healthData = [
        'status' => $overallStatus,
        'timestamp' => date('c'),
        'service' => 'BpWordzee API',
        'version' => '1.0.0',
        'response_time_ms' => $responseTime,
        'checks' => $checks,
    ];

    $statusCode = ($overallStatus === 'healthy') ? 200 : 503;

    $response->getBody()->write(json_encode($healthData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($statusCode);
});

/**
 * GET /bpwordzee/modificacion - Obtener timestamp de última modificación de la base de datos.
 */
$app->get('/bpwordzee/modificacion', function (Request $request, Response $response): Response {
    return ejecutarConManejoErrores($response, function () {
        $timestamp = BpWordzee::obtenerTimestampModificacion();

        return ['timestamp' => $timestamp];
    }, 'Error al obtener timestamp de modificación');
});

/**
 * POST /bpwordzee/palabras - Crear nueva palabra (privado).
 */
$app->post('/bpwordzee/palabras', function (Request $request, Response $response): Response {
    $body = $request->getParsedBody();
    if (!isset($body['palabra'])) {
        return respuestaError($response, 'Falta el parámetro "palabra"', 400);
    }

    return ejecutarConManejoErrores($response, function () use ($body) {
        return BpWordzee::crearPalabra($body['palabra']);
    }, 'Error al crear palabra');
})->add($authMiddleware);

/**
 * PUT /bpwordzee/palabras/{palabra} - Actualizar palabra (privado).
 */
$app->put('/bpwordzee/palabras/{palabra}', function (Request $request, Response $response, array $args): Response {
    $body = $request->getParsedBody();
    if (!isset($body['palabra_nueva'])) {
        return respuestaError($response, 'Falta el parámetro "palabra_nueva"', 400);
    }

    return ejecutarConManejoErrores($response, function () use ($args, $body) {
        return BpWordzee::actualizarPalabra($args['palabra'], $body['palabra_nueva']);
    }, 'Error al actualizar palabra');
})->add($authMiddleware);

/**
 * DELETE /bpwordzee/palabras/{palabra} - Eliminar palabra (privado).
 */
$app->delete('/bpwordzee/palabras/{palabra}', function (Request $request, Response $response, array $args): Response {
    return ejecutarConManejoErrores($response, function () use ($args) {
        return BpWordzee::eliminarPalabra($args['palabra']);
    }, 'Error al eliminar palabra');
})->add($authMiddleware);

$app->run();

/**
 * Ejecuta una operación con manejo automático de errores.
 *
 * @param Response $response Objeto de respuesta PSR-7
 * @param callable $operacion Función que ejecuta la operación de negocio
 * @param string $mensajeLog Mensaje para el log en caso de error genérico
 * @return Response Respuesta con éxito o error
 */
function ejecutarConManejoErrores(Response $response, callable $operacion, string $mensajeLog): Response
{
    try {
        $resultado = $operacion();

        return respuestaExito($response, $resultado);
    } catch (InvalidArgumentException $e) {
        return respuestaError($response, $e->getMessage(), 400);
    } catch (Exception $e) {
        error_log($mensajeLog . ': ' . $e->getMessage());

        return respuestaError($response, 'Error interno del servidor', 500);
    }
}

/**
 * Aplica los headers CORS a una respuesta.
 *
 * @param Response $response Objeto de respuesta PSR-7
 * @param bool $isAllowed Si el origen está permitido en la lista blanca
 * @param string $origin Origen de la petición
 * @return Response Respuesta con headers CORS aplicados
 */
function aplicarHeadersCors(Response $response, bool $isAllowed, string $origin): Response
{
    if ($isAllowed) {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'X-API-Key, Content-Type, Accept, Origin')
            ->withHeader('Access-Control-Max-Age', '3600');
    }

    return $response;
}

/**
 * Valida el API Key en el header X-API-Key.
 *
 * Compara el valor del header X-API-Key con la API_KEY configurada.
 * Usada para proteger endpoints administrativos (POST, PUT, DELETE).
 *
 * @param Request $request Objeto de petición PSR-7
 * @return bool True si el API Key es válido, false en caso contrario
 */
function validarApiKey(Request $request): bool
{
    $apiKey = $request->getHeaderLine('X-API-Key');

    return $apiKey === API_KEY;
}

/**
 * Valida los parámetros de entrada de la petición.
 *
 * Verifica que el parámetro 'letras' esté presente y sea un string.
 * No valida el formato específico (eso lo hace BpWordzee::buscarPalabras).
 *
 * @param array<string, mixed> $params Parámetros de la petición (query params)
 * @return string|null Mensaje de error si la validación falla, null si es válida
 */
function validarParametros(array $params): ?string
{
    if (!isset($params['letras'])) {
        return 'Falta el parámetro "letras"';
    }

    if (!is_string($params['letras'])) {
        return 'El parámetro "letras" debe ser un string';
    }

    return null;
}

/**
 * Genera una respuesta JSON de éxito.
 *
 * Formato de respuesta:
 * {
 *   "success": true,
 *   "data": {
 *     "palabras": [...],
 *     "total": 123
 *   }
 * }
 * o para otros datos:
 * {
 *   "success": true,
 *   "data": {...}
 * }
 *
 * @param Response $response Objeto de respuesta PSR-7
 * @param mixed $data Datos a devolver (array de palabras u otros datos)
 * @return Response Respuesta con JSON codificado
 */
function respuestaExito(Response $response, $data): Response
{
    // Si es un array numérico (lista), formatear con palabras y total
    if (is_array($data) && isset($data[0]) && is_string($data[0])) {
        $responseData = [
            'success' => true,
            'data' => [
                'palabras' => $data,
                'total' => count($data),
            ],
        ];
    } else {
        // Para otros tipos de datos (arrays asociativos, objetos, valores simples, etc.)
        $responseData = [
            'success' => true,
            'data' => $data,
        ];
    }

    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));

    return $response;
}

/**
 * Genera una respuesta JSON de error.
 *
 * Formato de respuesta:
 * {
 *   "success": false,
 *   "mensaje": "Descripción del error"
 * }
 *
 * @param Response $response Objeto de respuesta PSR-7
 * @param string $mensaje Mensaje descriptivo del error
 * @param int $codigo Código de estado HTTP (400, 401, 404, 500, etc.)
 * @return Response Respuesta con JSON codificado y código de estado HTTP
 */
function respuestaError(Response $response, string $mensaje, int $codigo): Response
{
    $response->getBody()->write(json_encode([
        'success' => false,
        'mensaje' => $mensaje,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withStatus($codigo);
}

/**
 * Convierte valores de memoria PHP (ej: '128M', '1G') a bytes.
 *
 * @param string $value Valor de memoria en formato PHP
 * @return int Bytes
 */
function convertToBytes(string $value): int
{
    $value = trim($value);
    $unit = strtolower(substr($value, -1));
    $number = (int) substr($value, 0, -1);

    switch ($unit) {
        case 'g':
            return $number * 1024 * 1024 * 1024;
        case 'm':
            return $number * 1024 * 1024;
        case 'k':
            return $number * 1024;
        default:
            return (int) $value;
    }
}
