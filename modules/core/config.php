<?php
// Configuración de zona horaria para Colombia
date_default_timezone_set('America/Bogota');

// Configuración de sesiones - DEBE ser antes de cualquier session_start()
if (!function_exists('iniciar_sesion')) {
    function iniciar_sesion() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurar parámetros de sesión
            ini_set('session.gc_maxlifetime', 28800); // 8 horas
            ini_set('session.gc_probability', 1);
            ini_set('session.gc_divisor', 100);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            
            // Configurar parámetros de cookies
            session_set_cookie_params([
                'lifetime' => 28800,
                'path' => '/',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            
            // Iniciar sesión
            session_start();
            
            // Regenerar ID solo la primera vez
            if (!isset($_SESSION['_iniciado'])) {
                session_regenerate_id(true);
                $_SESSION['_iniciado'] = true;
            }
        }
    }
}

// Iniciar sesión
iniciar_sesion();

// Configuración de la base de datos
// Soporte para Railway, Heroku (ClearDB) y desarrollo local

$is_remote_db = false;
$missing_remote_config = false;
$is_running_on_railway = (bool) (getenv('RAILWAY_ENVIRONMENT') || getenv('RAILWAY_PROJECT_ID') || getenv('RAILWAY_SERVICE_ID'));

// Railway proporciona variables individuales (con prefijo del servicio MySQL)
if (getenv('MYSQL_MYSQLHOST') && getenv('MYSQL_MYSQLUSER')) {
    define('DB_HOST', getenv('MYSQL_MYSQLHOST'));
    define('DB_USER', getenv('MYSQL_MYSQLUSER'));
    define('DB_PASS', getenv('MYSQL_MYSQLPASSWORD') ?: '');
    define('DB_NAME', getenv('MYSQL_MYSQLDATABASE') ?: 'railway');
    define('DB_PORT', getenv('MYSQL_MYSQLPORT') ?: 3306);
    $is_remote_db = true;
}
// Railway también puede usar variables sin prefijo
elseif (getenv('MYSQLHOST') && getenv('MYSQLUSER')) {
    define('DB_HOST', getenv('MYSQLHOST'));
    define('DB_USER', getenv('MYSQLUSER'));
    define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');
    define('DB_PORT', getenv('MYSQLPORT') ?: 3306);
    $is_remote_db = true;
}
// Railway también puede usar DATABASE_URL
elseif (getenv('DATABASE_URL') || getenv('MYSQL_URL')) {
    $db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
    $url = parse_url($db_url);
    define('DB_HOST', $url['host']);
    define('DB_USER', $url['user']);
    define('DB_PASS', $url['pass'] ?? '');
    define('DB_NAME', ltrim($url['path'], '/'));
    define('DB_PORT', $url['port'] ?? 3306);
    $is_remote_db = true;
}
// Heroku ClearDB
elseif (getenv('CLEARDB_DATABASE_URL')) {
    $url = parse_url(getenv('CLEARDB_DATABASE_URL'));
    define('DB_HOST', $url['host']);
    define('DB_USER', $url['user']);
    define('DB_PASS', $url['pass']);
    define('DB_NAME', ltrim($url['path'], '/'));
    define('DB_PORT', $url['port'] ?? 3306);
    $is_remote_db = true;
}
// Si está en Railway y no hay variables de DB, no usar fallback local
elseif ($is_running_on_railway) {
    $missing_remote_config = true;
}
// Configuración local (XAMPP)
else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'ferreteria_ferrocampo');
    define('DB_PORT', 3306);
}

// Crear archivo de log si no existe
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logError($message, $context = '') {
    $log_file = __DIR__ . '/logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    if ($context) {
        $log_message .= " | Contexto: $context";
    }
    $log_message .= "\n";
    error_log($log_message, 3, $log_file);
}

// Crear conexión con reintentos
$conn = null;
$max_reintentos = 3;
$retraso = 1; // segundos
$last_connect_error = '';

// Evita excepciones fatales de mysqli y permite manejar el error como 503.
mysqli_report(MYSQLI_REPORT_OFF);

if ($missing_remote_config) {
    logError('Railway detectado sin variables de base de datos (MYSQLHOST/MYSQLUSER o DATABASE_URL).');
    http_response_code(503);
    die('<h1>Error de Configuracion</h1><p>Faltan variables de base de datos en Railway. Configura MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD y MYSQLDATABASE en el servicio web.</p>');
}

for ($intento = 1; $intento <= $max_reintentos; $intento++) {
    try {
        if ($is_remote_db) {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        } else {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, null, DB_PORT);
        }
    } catch (Throwable $e) {
        $last_connect_error = $e->getMessage();
        logError("Intento $intento de conexión lanzó excepción", $last_connect_error);
        if ($intento < $max_reintentos) {
            sleep($retraso);
            $retraso *= 2;
            continue;
        }
        break;
    }
    
    if (!$conn->connect_error) {
        // Configurar charset
        $conn->set_charset("utf8mb4");
        // Configurar zona horaria para Colombia
        $conn->query("SET time_zone = '-05:00'");
        break;
    }
    
    $last_connect_error = $conn->connect_error;
    $error_msg = "Intento $intento de conexión fallido: " . $last_connect_error;
    logError($error_msg);
    
    if ($intento < $max_reintentos) {
        sleep($retraso);
        $retraso *= 2; // Backoff exponencial
    }
}

// Si después de reintentos sigue fallando
if (!$conn || $conn->connect_error) {
    logError("Falló conexión a MySQL después de $max_reintentos intentos", ($last_connect_error ?: ($_SERVER['REQUEST_URI'] ?? 'CLI')));
    http_response_code(503);
    die("<h1>Error de Sistema</h1><p>No se puede conectar a la base de datos. Por favor, intente más tarde.</p>");
}

// Crear base de datos si no existe (solo en local)
if (!$is_remote_db) {
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    $conn->query($sql);
}

// Seleccionar la base de datos
$conn->select_db(DB_NAME);

// Crear tabla de categorías base
$sql = "CREATE TABLE IF NOT EXISTS categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Insertar categorías por defecto
$categorias_default = [
    ['Materiales', 'Materiales y componentes'],
    ['Herramientas', 'Herramientas y equipos'],
    ['Pinturas', 'Pinturas y acabados'],
    ['Tuberia', 'Tuberias y accesorios'],
    ['Electrica', 'Material electrico'],
    ['Venenos', 'Venenos y pesticidas'],
    ['Aceites', 'Aceites y lubricantes'],
    ['Medicinas', 'Medicamentos y suplementos'],
    ['Aperos de caballo', 'Aperos y accesorios para caballos']
];

foreach ($categorias_default as $cat) {
    $stmt_cat = $conn->prepare("INSERT IGNORE INTO categorias (nombre, descripcion) VALUES (?, ?)");
    if ($stmt_cat) {
        $stmt_cat->bind_param("ss", $cat[0], $cat[1]);
        $stmt_cat->execute();
        $stmt_cat->close();
    }
}

// Crear tabla de productos
$sql = "CREATE TABLE IF NOT EXISTS productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    categoria_id INT DEFAULT NULL,
    cantidad INT NOT NULL DEFAULT 0,
    cantidad_minima INT NOT NULL DEFAULT 5,
    precio_unitario DECIMAL(10,2) NOT NULL,
    codigo_barras VARCHAR(50) UNIQUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo_barras (codigo_barras),
    INDEX idx_categoria_id (categoria_id),
    INDEX idx_cantidad (cantidad)
)";
$conn->query($sql);

// Agregar columna categoria_id si no existe
$check_column_categoria_id = $conn->query("SHOW COLUMNS FROM productos LIKE 'categoria_id'");
if ($check_column_categoria_id && $check_column_categoria_id->num_rows == 0) {
    $conn->query("ALTER TABLE productos ADD COLUMN categoria_id INT DEFAULT NULL");
}

// Migrar categoria texto -> categoria_id para instalaciones antiguas
$check_column_categoria_texto = $conn->query("SHOW COLUMNS FROM productos LIKE 'categoria'");
if ($check_column_categoria_texto && $check_column_categoria_texto->num_rows > 0) {
    $conn->query("UPDATE productos p
        JOIN categorias c ON p.categoria = c.nombre
        SET p.categoria_id = c.id
        WHERE p.categoria_id IS NULL OR p.categoria_id = 0");
}

// Asignar categoria por defecto si no se pudo mapear
$conn->query("UPDATE productos p
    JOIN categorias c ON c.nombre = 'Materiales'
    SET p.categoria_id = c.id
    WHERE p.categoria_id IS NULL");

// Eliminar columna categoria (texto) tras migrar a categoria_id
if ($check_column_categoria_texto && $check_column_categoria_texto->num_rows > 0) {
    $conn->query("ALTER TABLE productos DROP COLUMN categoria");
}

// Agregar columna código_barras si no existe (para bases de datos existentes)
$check_column = $conn->query("SHOW COLUMNS FROM productos LIKE 'codigo_barras'");
if ($check_column && $check_column->num_rows == 0) {
    $sql = "ALTER TABLE productos ADD COLUMN codigo_barras VARCHAR(50) UNIQUE DEFAULT NULL";
    $conn->query($sql);
}

// Agregar índices si no existen (para bases de datos existentes)
$check_idx = $conn->query("SHOW INDEX FROM productos WHERE Key_name = 'idx_codigo_barras'");
if ($check_idx && $check_idx->num_rows == 0) {
    $conn->query("ALTER TABLE productos ADD INDEX idx_codigo_barras (codigo_barras)");
    $conn->query("ALTER TABLE productos ADD INDEX idx_categoria_id (categoria_id)");
    $conn->query("ALTER TABLE productos ADD INDEX idx_cantidad (cantidad)");
}

// Asegurar llave foranea de categoria_id
$check_fk_categoria = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'productos'
    AND COLUMN_NAME = 'categoria_id'
    AND REFERENCED_TABLE_NAME = 'categorias'");
if ($check_fk_categoria && $check_fk_categoria->num_rows == 0) {
    $conn->query("ALTER TABLE productos ADD CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT");
}

// Crear tabla de movimientos (historial)
$sql = "CREATE TABLE IF NOT EXISTS movimientos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT NOT NULL,
    tipo_movimiento VARCHAR(20) NOT NULL,
    cantidad INT NOT NULL,
    motivo VARCHAR(100),
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_producto_id (producto_id),
    INDEX idx_fecha_movimiento (fecha_movimiento),
    INDEX idx_tipo_movimiento (tipo_movimiento)
)";
$conn->query($sql);

// Agregar índices a movimientos si no existen
$check_idx = $conn->query("SHOW INDEX FROM movimientos WHERE Key_name = 'idx_producto_id'");
if ($check_idx && $check_idx->num_rows == 0) {
    $conn->query("ALTER TABLE movimientos ADD INDEX idx_producto_id (producto_id)");
    $conn->query("ALTER TABLE movimientos ADD INDEX idx_fecha_movimiento (fecha_movimiento)");
    $conn->query("ALTER TABLE movimientos ADD INDEX idx_tipo_movimiento (tipo_movimiento)");
}

// Crear tabla de usuarios
$sql = "CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_usuario VARCHAR(100) NOT NULL UNIQUE,
    nombre_completo VARCHAR(150) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    rol VARCHAR(20) DEFAULT 'inspector',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Desactivar restricciones de clave foránea temporalmente
$conn->query("SET FOREIGN_KEY_CHECKS=0");

// Crear tabla de ventas solo si no existe
$sql = "CREATE TABLE IF NOT EXISTS ventas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_factura VARCHAR(50) UNIQUE NOT NULL,
    cliente_nombre VARCHAR(150) NOT NULL,
    cliente_cedula VARCHAR(20) NOT NULL,
    cliente_email VARCHAR(150) NULL,
    cliente_telefono VARCHAR(20) NULL,
    total DECIMAL(10,2) NOT NULL,
    usuario_id INT,
    fecha_venta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_numero_factura (numero_factura),
    INDEX idx_fecha_venta (fecha_venta),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_cliente_cedula (cliente_cedula)
)";
$conn->query($sql);

// Agregar campos de cliente si no existen en instalaciones antiguas
$check_cliente_email = $conn->query("SHOW COLUMNS FROM ventas LIKE 'cliente_email'");
if ($check_cliente_email && $check_cliente_email->num_rows == 0) {
    $conn->query("ALTER TABLE ventas ADD COLUMN cliente_email VARCHAR(150) NULL AFTER cliente_cedula");
}

$check_cliente_telefono = $conn->query("SHOW COLUMNS FROM ventas LIKE 'cliente_telefono'");
if ($check_cliente_telefono && $check_cliente_telefono->num_rows == 0) {
    $conn->query("ALTER TABLE ventas ADD COLUMN cliente_telefono VARCHAR(20) NULL AFTER cliente_email");
}

// Agregar índices a ventas si no existen
$check_idx = $conn->query("SHOW INDEX FROM ventas WHERE Key_name = 'idx_fecha_venta'");
if ($check_idx && $check_idx->num_rows == 0) {
    $conn->query("ALTER TABLE ventas ADD INDEX idx_numero_factura (numero_factura)");
    $conn->query("ALTER TABLE ventas ADD INDEX idx_fecha_venta (fecha_venta)");
    $conn->query("ALTER TABLE ventas ADD INDEX idx_usuario_id (usuario_id)");
    $conn->query("ALTER TABLE ventas ADD INDEX idx_cliente_cedula (cliente_cedula)");
}

// Agregar columna 'rol' a la tabla usuarios si no existe (para bases de datos existentes)
$check_column_rol = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'rol'");
if ($check_column_rol && $check_column_rol->num_rows == 0) {
    $sql = "ALTER TABLE usuarios ADD COLUMN rol VARCHAR(20) DEFAULT 'inspector'";
    $conn->query($sql);
}

// Asegurar que el usuario 'admin' tenga rol 'admin' (migración para instalaciones previas)
$conn->query("UPDATE usuarios SET rol = 'admin' WHERE (nombre_usuario = 'admin' OR nombre_completo LIKE 'Administrador') AND (rol IS NULL OR rol = '')");

// Asegurar que usuarios no admin queden como inspectores
$conn->query("UPDATE usuarios SET rol = 'inspector' WHERE (rol IS NULL OR rol = '' OR rol = 'user') AND (nombre_usuario <> 'admin' AND nombre_completo NOT LIKE 'Administrador')");

// Crear usuario administrador inicial si no existe aún.
$admin_inicial_usuario = getenv('DEFAULT_ADMIN_USER') ?: 'admin';
$admin_inicial_nombre = getenv('DEFAULT_ADMIN_NAME') ?: 'Administrador General';
$admin_inicial_clave = getenv('DEFAULT_ADMIN_PASSWORD') ?: 'Admin12345!';

$stmt_admin_existente = $conn->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ? LIMIT 1");
if ($stmt_admin_existente) {
    $stmt_admin_existente->bind_param("s", $admin_inicial_usuario);
    $stmt_admin_existente->execute();
    $resultado_admin_existente = $stmt_admin_existente->get_result();

    if ($resultado_admin_existente && $resultado_admin_existente->num_rows === 0) {
        $admin_inicial_hash = password_hash($admin_inicial_clave, PASSWORD_DEFAULT);
        $stmt_admin = $conn->prepare("INSERT INTO usuarios (nombre_usuario, nombre_completo, contrasena, rol, activo) VALUES (?, ?, ?, 'admin', 1)");
        if ($stmt_admin) {
            $stmt_admin->bind_param("sss", $admin_inicial_usuario, $admin_inicial_nombre, $admin_inicial_hash);
            $stmt_admin->execute();
            $stmt_admin->close();
            logError('Usuario administrador inicial creado automáticamente.');
        }
    }

    $stmt_admin_existente->close();
}

// Crear tabla de detalles_venta solo si no existe
$sql = "CREATE TABLE IF NOT EXISTS detalles_venta (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
)";
$conn->query($sql);

// Crear tabla de devoluciones
$sql = "CREATE TABLE IF NOT EXISTS devoluciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venta_id INT NOT NULL,
    numero_devolucion VARCHAR(50) UNIQUE NOT NULL,
    motivo TEXT NOT NULL,
    total_devuelto DECIMAL(10,2) NOT NULL,
    usuario_id INT,
    fecha_devolucion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_devoluciones_venta (venta_id),
    INDEX idx_devoluciones_fecha (fecha_devolucion)
)";
$conn->query($sql);

// Crear tabla de detalles_devolucion
$sql = "CREATE TABLE IF NOT EXISTS detalles_devolucion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    devolucion_id INT NOT NULL,
    detalle_venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad_devuelta INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (devolucion_id) REFERENCES devoluciones(id) ON DELETE CASCADE,
    FOREIGN KEY (detalle_venta_id) REFERENCES detalles_venta(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_detalles_devolucion (devolucion_id)
)";
$conn->query($sql);

// Reactivar restricciones de clave foránea
$conn->query("SET FOREIGN_KEY_CHECKS=1");

?>
