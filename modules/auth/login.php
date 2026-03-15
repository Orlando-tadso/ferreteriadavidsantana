<?php
// La sesión se inicia automáticamente en config.php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/seguridad.php';

// Establecer headers de seguridad
establecerHeadersSeguridad();

// Si ya está autenticado, redirige al dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once __DIR__ . '/../core/config.php';
    
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    
    if (empty($usuario) || empty($contrasena)) {
        $error = 'Por favor completa todos los campos';
    } else {
        try {
            // Verificar límite de intentos (protección contra fuerza bruta)
            LoginRateLimit::verificarIntentos($usuario);
            
            // Buscar usuario
            $sql = "SELECT id, nombre_usuario, nombre_completo, contrasena, rol FROM usuarios WHERE nombre_usuario = ? AND activo = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                // Verificar contraseña
                if (password_verify($contrasena, $row['contrasena'])) {
                    // Determinar rol (compatibilidad con filas antiguas)
                    $rol_obtenido = $row['rol'] ?? '';
                    if (empty($rol_obtenido) && isset($row['nombre_usuario']) && $row['nombre_usuario'] === 'admin') {
                        $rol_obtenido = 'admin';
                    }

                    // Limpiar intentos fallidos
                    LoginRateLimit::limpiarIntentos($usuario);
                    
                    // Iniciar sesión
                    $_SESSION['usuario_id'] = $row['id'];
                    $_SESSION['usuario_nombre'] = $row['nombre_usuario'];
                    $_SESSION['usuario_completo'] = $row['nombre_completo'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $rol_sesion = $rol_obtenido ?: 'inspector';
                    if ($rol_sesion === 'user') {
                        $rol_sesion = 'inspector';
                    }
                    $_SESSION['usuario_rol'] = $rol_sesion;
                    header("Location: dashboard.php");
                    exit;
                } else {
                    LoginRateLimit::registrarIntentoFallido($usuario);
                    $error = 'Usuario o contraseña incorrectos';
                }
            } else {
                LoginRateLimit::registrarIntentoFallido($usuario);
                $error = 'Usuario o contraseña incorrectos';
            }
            $stmt->close();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Ferretería</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at 50% 15%, rgba(25, 113, 218, 0.2), rgba(7, 10, 18, 0) 35%),
                #070a12;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: absolute;
            border: 1px solid rgba(56, 72, 104, 0.35);
            border-radius: 16px;
            width: 34vw;
            max-width: 420px;
            min-width: 260px;
            height: 72vh;
            max-height: 560px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }

        body::before {
            left: -6vw;
        }

        body::after {
            right: -6vw;
        }
        
        .login-container {
            background: rgba(23, 28, 39, 0.86);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(130, 148, 183, 0.2);
            width: 100%;
            max-width: 380px;
            padding: 30px 26px 22px;
            position: relative;
            z-index: 2;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 22px;
        }

        .login-header .brand-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            width: 100%;
        }

        .login-header .brand-icon {
            width: 26px;
            height: 26px;
            object-fit: contain;
            flex-shrink: 0;
        }
        
        .login-header h1 {
            font-size: 18px;
            color: rgba(232, 239, 255, 0.95);
            margin-bottom: 0;
            white-space: normal;
            line-height: 1;
            text-align: center;
        }

        .login-header .welcome {
            margin-top: 8px;
            color: #f5f7ff;
            font-size: 30px;
            font-weight: 700;
        }

        .login-header .welcome-sub {
            margin-top: 2px;
            color: rgba(208, 218, 236, 0.78);
            font-size: 13px;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: none;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(123, 139, 171, 0.32);
            border-radius: 6px;
            font-size: 13px;
            transition: border-color 0.3s;
            background: rgba(8, 12, 20, 0.72);
            color: #eef3ff;
            caret-color: #eef3ff;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon .field-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            opacity: 0.95;
            pointer-events: none;
            filter: brightness(0) invert(0.92);
        }

        .input-with-icon input {
            padding-left: 34px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #2d8cff;
            box-shadow: 0 0 0 3px rgba(45, 140, 255, 0.22);
        }

        .form-group input::placeholder {
            color: rgba(201, 212, 233, 0.8);
        }

        .form-group input:-webkit-autofill,
        .form-group input:-webkit-autofill:hover,
        .form-group input:-webkit-autofill:focus,
        .form-group input:-webkit-autofill:active {
            -webkit-text-fill-color: #eef3ff;
            box-shadow: 0 0 0 1000px rgba(8, 12, 20, 0.88) inset;
            caret-color: #eef3ff;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        .error-message {
            background: rgba(187, 34, 55, 0.16);
            color: #ffd6de;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 126, 148, 0.35);
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(180deg, #1884f5 0%, #1069c6 100%);
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 105, 198, 0.38);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: rgba(197, 209, 231, 0.72);
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="brand-title">
                <h1>Ferreteria Ferrocampo</h1>
                <img src="assets/icons/ferreteria.png" alt="Icono de ferretería" class="brand-icon">
            </div>
            <p class="welcome">Bienvenido</p>
            <p class="welcome-sub">Ingresa tus credenciales para continuar</p>
        </div>
        
        <form method="POST">
            <div class="error-message <?php echo !empty($error) ? 'show' : ''; ?>">
                <?php echo htmlspecialchars($error); ?>
            </div>
            
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <div class="input-with-icon">
                    <img src="assets/icons/usuario.png" alt="Icono de usuario" class="field-icon">
                    <input type="text" id="usuario" name="usuario" placeholder="email address" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <div class="input-with-icon">
                    <img src="assets/icons/candado.png" alt="Icono de contraseña" class="field-icon">
                    <input type="password" id="contrasena" name="contrasena" placeholder="Password" required>
                </div>
            </div>
            
            <button type="submit" class="btn-login"><img src="assets/icons/iniciar-sesion.png" alt="Iniciar sesión" style="width:18px;height:18px;object-fit:contain;">Iniciar Sesión</button>
        </form>
        
        <div class="login-footer">
            <p>Sistema de Gestión de Inventarios</p>
        </div>
    </div>
</body>
</html>
