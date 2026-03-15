// Script para detectar actualizaciones del sistema
// Se ejecuta cada 5 segundos y muestra un banner si hay nueva versión

(function() {
    let versionActual = null;
    let esPrimeraVerificacion = true;
    
    // Obtener versión inicial
    function obtenerVersion() {
        fetch('/version.php?_=' + Math.random()) // Evitar cache
            .then(response => response.json())
            .then(data => {
                const versionServidor = data.timestamp;
                
                if (esPrimeraVerificacion) {
                    // Primera vez - guardar versión sin mostrar banner
                    versionActual = versionServidor;
                    localStorage.setItem('systemVersion', versionActual);
                    esPrimeraVerificacion = false;
                } else if (versionServidor && versionServidor != versionActual && versionServidor > versionActual) {
                    // Hay una versión más reciente disponible
                    versionActual = versionServidor;
                    localStorage.setItem('systemVersion', versionActual);
                    mostrarBannerActualizacion();
                }
            })
            .catch(error => console.log('No se pudo verificar actualizaciones'));
    }
    
    // Mostrar banner de actualización
    function mostrarBannerActualizacion() {
        // Verificar si el banner ya existe
        if (document.getElementById('banner-actualizacion')) {
            return;
        }
        
        const banner = document.createElement('div');
        banner.id = 'banner-actualizacion';
        banner.innerHTML = `
            <div style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 20px;
                text-align: center;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10001;
                font-weight: bold;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <span>🔄 Actualización disponible - Presiona <strong>F5</strong> para recargar y ver los cambios</span>
                <button onclick="this.parentElement.parentElement.remove()" style="
                    background: rgba(255,255,255,0.3);
                    border: none;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-weight: bold;
                ">✕</button>
            </div>
        `;
        document.body.insertBefore(banner, document.body.firstChild);
    }
    
    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            obtenerVersion();
            setInterval(obtenerVersion, 5000);
        });
    } else {
        obtenerVersion();
        setInterval(obtenerVersion, 5000);
    }
})();
