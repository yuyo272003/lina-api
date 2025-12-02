# Sistema LINA

Este documento detalla el proceso completo para configurar el entorno de desarrollo completo del Backend (Laravel).

---

## I. Requisitos Previos

Asegúrese de tener instalado el siguiente software:

* **Laragon** (con Apache, MySQL, PHP 8.2+)
* **Composer** (gestor de dependencias de PHP)
* **Git** (para clonar el repositorio)
* **K6** (herramienta de pruebas de rendimiento)

---

## II. Configuración del Backend (Laravel)

### 1. Clonar e Instalar Dependencias

```bash
# 1. Clonar el repositorio
git clone https://github.com/yuyo272003/lina-api.git lina-api
cd lina-api

# 2. Instalar dependencias de Laravel
composer install
```

### 2. Variables de Entorno (.env)

Copie la plantilla de variables de entorno y configúrelas correctamente.

```bash
cp .env.example .env
php artisan key:generate
```

En el archivo `.env`, configure los parámetros más importantes:

**Base de Datos:**

* DB_HOST
* DB_DATABASE
* DB_USERNAME
* DB_PASSWORD

**Rendimiento y OAuth (CRUCIAL):**

```ini
# USAMOS LARAGON (PUERTO 80)
APP_URL=http://localhost

# La URL a la que Microsoft debe devolver la respuesta de login
OAUTH_REDIRECT_URI=http://localhost/callback
```

### 3. Preparación de la Base de Datos

Ejecute las migraciones y seeders.

```bash
php artisan migrate --seed
```

---

## III. Arranque del Backend y Optimización

**IMPORTANTE:** La optimización se basa en que Apache sirva directamente la carpeta **public** para un rendimiento óptimo.

### 1. Configuración de Laragon (Document Root)

1. Inicie Laragon (Apache y MySQL deben estar activos).
2. Abra **Preferencias (⚙️)**.
3. En la pestaña **General**, cambie **Document Root**.
4. Seleccione la carpeta:
   `C:\...\lina-api\public`

---

## IV. Pruebas de Rendimiento (K6)

Pruebas configuradas para 200 usuarios concurrentes.

### 1. Instalación de K6

**Windows (winget):**

```bash
winget install k6
```

### 2. Ejecución de la Prueba

Ejecute el script desde la carpeta `lina-api`:

```bash
k6 run tests/performance/prueba_carga.js
```

---

## ✔️ Listo para colaborar

Tu entorno queda completamente configurado para trabajar con el sistema LINA, desarrollar nuevas funcionalidades y validar su rendimiento.

---

## V. Flujo de Trabajo con Ramas (Git)
Para mantener un control limpio y organizado del código, siga este flujo de trabajo recomendado:

### 1. Crear una nueva rama para cada cambio
Nunca trabaje directamente en la rama **main** o **master**. Cree una rama nueva basada en main:
```bash
git checkout main
git pull
git checkout -b nombre-de-tu-rama
```

### 2. Realizar cambios y subirlos
Después de completar tus modificaciones:
```bash
git add .
git commit -m "Descripción clara del cambio"
git push origin nombre-de-tu-rama
```

### 3. Crear un Pull Request
1. Abra GitHub y vaya al repositorio.
2. Seleccione su rama recién subida.
3. Haga clic en **Create Pull Request**.
4. Agregue una descripción detallada de lo que se cambió.
5. Solicite revisión dependiendo del flujo del equipo.

### 4. Aprobación y Merge
Una vez revisado y aprobado, podrá hacer **merge** a la rama principal.

> ✔️ *Este flujo garantiza orden, control y evita conflictos innecesarios.*

