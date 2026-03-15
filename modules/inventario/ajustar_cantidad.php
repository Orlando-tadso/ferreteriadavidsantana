<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Producto.php';

requerirAdmin();

$producto_obj = new Producto($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $producto_id = $_POST['producto_id'] ?? 0;
    $cantidad = $_POST['cantidad'] ?? 0;
    $tipo = $_POST['tipo'] ?? 'entrada';
    $motivo = $_POST['motivo'] ?? '';
    
    // Convertir a negativo si es salida
    $cantidad_ajuste = ($tipo == 'salida') ? -$cantidad : $cantidad;
    
    if ($producto_obj->ajustarCantidad($producto_id, $cantidad_ajuste, $tipo, $motivo)) {
        header('Location: editar_producto.php?id=' . $producto_id . '&mensaje=Cantidad ajustada exitosamente');
    } else {
        header('Location: editar_producto.php?id=' . $producto_id . '&error=Error al ajustar cantidad');
    }
} else {
    header('Location: productos.php');
}

?>
