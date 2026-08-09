# 📦 Sistema de Inventario para Papelería

Sistema completo de gestión de inventario desarrollado en PHP puro, sin dependencias externas como Composer. Ideal para administrar productos de papelería con entrada rápida por teclado y escáner QR.

## 🚀 Características

- ✅ **Instalación tipo WordPress** - Asistente de instalación sencillo y visual
- ✅ **PHP Puro** - Sin Composer ni dependencias externas
- ✅ **Entrada Rápida** - Interfaz optimizada para uso con teclado
- ✅ **Escáner QR** - Soporte para escaneo de códigos QR/códigos de barras
- ✅ **Exportación PDF** - Genera reportes de inventario en PDF
- ✅ **Exportación Excel** - Descarga datos en formato Excel (.xls)
- ✅ **Lista de Productos** - Exporta listado sin cantidades para compartir
- ✅ **Gestión de Movimientos** - Registro de entradas y salidas
- ✅ **Alertas de Stock Bajo** - Notificaciones cuando productos tienen poco inventario
- ✅ **Interfaz Moderna** - Diseño responsive y profesional
- ✅ **Categorías** - Organización de productos por categorías
- ✅ **Multi-usuario** - Sistema de autenticación con roles

## 📋 Requisitos

- PHP 7.0 o superior
- MySQL 5.6 o superior / MariaDB
- Servidor web (Apache, Nginx, o IIS)
- Navegador moderno con soporte para cámara (para QR scanner)

## 🔧 Instalación

1. **Descarga el sistema** en tu servidor web
   ```
   f:\Inventario\
   ```

2. **Configura tu base de datos MySQL**
   - Crea una base de datos nueva (ej: `inventario`)
   - Anota el usuario y contraseña de MySQL

3. **Accede al instalador**
   - Abre tu navegador y ve a: `http://tu-dominio/install/`
   - El asistente te guiará paso a paso

4. **Completa la instalación**
   - **Paso 1:** Ingresa los datos de conexión a la base de datos
   - **Paso 2:** Crea tu usuario administrador
   - **Paso 3:** ¡Listo! Accede al sistema

5. **Librería QR Scanner (Opcional)**
   - Para usar el escáner QR, descarga la librería html5-qrcode
   - Opción 1: Descarga desde https://github.com/mebjas/html5-qrcode
   - Opción 2: Reemplaza en `quick-entry.php` la línea:
     ```html
     <script src="assets/js/html5-qrcode.min.js"></script>
     ```
     Por:
     ```html
     <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
     ```

## 📁 Estructura de Archivos

```
Inventario/
├── assets/
│   ├── css/
│   │   └── style.css          # Estilos del sistema
│   └── js/
│       └── html5-qrcode.min.js # Librería para escáner QR
├── includes/
│   ├── auth.php               # Sistema de autenticación
│   ├── functions.php          # Funciones principales
│   ├── header.php             # Encabezado común
│   ├── footer.php             # Pie de página común
│   └── pdf-generator.php      # Generador de PDF
├── install/
│   ├── index.php              # Asistente de instalación
│   └── install-database.php   # Creador de base de datos
├── uploads/                   # Carpeta para archivos subidos
├── index.php                  # Página de login
├── dashboard.php              # Dashboard principal
├── products.php               # Gestión de productos
├── quick-entry.php            # Entrada rápida con QR
├── export.php                 # Exportación de datos
├── logout.php                 # Cerrar sesión
└── config.php                 # Archivo de configuración (creado en instalación)
```

## 💡 Uso del Sistema

### Dashboard
- Visualiza estadísticas generales del inventario
- Ve productos con stock bajo
- Revisa movimientos recientes
- Accesos rápidos a funciones principales

### Gestión de Productos
- **Agregar:** Crea nuevos productos con código único
- **Editar:** Modifica información de productos existentes
- **Movimientos:** Registra entradas y salidas de inventario
- **Eliminar:** Borra productos (con confirmación)
- **Buscar:** Filtra por nombre, código o categoría

### Entrada Rápida
**Optimizada para uso diario con teclado:**

- **F2** - Enfocar búsqueda
- **F3** - Activar/Desactivar escáner QR
- **Tab** - Navegar entre campos
- **Enter** - Buscar / Registrar
- **Esc** - Nueva búsqueda

**Flujo de trabajo:**
1. Escanea o escribe el código del producto
2. El sistema muestra la información del producto
3. Selecciona tipo de movimiento (Entrada/Salida)
4. Ingresa cantidad y razón
5. Registra el movimiento

### Exportación de Datos

**PDF - Inventario Completo**
- Reporte profesional con todos los productos
- Incluye cantidades, precios y valores
- Listo para imprimir

**Excel - Inventario Completo**
- Todas las columnas y detalles
- Cantidades exactas y precios
- Para análisis interno

**Excel - Lista de Productos**
- Solo nombres y códigos de productos
- NO incluye cantidades exactas (solo disponibilidad)
- Ideal para compartir con trabajadores

## 🔐 Seguridad

- Contraseñas hasheadas con `password_hash()` de PHP
- Protección contra SQL Injection
- Sistema de sesiones seguras
- Separación de roles (admin/usuario)

## ⚙️ Configuración

Después de la instalación, puedes modificar:

**Zona Horaria** (en `config.php`):
```php
date_default_timezone_set('America/Mexico_City');
```

**Nombre del Sitio** (en `config.php`):
```php
define('SITE_NAME', 'Tu Nombre Aquí');
```

## 📊 Base de Datos

El sistema crea automáticamente estas tablas:

- **users** - Usuarios del sistema
- **categories** - Categorías de productos
- **products** - Productos del inventario
- **movements** - Historial de movimientos
- **settings** - Configuraciones del sistema

## 🎨 Personalización

**Colores del Sistema** (en `assets/css/style.css`):
```css
:root {
    --primary: #667eea;      /* Color principal */
    --secondary: #764ba2;    /* Color secundario */
    --success: #10b981;      /* Verde éxito */
    --danger: #ef4444;       /* Rojo peligro */
    --warning: #f59e0b;      /* Naranja advertencia */
}
```

## 🆘 Soporte y Problemas Comunes

**No se puede conectar a la base de datos:**
- Verifica usuario y contraseña de MySQL
- Asegúrate de que MySQL esté ejecutándose
- Confirma el nombre del host (usualmente 'localhost')

**El escáner QR no funciona:**
- Verifica que hayas descargado/cargado la librería html5-qrcode
- Permite el acceso a la cámara cuando el navegador lo solicite
- Usa HTTPS si es posible (requerido en algunos navegadores)

**Error al exportar PDF:**
- Verifica permisos de escritura en el servidor
- Asegúrate de que no haya errores de PHP

## 📝 Licencia

Este sistema es de código abierto y puede ser modificado según tus necesidades.

## 🤝 Contribuciones

Para mejoras o sugerencias, puedes modificar el código según tus requerimientos.

---

**Desarrollado con ❤️ usando PHP puro**

**Versión:** 1.0.0
**Última actualización:** Marzo 2026
