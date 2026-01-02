<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../clases/BpWordzee.php';

/**
 * Tests para la clase BpWordzee.
 *
 * Para ejecutar: vendor/bin/phpunit
 * Para ejecutar solo este test: vendor/bin/phpunit tests/BpWordzeeTest.php
 */
class BpWordzeeTest extends TestCase
{
    private static string $testDbPath;

    private static string $originalDbPath;

    /**
     * Se ejecuta una vez antes de todos los tests
     * Crea una base de datos de prueba con palabras conocidas.
     */
    public static function setUpBeforeClass(): void
    {
        self::$testDbPath = __DIR__ . '/../bpwordzee_test.sqlite';
        self::$originalDbPath = __DIR__ . '/../bpwordzee.sqlite';

        // Crear base de datos de prueba
        $bd = new SQLite3(self::$testDbPath);
        $bd->exec('CREATE TABLE IF NOT EXISTS palabras (palabra TEXT PRIMARY KEY)');

        // Insertar palabras de prueba
        $palabrasPrueba = ['ARBOL', 'CASA', 'PERRO', 'GATO', 'SOL', 'MAR', 'CIELO'];
        $stmt = $bd->prepare('INSERT INTO palabras (palabra) VALUES (:palabra)');
        foreach ($palabrasPrueba as $palabra) {
            $stmt->bindValue(':palabra', $palabra, SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        $bd->close();

        // Hacer backup de la BD original y usar la de prueba
        if (file_exists(self::$originalDbPath)) {
            rename(self::$originalDbPath, self::$originalDbPath . '.backup');
        }
        copy(self::$testDbPath, self::$originalDbPath);
    }

    /**
     * Se ejecuta una vez después de todos los tests
     * Restaura la base de datos original.
     */
    public static function tearDownAfterClass(): void
    {
        // Restaurar BD original
        if (file_exists(self::$originalDbPath)) {
            unlink(self::$originalDbPath);
        }
        if (file_exists(self::$originalDbPath . '.backup')) {
            rename(self::$originalDbPath . '.backup', self::$originalDbPath);
        }

        // Eliminar BD de prueba
        if (file_exists(self::$testDbPath)) {
            unlink(self::$testDbPath);
        }
    }

    /**
     * Test: Verificar que obtenerTimestampModificacion devuelve un timestamp válido.
     */
    public function testObtenerTimestampModificacion(): void
    {
        $timestamp = BpWordzee::obtenerTimestampModificacion();

        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    /**
     * Test: Buscar palabras con letras válidas debe devolver coincidencias.
     */
    public function testBuscarPalabrasConLetraValidas(): void
    {
        // ARBOL se puede formar con estas letras
        $letras = ['A', 'R', 'B', 'O', 'L', 'E', 'S'];
        $resultado = BpWordzee::buscarPalabras($letras);

        $this->assertIsArray($resultado);
        $this->assertContains('ARBOL', $resultado);
    }

    /**
     * Test: Buscar con letras que no forman ninguna palabra.
     */
    public function testBuscarPalabrasSinCoincidencias(): void
    {
        // Letras que probablemente no formen las palabras de prueba
        $letras = ['X', 'Z', 'Q', 'W', 'K', 'J', 'Y'];
        $resultado = BpWordzee::buscarPalabras($letras);

        $this->assertIsArray($resultado);
        $this->assertEmpty($resultado);
    }

    /**
     * Test: Debe lanzar excepción si no hay exactamente 7 letras.
     */
    public function testBuscarPalabrasConLetrasInsuficientes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactamente 7 letras');

        BpWordzee::buscarPalabras(['A', 'B', 'C']);
    }

    /**
     * Test: Debe lanzar excepción con demasiadas letras.
     */
    public function testBuscarPalabrasConDemasiadasLetras(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactamente 7 letras');

        BpWordzee::buscarPalabras(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
    }

    /**
     * Test: Crear palabra válida debe funcionar correctamente.
     */
    public function testCrearPalabraValida(): void
    {
        $resultado = BpWordzee::crearPalabra('NUEVA');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('mensaje', $resultado);
        $this->assertArrayHasKey('palabra', $resultado);
        $this->assertEquals('NUEVA', $resultado['palabra']);

        // Verificar que se puede buscar la palabra creada
        $encontradas = BpWordzee::buscarPalabras(['N', 'U', 'E', 'V', 'A', 'X', 'Y']);
        $this->assertContains('NUEVA', $encontradas);
    }

    /**
     * Test: Crear palabra con longitud menor a 3 debe fallar.
     */
    public function testCrearPalabraMuyCorta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entre 3 y 7 letras');

        BpWordzee::crearPalabra('AB');
    }

    /**
     * Test: Crear palabra con longitud mayor a 7 debe fallar.
     */
    public function testCrearPalabraMuyLarga(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entre 3 y 7 letras');

        BpWordzee::crearPalabra('DEMASIADO');
    }

    /**
     * Test: Crear palabra duplicada debe fallar.
     */
    public function testCrearPalabraDuplicada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ya existe');

        BpWordzee::crearPalabra('CASA'); // CASA ya existe en la BD de prueba
    }

    /**
     * Test: Actualizar palabra existente debe funcionar.
     */
    public function testActualizarPalabraExistente(): void
    {
        // Primero crear una palabra para actualizar
        BpWordzee::crearPalabra('VIEJA');

        $resultado = BpWordzee::actualizarPalabra('VIEJA', 'NUEVA2');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('mensaje', $resultado);
        $this->assertArrayHasKey('palabra_antigua', $resultado);
        $this->assertArrayHasKey('palabra_nueva', $resultado);
        $this->assertEquals('VIEJA', $resultado['palabra_antigua']);
        $this->assertEquals('NUEVA2', $resultado['palabra_nueva']);
    }

    /**
     * Test: Actualizar palabra inexistente debe fallar.
     */
    public function testActualizarPalabraInexistente(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no existe');

        BpWordzee::actualizarPalabra('INEXISTENTE', 'NUEVA');
    }

    /**
     * Test: Actualizar con palabra nueva inválida debe fallar.
     */
    public function testActualizarConPalabraNuevaInvalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entre 3 y 7 letras');

        BpWordzee::actualizarPalabra('CASA', 'AB');
    }

    /**
     * Test: Eliminar palabra existente debe funcionar.
     */
    public function testEliminarPalabraExistente(): void
    {
        // Crear una palabra para eliminar
        BpWordzee::crearPalabra('BORRAR');

        $resultado = BpWordzee::eliminarPalabra('BORRAR');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('mensaje', $resultado);
        $this->assertArrayHasKey('palabra', $resultado);
        $this->assertEquals('BORRAR', $resultado['palabra']);

        // Verificar que ya no se encuentra
        $this->expectException(InvalidArgumentException::class);
        BpWordzee::eliminarPalabra('BORRAR');
    }

    /**
     * Test: Eliminar palabra inexistente debe fallar.
     */
    public function testEliminarPalabraInexistente(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no existe');

        BpWordzee::eliminarPalabra('NOEXISTE');
    }

    /**
     * Test: Normalización de mayúsculas/minúsculas en búsqueda.
     */
    public function testBusquedaCaseInsensitive(): void
    {
        $resultadoMayusculas = BpWordzee::buscarPalabras(['A', 'R', 'B', 'O', 'L', 'E', 'S']);
        $resultadoMinusculas = BpWordzee::buscarPalabras(['a', 'r', 'b', 'o', 'l', 'e', 's']);
        $resultadoMixto = BpWordzee::buscarPalabras(['A', 'r', 'B', 'o', 'L', 'e', 'S']);

        $this->assertEquals($resultadoMayusculas, $resultadoMinusculas);
        $this->assertEquals($resultadoMayusculas, $resultadoMixto);
    }

    /**
     * Test: Crear palabra normaliza a mayúsculas.
     */
    public function testCrearPalabraNormalizaMayusculas(): void
    {
        $resultado = BpWordzee::crearPalabra('test');

        $this->assertEquals('TEST', $resultado['palabra']);
    }

    /**
     * Test: Búsqueda con letras repetidas.
     */
    public function testBusquedaConLetrasRepetidas(): void
    {
        // Crear palabra con letras repetidas
        BpWordzee::crearPalabra('AAA');

        // Debe encontrarla solo si tenemos suficientes 'A'
        $conSuficientesA = BpWordzee::buscarPalabras(['A', 'A', 'A', 'B', 'C', 'D', 'E']);
        $this->assertContains('AAA', $conSuficientesA);

        // No debe encontrarla si no hay suficientes 'A'
        $sinSuficientesA = BpWordzee::buscarPalabras(['A', 'B', 'C', 'D', 'E', 'F', 'G']);
        $this->assertNotContains('AAA', $sinSuficientesA);
    }
}
