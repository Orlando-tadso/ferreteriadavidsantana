<?php
require_once __DIR__ . '/../core/config.php';

class Producto {
    private $conn;
    
    public function __construct($conexion) {
        $this->conn = $conexion;
    }
    
    // Obtener todos los productos
    public function obtenerTodos() {
        try {
            $stmt = $this->conn->prepare('SELECT p.*, c.nombre as categoria FROM productos p 
                                          LEFT JOIN categorias c ON p.categoria_id = c.id 
                                          ORDER BY p.nombre ASC');
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $datos = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $datos;
            
        } catch (Exception $e) {
            logError("Error en obtenerTodos", $e->getMessage());
            return [];
        }
    }
    
    // Obtener producto por ID
    public function obtenerPorId($id) {
        try {
            $id = intval($id);
            $stmt = $this->conn->prepare('SELECT p.*, c.nombre as categoria FROM productos p 
                                         LEFT JOIN categorias c ON p.categoria_id = c.id 
                                         WHERE p.id = ?');
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $producto = $result->fetch_assoc();
            $stmt->close();
            return $producto;
            
        } catch (Exception $e) {
            logError("Error en obtenerPorId", $e->getMessage());
            return null;
        }
    }
    
    // Obtener ID de categoría por nombre
    private function obtenerCategoriaId($nombre_categoria) {
        $stmt = $this->conn->prepare("SELECT id FROM categorias WHERE nombre = ?");
        if (!$stmt) {
            throw new Exception("Error en prepared statement: " . $this->conn->error);
        }
        $stmt->bind_param("s", $nombre_categoria);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Categoría no encontrada: " . $nombre_categoria);
        }
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    }
    
    // Crear nuevo producto
    public function crear($nombre, $categoria, $cantidad, $cantidad_minima, $precio_unitario, $codigo_barras = null) {
        try {
            $categoria_id = $this->obtenerCategoriaId($categoria);
            
            $stmt = $this->conn->prepare("INSERT INTO productos (nombre, categoria_id, cantidad, cantidad_minima, precio_unitario, codigo_barras) 
                    VALUES (?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $cantidad = intval($cantidad);
            $cantidad_minima = intval($cantidad_minima);
            $precio_unitario = floatval($precio_unitario);
            $codigo_barras = $codigo_barras ? trim($codigo_barras) : null;
            
            $stmt->bind_param("siiids", $nombre, $categoria_id, $cantidad, $cantidad_minima, $precio_unitario, $codigo_barras);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al crear producto: " . $stmt->error);
            }
            
            $id = $stmt->insert_id;
            $stmt->close();
            return $id;
            
        } catch (Exception $e) {
            logError("Error en crear", $e->getMessage());
            return false;
        }
    }
    
    // Actualizar producto
    public function actualizar($id, $nombre, $categoria, $cantidad_minima, $precio_unitario, $codigo_barras = null) {
        try {
            $categoria_id = $this->obtenerCategoriaId($categoria);
            
            $stmt = $this->conn->prepare("UPDATE productos SET 
                    nombre = ?,
                    categoria_id = ?,
                    cantidad_minima = ?,
                    precio_unitario = ?,
                    codigo_barras = ?
                    WHERE id = ?");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $id = intval($id);
            $cantidad_minima = intval($cantidad_minima);
            $precio_unitario = floatval($precio_unitario);
            $codigo_barras = $codigo_barras ? trim($codigo_barras) : null;
            
            $stmt->bind_param("siidsi", $nombre, $categoria_id, $cantidad_minima, $precio_unitario, $codigo_barras, $id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al actualizar: " . $stmt->error);
            }
            
            $stmt->close();
            return true;
            
        } catch (Exception $e) {
            logError("Error en actualizar", $e->getMessage());
            return false;
        }
    }
    
    // Eliminar producto
    public function eliminar($id) {
        try {
            $id = intval($id);
            $stmt = $this->conn->prepare("DELETE FROM productos WHERE id = ?");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al eliminar: " . $stmt->error);
            }
            
            $stmt->close();
            return true;
            
        } catch (Exception $e) {
            logError("Error en eliminar", $e->getMessage());
            return false;
        }
    }
    
    // Ajustar cantidad
    public function ajustarCantidad($id, $cantidad, $tipo, $motivo = '') {
        try {
            $id = intval($id);
            $cantidad = intval($cantidad);
            
            // Actualizar cantidad en productos
            $stmt = $this->conn->prepare("UPDATE productos SET cantidad = cantidad + ? WHERE id = ?");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement actualizar: " . $this->conn->error);
            }
            
            $stmt->bind_param("ii", $cantidad, $id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ajustar cantidad: " . $stmt->error);
            }
            
            $stmt->close();
            
            // Registrar en movimientos
            $stmt_mov = $this->conn->prepare("INSERT INTO movimientos (producto_id, tipo_movimiento, cantidad, motivo) VALUES (?, ?, ?, ?)");
            
            if (!$stmt_mov) {
                logError("Error en prepared statement movimientos", $this->conn->error);
                return true; // No fallar si falla el log
            }
            
            $stmt_mov->bind_param("isis", $id, $tipo, $cantidad, $motivo);
            $stmt_mov->execute();
            $stmt_mov->close();
            
            return true;
            
        } catch (Exception $e) {
            logError("Error en ajustarCantidad", $e->getMessage());
            return false;
        }
    }
    
    // Obtener productos con bajo stock
    public function obtenerBajoStock() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM productos WHERE cantidad <= cantidad_minima ORDER BY cantidad ASC");
            
            if (!$stmt) {
                throw new Exception("Error en prepared statement: " . $this->conn->error);
            }
            
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $datos = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $datos;
            
        } catch (Exception $e) {
            logError("Error en obtenerBajoStock", $e->getMessage());
            return [];
        }
    }
    
    // Obtener historial de movimientos
    public function obtenerHistorial($producto_id = null) {
        try {
            if ($producto_id) {
                $producto_id = intval($producto_id);
                $stmt = $this->conn->prepare("SELECT m.*, p.nombre FROM movimientos m 
                        JOIN productos p ON m.producto_id = p.id 
                        WHERE m.producto_id = ? 
                        ORDER BY m.fecha_movimiento DESC");
                
                if (!$stmt) {
                    throw new Exception("Error en prepared statement: " . $this->conn->error);
                }
                
                $stmt->bind_param("i", $producto_id);
                
            } else {
                $stmt = $this->conn->prepare("SELECT m.*, p.nombre FROM movimientos m 
                        JOIN productos p ON m.producto_id = p.id 
                        ORDER BY m.fecha_movimiento DESC LIMIT 50");
                
                if (!$stmt) {
                    throw new Exception("Error en prepared statement: " . $this->conn->error);
                }
            }
            
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Error al ejecutar: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $datos = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $datos;
            
        } catch (Exception $e) {
            logError("Error en obtenerHistorial", $e->getMessage());
            return [];
        }
    }
    
    // Obtener producto por código de barras
    public function obtenerPorCodigoBarras($codigo_barras) {
        try {
            // Normalizar el código de barras eliminando espacios en blanco
            $codigo_barras = str_replace(' ', '', trim($codigo_barras));
            
            $stmt = $this->conn->prepare("SELECT * FROM productos WHERE REPLACE(codigo_barras, ' ', '') = ?");
            
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
                $producto = $result->fetch_assoc();
                $stmt->close();
                return $producto;
            }
            $stmt->close();
            return null;
            
        } catch (Exception $e) {
            logError("Error en obtenerPorCodigoBarras", $e->getMessage());
            return null;
        }
    }
}

?>
