<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Venta.php';

$venta = new Venta($conn);

// Mostrar siempre la semana actual
$semana_actual = date('Y-W');

// Calcular fechas de lunes y sábado de la semana seleccionada
$year = substr($semana_actual, 0, 4);
$week = substr($semana_actual, 5, 2);

// Calcular lunes de la semana
$fecha_lunes = new DateTime();
$fecha_lunes->setISODate($year, $week, 1); // 1 = lunes
$lunes_str = $fecha_lunes->format('Y-m-d');

// Calcular sábado de la semana (6 días después del lunes)
$fecha_sabado = clone $fecha_lunes;
$fecha_sabado->modify('+5 days'); // +5 días = sábado
$sabado_str = $fecha_sabado->format('Y-m-d');

// Obtener todas las ventas y filtrar por la semana
$ventas = $venta->obtenerHistorialVentas(500);

// Procesar ventas con sus detalles
$ventas_procesadas = [];
$total_semana = 0;

foreach ($ventas as $v) {
    // Filtrar solo ventas del sistema de ferretería (con total > 0)
    if ($v['total'] <= 0) {
        continue;
    }
    
    // Filtrar por rango de fechas (lunes a sábado)
    $fecha_venta = date('Y-m-d', strtotime($v['fecha_venta']));
    if ($fecha_venta < $lunes_str || $fecha_venta > $sabado_str) {
        continue;
    }
    
    $detalles = $venta->obtenerDetallesVenta($v['id']);
    $v['detalles'] = $detalles;
    $v['total_venta'] = 0;
    foreach ($detalles as $detalle) {
        $v['total_venta'] += $detalle['subtotal'];
    }
    
    // Restar devoluciones del total
    $total_devuelto = $venta->obtenerTotalDevuelto($v['id']);
    $v['total_devuelto'] = $total_devuelto;
    $v['total_neto'] = $v['total_venta'] - $total_devuelto;
    
    $total_semana += $v['total_neto'];
    $ventas_procesadas[] = $v;
}

// Ordenar por fecha descendente
usort($ventas_procesadas, function($a, $b) {
    return strtotime($b['fecha_venta']) - strtotime($a['fecha_venta']);
});

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas - Ferretería</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .form-control {
            padding: 10px;
            border: 2px solid #3498db;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .detalles-venta {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 3px solid #3498db;
        }
        
        .detalles-venta table {
            width: 100%;
            font-size: 13px;
        }
        
        .detalles-venta th,
        .detalles-venta td {
            padding: 5px;
            text-align: left;
        }
        
        .btn-detalles {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-detalles:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php require_once __DIR__ . '/../core/menu.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1><img src="assets/icons/historial-ventas.png" alt="Historial" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Historial de Ventas Semanales</h1>
                <p>Ventas de Lunes a Sábado para cuentas semanales</p>
            </header>

            <!-- Selector de Semana -->
            <section class="card">
                <h2><img src="assets/icons/calendario.png" alt="Calendario" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Seleccionar Semana</h2>
                <form style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <select class="form-control" style="width: 300px;" disabled>
                        <option value="<?php echo $semana_actual; ?>" selected>
                            <?php echo $fecha_lunes->format('d/m/Y'); ?> - <?php echo $fecha_sabado->format('d/m/Y'); ?>
                        </option>
                    </select>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background-color: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;"><img src="assets/icons/dinero.png" alt="Dinero" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Total de la Semana</h3>
                    <p style="margin: 0; font-size: 24px; font-weight: bold; color: #1b5e20;">
                        $<?php echo number_format($total_semana, 2); ?> COP
                    </p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">
                        Del <?php echo $fecha_lunes->format('d/m/Y'); ?> al <?php echo $fecha_sabado->format('d/m/Y'); ?>
                        (<?php echo count($ventas_procesadas); ?> ventas)
                    </p>
                </div>
            </section>

            <section class="card">
                <h2>Detalle de Ventas</h2>
                
                <?php if (count($ventas_procesadas) > 0): ?>
                    <table class="table table-responsive">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Cliente</th>
                                <th>Cédula</th>
                                <th>Productos</th>
                                <th>Total Inicial</th>
                                <th>Devuelto</th>
                                <th>Total Neto</th>
                                <th>Fecha y Hora</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas_procesadas as $idx => $venta): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($venta['numero_factura']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($venta['cliente_cedula']); ?></td>
                                    <td>
                                        <?php 
                                        $productos_list = [];
                                        foreach ($venta['detalles'] as $det) {
                                            $productos_list[] = $det['nombre'] . ' (x' . $det['cantidad'] . ')';
                                        }
                                        echo htmlspecialchars(implode(', ', $productos_list));
                                        ?>
                                    </td>
                                    <td><strong>$<?php echo number_format($venta['total_venta'], 2); ?></strong></td>
                                    <td style="color: #e74c3c;"><strong><?php echo $venta['total_devuelto'] > 0 ? '-$' . number_format($venta['total_devuelto'], 2) : '-'; ?></strong></td>
                                    <td style="color: #27ae60; font-weight: bold;">$<?php echo number_format($venta['total_neto'], 2); ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($venta['fecha_venta'])); ?></td>
                                    <td>
                                        <button class="btn-detalles" onclick="toggleDetalles(<?php echo $idx; ?>)">Ver Detalles</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7" style="padding: 0;">
                                        <div id="detalles-<?php echo $idx; ?>" class="detalles-venta">
                                            <h4>Detalles de la Venta:</h4>
                                            <div style="margin-bottom: 10px; padding: 10px; background-color: #f0f0f0; border-radius: 5px;">
                                                <strong>Cliente:</strong> <?php echo htmlspecialchars($venta['cliente_nombre']); ?><br>
                                                <strong>Cédula:</strong> <?php echo htmlspecialchars($venta['cliente_cedula']); ?><br>
                                                <?php if (!empty($venta['cliente_email'])): ?>
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($venta['cliente_email']); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($venta['cliente_telefono'])): ?>
                                                    <strong>Teléfono:</strong> <?php echo htmlspecialchars($venta['cliente_telefono']); ?><br>
                                                <?php endif; ?>
                                            </div>
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>Producto</th>
                                                        <th>Cantidad</th>
                                                        <th>Precio Unitario</th>
                                                        <th>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($venta['detalles'] as $detalle): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($detalle['nombre']); ?></td>
                                                            <td><?php echo $detalle['cantidad']; ?></td>
                                                            <td>$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                                            <td>$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No hay ventas registradas en esta semana (<?php echo $fecha_lunes->format('d/m/Y'); ?> - <?php echo $fecha_sabado->format('d/m/Y'); ?>)</p>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        function toggleDetalles(idx) {
            const detallesDiv = document.getElementById('detalles-' + idx);
            if (detallesDiv.style.display === 'none' || detallesDiv.style.display === '') {
                detallesDiv.style.display = 'block';
            } else {
                detallesDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>
