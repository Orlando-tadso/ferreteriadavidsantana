<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';

// Solo los administradores pueden ejecutar este script
if (!esAdmin()) {
    die("Acceso denegado. Solo administradores pueden limpiar el historial.");
}

$mensaje = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        // Iniciar transacción
        $conn->begin_transaction();
        
        // Limpiar todas las tablas de historial
        $conn->query("DELETE FROM detalles_venta");
        $conn->query("DELETE FROM ventas");
        $conn->query("DELETE FROM movimientos");
        
        // Commit de la transacción
        $conn->commit();
        
        $mensaje = "✅ Historial limpiado exitosamente. Todas las ventas y movimientos han sido eliminados.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "❌ Error al limpiar el historial: " . $e->getMessage();
    }
}

// Obtener conteo de registros
$count_ventas = $conn->query("SELECT COUNT(*) as total FROM ventas")->fetch_assoc()['total'];
$count_detalles = $conn->query("SELECT COUNT(*) as total FROM detalles_venta")->fetch_assoc()['total'];
$count_movimientos = $conn->query("SELECT COUNT(*) as total FROM movimientos")->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiar Historial - Ferretería</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .warning-box h3 {
            color: #856404;
            margin-top: 0;
        }
        
        .stats-box {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .stats-item {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 4px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .success-message {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .error-message {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/../core/menu.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1>🗑️ Limpiar Historial del Sistema</h1>
                <p>Eliminar todos los registros de ventas y movimientos</p>
            </header>

            <?php if ($mensaje): ?>
                <div class="success-message">
                    <strong><?php echo $mensaje; ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <strong><?php echo $error; ?></strong>
                </div>
            <?php endif; ?>

            <section class="card">
                <div class="stats-box">
                    <h3><img src="assets/icons/dasboard.png" alt="Registros" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Registros Actuales:</h3>
                    <div class="stats-item">
                        <strong>Ventas:</strong> <?php echo $count_ventas; ?> registros
                    </div>
                    <div class="stats-item">
                        <strong>Detalles de Ventas:</strong> <?php echo $count_detalles; ?> registros
                    </div>
                    <div class="stats-item">
                        <strong>Movimientos de Inventario:</strong> <?php echo $count_movimientos; ?> registros
                    </div>
                </div>

                <div class="warning-box">
                    <h3>⚠️ ADVERTENCIA</h3>
                    <p><strong>Esta acción NO se puede deshacer.</strong></p>
                    <p>Se eliminarán permanentemente:</p>
                    <ul>
                        <li>Todas las ventas registradas</li>
                        <li>Todos los detalles de ventas</li>
                        <li>Todo el historial de movimientos de inventario</li>
                    </ul>
                    <p>Los productos actuales NO se eliminarán, solo su historial.</p>
                </div>

                <form method="POST" onsubmit="return confirm('¿Estás COMPLETAMENTE SEGURO de eliminar TODO el historial? Esta acción NO se puede deshacer.');">
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="confirmar" value="1" class="btn-danger">
                            🗑️ ELIMINAR TODO EL HISTORIAL
                        </button>
                        <a href="dashboard.php" class="btn-secondary">Cancelar</a>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
