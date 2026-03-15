<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Venta.php';

requerirAdmin();

$venta = new Venta($conn);
$usuario_id = $_SESSION['usuario_id'];

// Manejar AJAX para agregar producto por código de barras
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'buscar_producto') {
        $codigo = $_POST['codigo'] ?? '';
        $producto = $venta->obtenerPorCodigoBarras($codigo);
        
        if ($producto && $producto['cantidad'] > 0) {
            echo json_encode([
                'success' => true,
                'producto' => $producto
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Producto no encontrado o sin stock disponible'
            ]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'procesar_venta') {
        $cliente_nombre = $_POST['cliente_nombre'] ?? '';
        $cliente_cedula = $_POST['cliente_cedula'] ?? '';
        $cliente_email = $_POST['cliente_email'] ?? '';
        $cliente_telefono = $_POST['cliente_telefono'] ?? '';
        $productos_json = $_POST['productos'] ?? '[]';
        
        if (empty($cliente_nombre) || empty($cliente_cedula)) {
            echo json_encode([
                'success' => false,
                'message' => 'Nombre y cédula son requeridos'
            ]);
            exit;
        }
        
        $productos = json_decode($productos_json, true);
        if (empty($productos)) {
            echo json_encode([
                'success' => false,
                'message' => 'No hay productos en la venta'
            ]);
            exit;
        }
        
        // Validar que hay stock disponible
        foreach ($productos as $prod) {
            $producto = $venta->obtenerProductoPorId($prod['producto_id']);
            if (!$producto || $producto['cantidad'] < $prod['cantidad']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Stock insuficiente para ' . $prod['nombre']
                ]);
                exit;
            }
        }
        
        $resultado = $venta->registrarVenta($cliente_nombre, $cliente_cedula, $productos, $usuario_id, $cliente_email, $cliente_telefono);
        echo json_encode($resultado);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Ferretería</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .pdv-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: 100vh;
        }
        
        .pdv-section {
            overflow-y: auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .pdv-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #3498db;
            border-radius: 5px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .pdv-input:focus {
            outline: none;
            border-color: #2980b9;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }
        
        .productos-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .productos-tabla th {
            background-color: #34495e;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        
        .productos-tabla td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .productos-tabla tr:hover {
            background-color: #f5f5f5;
        }
        
        .btn-eliminar {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-eliminar:hover {
            background-color: #c0392b;
        }
        
        .resumen-venta {
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .resumen-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .resumen-total {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            border-top: 2px solid #34495e;
            padding-top: 10px;
        }
        
        .btn-procesar {
            width: 100%;
            padding: 15px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-procesar:hover {
            background-color: #229954;
        }
        
        .btn-procesar:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }
        
        .btn-limpiar {
            width: 100%;
            padding: 10px;
            background-color: #95a5a6;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .btn-limpiar:hover {
            background-color: #7f8c8d;
        }
        
        .mensaje-exito {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
        }
        
        .mensaje-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }
        
        .cliente-datos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .cliente-datos input {
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
        }
        
        .cantidad-input {
            width: 60px;
            text-align: center;
        }
        
        .print-section {
            display: none;
        }
        
        @media print {
            * {
                margin: 0 !important;
                padding: 0 !important;
                background: transparent !important;
            }
            
            html, body {
                width: 80mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            
            .pdv-container {
                display: block !important;
                grid-template-columns: none !important;
                height: auto !important;
            }
            
            .pdv-section {
                display: none !important;
                border: none !important;
                padding: 0 !important;
            }
            
            .pdv-section.no-print {
                display: none !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            #seccionFactura {
                display: block !important;
            }
            
            .factura-container {
                width: 80mm !important;
                max-width: 80mm !important;
                margin: 0 !important;
                padding: 2px !important;
                font-family: monospace !important;
                font-size: 12px !important;
                line-height: 1.1 !important;
                background: white !important;
                border: none !important;
            }
            
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-bottom: 2px !important;
            }
            
            table td, table th {
                padding: 1px 2px !important;
                border: none !important;
                font-size: 9px !important;
                background: white !important;
            }
            
            .factura-empresa {
                text-align: center !important;
                padding: 2px 0 !important;
                margin: 0 !important;
                border: none !important;
            }
        }
        
        .factura-container {
            font-family: 'Courier New', 'Courier', monospace;
            max-width: 80mm;
            margin: 0 auto;
            padding: 2px;
            background: white;
            line-height: 1.1;
            width: 80mm;
        }

        .factura-container p {
            margin: 0;
            font-size: 11px;
        }

        .factura-container h2 {
            margin: 0;
            text-align: center;
            font-size: 14px;
        }

        .factura-empresa {
            text-align: center;
            margin-bottom: 3px;
            border-bottom: 1px solid #333;
            padding-bottom: 3px;
        }

        .factura-info {
            font-size: 10px;
            margin-bottom: 3px;
        }

        .factura-cliente {
            font-size: 10px;
            margin-bottom: 3px;
        }

        .factura-footer {
            text-align: center;
            font-size: 9px;
            border-top: 1px solid #333;
            padding-top: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php require_once __DIR__ . '/../core/menu.php'; ?>

        <main class="main-content">
            <div class="header">
                <h1><img src="assets/icons/venta.png" alt="Punto de Venta" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Punto de Venta</h1>
                <p>Sistema de venta y facturación</p>
            </div>

            <div class="pdv-container">
                <!-- Sección izquierda: Entrada de productos -->
                <div class="pdv-section no-print">
                    <h3>Escanear Productos</h3>
                    <input 
                        type="text" 
                        id="inputCodigoBarras" 
                        class="pdv-input" 
                        placeholder="Escanea código de barras aquí..." 
                        autofocus
                    >
                    
                    <h3>Productos en la Venta</h3>
                    <table class="productos-tabla" id="tablaProductos">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Subtotal</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                        </tbody>
                    </table>
                </div>

                <!-- Sección derecha: Resumen y cliente -->
                <div class="pdv-section">
                    <h3>Datos del Cliente</h3>
                    <div class="cliente-datos">
                        <input 
                            type="text" 
                            id="clienteNombre" 
                            placeholder="Nombre del cliente *"
                        >
                        <input 
                            type="text" 
                            id="clienteCedula" 
                            placeholder="Cédula del cliente *"
                        >
                        <input 
                            type="email" 
                            id="clienteEmail" 
                            placeholder="Correo electrónico (opcional)"
                        >
                        <input 
                            type="tel" 
                            id="clienteTelefono" 
                            placeholder="Teléfono/Celular (opcional)"
                        >
                    </div>

                    <div class="resumen-venta">
                        <h4>Resumen de Venta</h4>
                        <div class="resumen-item">
                            <span>Subtotal:</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="resumen-item">
                            <span>Total Productos:</span>
                            <span id="totalProductos">0</span>
                        </div>
                        <div class="resumen-total">
                            Total: <span id="totalPrecio">$0.00</span>
                        </div>
                    </div>

                    <button class="btn-procesar no-print" onclick="procesarVenta()">
                        <img src="assets/icons/proceso.png" alt="Procesar" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Procesar Venta y Generar Factura
                    </button>
                    <button class="btn-limpiar no-print" onclick="limpiarVenta()">
                        <img src="assets/icons/limpiar.png" alt="Limpiar" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Limpiar
                    </button>

                    <!-- Sección de factura (oculta hasta procesar) -->
                    <div id="seccionFactura" style="display: none;">
                        <div class="factura-container" id="factura">
                        </div>
                        <button class="btn-procesar no-print" onclick="imprimirFactura()" style="margin-top: 20px;">
                            <img src="assets/icons/impresora.png" alt="Imprimir" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Imprimir Factura
                        </button>
                        <button class="btn-limpiar no-print" onclick="nuevaVenta()">
                            <img src="assets/icons/nueva_venta.png" alt="Nueva venta" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Nueva Venta
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let productosEnVenta = [];

        function formatCurrency(valor) {
            const numero = Number(valor) || 0;
            return new Intl.NumberFormat('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(numero);
        }

        // Event listener para código de barras
        document.getElementById('inputCodigoBarras').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarProducto(this.value);
                this.value = '';
            }
        });

        function buscarProducto(codigo) {
            if (!codigo.trim()) return;

            fetch('punto_venta.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=buscar_producto&codigo=' + encodeURIComponent(codigo)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    agregarProducto(data.producto);
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al buscar el producto');
            });
        }

        function agregarProducto(producto) {
            // Buscar si el producto ya está en la venta
            let productoExistente = productosEnVenta.find(p => p.producto_id === producto.id);

            const precioUnitario = Number(producto.precio_unitario) || 0;

            if (productoExistente) {
                // Aumentar cantidad
                if (productoExistente.cantidad < producto.cantidad) {
                    productoExistente.cantidad++;
                    productoExistente.subtotal = productoExistente.cantidad * productoExistente.precio_unitario;
                } else {
                    alert('⚠️ No hay más stock disponible de este producto');
                }
            } else {
                // Agregar nuevo producto
                productosEnVenta.push({
                    producto_id: producto.id,
                    nombre: producto.nombre,
                    cantidad: 1,
                    precio_unitario: precioUnitario,
                    subtotal: precioUnitario
                });
            }

            actualizarTabla();
        }

        function cambiarCantidad(indice, cantidad) {
            cantidad = parseInt(cantidad) || 0;
            if (cantidad <= 0) {
                eliminarProducto(indice);
                return;
            }

            productosEnVenta[indice].cantidad = cantidad;
            productosEnVenta[indice].subtotal = cantidad * (Number(productosEnVenta[indice].precio_unitario) || 0);
            actualizarTabla();
        }

        function eliminarProducto(indice) {
            productosEnVenta.splice(indice, 1);
            actualizarTabla();
        }

        function actualizarTabla() {
            let tbody = document.getElementById('productosBody');
            tbody.innerHTML = '';

            let total = 0;
            let totalProductos = 0;

            productosEnVenta.forEach((producto, indice) => {
                let fila = `
                    <tr>
                        <td>${producto.nombre}</td>
                        <td>
                            <input 
                                type="number" 
                                class="cantidad-input"
                                value="${producto.cantidad}"
                                min="1"
                                onchange="cambiarCantidad(${indice}, this.value)"
                            >
                        </td>
                        <td>$${formatCurrency(producto.precio_unitario)}</td>
                        <td>$${formatCurrency(producto.subtotal)}</td>
                        <td>
                            <button class="btn-eliminar" onclick="eliminarProducto(${indice})">
                                ✕
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += fila;

                total += Number(producto.subtotal) || 0;
                totalProductos += Number(producto.cantidad) || 0;
            });

            document.getElementById('subtotal').textContent = '$' + formatCurrency(total);
            document.getElementById('totalProductos').textContent = totalProductos;
            document.getElementById('totalPrecio').textContent = '$' + formatCurrency(total);
        }

        function calcularTotal() {
            return productosEnVenta.reduce((acc, item) => acc + (Number(item.subtotal) || 0), 0);
        }

        function procesarVenta() {
            let clienteNombre = document.getElementById('clienteNombre').value.trim();
            let clienteCedula = document.getElementById('clienteCedula').value.trim();
            let clienteEmail = document.getElementById('clienteEmail').value.trim();
            let clienteTelefono = document.getElementById('clienteTelefono').value.trim();

            if (!clienteNombre) {
                alert('⚠️ Por favor ingrese el nombre del cliente');
                return;
            }

            if (!clienteCedula) {
                alert('⚠️ Por favor ingrese la cédula del cliente');
                return;
            }

            if (productosEnVenta.length === 0) {
                alert('⚠️ La venta debe tener al menos un producto');
                return;
            }

            // Mostrar confirmación
            let total = calcularTotal();
            if (!confirm(`¿Confirmar venta de $${formatCurrency(total)}?`)) {
                return;
            }

            // Enviar al servidor
            fetch('punto_venta.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=procesar_venta&cliente_nombre=' + encodeURIComponent(clienteNombre) + 
                      '&cliente_cedula=' + encodeURIComponent(clienteCedula) +
                      '&cliente_email=' + encodeURIComponent(clienteEmail) +
                      '&cliente_telefono=' + encodeURIComponent(clienteTelefono) +
                      '&productos=' + encodeURIComponent(JSON.stringify(productosEnVenta))
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    generarFactura(data);
                    productosEnVenta = [];
                } else {
                    alert('❌ Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la venta');
            });
        }

        function generarFactura(data) {
            let clienteNombre = document.getElementById('clienteNombre').value;
            let clienteCedula = document.getElementById('clienteCedula').value;
            let clienteEmail = document.getElementById('clienteEmail').value;
            let clienteTelefono = document.getElementById('clienteTelefono').value;
            let total = calcularTotal();
            let fecha = new Date();
            let fechaFormato = fecha.toLocaleDateString('es-ES') + ' ' + fecha.toLocaleTimeString('es-ES');

            let subtotal = 0;
            productosEnVenta.forEach(p => subtotal += Number(p.subtotal) || 0);
            let impuesto = subtotal * 0.16; // Impuesto del 16%

            // HTML SIMPLIFICADO PARA IMPRESORAS TÉRMICAS
            let html = `
<div class="factura-container" data-numero-factura="${data.numero_factura}" style="font-family: monospace; width: 80mm; margin: 0; padding: 0; font-size: 12px; line-height: 1.2;">

<table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 5px;">
<tr><td style="text-align: center; font-weight: bold; font-size: 14px;">FERRETERIA FERROCAMPO PINILLOS</td></tr>
<tr><td style="text-align: center; font-size: 10px;">NIT: 9.166.294-4</td></tr>
<tr><td style="text-align: center; font-size: 10px;">Tel: 3001232763</td></tr>
<tr><td style="text-align: center; font-size: 10px;">Dir: Calle 11 A Sector Avenida</td></tr>
<tr><td style="text-align: center; font-size: 10px; border-bottom: 1px solid #000; padding-bottom: 3px;">&nbsp;</td></tr>
</table>

<table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 3px;">
<tr><td style="font-size: 10px;"><strong>FACTURA:</strong> ${data.numero_factura}</td></tr>
<tr><td style="font-size: 9px;"><strong>FECHA:</strong> ${fechaFormato}</td></tr>
</table>

<table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 3px; border-bottom: 1px solid #000; padding-bottom: 3px;">
<tr><td style="font-size: 10px;"><strong>CLIENTE:</strong> ${clienteNombre}</td></tr>
<tr><td style="font-size: 10px;"><strong>CEDULA:</strong> ${clienteCedula}</td></tr>
${clienteEmail ? '<tr><td style="font-size: 9px;"><strong>EMAIL:</strong> ' + clienteEmail + '</td></tr>' : ''}
${clienteTelefono ? '<tr><td style="font-size: 9px;"><strong>TEL:</strong> ' + clienteTelefono + '</td></tr>' : ''}
</table>

<table width="100%" border="0" cellpadding="2" cellspacing="0" style="margin-bottom: 3px; font-size: 9px;">
<tr style="border-bottom: 1px solid #000;">
<td style="text-align: left;"><strong>PRODUCTO</strong></td>
<td style="text-align: center; width: 30px;"><strong>CAN</strong></td>
<td style="text-align: right; width: 50px;"><strong>PRECIO</strong></td>
<td style="text-align: right; width: 50px;"><strong>TOTAL</strong></td>
</tr>
`;

            // ITEMS
            productosEnVenta.forEach(producto => {
                let punitario = (Number(producto.subtotal) || 0) / (Number(producto.cantidad) || 1);
                let nombreCorto = producto.nombre.substring(0, 25);
                html += `<tr style="font-size: 9px;">
<td style="text-align: left;">${nombreCorto}</td>
<td style="text-align: center; width: 30px;">${producto.cantidad}</td>
<td style="text-align: right; width: 50px;">$${formatCurrency(punitario)}</td>
<td style="text-align: right; width: 50px;">$${formatCurrency(producto.subtotal)}</td>
</tr>`;
            });

            html += `
</table>

<table width="100%" border="0" cellpadding="1" cellspacing="0" style="margin-bottom: 3px; border-top: 1px solid #000; border-bottom: 1px solid #000; font-size: 10px;">
<tr><td style="text-align: right;"><strong>SUBTOTAL:</strong></td><td style="text-align: right; width: 70px;">$${formatCurrency(subtotal)}</td></tr>
<tr><td style="text-align: right;"><strong>IMP (16%):</strong></td><td style="text-align: right; width: 70px;">$${formatCurrency(impuesto)}</td></tr>
<tr style="border-top: 1px solid #000; border-bottom: 1px solid #000;"><td style="text-align: right; font-weight: bold; font-size: 12px;"><strong>TOTAL:</strong></td><td style="text-align: right; width: 70px; font-weight: bold; font-size: 12px;">$${formatCurrency(total)}</td></tr>
</table>

<table width="100%" border="0" cellpadding="2" cellspacing="0" style="text-align: center; font-size: 9px;">
<tr><td style="padding: 5px 0;"><strong>GRACIAS POR SU COMPRA</strong></td></tr>
<tr><td style="font-size: 8px;">Conserve esta factura como comprobante</td></tr>
<tr><td style="font-size: 8px;">Vuelva pronto, le esperamos!</td></tr>
<tr><td style="font-size: 8px; color: #666; border-top: 1px dashed #000; padding-top: 3px;">${new Date().getFullYear()} Ferreteria Ferrocampo Pinillos - Todos derechos reservados</td></tr>
</table>

</div>
            `;

            document.getElementById('factura').innerHTML = html;
            document.getElementById('seccionFactura').style.display = 'block';
            document.getElementById('inputCodigoBarras').disabled = true;
        }

        function imprimirFactura() {
            // Generar ticket en texto puro
            let clienteNombre = document.getElementById('clienteNombre').value;
            let clienteCedula = document.getElementById('clienteCedula').value;
            let clienteEmail = document.getElementById('clienteEmail').value;
            let clienteTelefono = document.getElementById('clienteTelefono').value;
            let numeroFactura = document.getElementById('factura').getAttribute('data-numero-factura');
            
            // Abrir en una nueva ventana
            let printWindow = window.open('', 'TicketPrinter', 'width=400,height=600');
            
            fetch('generar_ticket_texto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=generar_ticket&numero_factura=' + encodeURIComponent(numeroFactura) +
                      '&cliente_nombre=' + encodeURIComponent(clienteNombre) +
                      '&cliente_cedula=' + encodeURIComponent(clienteCedula) +
                      '&cliente_email=' + encodeURIComponent(clienteEmail) +
                      '&cliente_telefono=' + encodeURIComponent(clienteTelefono) +
                      '&productos=' + encodeURIComponent(JSON.stringify(productosEnVenta))
            })
            .then(response => response.text())
            .then(ticket => {
                // Mostrar ticket en texto plano
                printWindow.document.write('<pre style="font-family: monospace; font-size: 11px; padding: 10px;">');
                printWindow.document.write(ticket);
                printWindow.document.write('</pre>');
                printWindow.document.close();
                
                // Esperar un poco y imprimir
                setTimeout(function() {
                    printWindow.print();
                }, 500);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al generar el ticket');
                printWindow.close();
            });
        }
        
        function enviarDirectoAImpresora() {
            // Esta función envía directamente a la impresora térmica
            let printWindow = window.open('', '', 'width=80mm,height=500');
            printWindow.document.write(document.getElementById('factura').innerHTML);
            printWindow.document.close();
            printWindow.print();
            setTimeout(function() {
                printWindow.close();
            }, 500);
        }

        function limpiarVenta() {
            if (confirm('¿Limpiar la venta actual?')) {
                productosEnVenta = [];
                document.getElementById('clienteNombre').value = '';
                document.getElementById('clienteCedula').value = '';
                document.getElementById('clienteEmail').value = '';
                document.getElementById('clienteTelefono').value = '';
                document.getElementById('seccionFactura').style.display = 'none';
                document.getElementById('inputCodigoBarras').disabled = false;
                actualizarTabla();
                document.getElementById('inputCodigoBarras').focus();
            }
        }

        function nuevaVenta() {
            limpiarVenta();
        }

        // Enfocar input al cargar la página
        document.getElementById('inputCodigoBarras').focus();
    </script>
</body>
</html>
