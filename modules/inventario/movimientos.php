<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Producto.php';
require_once __DIR__ . '/../ventas/Venta.php';

$producto_obj = new Producto($conn);
$venta_obj = new Venta($conn);

if (isset($_GET['id'])) {
    $producto = $producto_obj->obtenerPorId($_GET['id']);
} else {
    $producto = null;
}

// Obtener movimientos MANUALES (solo entradas y salidas, NO ventas)
$todos_movimientos = $producto_obj->obtenerHistorial();

// Filtrar solo entrada y salida manual (excluir ventas que se registraron en movimientos)
$historial = array_filter($todos_movimientos, function($mov) {
    // Mantener solo entrada y salida manual (no venta)
    return $mov['tipo_movimiento'] == 'entrada' || $mov['tipo_movimiento'] == 'salida';
});

// Obtener ventas agrupadas
$ventas = $venta_obj->obtenerHistorialVentas(100);
$movimientos_ventas = [];

foreach ($ventas as $v) {
    // Filtrar solo ventas del sistema de ferretería (con total > 0)
    if ($v['total'] <= 0) {
        continue;
    }
    
    $detalles = $venta_obj->obtenerDetallesVenta($v['id']);
    
    // Agrupar todos los productos de una venta en una sola línea
    $productos_nombres = [];
    $cantidad_total = 0;
    
    foreach ($detalles as $detalle) {
        $productos_nombres[] = $detalle['nombre'] . ' (x' . $detalle['cantidad'] . ')';
        $cantidad_total += $detalle['cantidad'];
    }
    
    $movimientos_ventas[] = [
        'nombre' => implode(', ', $productos_nombres),
        'tipo_movimiento' => 'venta',
        'cantidad' => $cantidad_total,
        'motivo' => 'Venta factura ' . $v['numero_factura'] . (!empty($v['cliente_nombre']) ? ' - Cliente: ' . $v['cliente_nombre'] : ''),
        'fecha_movimiento' => $v['fecha_venta']
    ];
}

// Combinar movimientos manuales y ventas agrupadas, ordenar por fecha descendente
$historial_combinado = array_merge($historial, $movimientos_ventas);
usort($historial_combinado, function($a, $b) {
    return strtotime($b['fecha_movimiento']) - strtotime($a['fecha_movimiento']);
});

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Inventario - Ferretería</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container">
        <?php require_once __DIR__ . '/../core/menu.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1><img src="assets/icons/movimientos.png" alt="Movimientos" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Historial de Movimientos y Ventas</h1>
                <?php if ($producto): ?>
                    <p>Producto: <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong></p>
                    <a href="movimientos.php" class="btn btn-secondary">Ver todos</a>
                <?php endif; ?>
            </header>

            <!-- Sección combinada de Movimientos y Ventas -->
            <section class="card">
                <h2><img src="assets/icons/carrito.png" alt="Movimientos" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Todos los Movimientos (Inventario y Ventas)</h2>
                <?php if (count($historial_combinado) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Motivo / Detalles</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial_combinado as $mov): ?>
                                <tr class="<?php echo $mov['tipo_movimiento'] == 'entrada' ? 'entrada' : ($mov['tipo_movimiento'] == 'venta' ? 'salida' : 'salida'); ?>">
                                    <td><?php echo htmlspecialchars($mov['nombre']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $mov['tipo_movimiento']; ?>">
                                            <?php 
                                            if ($mov['tipo_movimiento'] == 'entrada') {
                                                echo '<img src="assets/icons/iniciar-sesion.png" alt="Entrada" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Entrada';
                                            } elseif ($mov['tipo_movimiento'] == 'venta') {
                                                echo '<img src="assets/icons/venta.png" alt="Venta" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Venta';
                                            } else {
                                                echo '<img src="assets/icons/cerrar_sesion.png" alt="Salida" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Salida';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo abs($mov['cantidad']); ?></td>
                                    <td><?php echo htmlspecialchars($mov['motivo'] ?: 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($mov['fecha_movimiento'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No hay movimientos registrados</p>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        // Script para estilos dinámicos de ventas
    </script>
</body>
</html>
