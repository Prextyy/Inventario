# Control Total (app portable)

Aplicación para administrar lo que antes llevabas en **CONTROL TOTAL.xlsx**:

| Módulo | Qué guarda | Hojas que importa |
|---|---|---|
| 📋 Requisiciones | Almacén, UO, material, fecha, importe, REQ, OC, recepción, estatus, comentarios, cuenta contable | `REQUI TIJ 2026` (+ las que solo estén en `REQUI MXC 2026`) |
| 🚚 Panamericano | Folio fiscal, fechas, subtotal, Sol Int, OC, despacho, aprobación, recepción, supply, prog. pago, estatus | `PANAMERICANO TIJ 2026` y `PANAMERICANO MXC 2026` |
| 🧾 Servicios | Proveedor, servicio, factura, REQ, OC, recepción, supply, comentarios | `SERVICIOS 2026` |
| 💳 Tarjetas | Dispersión (Broxel), Combustible e IAVE | `Tarjetas Dispersion FNA TIJ`, `TARJETAS DE COMBUSTIBLE`, `TARJETAS IAVE` |

Las hojas de 2024/2025 y las demás (Mtto, Reclasificación, etc.) **no** se importan.

## Cómo usarla

**Opción A – Ejecutable (no necesita instalar nada)**
1. Descarga `ControlTotal-portable-windows` desde la pestaña *Actions* del repositorio (se genera solo en cada cambio), o genéralo tú con `CONSTRUIR_EXE.bat`.
2. Copia `ControlTotal.exe` a la carpeta o USB donde quieras trabajar.
3. Doble clic → se abre en tu navegador. La base de datos `control_total.db` se crea junto al .exe.

**Opción B – Con Python**
Doble clic en `INICIAR.bat` (o `python app.py`). No requiere instalar librerías.

Para cerrar la app, cierra la ventana negra.

## Primera vez: importar tu Excel
Menú **📥 Importar Excel** → arrastra `CONTROL TOTAL.xlsx` → listo.
También desde consola: `python app.py --importar "CONTROL TOTAL.xlsx"`.

- Importar **reemplaza** los datos del año elegido en los módulos marcados (los demás años no se tocan).
- Antes de cada importación se guarda un respaldo automático en la carpeta `respaldos/`.
- Panamericano: las hojas TIJ y MXC traen las mismas facturas, pero cada una lleva al día el estatus de su sucursal; la app toma cada factura de la hoja de **su** sucursal.

## Funciones
- Inicio con totales, importes y pendientes por estatus (clic en un estatus para filtrar).
- Tablas con búsqueda (`/`), filtros por estatus, almacén/sucursal, "solo pendientes" y orden por columna.
- Alta (`N`), edición (clic en la fila), duplicar y eliminar. Guardar con `Ctrl+Enter`.
- Sugerencias automáticas en los campos (estatus, almacén, proveedor…) con lo que ya capturaste.
- Selector de año (2026, 2027…) para Requis, Panamericano y Servicios.
- Exportar cada módulo a CSV (abre en Excel), descargar respaldo y bitácora de todos los cambios.

## Mover tus datos
Todo vive en un solo archivo: `control_total.db`. Para llevártelo a otra computadora copia la carpeta completa
(el `.exe` + `control_total.db`). Este archivo **no** se sube a GitHub (`.gitignore`).

## Para modificarla
- `modulos.py` – campos de cada módulo. Si agregas un campo, la app lo crea en la base al iniciar sin perder datos.
- `importador.py` – qué columnas del Excel van a qué campo.
- `app.py` – servidor local (solo librería estándar de Python) + SQLite.
- `static/` – interfaz (HTML/CSS/JS sin dependencias).
