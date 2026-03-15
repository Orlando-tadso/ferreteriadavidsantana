<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Devolucion.php';

requerirAdmin();

$devolucion = new Devolucion($conn);
$usuario_id = $_SESSION['usuario_id'];

$mensaje = '';
$tipo_mensaje = '';
$venta_encontrada = null;
$detalles_venta = [];

// Manejar AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'buscar_venta') {
        $numero_factura = $_POST['numero_factura'] ?? '';
        
        if (empty($numero_factura)) {
            echo json_encode([
                'success' => false,
                'message' => 'Debe ingresar un número de factura'
            ]);
            exit;
        }
        
        $venta = $devolucion->buscarVentaPorFactura($numero_factura);
        
        if (!$venta) {
            echo json_encode([
                'success' => false,
                'message' => 'Venta no encontrada'
            ]);
            exit;
        }
        
        $detalles = $devolucion->obtenerDetallesVenta($venta['id']);
        
        if (empty($detalles)) {
            echo json_encode([
                'success' => false,
                'message' => 'No hay productos disponibles para devolver en esta venta'
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'venta' => $venta,
            'detalles' => $detalles
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'procesar_devolucion') {
        $venta_id = $_POST['venta_id'] ?? 0;
        $motivo = $_POST['motivo'] ?? '';
        $productos_json = $_POST['productos'] ?? '[]';
        
        $productos = json_decode($productos_json, true);
        
        if (empty($venta_id) || empty($motivo) || empty($productos)) {
            echo json_encode([
                'success' => false,
                'message' => 'Faltan datos requeridos'
            ]);
            exit;
        }
        
        $resultado = $devolucion->registrarDevolucion($venta_id, $productos, $motivo, $usuario_id);
        echo json_encode($resultado);
        exit;
    }
}

// Obtener historial de devoluciones
$historial = $devolucion->obtenerHistorialDevoluciones(100);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Devoluciones - Sistema Ferretería</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .devolucion-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .formulario-busqueda {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .formulario-busqueda h3 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .form-group {
            margin: 15px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #229954;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        
        .info-item label {
            font-weight: bold;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-item span {
            display: block;
            font-size: 16px;
            color: #2c3e50;
            font-weight: bold;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #4caf50;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f44336;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #2196F3;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            margin-top: 20px;
            font-size: 14px;
        }
        
        .table th {
            background-color: #3498db;
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: bold;
            border-right: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
        }
        
        .table th:last-child {
            border-right: none;
        }
        
        .table td {
            padding: 15px 18px;
            border-bottom: 1px solid #e8e8e8;
            border-right: 1px solid #f0f0f0;
        }
        
        .table td:last-child {
            border-right: none;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .table strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .table small {
            color: #7f8c8d;
            font-size: 12px;
        }
        
        .checkbox-devolver {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .cantidad-input {
            width: 70px;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        
        .cantidad-input:disabled {
            background-color: #eee;
            cursor: not-allowed;
        }
        
        .motivo-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .motivo-section label {
            display: block;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .motivo-section textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            resize: vertical;
            box-sizing: border-box;
        }
        
        .motivo-section textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        .total-devolucion {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: right;
            font-size: 24px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .acciones-devolucion {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        
        .venta-info {
            display: none;
        }
        
        .venta-info.show {
            display: block;
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
                <h1><img src="assets/icons/devoluciones.png" alt="Devoluciones" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Gestionar Devoluciones</h1>
                <p>Procesar devoluciones de productos. El inventario se ajustará automáticamente.</p>
            </header>

            <div id="mensaje-container"></div>

            <section class="card">
                <div class="devolucion-container">
                    <!-- Buscar Venta -->
                    <div class="formulario-busqueda">
                        <h3><img src="assets/icons/lupa.png" alt="Buscar" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Buscar Venta</h3>
                        <div class="form-group">
                            <label for="numero_factura">Número de Factura</label>
                            <input 
                                type="text" 
                                id="numero_factura" 
                                placeholder="FAC-20260226140813-9204"
                                autocomplete="off"
                            >
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="buscarVenta()">Buscar</button>
                            <button class="btn btn-secondary" onclick="cancelarDevolucion()">Limpiar</button>
                        </div>
                    </div>
                </div>

                <!-- Información de la Venta -->
                <div class="venta-info" id="venta-info">
                    <h3 style="margin-top: 0; color: #2c3e50;">📄 Información de la Venta</h3>
                    <div class="info-grid" id="info-grid">
                        <!-- Se llenará dinámicamente -->
                    </div>

                    <h4 style="margin: 20px 0 10px 0; color: #2c3e50;">Productos en la Venta</h4>
                    <p style="color: #666; font-size: 14px; margin: 0 0 15px 0;">Seleccione los productos a devolver e indique la cantidad</p>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Devolver</th>
                                <th>Producto</th>
                                <th>Vendida</th>
                                <th>Devuelto</th>
                                <th>Disponible</th>
                                <th>Cantidad a Devolver</th>
                                <th>Precio Unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="productos-tbody">
                            <!-- Se llenará dinámicamente -->
                        </tbody>
                    </table>
                    
                    <div class="total-devolucion" id="total-devolucion">
                        Total a Devolver: $0.00
                    </div>
                    
                    <div class="motivo-section">
                        <label for="motivo">Motivo de la Devolución *</label>
                        <textarea 
                            id="motivo" 
                            placeholder="Describa el motivo de la devolución (ej: producto equivocado, defectuoso, etc.)"
                            required
                        ></textarea>
                    </div>
                    
                    <div class="acciones-devolucion">
                        <button class="btn btn-secondary" onclick="cancelarDevolucion()">Cancelar</button>
                        <button class="btn btn-success" onclick="procesarDevolucion()">✓ Procesar Devolución</button>
                    </div>
                </div>
                
                <!-- Historial de Devoluciones -->
                <h3 style="margin-top: 40px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;"><img src="assets/icons/devoluciones.png" alt="Devoluciones" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Historial de Devoluciones</h3>
            <?php if (empty($historial)): ?>
                <p style="color: #666; margin-top: 20px;">No hay devoluciones registradas</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Nº Devolución</th>
                            <th style="width: 12%;">Fecha</th>
                            <th style="width: 12%;">Nº Factura</th>
                            <th style="width: 18%;">Cliente</th>
                            <th style="width: 22%;">Motivo</th>
                            <th style="width: 12%; text-align: right;">Total Devuelto</th>
                            <th style="width: 12%;">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $dev): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dev['numero_devolucion']); ?></strong></td>
                                <td><?php echo date('d/m/Y<br>H:i', strtotime($dev['fecha_devolucion'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($dev['numero_factura']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($dev['cliente_nombre']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($dev['cliente_cedula']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars(substr($dev['motivo'], 0, 60)) . (strlen($dev['motivo']) > 60 ? '...' : ''); ?></td>
                                <td style="text-align: right; color: #27ae60; font-weight: bold;">$<?php echo number_format($dev['total_devuelto'], 2); ?></td>
                                <td><?php echo htmlspecialchars($dev['usuario']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        let ventaActual = null;
        let detallesActuales = [];
        
        function buscarVenta() {
            const numero_factura = document.getElementById('numero_factura').value.trim();
            
            if (!numero_factura) {
                mostrarMensaje('Debe ingresar un número de factura', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'buscar_venta');
            formData.append('numero_factura', numero_factura);
            
            fetch('gestionar_devoluciones.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    ventaActual = data.venta;
                    detallesActuales = data.detalles;
                    mostrarInformacionVenta();
                } else {
                    mostrarMensaje(data.message || 'Error al buscar la venta', 'error');
                    ocultarInformacionVenta();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('Error al procesar la solicitud', 'error');
            });
        }
        
        function mostrarInformacionVenta() {
            // Mostrar información general
            const infoGrid = document.getElementById('info-grid');
            const totalDevuelto = parseFloat(ventaActual.total_devuelto || 0);
            const numDevoluciones = parseInt(ventaActual.num_devoluciones || 0);
            
            infoGrid.innerHTML = `
                <div class="info-item">
                    <label>Número de Factura:</label>
                    <span>${ventaActual.numero_factura}</span>
                </div>
                <div class="info-item">
                    <label>Cliente:</label>
                    <span>${ventaActual.cliente_nombre}</span>
                </div>
                <div class="info-item">
                    <label>Cédula:</label>
                    <span>${ventaActual.cliente_cedula}</span>
                </div>
                <div class="info-item">
                    <label>Fecha Venta:</label>
                    <span>${formatearFecha(ventaActual.fecha_venta)}</span>
                </div>
                <div class="info-item">
                    <label>Total Venta:</label>
                    <span style="font-weight: bold; color: #2c3e50;">$${parseFloat(ventaActual.total).toFixed(2)}</span>
                </div>
                ${numDevoluciones > 0 ? `
                <div class="info-item">
                    <label>Devoluciones Previas:</label>
                    <span class="badge badge-warning">${numDevoluciones} - $${totalDevuelto.toFixed(2)}</span>
                </div>
                ` : ''}
            `;
            
            // Mostrar productos
            const tbody = document.getElementById('productos-tbody');
            tbody.innerHTML = '';
            
            detallesActuales.forEach((detalle, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align: center;">
                        <input 
                            type="checkbox" 
                            class="checkbox-devolver" 
                            id="check-${index}"
                            onchange="toggleProducto(${index})"
                        >
                    </td>
                    <td>${detalle.producto_nombre}</td>
                    <td>${detalle.cantidad_vendida}</td>
                    <td>${detalle.cantidad_ya_devuelta}</td>
                    <td style="font-weight: bold;">${detalle.cantidad_disponible_devolver}</td>
                    <td>
                        <input 
                            type="number" 
                            class="cantidad-input" 
                            id="cantidad-${index}"
                            min="1"
                            max="${detalle.cantidad_disponible_devolver}"
                            value="1"
                            disabled
                            onchange="calcularTotal()"
                        >
                    </td>
                    <td>$${parseFloat(detalle.precio_unitario).toFixed(2)}</td>
                    <td id="subtotal-${index}">$0.00</td>
                `;
                tbody.appendChild(tr);
            });
            
            document.getElementById('venta-info').classList.add('show');
            document.getElementById('motivo').value = '';
            calcularTotal();
        }
        
        function ocultarInformacionVenta() {
            document.getElementById('venta-info').classList.remove('show');
            ventaActual = null;
            detallesActuales = [];
        }
        
        function toggleProducto(index) {
            const checkbox = document.getElementById(`check-${index}`);
            const input = document.getElementById(`cantidad-${index}`);
            input.disabled = !checkbox.checked;
            
            if (!checkbox.checked) {
                input.value = 1;
            }
            
            calcularTotal();
        }
        
        function calcularTotal() {
            let total = 0;
            
            detallesActuales.forEach((detalle, index) => {
                const checkbox = document.getElementById(`check-${index}`);
                const input = document.getElementById(`cantidad-${index}`);
                const subtotalElement = document.getElementById(`subtotal-${index}`);
                
                if (checkbox.checked) {
                    const cantidad = parseInt(input.value) || 0;
                    const subtotal = cantidad * parseFloat(detalle.precio_unitario);
                    subtotalElement.textContent = `$${subtotal.toFixed(2)}`;
                    total += subtotal;
                } else {
                    subtotalElement.textContent = '$0.00';
                }
            });
            
            document.getElementById('total-devolucion').textContent = 
                `Total a Devolver: $${total.toFixed(2)}`;
        }
        
        function procesarDevolucion() {
            const motivo = document.getElementById('motivo').value.trim();
            
            if (!motivo) {
                mostrarMensaje('Debe especificar el motivo de la devolución', 'error');
                return;
            }
            
            // Recolectar productos a devolver
            const productos = [];
            detallesActuales.forEach((detalle, index) => {
                const checkbox = document.getElementById(`check-${index}`);
                const input = document.getElementById(`cantidad-${index}`);
                
                if (checkbox.checked) {
                    const cantidad = parseInt(input.value) || 0;
                    if (cantidad > 0 && cantidad <= detalle.cantidad_disponible_devolver) {
                        productos.push({
                            detalle_venta_id: detalle.detalle_id,
                            producto_id: detalle.producto_id,
                            precio_unitario: detalle.precio_unitario,
                            cantidad_devolver: cantidad
                        });
                    }
                }
            });
            
            if (productos.length === 0) {
                mostrarMensaje('Debe seleccionar al menos un producto para devolver', 'error');
                return;
            }
            
            if (!confirm('¿Está seguro de procesar esta devolución? Esta acción ajustará el inventario y no se puede deshacer.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'procesar_devolucion');
            formData.append('venta_id', ventaActual.id);
            formData.append('motivo', motivo);
            formData.append('productos', JSON.stringify(productos));
            
            fetch('gestionar_devoluciones.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje(
                        `Devolución procesada exitosamente. Número: ${data.numero_devolucion}. Total: $${data.total_devuelto.toFixed(2)}`,
                        'success'
                    );
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    mostrarMensaje(data.error || 'Error al procesar la devolución', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('Error al procesar la solicitud', 'error');
            });
        }
        
        function cancelarDevolucion() {
            if (confirm('¿Desea cancelar esta devolución?')) {
                ocultarInformacionVenta();
                document.getElementById('numero_factura').value = '';
            }
        }
        
        function mostrarMensaje(mensaje, tipo) {
            const container = document.getElementById('mensaje-container');
            const clase = tipo === 'success' ? 'alert-success' : 
                         tipo === 'error' ? 'alert-error' : 'alert-info';
            
            container.innerHTML = `<div class="alert ${clase}">${mensaje}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }
        
        function formatearFecha(fecha) {
            const date = new Date(fecha);
            return date.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Permitir buscar con Enter
        document.getElementById('numero_factura').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                buscarVenta();
            }
        });
    </script>
</body>
</html>
