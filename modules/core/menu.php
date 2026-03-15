<?php
// Archivo de menú compartido para toda la aplicación
// Se incluye en todos los archivos que tienen sidebar

// Obtener la página actual para marcar como activa
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="logo">
        <h2>
            <img src="assets/icons/ferreteria.png" alt="Icono de ferretería" class="logo-icon" width="34" height="34" style="width:34px;height:34px;min-width:34px;max-width:34px;min-height:34px;max-height:34px;object-fit:contain;display:inline-block;vertical-align:middle;">
            Ferretería
        </h2>
    </div>
    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"><img src="assets/icons/dasboard.png" alt="Dashboard" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Dashboard</a>
        <a href="productos.php" class="nav-link <?php echo $current_page === 'productos.php' ? 'active' : ''; ?>"><img src="assets/icons/producto.png" alt="Productos" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Productos</a>
        <?php if (esAdmin()): ?>
            <a href="agregar_producto.php" class="nav-link <?php echo $current_page === 'agregar_producto.php' ? 'active' : ''; ?>"><img src="assets/icons/agregar_producto.png" alt="Agregar producto" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Agregar Producto</a>
            <a href="punto_venta.php" class="nav-link <?php echo $current_page === 'punto_venta.php' ? 'active' : ''; ?>"><img src="assets/icons/venta.png" alt="Punto de Venta" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Punto de Venta</a>
        <?php endif; ?>
        <a href="movimientos.php" class="nav-link <?php echo $current_page === 'movimientos.php' ? 'active' : ''; ?>"><img src="assets/icons/movimientos.png" alt="Movimientos" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Movimientos</a>
        <a href="historial_ventas.php" class="nav-link <?php echo $current_page === 'historial_ventas.php' ? 'active' : ''; ?>"><img src="assets/icons/historial-ventas.png" alt="Historial" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Historial Ventas</a>
        <?php if (esAdmin()): ?>
            <a href="gestionar_devoluciones.php" class="nav-link <?php echo $current_page === 'gestionar_devoluciones.php' ? 'active' : ''; ?>"><img src="assets/icons/devoluciones.png" alt="Devoluciones" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Devoluciones</a>
        <?php endif; ?>
        <a href="bajo_stock.php" class="nav-link <?php echo $current_page === 'bajo_stock.php' ? 'active' : ''; ?>"><img src="assets/icons/bajo_stock.png" alt="Bajo Stock" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Bajo Stock</a>
        <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
        <?php if (esAdmin()): ?>
            <a href="crear_usuario.php" class="nav-link <?php echo $current_page === 'crear_usuario.php' ? 'active' : ''; ?>"><img src="assets/icons/crear_usuario.png" alt="Crear usuario" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Crear Usuario</a>
        <?php endif; ?>
        <a href="logout.php" class="nav-link" style="color: #e74c3c;"><img src="assets/icons/cerrar_sesion.png" alt="Cerrar sesión" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Cerrar Sesión</a>
    </nav>
</aside>
