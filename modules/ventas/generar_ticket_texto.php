<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Venta.php';

requerirAdmin();

// Este archivo genera un archivo de texto puro para la impresora térmica
// Sin HTML, CSS, ni caracteres especiales

if ($_POST['action'] === 'generar_ticket') {
    $numero_factura = $_POST['numero_factura'] ?? '';
    $cliente_nombre = $_POST['cliente_nombre'] ?? '';
    $cliente_cedula = $_POST['cliente_cedula'] ?? '';
    $cliente_email = $_POST['cliente_email'] ?? '';
    $cliente_telefono = $_POST['cliente_telefono'] ?? '';
    $productos_json = $_POST['productos'] ?? '[]';
    
    $productos = json_decode($productos_json, true);
    
    // Calcular totales
    $subtotal = 0;
    foreach ($productos as $p) {
        $subtotal += isset($p['subtotal']) ? floatval($p['subtotal']) : 0;
    }
    $impuesto = $subtotal * 0.16;
    $total = $subtotal + $impuesto;
    $fecha = date('d/m/Y H:i:s');
    
    // GENERAR TICKET EN TEXTO PURO (80 caracteres de ancho para impresora térmica)
    $ticket = "";
    $ticket .= str_repeat("=", 80) . "\n";
    $ticket .= centrar("FERRETERIA", 80) . "\n";
    $ticket .= str_repeat("=", 80) . "\n";
    $ticket .= centrar("RIF: J-00000000-0", 80) . "\n";
    $ticket .= centrar("Tel: +58-XXX-XXXXXXX", 80) . "\n";
    $ticket .= centrar("Dir: Tu Direccion Aqui", 80) . "\n";
    $ticket .= str_repeat("=", 80) . "\n\n";
    
    $ticket .= "FACTURA: " . $numero_factura . "\n";
    $ticket .= "FECHA: " . $fecha . "\n";
    $ticket .= str_repeat("-", 80) . "\n\n";
    
    $ticket .= "CLIENTE: " . strtoupper($cliente_nombre) . "\n";
    $ticket .= "CEDULA:  " . $cliente_cedula . "\n";
    if (!empty($cliente_email)) {
        $ticket .= "EMAIL:   " . $cliente_email . "\n";
    }
    if (!empty($cliente_telefono)) {
        $ticket .= "TELEF:   " . $cliente_telefono . "\n";
    }
    $ticket .= str_repeat("-", 80) . "\n\n";
    
    // Encabezados de tabla
    $ticket .= sprintf("%-40s %6s %10s %10s\n", "PRODUCTO", "CANT.", "PRECIO", "TOTAL");
    $ticket .= str_repeat("-", 80) . "\n";
    
    // Productos
    foreach ($productos as $p) {
        $nombre = substr($p['nombre'], 0, 40);
        $cant = $p['cantidad'];
        $precio = $p['precio_unitario'];
        $subtot = $p['subtotal'];
        
        $ticket .= sprintf("%-40s %6d $%8s $%8s\n", 
            $nombre, 
            $cant, 
            number_format($precio, 0), 
            number_format($subtot, 0)
        );
    }
    
    $ticket .= str_repeat("-", 80) . "\n";
    $ticket .= sprintf("%-40s %21s $%8s\n", "SUBTOTAL:", "", number_format($subtotal, 0));
    $ticket .= sprintf("%-40s %21s $%8s\n", "IMPUESTO (16%):", "", number_format($impuesto, 0));
    $ticket .= str_repeat("=", 80) . "\n";
    $ticket .= sprintf("%-40s %21s $%8s\n", "TOTAL:", "", number_format($total, 0));
    $ticket .= str_repeat("=", 80) . "\n\n";
    
    $ticket .= centrar("GRACIAS POR SU COMPRA", 80) . "\n";
    $ticket .= centrar("Conserve esta factura como comprobante", 80) . "\n";
    $ticket .= centrar("Vuelva pronto, le esperamos!", 80) . "\n\n";
    $ticket .= centrar(date("Y") . " Ferreteria - Todos derechos reservados", 80) . "\n";
    $ticket .= str_repeat("=", 80) . "\n";
    
    echo $ticket;
    exit;
}

function centrar($texto, $ancho) {
    $padding = ($ancho - strlen($texto)) / 2;
    return str_repeat(" ", (int)$padding) . $texto;
}

function obtenerProductoPorId($id, $conn) {
    $sql = "SELECT id, nombre, cantidad, precio_unitario FROM productos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

?>
