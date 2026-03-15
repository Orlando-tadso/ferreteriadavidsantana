<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Producto.php';

$producto_obj = new Producto($conn);
$bajo_stock = $producto_obj->obtenerBajoStock();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Bajo Stock - Ferretería</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container">
        <?php require_once __DIR__ . '/../core/menu.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1><img src="assets/icons/bajo_stock.png" alt="Bajo Stock" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Productos en Bajo Stock</h1>
                <p>Productos que necesitan restock: <?php echo count($bajo_stock); ?></p>
            </header>

            <section class="card">
                <?php if (count($bajo_stock) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Cantidad Actual</th>
                                <th>Cantidad Mínima</th>
                                <th>Diferencia</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bajo_stock as $prod):
                                $diferencia = $prod['cantidad_minima'] - $prod['cantidad'];
                            ?>
                                <tr class="alert-row">
                                    <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                    <td><?php echo $prod['categoria']; ?></td>
                                    <td><?php echo $prod['cantidad']; ?></td>
                                    <td><?php echo $prod['cantidad_minima']; ?></td>
                                    <td class="alert-value">-<?php echo $diferencia; ?></td>
                                    <td>
                                        <?php if (esAdmin()): ?>
                                            <a href="editar_producto.php?id=<?php echo $prod['id']; ?>" class="btn-small">✏️ Editar</a>
                                        <?php else: ?>
                                            <span class="badge">Solo lectura</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-success">
                        ✓ Todos los productos tienen suficiente stock
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="assets/js/check-updates.js"></script>
</body>
</html>
