<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../clases/BpWordzee.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

// Configurar base path porque el .htaccess redirige desde /www
$app->setBasePath('/api');

/**
 * Lista blanca de orígenes permitidos para CORS
 * 
 * Solo los orígenes en esta lista pueden hacer peticiones AJAX a la API.
 * Las peticiones directas (curl, Postman) no están afectadas por CORS.
 * 
 * @var array<int, string>
 */
const ALLOWED_ORIGINS = [
    'https://angelcastro.es'
];

// API Key dinámica: cambia automáticamente cada mes para mayor seguridad
// Formato: prefijo fijo + hash SHA256 del mes actual
define('API_KEY', 'Cambiar_Esta_Clave' . hash('sha256', 'wordzee_admin_' . date('Y-m')));

/**
 * Duración del cache en segundos (24 horas)
 * 
 * Tiempo que los clientes deben cachear las respuestas de la API.
 * 
 * @var int
 */
const CACHE_DURATION_SECONDS = 86400;

/**
 * Middleware para headers JSON y CORS
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
        
        if ($isAllowed) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'X-API-Key, Content-Type, Accept, Origin')
                ->withHeader('Access-Control-Max-Age', '3600');
        }
        
        return $response;
    }
    
    $response = $handler->handle($request);
    $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    
    // Aplicar headers CORS a respuestas normales
    if ($isAllowed) {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'X-API-Key, Content-Type, Accept, Origin')
            ->withHeader('Access-Control-Max-Age', '3600');
    }
    
    return $response;
});

/**
 * Middleware de manejo de errores
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
 * GET / - Redirige a la documentación
 */
$app->get('/', function (Request $request, Response $response): Response {
    return $response
        ->withHeader('Location', '/api/docs.html')
        ->withStatus(302);
});

/**
 * GET /docs - Alias para la documentación
 */
$app->get('/docs', function (Request $request, Response $response): Response {
    return $response
        ->withHeader('Location', '/api/docs.html')
        ->withStatus(302);
});

/**
 * Handler para búsqueda de palabras
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

    try {
        // Convertir el string de letras en array
        $letras = explode(',', $params['letras']);
        $letras = array_map('trim', $letras);
        
        $palabras = BpWordzee::buscarPalabras($letras);

        return respuestaExito($response, $palabras);
        
    } catch (InvalidArgumentException $e) {
        return respuestaError($response, $e->getMessage(), 400);
    } catch (Exception $e) {
        // Log del error en producción
        error_log('Error en buscarPalabras: ' . $e->getMessage());
        return respuestaError($response, 'Error interno del servidor', 500);
    }
};

// Registrar la ruta con y sin barra final
$app->get('/bpwordzee', $bpwordzeeHandler);
$app->get('/bpwordzee/', $bpwordzeeHandler);

/**
 * POST /palabras - Crear nueva palabra (privado)
 */
$app->post('/palabras', function (Request $request, Response $response): Response {
    if (!validarApiKey($request)) {
        return respuestaError($response, 'No autorizado', 401);
    }

    $body = $request->getParsedBody();
    if (!isset($body['palabra'])) {
        return respuestaError($response, 'Falta el parámetro "palabra"', 400);
    }

    try {
        $resultado = BpWordzee::crearPalabra($body['palabra']);
        return respuestaExito($response, $resultado);
    } catch (InvalidArgumentException $e) {
        return respuestaError($response, $e->getMessage(), 400);
    } catch (Exception $e) {
        error_log('Error al crear palabra: ' . $e->getMessage());
        return respuestaError($response, 'Error interno del servidor', 500);
    }
});

/**
 * PUT /palabras/{palabra} - Actualizar palabra (privado)
 */
$app->put('/palabras/{palabra}', function (Request $request, Response $response, array $args): Response {
    if (!validarApiKey($request)) {
        return respuestaError($response, 'No autorizado', 401);
    }

    $body = $request->getParsedBody();
    if (!isset($body['palabra_nueva'])) {
        return respuestaError($response, 'Falta el parámetro "palabra_nueva"', 400);
    }

    try {
        $resultado = BpWordzee::actualizarPalabra($args['palabra'], $body['palabra_nueva']);
        return respuestaExito($response, $resultado);
    } catch (InvalidArgumentException $e) {
        return respuestaError($response, $e->getMessage(), 400);
    } catch (Exception $e) {
        error_log('Error al actualizar palabra: ' . $e->getMessage());
        return respuestaError($response, 'Error interno del servidor', 500);
    }
});

/**
 * DELETE /palabras/{palabra} - Eliminar palabra (privado)
 */
$app->delete('/palabras/{palabra}', function (Request $request, Response $response, array $args): Response {
    if (!validarApiKey($request)) {
        return respuestaError($response, 'No autorizado', 401);
    }

    try {
        $resultado = BpWordzee::eliminarPalabra($args['palabra']);
        return respuestaExito($response, $resultado);
    } catch (InvalidArgumentException $e) {
        return respuestaError($response, $e->getMessage(), 400);
    } catch (Exception $e) {
        error_log('Error al eliminar palabra: ' . $e->getMessage());
        return respuestaError($response, 'Error interno del servidor', 500);
    }
});

$app->run();

/**
 * Valida el API Key en el header X-API-Key
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
 * Valida los parámetros de entrada de la petición
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
 * Genera una respuesta JSON de éxito
 * 
 * Formato de respuesta:
 * {
 *   "success": true,
 *   "data": {
 *     "palabras": [...],
 *     "total": 123
 *   }
 * }
 * 
 * @param Response $response Objeto de respuesta PSR-7
 * @param array<int, string> $palabras Array de palabras encontradas
 * @return Response Respuesta con JSON codificado
 */
function respuestaExito(Response $response, array $palabras): Response
{
    $response->getBody()->write(json_encode([
        'success' => true,
        'data' => [
            'palabras' => $palabras,
            'total' => count($palabras)
        ]
    ], JSON_UNESCAPED_UNICODE));
    return $response;
}

/**
 * Genera una respuesta JSON de error
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
        'mensaje' => $mensaje
    ], JSON_UNESCAPED_UNICODE));
    return $response->withStatus($codigo);
}
