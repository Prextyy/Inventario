# Changelog - Sistema de Inventario

Todos los cambios notables en este proyecto serán documentados en este archivo.

## [1.0.0] - 2026-03-10

### Agregado
- ✨ Sistema de instalación interactivo tipo WordPress
- ✨ Sistema de autenticación con roles (admin/usuario)
- ✨ Dashboard con estadísticas en tiempo real
- ✨ Gestión completa de productos (CRUD)
- ✨ Sistema de categorías para organizar productos
- ✨ Módulo de entrada rápida con soporte de teclado
- ✨ Integración de escáner QR/códigos de barras
- ✨ Registro de movimientos (entradas/salidas)
- ✨ Alertas de stock bajo automáticas
- ✨ Exportación de inventario completo a PDF
- ✨ Exportación de inventario completo a Excel
- ✨ Exportación de lista de productos (sin cantidades) a Excel
- ✨ Interfaz moderna y responsive
- ✨ Atajos de teclado para navegación rápida
- ✨ Historial de movimientos por producto
- ✨ Generación automática de códigos de producto
- ✨ Cálculo de valor total del inventario
- ✨ Sistema de búsqueda y filtrado

### Características Técnicas
- PHP puro sin dependencias de Composer
- Base de datos MySQL/MariaDB
- Diseño responsive con CSS Grid y Flexbox
- Seguridad: passwords hasheados, prevención SQL injection
- UTF-8 completo para español
- Zona horaria configurable
- Soporte para Apache, Nginx, IIS

### Documentación
- README.md con instrucciones completas
- INSTALL.html para instalación visual
- Guía de descarga de librería QR
- Comentarios en código
- Archivo de configuración de ejemplo

### Seguridad
- Protección de archivos sensibles con .htaccess
- Validación y sanitización de inputs
- Prevención XSS y clickjacking
- Sesiones seguras
- Claves de seguridad únicas generadas en instalación

---

## Próximas Versiones Planeadas

### [1.1.0] - Futuro
- [ ] Generador de códigos QR para productos
- [ ] Importación masiva desde Excel
- [ ] Gráficos de inventario
- [ ] Reportes personalizados
- [ ] Notificaciones por email

### [1.2.0] - Futuro
- [ ] API REST para integraciones
- [ ] Aplicación móvil
- [ ] Modo offline
- [ ] Múltiples almacenes
- [ ] Control de préstamos

---

**Formato basado en [Keep a Changelog](https://keepachangelog.com/)**
**Versionamiento: [Semantic Versioning](https://semver.org/)**
