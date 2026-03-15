<?php
/**
 * SOLUCIÓN DE IMPRESIÓN PARA IMPRESORAS TÉRMICAS
 * Texto plano sin HTML - Compatible con cualquier impresora térmica
 */

require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';

requerirAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'descargar_ticket') {
    $numero_factura = $_POST['numero_factura'] ?? 'PRUEBA';
    $cliente_nombre = $_POST['cliente_nombre'] ?? 'CLIENTE';
    $cliente_cedula = $_POST['cliente_cedula'] ?? '0000-0000000-0';
    $productos_json = $_POST['productos'] ?? '[]';
    
    $productos = json_decode($productos_json, true);
    
    // Calcular totales
    $subtotal = 0;
    foreach ($productos as $p) {
        $subtotal += (isset($p['subtotal']) ? floatval($p['subtotal']) : 0);
    }
    $impuesto = $subtotal * 0.16;
    $total = $subtotal + $impuesto;
    $fecha = date('d/m/Y H:i');
    
    // GENERAR ARCHIVO DE TEXTO PURO
    $contenido = generarTicketTexto($numero_factura, $cliente_nombre, $cliente_cedula, $productos, $subtotal, $impuesto, $total, $fecha);
    
    // Descargar como archivo TXT
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ticket_' . $numero_factura . '.txt"');
    header('Content-Length: ' . strlen($contenido));
    
    echo $contenido;
    exit;
}

function generarTicketTexto($numero_factura, $cliente_nombre, $cliente_cedula, $productos, $subtotal, $impuesto, $total, $fecha) {
    $ancho = 80;
    $linea = str_repeat("=", $ancho) . "\n";
    
    $ticket = $linea;
    $ticket .= centrar("FERRETERIA", $ancho) . "\n";
    $ticket .= centrar("Punto de Venta", $ancho) . "\n";
    $ticket .= str_repeat("=", $ancho) . "\n";
    $ticket .= "\n";
    
    $ticket .= "FACTURA: " . str_pad($numero_factura, $ancho - 10, " ", STR_PAD_LEFT) . "\n";
    $ticket .= "FECHA:   " . str_pad($fecha, $ancho - 10, " ", STR_PAD_LEFT) . "\n";
    $ticket .= "\n" . str_repeat("-", $ancho) . "\n\n";
    
    $ticket .= "CLIENTE: " . $cliente_nombre . "\n";
    $ticket .= "CEDULA:  " . $cliente_cedula . "\n";
    $ticket .= "\n" . str_repeat("-", $ancho) . "\n\n";
    
    // Encabezados
    $ticket .= sprintf("%-32s %8s %10s %10s\n", "PRODUCTO", "CANT.", "P.UNIT.", "TOTAL");
    $ticket .= str_repeat("-", $ancho) . "\n";
    
    // Productos
    foreach ($productos as $p) {
        $nombre = substr($p['nombre'], 0, 32);
        $cant = intval($p['cantidad']);
        $precio = floatval($p['precio_unitario']);
        $subtot = floatval($p['subtotal']);
        
        $ticket .= sprintf("%-32s %8d $%8s $%8s\n",
            $nombre,
            $cant,
            number_format($precio, 0),
            number_format($subtot, 0)
        );
    }
    
    $ticket .= "\n" . str_repeat("-", $ancho) . "\n";
    $ticket .= sprintf("%-32s %8s %10s $%8s\n", "SUBTOTAL:", "", "", number_format($subtotal, 0));
    $ticket .= sprintf("%-32s %8s %10s $%8s\n", "IMP (16%):", "", "", number_format($impuesto, 0));
    $ticket .= str_repeat("=", $ancho) . "\n";
    $ticket .= sprintf("%-32s %8s %10s $%8s\n", "TOTAL:", "", "", number_format($total, 0));
    $ticket .= str_repeat("=", $ancho) . "\n\n";
    
    $ticket .= centrar("GRACIAS POR SU COMPRA", $ancho) . "\n";
    $ticket .= centrar("Conserve esta factura", $ancho) . "\n";
    $ticket .= centrar("Vuelva pronto!", $ancho) . "\n\n";
    
    return $ticket;
}

function centrar($texto, $ancho) {
    $espacios = ($ancho - strlen($texto)) / 2;
    return str_repeat(" ", (int)$espacios) . $texto . "\n";
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargar Ticket de Impresora</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
        }
        
        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 10px 0;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            background-color: #45a049;
        }
        
        .btn-secondary {
            background-color: #2196F3;
        }
        
        .btn-secondary:hover {
            background-color: #0b7dda;
        }
        
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        pre {
            background-color: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Descargar Ticket para Impresora Térmica</h1>
        
        <div class="info-box">
            <strong>Si tu impresora imprime caracteres raros:</strong>
            <p>Usa esta herramienta para descargar el ticket en formato TXT puro que cualquier impresora térmica puede procesar correctamente.</p>
        </div>
        
        <h2>Opción 1: Descargar Ticket de Venta</h2>
        <p>Usa este formulario para descargar un archivo TXT con el formato correcto:</p>
        
        <form method="POST" style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <div style="margin-bottom: 15px;">
                <label for="numero_factura">Número de Factura:</label><br>
                <input type="text" id="numero_factura" name="numero_factura" value="TEST-001" style="width: 100%; padding: 8px; margin-top: 5px;" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="cliente_nombre">Nombre del Cliente:</label><br>
                <input type="text" id="cliente_nombre" name="cliente_nombre" value="CLIENTE PRUEBA" style="width: 100%; padding: 8px; margin-top: 5px;" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="cliente_cedula">Cédula:</label><br>
                <input type="text" id="cliente_cedula" name="cliente_cedula" value="0000-0000000-0" style="width: 100%; padding: 8px; margin-top: 5px;" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label>Productos (JSON):</label><br>
                <textarea id="productos" name="productos" rows="6" style="width: 100%; padding: 8px; margin-top: 5px; font-family: monospace;" required>[
  {"nombre": "Clavo 2 pulgadas", "cantidad": 50, "precio_unitario": 150, "subtotal": 7500},
  {"nombre": "Tornillo Phillips", "cantidad": 100, "precio_unitario": 75, "subtotal": 7500},
  {"nombre": "Tuerca 1/2 pulgada", "cantidad": 25, "precio_unitario": 200, "subtotal": 5000}
]</textarea>
            </div>
            
            <input type="hidden" name="action" value="descargar_ticket">
            <button type="submit" class="btn">📥 Descargar Ticket TXT</button>
        </form>
        
        <h2 style="margin-top: 40px;">Opción 2: Instrucciones Manuales</h2>
        <ol>
            <li>Abre el archivo TXT descargado con <strong>Notepad</strong> (no Word)</li>
            <li>Selecciona <strong>Archivo > Imprimir</strong></li>
            <li>Selecciona tu impresora térmica</li>
            <li>Márgenes: <strong>Ninguno (0mm)</strong></li>
            <li>Haz clic en <strong>Imprimir</strong></li>
        </ol>
        
        <h2>Vista Previa del Ticket</h2>
        <p>Así se verá el ticket impreso:</p>
        <pre><?php 
            $ejemplo = generarTicketTexto(
                'TEST-001',
                'CLIENTE PRUEBA',
                '0000-0000000-0',
                [
                    ['nombre' => 'Clavo 2 pulgadas', 'cantidad' => 50, 'precio_unitario' => 150, 'subtotal' => 7500],
                    ['nombre' => 'Tornillo Phillips', 'cantidad' => 100, 'precio_unitario' => 75, 'subtotal' => 7500],
                    ['nombre' => 'Tuerca 1/2 pulgada', 'cantidad' => 25, 'precio_unitario' => 200, 'subtotal' => 5000]
                ],
                20000,
                3200,
                23200,
                date('d/m/Y H:i')
            );
            echo htmlspecialchars($ejemplo);
        ?></pre>
        
        <div class="info-box" style="margin-top: 40px;">
            <strong>¿Aún tienes problemas?</strong>
            <ul>
                <li>Verifica que tu impresora está encendida y conectada</li>
                <li>Intenta imprimir directamente desde Notepad</li>
                <li>Si aún ves caracteres raros, podría ser un problema del driver</li>
                <li>Descarga el driver más reciente de tu impresora térmica</li>
            </ul>
        </div>
    </div>
</body>
</html>
