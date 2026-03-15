# Ferreteria Ferrocampo - Deploy en GitHub y Railway con Docker

Este proyecto ya incluye Docker para ejecutar la app en Railway.

## 1) Probar Docker localmente (opcional)

```bash
docker build -t ferrocampo .
docker run --rm -p 8080:80 ferrocampo
```

Abrir en navegador:

```text
http://localhost:8080
```

## 2) Subir a GitHub

Si aun no tienes repositorio git inicializado:

```bash
git init
git add .
git commit -m "chore: preparar deploy docker para railway"
```

Crear repositorio vacio en GitHub y luego vincularlo:

```bash
git remote add origin https://github.com/TU_USUARIO/TU_REPO.git
git branch -M main
git push -u origin main
```

## 3) Desplegar en Railway

1. En Railway: New Project > Deploy from GitHub Repo.
2. Selecciona este repositorio.
3. Railway detectara el `Dockerfile` y construira el contenedor.
4. En Variables del servicio web, define las credenciales MySQL.

Variables recomendadas (Railway MySQL):

- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLDATABASE`

Tu `modules/core/config.php` ya soporta estas variables y tambien variantes con prefijo (`MYSQL_MYSQLHOST`, etc.) y `DATABASE_URL`.

## 4) Base de datos en Railway

1. En el proyecto de Railway agrega un servicio MySQL.
2. Copia variables del servicio MySQL al servicio web (si Railway no las inyecta automaticamente en tu plantilla).
3. Redeploy del servicio web.

## 5) Verificar

- Debe cargar login/dashboard.
- Debe permitir crear/consultar productos.
- Si hay errores de conexion, revisar variables de entorno en Railway.
