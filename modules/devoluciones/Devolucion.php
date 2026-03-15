<?php
require_once __DIR__ . '/../core/config.php';

class Devolucion {
    private $conn;
    
    public function __construct($conexion) {
        $this->conn = $conexion;
    }
    
    // Generar número de devolución único
    private function generarNumeroDevolucion() {
        $fecha = date('YmdHis');
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        return "DEV-" . $fecha . "-" . $random;
    }
    
    // Buscar venta por número de factura
    public function buscarVentaPorFactura($numero_factura) {
        try {
            $stmt = $this->conn->prepare("
                SELECT v.*, 
                       (SELECT COUNT(*) FROM devoluciones WHERE venta_id = v.id) as num_devoluciones,
                       (SELECT SUM(total_devuelto) FROM devoluciones WHERE venta_id = v.id) as total_devuelto
                FROM ventas v 
                WHERE v.numero_factura = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("s", $numero_factura);
            $stmt->execute();
            
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $venta = $result->fetch_assoc();
                $stmt->close();
                return $venta;
            }
            $stmt->close();
            return null;
            
        } catch (Exception $e) {
            logError("Error en buscarVentaPorFactura", $e->getMessage());
            return null;
        }
    }
    
    // Obtener detalles de una venta con cantidades ya devueltas
    public function obtenerDetallesVenta($venta_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    dv.id as detalle_id,
                    dv.producto_id,
                    p.nombre as producto_nombre,
                    dv.cantidad as cantidad_vendida,
                    dv.precio_unitario,
                    dv.subtotal,
                    COALESCE(
                        (SELECT SUM(dd.cantidad_devuelta) 
                         FROM detalles_devolucion dd 
                         WHERE dd.detalle_venta_id = dv.id), 0
                    ) as cantidad_ya_devuelta,
                    (dv.cantidad - COALESCE(
                        (SELECT SUM(dd.cantidad_devuelta) 
                         FROM detalles_devolucion dd 
                         WHERE dd.detalle_venta_id = dv.id), 0
                    )) as cantidad_disponible_devolver
                FROM detalles_venta dv
                JOIN productos p ON dv.producto_id = p.id
                WHERE dv.venta_id = ?
                HAVING cantidad_disponible_devolver > 0
                ORDER BY dv.id
            ");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $venta_id = intval($venta_id);
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $detalles = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $detalles[] = $row;
                }
            }
            $stmt->close();
            return $detalles;
            
        } catch (Exception $e) {
            logError("Error en obtenerDetallesVenta", $e->getMessage());
            return [];
        }
    }
    
    // Registrar una devolución
    public function registrarDevolucion($venta_id, $productos_devueltos, $motivo, $usuario_id) {
        try {
            // Validaciones
            if (empty($productos_devueltos)) {
                return [
                    'success' => false,
                    'error' => 'Debe seleccionar al menos un producto para devolver'
                ];
            }
            
            if (empty($motivo)) {
                return [
                    'success' => false,
                    'error' => 'Debe especificar el motivo de la devolución'
                ];
            }
            
            // Iniciar transacción
            $this->conn->begin_transaction();
            
            // Validar que la venta existe
            $stmt = $this->conn->prepare("SELECT id, numero_factura FROM ventas WHERE id = ?");
            $venta_id = intval($venta_id);
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                throw new Exception("La venta no existe");
            }
            
            $venta = $result->fetch_assoc();
            $numero_factura = $venta['numero_factura'];
            $stmt->close();
            
            // Validar cada producto y calcular total
            $total_devuelto = 0;
            foreach ($productos_devueltos as &$prod) {
                $detalle_id = intval($prod['detalle_venta_id']);
                $cantidad_devolver = intval($prod['cantidad_devolver']);
                
                // Obtener información del detalle de venta
                $stmt = $this->conn->prepare("
                    SELECT 
                        dv.producto_id,
                        dv.precio_unitario,
                        dv.cantidad,
                        COALESCE(
                            (SELECT SUM(dd.cantidad_devuelta) 
                             FROM detalles_devolucion dd 
                             WHERE dd.detalle_venta_id = dv.id), 0
                        ) as ya_devuelto
                    FROM detalles_venta dv
                    WHERE dv.id = ? AND dv.venta_id = ?
                ");
                
                $stmt->bind_param("ii", $detalle_id, $venta_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if (!$result || $result->num_rows === 0) {
                    throw new Exception("Detalle de venta no encontrado");
                }
                
                $detalle = $result->fetch_assoc();
                $stmt->close();
                
                // Validar que no se devuelva más de lo vendido
                $disponible = $detalle['cantidad'] - $detalle['ya_devuelto'];
                if ($cantidad_devolver > $disponible) {
                    throw new Exception("Cantidad a devolver excede lo disponible");
                }
                
                $prod['producto_id'] = $detalle['producto_id'];
                $prod['precio_unitario'] = $detalle['precio_unitario'];
                $prod['subtotal'] = $cantidad_devolver * $detalle['precio_unitario'];
                $total_devuelto += $prod['subtotal'];
            }
            
            // Registrar la devolución
            $numero_devolucion = $this->generarNumeroDevolucion();
            $usuario_id = intval($usuario_id);
            
            $stmt = $this->conn->prepare("
                INSERT INTO devoluciones (venta_id, numero_devolucion, motivo, total_devuelto, usuario_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("issdi", $venta_id, $numero_devolucion, $motivo, $total_devuelto, $usuario_id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al registrar devolución: " . $stmt->error);
            }
            
            $devolucion_id = $stmt->insert_id;
            $stmt->close();
            
            // Procesar cada producto devuelto
            foreach ($productos_devueltos as $prod) {
                $detalle_id = intval($prod['detalle_venta_id']);
                $producto_id = intval($prod['producto_id']);
                $cantidad = intval($prod['cantidad_devolver']);
                $precio_unitario = floatval($prod['precio_unitario']);
                $subtotal = floatval($prod['subtotal']);
                
                // Registrar detalle de devolución con prepared statement nuevo en cada iteración
                $stmt_detalle = $this->conn->prepare("
                    INSERT INTO detalles_devolucion 
                    (devolucion_id, detalle_venta_id, producto_id, cantidad_devuelta, precio_unitario, subtotal) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if (!$stmt_detalle) {
                    throw new Exception("Error en prepared statement detalles: " . $this->conn->error);
                }
                
                $stmt_detalle->bind_param("iiiidd", $devolucion_id, $detalle_id, $producto_id, $cantidad, $precio_unitario, $subtotal);
                $stmt_detalle->execute();
                
                if ($stmt_detalle->error) {
                    throw new Exception("Error al registrar detalle: " . $stmt_detalle->error);
                }
                $stmt_detalle->close();
                
                // Devolver producto al inventario con prepared statement nuevo en cada iteración
                $stmt_ajuste = $this->conn->prepare("
                    UPDATE productos SET cantidad = cantidad + ? WHERE id = ?
                ");
                
                if (!$stmt_ajuste) {
                    throw new Exception("Error en prepared statement ajuste: " . $this->conn->error);
                }
                
                $stmt_ajuste->bind_param("ii", $cantidad, $producto_id);
                $stmt_ajuste->execute();
                
                if ($stmt_ajuste->error) {
                    throw new Exception("Error al ajustar inventario: " . $stmt_ajuste->error);
                }
                $stmt_ajuste->close();
                
                // Registrar movimiento con prepared statement nuevo en cada iteración
                $tipo_mov = 'devolucion';
                $motivo_mov = "Devolución " . $numero_devolucion . " (Fact: " . $numero_factura . ")";
                
                $stmt_mov = $this->conn->prepare("
                    INSERT INTO movimientos (producto_id, tipo_movimiento, cantidad, motivo) 
                    VALUES (?, ?, ?, ?)
                ");
                
                if (!$stmt_mov) {
                    throw new Exception("Error en prepared statement movimientos: " . $this->conn->error);
                }
                
                $stmt_mov->bind_param("isis", $producto_id, $tipo_mov, $cantidad, $motivo_mov);
                $stmt_mov->execute();
                $stmt_mov->close();
            }
            
            // Confirmar transacción
            $this->conn->commit();
            
            return [
                'success' => true,
                'devolucion_id' => $devolucion_id,
                'numero_devolucion' => $numero_devolucion,
                'total_devuelto' => $total_devuelto
            ];
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error
            $this->conn->rollback();
            logError("Error en registrarDevolucion", $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Obtener historial de devoluciones
    public function obtenerHistorialDevoluciones($limite = 50) {
        try {
            $limite = intval($limite);
            $stmt = $this->conn->prepare("
                SELECT 
                    d.id,
                    d.numero_devolucion,
                    d.fecha_devolucion,
                    v.numero_factura,
                    v.cliente_nombre,
                    v.cliente_cedula,
                    d.motivo,
                    d.total_devuelto,
                    u.nombre_completo as usuario
                FROM devoluciones d
                JOIN ventas v ON d.venta_id = v.id
                LEFT JOIN usuarios u ON d.usuario_id = u.id
                ORDER BY d.fecha_devolucion DESC
                LIMIT ?
            ");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $limite);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $devoluciones = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $devoluciones[] = $row;
                }
            }
            $stmt->close();
            return $devoluciones;
            
        } catch (Exception $e) {
            logError("Error en obtenerHistorialDevoluciones", $e->getMessage());
            return [];
        }
    }
    
    // Obtener detalles completos de una devolución
    public function obtenerDetallesDevolucion($devolucion_id) {
        try {
            $devolucion_id = intval($devolucion_id);
            
            // Obtener información de la devolución
            $stmt = $this->conn->prepare("
                SELECT 
                    d.*,
                    v.numero_factura,
                    v.cliente_nombre,
                    v.cliente_cedula,
                    u.nombre_completo as usuario
                FROM devoluciones d
                JOIN ventas v ON d.venta_id = v.id
                LEFT JOIN usuarios u ON d.usuario_id = u.id
                WHERE d.id = ?
            ");
            
            $stmt->bind_param("i", $devolucion_id);
            $stmt->execute();
            
            $result = $stmt->get_result();
            if (!$result || $result->num_rows === 0) {
                return null;
            }
            
            $devolucion = $result->fetch_assoc();
            $stmt->close();
            
            // Obtener productos devueltos
            $stmt = $this->conn->prepare("
                SELECT 
                    dd.*,
                    p.nombre as producto_nombre,
                    p.codigo_barras
                FROM detalles_devolucion dd
                JOIN productos p ON dd.producto_id = p.id
                WHERE dd.devolucion_id = ?
            ");
            
            $stmt->bind_param("i", $devolucion_id);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $productos = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $productos[] = $row;
                }
            }
            $stmt->close();
            
            $devolucion['productos'] = $productos;
            return $devolucion;
            
        } catch (Exception $e) {
            logError("Error en obtenerDetallesDevolucion", $e->getMessage());
            return null;
        }
    }
}
?>
