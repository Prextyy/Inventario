# 🚀 Guía Rápida - Sistema de Solicitudes de Productos

¡Se han agregado nuevas funcionalidades al sistema de inventario! Aquí te mostramos cómo usarlas.

## 📋 Antes de Empezar

### ✅ Checklist de Actualización

Sigue estos pasos en orden:

1. **Ejecutar Actualización de BD** (Si es la primera vez)
   - Ir a: `https://tu-sitio/inventario/upgrade-db.php`
   - Inicia sesión como Administrador
   - Revisa los cambios
   - Haz clic en "Ejecutar Actualización"
   - Espera a que se complete

2. **Verificar Instalación**
   - Ir a: `https://tu-sitio/inventario/check-update.php`
   - Debe mostrar todos los items en verde (✓)

3. **Recargar la Página**
   - F5 o actualizar navegador
   - Deberías ver nuevos enlaces en el menú

## 👥 Para Usuarios (Empleados)

### Crear Solicitud de Producto del Inventario

**Pasos:**
1. Haz clic en **"Mis Solicitudes"** en el menú superior
2. Verás tres tabs:
   - **📦 Tab 1: Productos Disponibles** ← Empieza aquí
   - ➕ Tab 2: Solicitud Personalizada
   - 📋 Tab 3: Mis Solicitudes

3. En **Tab 1 (Productos Disponibles):**
   - Ves todos los productos con:
     - 🖼️ Imagen del producto
     - 📝 Nombre y código
     - 🟢/🟡 Disponibilidad (Verde=Hay stock, Amarillo=Stock bajo, Rojo=Sin stock)
   
4. Ingresa la cantidad que necesitas
5. Haz clic en **"Solicitar"**
6. ¡Listo! Tu solicitud se crea y el administrador la revisará

### Crear Solicitud Personalizada (Producto no en Inventario)

**Cuándo usar:**
- Necesitas un producto que NO está en el inventario
- Necesitas algo que no tenemos catalogado

**Pasos:**
1. Haz clic en **"Mis Solicitudes"**
2. Vete al **Tab 2: Solicitud Personalizada**
3. Completa el formulario:
   - **Nombre del Producto**: Ej "Tinta negra Brother LC129"
   - **Descripción** (opcional): Detalles adicionales
   - **Cantidad**: 5 unidades
   - **Notas** (opcional): "Para impresora LED2"
4. Haz clic en **"Enviar Solicitud Personalizada"**
5. El administrador revisará y te dirá si se aprueba

### Ver Estado de Mis Solicitudes

**Pasos:**
1. Haz clic en **"Mis Solicitudes"**
2. Vete al **Tab 3: Mis Solicitudes**
3. Ves lista de todas tus solicitudes con:
   - 📦 Producto
   - 📅 Fecha de solicitud
   - 🏷️ Estado:
     - **🟨 Pendiente** - Esperando aprobación
     - **🟩 Aprobado** - Admin aprobó, en preparación
     - **🟥 Rechazado** - Admin rechazó
     - **🟦 Entregado** - ¡Ya tienes tu producto!

## 🛡️ Para Administrador

### Gestionar Solicitudes

**Acceso:**
1. Haz clic en **"Gestionar Solicitudes"** en el menú
   - (Solo aparece si eres admin)

**Panel:**
- Ver estadísticas en la parte superior:
  - 📋 Solicitudes Pendientes
  - ➕ Solicitudes Personalizadas Pendientes
  - ✅ Aprobadas Hoy

**Tab 1: Solicitudes de Productos**
- Ves todas las solicitudes de productos catalogados
- Para cada una:
  - Información: Producto, Área, Usuario, Cantidad, Fecha
  - Acciones:
    - ✅ **Aprobar** - El área recibirá el producto
    - ❌ **Rechazar** - Declinar la solicitud
    - ✓ **Marcar Entregado** - Ya entregado (después de aprobar)

**Tab 2: Solicitudes Personalizadas**
- Ves solicitudes de productos nuevos (no catalogados)
- Mismas acciones:
  - Aprobar / Rechazar / Marcar Completado

### Agregar Imagen a Producto

**Cuándo:**
- Creando nuevo producto
- Editando producto existente
- Queriendo reemplazar imagen actual

**Pasos:**
1. Ir a **"Productos"** → Editar producto (✏️)
2. Baja hasta encontrar sección: **"🖼️ Imagen del Producto"**
3. Verás:
   - A la izquierda: Imagen actual (o 📦 placeholder)
   - A la derecha: Formulario para cargar nueva
4. Haz clic en **"Seleccionar Archivo"**
5. Elige imagen:
   - ✅ Formatos: JPG, PNG, GIF, WebP
   - ⚠️ Máximo: 5MB
6. Haz clic en **"Guardar Imagen"**
7. ¡Listo! Imagen se actualiza automáticamente

**Consejo:** Las imágenes ayudan mucho a los usuarios a identificar qué solicitan.

### Ver Estadísticas

**En Dashboard:**
- Ahora verás dos nuevas tarjetas:
  - 🔔 **Solicitudes Pendientes** - Total esperando aprobación
  - ✅ **Solicitudes Aprobadas Hoy** - Contador de hoy
  - Haz clic en "Ver todas →" para ir al panel completo

## 🔍 Troubleshooting

### "Mi usuario no está asignado a un área"
**Problema:** Ves este mensaje al intentar solicitar
- **Causa:** Tu admin aún no te asignó área
- **Solución:** Contacta admin para que te asigne a tu departamento

### "Botones de solicitud no aparecen"
**Problema:** En "Mis Solicitudes" no ves el formulario para solicitar
- **Causa:** Posible que BD aún no esté actualizada
- **Solución:** 
  1. Ve a `check-update.php`
  2. Verifica que todo sea verde ✓
  3. Si no, ejecuta `upgrade-db.php`

### "Error al subir imagen"
**Problema:** Cuando intento agregar imagen a producto, error
- **Solución:**
  1. Verifica que archivo es JPG/PNG/GIF/WebP
  2. Verifica que es menos de 5MB
  3. Intenta con una imagen diferente
  4. Contacta admin si persiste

### Sistema dice "Tabla no existe"
**Problema:** Error de base de datos
- **Solución:** Ve a `upgrade-db.php` y ejecuta actualización

## 📞 Características Principales

✅ Solicitudes de productos catalogados  
✅ Solicitudes personalizadas (productos nuevos)  
✅ Imágenes de productos  
✅ Seguimiento de solicitudes  
✅ Notificaciones de estado  
✅ Estadísticas de solicitudes  
✅ Historial completo  

## ⚠️ Importante

- Solo **Administradores** pueden aprobar/rechazar solicitudes
- Las imágenes se guardan automáticamente
- Las solicitudes son **por área** - cada usuario solo ve su área
- Los productos sin imagen muestran 📦 (placeholder)

## 🎓 Casos de Uso Comunes

### Caso 1: Departamento de RH necesita bolígrafos
1. Usuario de RH: Ir a "Mis Solicitudes"
2. Buscar "bolígrafos" en la lista
3. Verificar que hay stock (verde)
4. Solicitar 50 unidades
5. Admin aprueba
6. SE entrega en RH

### Caso 2: Marketing necesita un producto nuevo no catálogo
1. Usuario de Marketing: "Mis Solicitudes"
2. Tab 2: Solicitud Personalizada
3. Nombre: "Stickers personalizados"
4. Descripción: "200x200mm, logo empresa"
5. Enviar
6. Admin revisa y aprueba o rechaza
7. Si aprueba: puede agregarlo al inventario luego

### Caso 3: Admin agregar imagen a producto existente
1. Ir a "Productos"
2. Click editar sobre "Papel Tamaño Carta"
3. Bajar a "Imagen del Producto"
4. Cargar foto del papel
5. ¡Listo! Usuarios ahora ven la imagen

---

## 📚 Más Información

Ver documentos completos:
- `ACTUALIZACION_MARZO_2026.md` - Cambios técnicos
- `README.md` - Manual completo del sistema
- `GUIA_COMPLETA.md` - Guía detallada

¡Que disfrutes del nuevo sistema! 🎉
