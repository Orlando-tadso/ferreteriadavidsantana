<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Venta.php';

$venta = new Venta($conn);
$todos_productos = [];

// Obtener todos los productos para el simulador
$sql = "SELECT id, nombre, precio_unitario, cantidad, codigo_barras FROM productos WHERE codigo_barras IS NOT NULL";
$result = $conn->query($sql);
if ($result) {
    $todos_productos = $result->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulador de POS - Ferretería</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .simulador-container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .productos-simulador {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .producto-card {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .producto-card:hover {
            background-color: #f0f0f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .producto-card.sin-codigo {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .codigo-barras {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
            font-family: monospace;
            padding: 5px;
            background: #f5f5f5;
            border-radius: 3px;
        }

        .precio-producto {
            font-size: 18px;
            font-weight: bold;
            color: #27ae60;
            margin-top: 8px;
        }

        .stock-producto {
            font-size: 12px;
            color: #e74c3c;
            margin-top: 5px;
        }

        .instrucciones {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }

        .btn-escanear {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-escanear:hover {
            background: #2980b9;
        }

        .btn-escanear:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }

        .notificacion {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            display: none;
            animation: slideIn 0.3s ease-out;
            z-index: 1000;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .notificacion.show {
            display: block;
        }

        .sin-productos {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo">
                <h2>👨‍🔧 Ferretería</h2>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link"><img src="assets/icons/dasboard.png" alt="Dashboard" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Dashboard</a>
                <a href="productos.php" class="nav-link"><img src="assets/icons/producto.png" alt="Productos" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Productos</a>
                <a href="agregar_producto.php" class="nav-link">➕ Agregar Producto</a>
                <a href="punto_venta.php" class="nav-link"><img src="assets/icons/venta.png" alt="Punto de Venta" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Punto de Venta</a>
                <a href="simulador_pos.php" class="nav-link active">🎮 Simulador POS</a>
                <a href="movimientos.php" class="nav-link"><img src="assets/icons/movimientos.png" alt="Movimientos" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Movimientos</a>
                <a href="historial_ventas.php" class="nav-link"><img src="assets/icons/historial-ventas.png" alt="Historial" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Historial Ventas</a>
                <a href="bajo_stock.php" class="nav-link"><img src="assets/icons/bajo_stock.png" alt="Bajo Stock" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Bajo Stock</a>
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
                <?php if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin'): ?>
                    <a href="crear_usuario.php" class="nav-link"><img src="assets/icons/crear_usuario.png" alt="Crear usuario" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Crear Usuario</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-link" style="color: #e74c3c;"><img src="assets/icons/cerrar_sesion.png" alt="Cerrar sesión" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Cerrar Sesión</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <h1>🎮 Simulador de Punto de Venta</h1>
                <p>Para pruebas sin lectora física de códigos de barras</p>
            </header>

            <div class="simulador-container">
                <div class="instrucciones">
                    <strong>ℹ️ Instrucciones:</strong>
                    <p>Haz clic en cualquier producto para simular un escaneo de código de barras. El producto se agregará automáticamente al punto de venta.</p>
                    <p>Solo se muestran productos con código de barras asignado.</p>
                </div>

                <?php if (count($todos_productos) > 0): ?>
                    <h2>Productos Disponibles</h2>
                    <div class="productos-simulador">
                        <?php foreach ($todos_productos as $prod): ?>
                            <div class="producto-card" onclick="escanearProducto('<?php echo htmlspecialchars($prod['codigo_barras']); ?>')">
                                <strong><?php echo htmlspecialchars($prod['nombre']); ?></strong>
                                <div class="precio-producto">$<?php echo number_format($prod['precio_unitario'], 2); ?></div>
                                <div class="stock-producto">
                                    Stock: <?php echo $prod['cantidad']; ?> unidades
                                </div>
                                <div class="codigo-barras">
                                    🔷 <?php echo htmlspecialchars($prod['codigo_barras']); ?>
                                </div>
                                <button class="btn-escanear">👆 Simular Escaneo</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p style="margin-top: 20px; text-align: center; color: #666;">
                        <strong><?php echo count($todos_productos); ?> productos disponibles para escanear</strong>
                    </p>
                <?php else: ?>
                    <div class="sin-productos">
                        <h3>❌ No hay productos con código de barras</h3>
                        <p>Primero debes:</p>
                        <ol>
                            <li>Ir a "Agregar Producto" o editar un producto existente</li>
                            <li>Agregar un "Código de Barras" a cada producto</li>
                            <li>O usa la opción "Agregar Productos de Ejemplo"</li>
                        </ol>
                        <a href="agregar_productos_demo.php?confirmar=si" class="btn btn-primary" style="display: inline-block; margin-top: 10px;">
                            ➕ Agregar Productos de Ejemplo
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="notificacion" id="notificacion"></div>

    <script>
        function escanearProducto(codigo) {
            // Abrir el punto de venta en una nueva pestaña/ventana con el código
            const ventana = window.open('punto_venta.php', 'punto_venta', 'width=1200,height=800');
            
            // Mostrar notificación
            const notif = document.getElementById('notificacion');
            notif.innerHTML = '✓ Simulando escaneo de: <strong>' + codigo + '</strong>';
            notif.classList.add('show');
            
            setTimeout(() => {
                notif.classList.remove('show');
            }, 3000);

            // Después de un pequeño delay, enviar el código a la ventana
            setTimeout(() => {
                if (ventana && !ventana.closed) {
                    try {
                        // Enviar mensaje a la ventana del punto de venta
                        const evento = new CustomEvent('codigoEscaneado', { detail: { codigo: codigo } });
                        ventana.document.dispatchEvent(evento);
                    } catch (e) {
                        // Si no funciona por CORS, al menos enfocamos la ventana
                        ventana.focus();
                    }
                }
            }, 500);
        }
    </script>
</body>
</html>
