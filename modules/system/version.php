<?php
// Archivo de versión - usa el mtime de archivos clave
// Solo cambia cuando realmente hay cambios en el código
header('Content-Type: application/json');

// Archivos clave cuyo cambio indica una actualización
$root = dirname(__DIR__, 2);
$archivos_clave = [
    $root . '/modules/inventario/dashboard.php',
    $root . '/modules/inventario/productos.php',
    $root . '/modules/ventas/punto_venta.php',
    $root . '/modules/inventario/movimientos.php',
    $root . '/modules/ventas/historial_ventas.php',
    $root . '/modules/inventario/agregar_producto.php',
    $root . '/modules/inventario/editar_producto.php',
    $root . '/modules/inventario/bajo_stock.php',
    $root . '/modules/auth/crear_usuario.php',
    $root . '/modules/auth/login.php',
    $root . '/assets/css/styles.css'
];

// Obtener el timestamp más reciente de los archivos
$timestamp_maximo = 0;
foreach ($archivos_clave as $archivo) {
    if (file_exists($archivo)) {
        $mtime = filemtime($archivo);
        if ($mtime > $timestamp_maximo) {
            $timestamp_maximo = $mtime;
        }
    }
}

echo json_encode(['timestamp' => $timestamp_maximo]);
?>
