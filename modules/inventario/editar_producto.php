<?php
require_once __DIR__ . '/../core/verificar_sesion.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/Producto.php';

requerirAdmin();

$mensaje = '';
$tipo_mensaje = '';

if (isset($_GET['id'])) {
    $producto_obj = new Producto($conn);
    $producto = $producto_obj->obtenerPorId($_GET['id']);
    
    if (!$producto) {
        header('Location: productos.php');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $nombre = $_POST['nombre'] ?? '';
        $categoria = $_POST['categoria'] ?? '';
        $cantidad_minima = $_POST['cantidad_minima'] ?? 5;
        $precio_unitario = $_POST['precio_unitario'] ?? 0;
        $codigo_barras = $_POST['codigo_barras'] ?? '';
        
        if ($nombre && $categoria && $precio_unitario) {
            if ($producto_obj->actualizar($_GET['id'], $nombre, $categoria, $cantidad_minima, $precio_unitario, $codigo_barras)) {
                $mensaje = '✓ Producto actualizado exitosamente';
                $tipo_mensaje = 'success';
                $producto = $producto_obj->obtenerPorId($_GET['id']);
            } else {
                $mensaje = '✗ Error al actualizar el producto';
                $tipo_mensaje = 'error';
            }
        }
    }
} else {
    header('Location: productos.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Ferretería</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .custom-select {
            position: relative;
            width: 100%;
        }
        .select-header {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            background: white;
            transition: all 0.3s ease;
        }
        .select-header:hover {
            border-color: #3498db;
        }
        .select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            z-index: 100;
        }
        .select-dropdown.open {
            max-height: 400px;
            border-top: 1px solid #ddd;
        }
        .category-option {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            cursor: pointer;
            transition: background 0.2s ease;
            gap: 10px;
        }
        .category-option:hover {
            background-color: #f0f0f0;
        }
        .category-option span {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php require_once __DIR__ . '/../core/menu.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1><img src="assets/icons/editar_producto.png" alt="Editar" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Editar Producto</h1>
            </header>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <section class="card">
                <form method="POST" class="form">
                    <div class="form-group">
                        <label for="nombre">Nombre del Producto *</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoria">Categoría *</label>
                            <div class="custom-select" id="categoria-select">
                                <div class="select-header" onclick="toggleCategoryDropdown(this)">
                                    <?php 
                                        $iconMap = [
                                            'Herramientas' => 'herramienta.png',
                                            'Materiales' => 'materiales.png',
                                            'Pinturas' => 'pinturas.png',
                                            'Tubería' => 'tuberias.png',
                                            'Eléctrica' => 'electrica.png',
                                            'Venenos' => 'venenos.png',
                                            'Aceites' => 'aceites.png',
                                            'Medicinas' => 'medicinas.png',
                                            'Aperos de caballo' => 'caballo.png'
                                        ];
                                        $currentIcon = $iconMap[$producto['categoria']] ?? 'herramienta.png';
                                    ?>
                                    <img id="categoria-icon" src="assets/icons/<?php echo $currentIcon; ?>" alt="Categoría" style="width:24px;height:24px;object-fit:contain;margin-right:8px;">
                                    <span id="categoria-text"><?php echo htmlspecialchars($producto['categoria']); ?></span>
                                </div>
                                <div class="select-dropdown">
                                    <div class="category-option" data-value="Herramientas" onclick="selectCategory(this, 'Herramientas', 'herramienta.png')">
                                        <img src="assets/icons/herramienta.png" alt="Herramientas" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Herramientas</span>
                                    </div>
                                    <div class="category-option" data-value="Materiales" onclick="selectCategory(this, 'Materiales', 'materiales.png')">
                                        <img src="assets/icons/materiales.png" alt="Materiales" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Materiales</span>
                                    </div>
                                    <div class="category-option" data-value="Pinturas" onclick="selectCategory(this, 'Pinturas', 'pinturas.png')">
                                        <img src="assets/icons/pinturas.png" alt="Pinturas" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Pinturas</span>
                                    </div>
                                    <div class="category-option" data-value="Tubería" onclick="selectCategory(this, 'Tubería', 'tuberias.png')">
                                        <img src="assets/icons/tuberias.png" alt="Tubería" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Tubería</span>
                                    </div>
                                    <div class="category-option" data-value="Eléctrica" onclick="selectCategory(this, 'Eléctrica', 'electrica.png')">
                                        <img src="assets/icons/electrica.png" alt="Eléctrica" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Eléctrica</span>
                                    </div>
                                    <div class="category-option" data-value="Venenos" onclick="selectCategory(this, 'Venenos', 'venenos.png')">
                                        <img src="assets/icons/venenos.png" alt="Venenos" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Venenos</span>
                                    </div>
                                    <div class="category-option" data-value="Aceites" onclick="selectCategory(this, 'Aceites', 'aceites.png')">
                                        <img src="assets/icons/aceites.png" alt="Aceites" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Aceites</span>
                                    </div>
                                    <div class="category-option" data-value="Medicinas" onclick="selectCategory(this, 'Medicinas', 'medicinas.png')">
                                        <img src="assets/icons/medicinas.png" alt="Medicinas" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Medicinas</span>
                                    </div>
                                    <div class="category-option" data-value="Aperos de caballo" onclick="selectCategory(this, 'Aperos de caballo', 'caballo.png')">
                                        <img src="assets/icons/caballo.png" alt="Aperos de caballo" style="width:20px;height:20px;object-fit:contain;">
                                        <span>Aperos de caballo</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="categoria" name="categoria" value="<?php echo htmlspecialchars($producto['categoria']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="cantidad_actual">Cantidad Actual</label>
                            <input type="number" id="cantidad_actual" name="cantidad_actual" value="<?php echo $producto['cantidad']; ?>" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio_unitario">Precio Unitario ($) *</label>
                            <input type="number" id="precio_unitario" name="precio_unitario" value="<?php echo $producto['precio_unitario']; ?>" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label for="cantidad_minima">Cantidad Mínima</label>
                            <input type="number" id="cantidad_minima" name="cantidad_minima" value="<?php echo $producto['cantidad_minima']; ?>" min="1">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="codigo_barras">Código de Barras</label>
                        <input type="text" id="codigo_barras" name="codigo_barras" value="<?php echo $producto['codigo_barras'] ?? ''; ?>" placeholder="Ej: 1234567890123">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><img src="assets/icons/guardar.png" alt="Guardar" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Guardar Cambios</button>
                        <a href="productos.php" class="btn btn-secondary">❌ Cancelar</a>
                    </div>
                </form>
            </section>

            <!-- Sección de Ajuste de Cantidad -->
            <section class="card">
                <h2><img src="assets/icons/ajuste_stock.png" alt="Ajuste" style="width:34px;height:34px;vertical-align:middle;margin-right:8px;object-fit:contain;">Ajustar Cantidad de Stock</h2>
                <form method="POST" action="ajustar_cantidad.php" class="form">
                    <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cantidad_ajuste">Cantidad</label>
                            <input type="number" id="cantidad_ajuste" name="cantidad" value="1" required>
                        </div>

                        <div class="form-group">
                            <label for="tipo">Tipo de Movimiento</label>
                            <select id="tipo" name="tipo" required>
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="motivo">Motivo</label>
                            <input type="text" id="motivo" name="motivo" placeholder="Ej: Restock, Dañado...">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><img src="assets/icons/actualizar.png" alt="Actualizar" style="width:20px;height:20px;vertical-align:middle;margin-right:8px;object-fit:contain;">Ajustar Cantidad</button>
                </form>
            </section>
        </main>
    </div>
<script>
    function toggleCategoryDropdown(header) {
        const dropdown = header.nextElementSibling;
        dropdown.classList.toggle('open');
    }

    function selectCategory(element, categoryName, iconFile) {
        document.getElementById('categoria').value = categoryName;
        document.getElementById('categoria-text').textContent = categoryName;
        document.getElementById('categoria-icon').src = 'assets/icons/' + iconFile;
        element.parentElement.classList.remove('open');
    }

    document.addEventListener('click', function(event) {
        const customSelect = document.getElementById('categoria-select');
        if (customSelect && !customSelect.contains(event.target)) {
            const dropdown = customSelect.querySelector('.select-dropdown');
            dropdown.classList.remove('open');
        }
    });
</script>
</body>
</html>
