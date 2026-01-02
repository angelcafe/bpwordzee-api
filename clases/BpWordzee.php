<?php

/**
 * Clase para búsqueda de palabras óptimas en Wordzee y gestión de base de datos.
 *
 * Uso para búsqueda:
 *   $palabras = BpWordzee::buscarPalabras($letras_disponibles);
 *
 * Uso para gestión:
 *   BpWordzee::crearPalabra('NUEVA');
 *   BpWordzee::actualizarPalabra('VIEJA', 'NUEVA');
 *   BpWordzee::eliminarPalabra('PALABRA');
 */
class BpWordzee
{
    /** Ruta a la base de datos SQLite */
    private const DB_PATH = __DIR__ . '/../bpwordzee.sqlite';

    /** Longitud mínima de palabra */
    private const MIN_PALABRA_LENGTH = 3;

    /** Longitud máxima de palabra */
    private const MAX_PALABRA_LENGTH = 7;

    /** Número exacto de letras requeridas */
    private const REQUIRED_LETTERS = 7;

    /**
     * Devuelve la fecha de modificación de la base de datos.
     *
     * @return int Unix timestamp de la última modificación
     */
    public static function obtenerTimestampModificacion(): int
    {
        return filemtime(self::DB_PATH) ?: 0;
    }

    /**
     * Busca palabras que se pueden formar con las letras disponibles.
     *
     * @param array<int, string> $letras_disponibles Array de 7 letras disponibles
     * @return array<int, string> Array de palabras que coinciden
     * @throws InvalidArgumentException Si los parámetros no son válidos
     */
    public static function buscarPalabras(array $letras_disponibles): array
    {
        if (count($letras_disponibles) !== self::REQUIRED_LETTERS) {
            throw new InvalidArgumentException(
                sprintf('El array de letras disponibles debe tener exactamente %d letras.', self::REQUIRED_LETTERS)
            );
        }

        // Normalizar a mayúsculas para búsqueda case-insensitive
        $letras_disponibles = array_map('mb_strtoupper', $letras_disponibles);

        // Crear contador de letras para validación eficiente (evita múltiples iteraciones)
        // Ejemplo: ['A', 'R', 'B', 'O', 'L', 'E', 'S'] -> ['A'=>1, 'R'=>1, 'B'=>1, ...]
        $letras_contador = array_count_values($letras_disponibles);

        return self::procesarPalabras($letras_contador);
    }

    /**
     * Procesa todas las palabras de la base de datos y filtra las que coinciden.
     *
     * Usa SQLITE3_OPEN_READONLY para prevenir escrituras accidentales y mejorar rendimiento.
     * Itera sobre todas las palabras y valida una por una si se puede formar con las letras.
     *
     * @param array<string, int> $letras_contador Contador de letras disponibles ['A'=>2, 'B'=>1, ...]
     * @return array<int, string> Array de palabras que coinciden
     */
    private static function procesarPalabras(array $letras_contador): array
    {
        $bd = new SQLite3(self::DB_PATH, SQLITE3_OPEN_READONLY);
        $result = $bd->query('SELECT palabra FROM palabras');

        $palabras = [];

        while ($row = $result->fetchArray(SQLITE3_NUM)) {
            $palabra = $row[0];
            $longitud = mb_strlen($palabra);

            if (self::puedeFormarsePalabra($palabra, $longitud, $letras_contador)) {
                $palabras[] = $palabra;
            }
        }

        $bd->close();

        return $palabras;
    }

    /**
     * Verifica si una palabra puede formarse con las letras disponibles.
     *
     * Algoritmo: Copia el contador de letras y va descontando cada letra usada.
     * Si una letra no está disponible o se agotó, la palabra es inválida.
     * Complejidad: O(n) donde n es la longitud de la palabra.
     *
     * @param string $palabra Palabra a validar
     * @param int $longitud Longitud precalculada de la palabra (optimización)
     * @param array<string, int> $letras_contador Contador de letras disponibles
     * @return bool True si la palabra puede formarse, false en caso contrario
     */
    private static function puedeFormarsePalabra(string $palabra, int $longitud, array $letras_contador): bool
    {
        // Clonar el contador para no modificar el original
        $letras_disponibles = $letras_contador;

        for ($i = 0; $i < $longitud; $i++) {
            $letra = mb_substr($palabra, $i, 1);

            if (isset($letras_disponibles[$letra]) && $letras_disponibles[$letra] > 0) {
                $letras_disponibles[$letra]--;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Crea una nueva palabra en la base de datos.
     *
     * Normaliza la palabra a mayúsculas y valida que:
     * - Tenga entre 3 y 7 letras
     * - No exista ya en la base de datos
     *
     * @param string $palabra Palabra a crear (será normalizada a mayúsculas)
     * @return array{mensaje: string, palabra: string} Array asociativo con resultado
     * @throws InvalidArgumentException Si la palabra tiene longitud inválida o ya existe
     */
    public static function crearPalabra(string $palabra): array
    {
        $palabra = mb_strtoupper(trim($palabra));
        $longitud = mb_strlen($palabra);

        if ($longitud < self::MIN_PALABRA_LENGTH || $longitud > self::MAX_PALABRA_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('La palabra debe tener entre %d y %d letras', self::MIN_PALABRA_LENGTH, self::MAX_PALABRA_LENGTH)
            );
        }

        $bd = new SQLite3(self::DB_PATH, SQLITE3_OPEN_READWRITE);

        try {
            // Prevenir duplicados: verificar existencia antes de insertar
            $stmt = $bd->prepare('SELECT palabra FROM palabras WHERE palabra = :palabra');
            $stmt->bindValue(':palabra', $palabra, SQLITE3_TEXT);
            $result = $stmt->execute();

            if ($result->fetchArray()) {
                throw new InvalidArgumentException('La palabra ya existe');
            }

            // Insertar
            $stmt = $bd->prepare('INSERT INTO palabras (palabra) VALUES (:palabra)');
            $stmt->bindValue(':palabra', $palabra, SQLITE3_TEXT);
            $stmt->execute();

            return ['mensaje' => 'Palabra creada', 'palabra' => $palabra];
        } finally {
            $bd->close();
        }
    }

    /**
     * Actualiza una palabra existente en la base de datos.
     *
     * Reemplaza una palabra antigua por una nueva. Ambas palabras se normalizan
     * a mayúsculas automáticamente.
     *
     * @param string $palabraAntigua Palabra actual a reemplazar
     * @param string $palabraNueva Nueva palabra (debe tener entre 3 y 7 letras)
     * @return array{mensaje: string, palabra_antigua: string, palabra_nueva: string} Array con resultado
     * @throws InvalidArgumentException Si la palabra antigua no existe o la nueva tiene longitud inválida
     */
    public static function actualizarPalabra(string $palabraAntigua, string $palabraNueva): array
    {
        $palabraAntigua = mb_strtoupper(trim($palabraAntigua));
        $palabraNueva = mb_strtoupper(trim($palabraNueva));
        $longitud = mb_strlen($palabraNueva);

        if ($longitud < self::MIN_PALABRA_LENGTH || $longitud > self::MAX_PALABRA_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('La palabra debe tener entre %d y %d letras', self::MIN_PALABRA_LENGTH, self::MAX_PALABRA_LENGTH)
            );
        }

        $bd = new SQLite3(self::DB_PATH, SQLITE3_OPEN_READWRITE);

        try {
            // Verificar que existe la antigua
            $stmt = $bd->prepare('SELECT palabra FROM palabras WHERE palabra = :palabra');
            $stmt->bindValue(':palabra', $palabraAntigua, SQLITE3_TEXT);
            $result = $stmt->execute();

            if (!$result->fetchArray()) {
                throw new InvalidArgumentException('La palabra no existe');
            }

            // Actualizar
            $stmt = $bd->prepare('UPDATE palabras SET palabra = :nueva WHERE palabra = :antigua');
            $stmt->bindValue(':nueva', $palabraNueva, SQLITE3_TEXT);
            $stmt->bindValue(':antigua', $palabraAntigua, SQLITE3_TEXT);
            $stmt->execute();

            return [
                'mensaje' => 'Palabra actualizada',
                'palabra_antigua' => $palabraAntigua,
                'palabra_nueva' => $palabraNueva,
            ];
        } finally {
            $bd->close();
        }
    }

    /**
     * Elimina una palabra de la base de datos.
     *
     * Verifica que la palabra exista antes de eliminarla.
     * La palabra se normaliza a mayúsculas automáticamente.
     *
     * @param string $palabra Palabra a eliminar
     * @return array{mensaje: string, palabra: string} Array asociativo con resultado
     * @throws InvalidArgumentException Si la palabra no existe en la base de datos
     */
    public static function eliminarPalabra(string $palabra): array
    {
        $palabra = mb_strtoupper(trim($palabra));

        $bd = new SQLite3(self::DB_PATH, SQLITE3_OPEN_READWRITE);

        try {
            // Verificar que existe
            $stmt = $bd->prepare('SELECT palabra FROM palabras WHERE palabra = :palabra');
            $stmt->bindValue(':palabra', $palabra, SQLITE3_TEXT);
            $result = $stmt->execute();

            if (!$result->fetchArray()) {
                throw new InvalidArgumentException('La palabra no existe');
            }

            // Eliminar
            $stmt = $bd->prepare('DELETE FROM palabras WHERE palabra = :palabra');
            $stmt->bindValue(':palabra', $palabra, SQLITE3_TEXT);
            $stmt->execute();

            return ['mensaje' => 'Palabra eliminada', 'palabra' => $palabra];
        } finally {
            $bd->close();
        }
    }
}
