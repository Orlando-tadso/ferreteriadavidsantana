<?php
declare(strict_types=1);

$routes = [
	'agregar_producto' => __DIR__ . '/modules/inventario/agregar_producto.php',
	'agregar_productos_demo' => __DIR__ . '/modules/inventario/agregar_productos_demo.php',
	'ajustar_cantidad' => __DIR__ . '/modules/inventario/ajustar_cantidad.php',
	'bajo_stock' => __DIR__ . '/modules/inventario/bajo_stock.php',
	'crear_usuario' => __DIR__ . '/modules/auth/crear_usuario.php',
	'dashboard' => __DIR__ . '/modules/inventario/dashboard.php',
	'descargar_ticket' => __DIR__ . '/modules/ventas/descargar_ticket.php',
	'editar_producto' => __DIR__ . '/modules/inventario/editar_producto.php',
	'generar_ticket_texto' => __DIR__ . '/modules/ventas/generar_ticket_texto.php',
	'gestionar_devoluciones' => __DIR__ . '/modules/devoluciones/gestionar_devoluciones.php',
	'historial_ventas' => __DIR__ . '/modules/ventas/historial_ventas.php',
	'limpiar_historial' => __DIR__ . '/modules/system/limpiar_historial.php',
	'login' => __DIR__ . '/modules/auth/login.php',
	'logout' => __DIR__ . '/modules/auth/logout.php',
	'migrar_categorias' => __DIR__ . '/modules/inventario/migrar_categorias.php',
	'movimientos' => __DIR__ . '/modules/inventario/movimientos.php',
	'productos' => __DIR__ . '/modules/inventario/productos.php',
	'punto_venta' => __DIR__ . '/modules/ventas/punto_venta.php',
	'simulador_pos' => __DIR__ . '/modules/ventas/simulador_pos.php',
	'ver_historial' => __DIR__ . '/modules/inventario/ver_historial.php',
	'version' => __DIR__ . '/modules/system/version.php',
];

$route = $_GET['route'] ?? '';
$route = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $route);

if ($route === '' || $route === 'index') {
	header('Location: /dashboard.php', true, 302);
	exit;
}

if (!array_key_exists($route, $routes)) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=UTF-8');
	echo 'Ruta no encontrada';
	exit;
}

$targetFile = $routes[$route];
if (!file_exists($targetFile)) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=UTF-8');
	echo 'Archivo de ruta no disponible';
	exit;
}

require_once $targetFile;
?>
