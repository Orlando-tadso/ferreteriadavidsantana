<?php
/**
 * Archivo de seguridad - Funciones para proteger la aplicación
 */

// Prevenir acceso directo
if (!defined('SECURITY_LOADED')) {
    define('SECURITY_LOADED', true);
}

/**
 * Protección contra ataques de fuerza bruta en login
 */
class LoginRateLimit {
    private static $max_intentos = 5;
    private static $tiempo_bloqueo = 900; // 15 minutos en segundos
    
    public static function verificarIntentos($usuario) {
        if (!isset($_SESSION['login_intentos'])) {
            $_SESSION['login_intentos'] = [];
        }
        
        $ahora = time();
        $intentos = $_SESSION['login_intentos'][$usuario] ?? [];
        
        // Limpiar intentos antiguos (más de 15 minutos)
        $intentos = array_filter($intentos, function($tiempo) use ($ahora) {
            return ($ahora - $tiempo) < self::$tiempo_bloqueo;
        });
        
        if (count($intentos) >= self::$max_intentos) {
            $tiempo_restante = self::$tiempo_bloqueo - ($ahora - min($intentos));
            $minutos = ceil($tiempo_restante / 60);
            throw new Exception("Demasiados intentos fallidos. Espera $minutos minutos.");
        }
        
        $_SESSION['login_intentos'][$usuario] = $intentos;
    }
    
    public static function registrarIntentoFallido($usuario) {
        if (!isset($_SESSION['login_intentos'])) {
            $_SESSION['login_intentos'] = [];
        }
        if (!isset($_SESSION['login_intentos'][$usuario])) {
            $_SESSION['login_intentos'][$usuario] = [];
        }
        $_SESSION['login_intentos'][$usuario][] = time();
    }
    
    public static function limpiarIntentos($usuario) {
        if (isset($_SESSION['login_intentos'][$usuario])) {
            unset($_SESSION['login_intentos'][$usuario]);
        }
    }
}

/**
 * Protección CSRF (Cross-Site Request Forgery)
 */
class CSRF {
    public static function generarToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function verificarToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new Exception('Token de seguridad inválido');
        }
        return true;
    }
    
    public static function campoHtml() {
        $token = self::generarToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

/**
 * Sanitización de entradas
 */
class Sanitize {
    public static function texto($texto) {
        return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
    }
    
    public static function numero($numero) {
        return filter_var($numero, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    public static function entero($numero) {
        return (int) filter_var($numero, FILTER_SANITIZE_NUMBER_INT);
    }
    
    public static function email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

/**
 * Validación de entradas
 */
class Validate {
    public static function requerido($valor, $nombre) {
        if (empty(trim($valor))) {
            throw new Exception("El campo $nombre es requerido");
        }
        return true;
    }
    
    public static function longitud($valor, $min, $max, $nombre) {
        $len = strlen($valor);
        if ($len < $min || $len > $max) {
            throw new Exception("$nombre debe tener entre $min y $max caracteres");
        }
        return true;
    }
    
    public static function numeroPositivo($valor, $nombre) {
        if (!is_numeric($valor) || $valor < 0) {
            throw new Exception("$nombre debe ser un número positivo");
        }
        return true;
    }
}

/**
 * Prevención de inyección SQL adicional
 */
class SQLSafe {
    public static function escaparLike($valor, $conn) {
        // Escapar caracteres especiales de LIKE
        $valor = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
        return $conn->real_escape_string($valor);
    }
}

/**
 * Headers de seguridad
 */
function establecerHeadersSeguridad() {
    // Prevenir clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevenir XSS
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevenir MIME sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Política de referrer
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy - Permitir Chart.js CDN
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
}

/**
 * Validar que la sesión no haya sido secuestrada
 */
function validarSesion() {
    // Verificar que la sesión tenga los datos necesarios
    if (!isset($_SESSION['usuario_id'])) {
        session_destroy();
        header('Location: login.php?error=sesion_invalida');
        exit;
    }
    
    // Registrar IP para protección contra secuestro
    if (!isset($_SESSION['ip_address'])) {
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    // Nota: No destruimos sesión por cambio de IP (puede ocurrir con proxies/balanceadores)
}
