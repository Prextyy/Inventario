# Descarga de Librería Html5-QRCode

Para que el escáner QR funcione correctamente, necesitas descargar la librería Html5-QRCode.

## Opción 1: Usar CDN (Recomendado para producción)

Edita el archivo `quick-entry.php` y reemplaza:

```html
<script src="assets/js/html5-qrcode.min.js"></script>
```

Por:

```html
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
```

## Opción 2: Descarga Local

1. Ve a: https://github.com/mebjas/html5-qrcode/releases
2. Descarga el archivo `html5-qrcode.min.js` de la última versión
3. Colócalo en: `assets/js/html5-qrcode.min.js`
4. Reemplaza el archivo placeholder existente

## Opción 3: Descarga directa desde CDN

Puedes descargar directamente desde:
```
https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js
```

Y guardarlo en `assets/js/html5-qrcode.min.js`

## Verificación

Para verificar que la librería funciona:

1. Abre `quick-entry.php` en tu navegador
2. Haz clic en "Activar Escáner QR"
3. Permite el acceso a la cámara cuando se solicite
4. Si ves la vista de la cámara, ¡todo está funcionando!

## Solución de Problemas

**Error: "Escáner QR no disponible"**
- La librería no se cargó correctamente
- Verifica la ruta del archivo
- Revisa la consola del navegador para errores

**No se puede acceder a la cámara:**
- Usa HTTPS (algunos navegadores lo requieren)
- Verifica permisos de cámara en tu navegador
- En Chrome: Configuración > Privacidad > Configuración del sitio > Cámara

**La cámara no enfoca bien:**
- Ajusta la iluminación
- Acerca/aleja el código QR
- Prueba con diferentes códigos QR o códigos de barras
