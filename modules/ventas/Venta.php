<?php
require_once __DIR__ . '/../core/config.php';

class Venta {
    private $conn;
    
    public function __construct($conexion) {
        $this->conn = $conexion;
    }
    
    // Obtener producto por código de barras con prepared statement
    public function obtenerPorCodigoBarras($codigo_barras) {
        try {
            // Normalizar el código de barras eliminando espacios en blanco
            $codigo_barras = str_replace(' ', '', trim($codigo_barras));
            
            $stmt = $this->conn->prepare("SELECT id, nombre, precio_unitario, cantidad FROM productos WHERE REPLACE(codigo_barras, ' ', '') = ?");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("s", $codigo_barras);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                return $result->fetch_assoc();
            }
            $stmt->close();
            return null;
            
        } catch (Exception $e) {
            logError("Error en obtenerPorCodigoBarras", $e->getMessage());
            return null;
        }
    }
    
    // Obtener producto por ID con prepared statement
    public function obtenerProductoPorId($id) {
        try {
            $id = intval($id);
            $stmt = $this->conn->prepare("SELECT id, nombre, precio_unitario, cantidad FROM productos WHERE id = ?");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $producto = $result->fetch_assoc();
                $stmt->close();
                return $producto;
            }
            $stmt->close();
            return null;
            
        } catch (Exception $e) {
            logError("Error en obtenerProductoPorId", $e->getMessage());
            return null;
        }
    }
    
    // Generar número de factura único
    private function generarNumeroFactura() {
        $fecha = date('YmdHis');
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        return "FAC-" . $fecha . "-" . $random;
    }
    
    // Registrar una venta con transacción y prepared statements
    public function registrarVenta($cliente_nombre, $cliente_cedula, $productos, $usuario_id, $cliente_email = '', $cliente_telefono = '') {
        try {
            // Iniciar transacción
            $this->conn->begin_transaction();
            
            // Calcular total
            $total = 0;
            foreach ($productos as $prod) {
                $total += $prod['subtotal'];
            }
            
            $numero_factura = $this->generarNumeroFactura();
            $total = floatval($total);
            $usuario_id = intval($usuario_id);
            
            // Insertar venta con prepared statement
            $stmt = $this->conn->prepare("INSERT INTO ventas (numero_factura, cliente_nombre, cliente_cedula, cliente_email, cliente_telefono, total, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("sssssdi", $numero_factura, $cliente_nombre, $cliente_cedula, $cliente_email, $cliente_telefono, $total, $usuario_id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al registrar venta: " . $stmt->error);
            }
            
            $venta_id = $stmt->insert_id;
            $stmt->close();
            
            // Procesar cada producto
            foreach ($productos as $prod) {
                $producto_id = intval($prod['producto_id']);
                $cantidad = intval($prod['cantidad']);
                $precio_unitario = floatval($prod['precio_unitario']);
                $subtotal = floatval($prod['subtotal']);
                $tipo_mov = 'venta';
                $motivo = "Venta factura " . $numero_factura;
                
                // Insertar detalle con prepared statement nuevo en cada iteración
                $stmt_detalle = $this->conn->prepare("INSERT INTO detalles_venta (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                
                if (!$stmt_detalle) {
                    throw new Exception("Error en prepared statement detalles: " . $this->conn->error);
                }
                
                $stmt_detalle->bind_param("iiidd", $venta_id, $producto_id, $cantidad, $precio_unitario, $subtotal);
                $stmt_detalle->execute();
                
                if ($stmt_detalle->error) {
                    throw new Exception("Error al registrar detalle: " . $stmt_detalle->error);
                }
                $stmt_detalle->close();
                
                // Ajustar cantidad en inventario con prepared statement nuevo en cada iteración
                $stmt_ajuste = $this->conn->prepare("UPDATE productos SET cantidad = cantidad - ? WHERE id = ?");
                
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
                $stmt_mov = $this->conn->prepare("INSERT INTO movimientos (producto_id, tipo_movimiento, cantidad, motivo) VALUES (?, ?, ?, ?)");
                
                if (!$stmt_mov) {
                    throw new Exception("Error en prepared statement movimientos: " . $this->conn->error);
                }
                
                $stmt_mov->bind_param("isis", $producto_id, $tipo_mov, $cantidad, $motivo);
                $stmt_mov->execute();
                $stmt_mov->close();
                // No lanzar excepción si falla el movimiento, es solo un log
            }
            
            // Confirmar transacción
            $this->conn->commit();

            return [
                'success' => true,
                'venta_id' => $venta_id,
                'numero_factura' => $numero_factura,
                'total' => $total
            ];
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error
            $this->conn->rollback();
            logError("Error en registrarVenta", $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Obtener detalles de una venta
    public function obtenerDetallesVenta($venta_id) {
        try {
            $venta_id = intval($venta_id);
            $stmt = $this->conn->prepare("SELECT dv.*, p.nombre FROM detalles_venta dv 
                    JOIN productos p ON dv.producto_id = p.id 
                    WHERE dv.venta_id = ?
                    ORDER BY dv.id");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $datos = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $datos;
            
        } catch (Exception $e) {
            logError("Error en obtenerDetallesVenta", $e->getMessage());
            return [];
        }
    }
    
    // Obtener venta completa
    public function obtenerVenta($venta_id) {
        try {
            $venta_id = intval($venta_id);
            $stmt = $this->conn->prepare("SELECT * FROM ventas WHERE id = ?");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $venta = $result->fetch_assoc();
            $stmt->close();
            return $venta;
            
        } catch (Exception $e) {
            logError("Error en obtenerVenta", $e->getMessage());
            return null;
        }
    }
    
    // Obtener historial de ventas
    public function obtenerHistorialVentas($limite = 50) {
        try {
            $limite = intval($limite);
            $stmt = $this->conn->prepare("SELECT * FROM ventas ORDER BY fecha_venta DESC LIMIT ?");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $limite);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $datos = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $datos;
            
        } catch (Exception $e) {
            logError("Error en obtenerHistorialVentas", $e->getMessage());
            return [];
        }
    }
    
    // Obtener total devuelto de una venta
    public function obtenerTotalDevuelto($venta_id) {
        try {
            $venta_id = intval($venta_id);
            $stmt = $this->conn->prepare("
                SELECT COALESCE(SUM(total_devuelto), 0) as total_devuelto 
                FROM devoluciones 
                WHERE venta_id = ?
            ");
            
            if (!$stmt) {
                return 0;
            }
            
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            return floatval($row['total_devuelto']);
        } catch (Exception $e) {
            return 0;
        }
    }
}
?>
