<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico ColaHandler</h1>";

// Verificar que el archivo existe
if (file_exists(__DIR__ . '/sync/cola_handler.php')) {
    echo "✅ archivo cola_handler.php existe<br>";
} else {
    echo "❌ archivo cola_handler.php NO existe<br>";
}

// Incluir el archivo
require_once __DIR__ . '/sync/cola_handler.php';

// Verificar que la clase existe
if (class_exists('ColaHandler')) {
    echo "✅ Clase ColaHandler encontrada<br>";
    
    // Intentar crear instancia
    try {
        $cola = new ColaHandler();
        echo "✅ Objeto ColaHandler creado correctamente<br>";
    } catch (Error $e) {
        echo "❌ Error al crear objeto: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Clase ColaHandler NO encontrada<br>";
    
    // Verificar errores de sintaxis
    $content = file_get_contents(__DIR__ . '/sync/cola_handler.php');
    echo "<h3>Primeras 20 líneas del archivo:</h3>";
    echo "<pre>" . htmlspecialchars(substr($content, 0, 500)) . "</pre>";
}