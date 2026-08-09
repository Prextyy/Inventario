<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

function normalizeScannedCode(string $code): string {
    $code = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $code) ?? '');

    // Algunos lectores agregan un identificador AIM, por ejemplo ]C1 en GS1-128.
    if (preg_match('/^\][A-Za-z][0-9]/', $code) === 1) {
        $code = substr($code, 3);
    }

    return trim($code);
}

function findProductByScannedCode(string $rawCode): ?array {
    $code = normalizeScannedCode($rawCode);
    if ($code === '' || strlen($code) > 128) {
        return null;
    }

    $candidates = [$code];

    // UPC-A puede ser reportado por algunas cámaras como EAN-13 con cero inicial.
    if (ctype_digit($code) && strlen($code) === 12) {
        $candidates[] = '0' . $code;
    } elseif (ctype_digit($code) && strlen($code) === 13 && str_starts_with($code, '0')) {
        $candidates[] = substr($code, 1);
    }

    foreach (array_unique($candidates) as $candidate) {
        $product = getProductByCode($candidate);
        if ($product) {
            $product['_scanned_code'] = $code;
            $product['_matched_code'] = $candidate;
            return $product;
        }
    }

    return null;
}

function processQuickScanMovement(string $scan_code, string $scan_type, int $scan_quantity, string $scan_reason): array {
    $scan_code = normalizeScannedCode($scan_code);
    $scan_reason = trim($scan_reason);

    if ($scan_code === '') {
        return [
            'success' => false,
            'message' => 'No se recibió un código válido.'
        ];
    }

    $scan_product = findProductByScannedCode($scan_code);
    if (!$scan_product) {
        return [
            'success' => false,
            'message' => 'No existe un producto con el código ' . $scan_code . '.'
        ];
    }

    if (!in_array($scan_type, ['entrada', 'salida'], true)) {
        $scan_type = 'entrada';
    }

    if ($scan_quantity < 1 || $scan_quantity > 100000) {
        return [
            'success' => false,
            'message' => 'La cantidad debe estar entre 1 y 100,000 unidades.'
        ];
    }

    if ($scan_type === 'salida' && $scan_quantity > (int)$scan_product['quantity']) {
        return [
            'success' => false,
            'message' => 'Existencia insuficiente. Hay ' . (int)$scan_product['quantity'] . ' unidades disponibles.'
        ];
    }

    if ($scan_reason === '') {
        $scan_reason = 'Movimiento rápido por escaneo';
    }

    if (!registerMovement($scan_product['id'], $scan_type, $scan_quantity, $scan_reason)) {
        return [
            'success' => false,
            'message' => 'No se pudo registrar el movimiento. Verifica la existencia e intenta nuevamente.'
        ];
    }

    $label = $scan_type === 'entrada' ? 'entrada' : 'salida';
    $updated_product = getProduct($scan_product['id']);

    return [
        'success' => true,
        'message' => ucfirst($label) . ' registrada correctamente.',
        'product' => [
            'id' => (int)$updated_product['id'],
            'name' => (string)$updated_product['name'],
            'code' => (string)$updated_product['code'],
            'quantity' => (int)$updated_product['quantity'],
            'min_stock' => (int)$updated_product['min_stock']
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'scan-product') {
    header('Content-Type: application/json; charset=UTF-8');

    $code = normalizeScannedCode($_GET['code'] ?? '');
    if ($code === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Código vacío.'
        ]);
        exit;
    }

    $scanProduct = findProductByScannedCode($code);
    if (!$scanProduct) {
        echo json_encode([
            'success' => false,
            'message' => 'Producto no encontrado.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'product' => [
            'id' => (int)$scanProduct['id'],
            'name' => (string)$scanProduct['name'],
            'code' => (string)$scanProduct['code'],
            'category_name' => (string)($scanProduct['category_name'] ?? 'Sin categoria'),
            'quantity' => (int)$scanProduct['quantity'],
            'min_stock' => (int)$scanProduct['min_stock'],
            'unit_price' => number_format((float)$scanProduct['unit_price'], 2),
            'image' => (string)($scanProduct['image'] ?? ''),
            'scanned_code' => (string)($scanProduct['_scanned_code'] ?? $code),
            'matched_code' => (string)($scanProduct['_matched_code'] ?? $scanProduct['code'])
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'scan-movement') {
    header('Content-Type: application/json; charset=UTF-8');

    $requestKey = substr(
        preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['request_key'] ?? '')),
        0,
        80
    );
    $_SESSION['scan_request_results'] = $_SESSION['scan_request_results'] ?? [];

    if ($requestKey !== '' && isset($_SESSION['scan_request_results'][$requestKey])) {
        echo json_encode($_SESSION['scan_request_results'][$requestKey]);
        exit;
    }

    $result = processQuickScanMovement(
        $_POST['code'] ?? '',
        $_POST['type'] ?? 'entrada',
        (int)($_POST['quantity'] ?? 1),
        $_POST['reason'] ?? ''
    );

    if ($requestKey !== '') {
        $_SESSION['scan_request_results'][$requestKey] = $result;
        $_SESSION['scan_request_results'] = array_slice($_SESSION['scan_request_results'], -20, null, true);
    }

    echo json_encode($result);
    exit;
}

$message = '';
$error = '';
$product = null;

// Procesar búsqueda rápida de producto
if (isset($_GET['code']) && !empty($_GET['code'])) {
    $product = findProductByScannedCode($_GET['code']);
    if (!$product) {
        $error = 'Producto no encontrado con el código: ' . htmlspecialchars($_GET['code']);
    }
}

// Procesar movimiento rápido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_movement'])) {
    $product_id = $_POST['product_id'];
    $type = $_POST['type'];
    $quantity = $_POST['quantity'];
    $reason = $_POST['reason'] ?? '';
    
    if (registerMovement($product_id, $type, $quantity, $reason)) {
        $message = 'Movimiento registrado exitosamente.';
        $product = getProduct($product_id); // Actualizar datos del producto
    } else {
        $error = 'Error al registrar el movimiento.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de movimiento - <?php echo SITE_NAME; ?></title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
    <script src="assets/js/html5-qrcode.min.js"></script>
</head>
<body class="operations-page">
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header operations-hero">
            <div>
                <span class="eyebrow">Punto de movimiento</span>
                <h1>Escanear y mover inventario</h1>
                <p>Registra entradas o salidas leyendo el código del empaque, sin capturas repetitivas.</p>
            </div>
            <div class="hero-barcode" aria-hidden="true"><span></span></div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo e($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <div class="quick-entry-container scan-workbench">
            <div class="scan-entry-grid">
                <section class="scanner-launch-card">
                    <div class="scanner-launch-icon" aria-hidden="true">
                        <span class="barcode-glyph"></span>
                        <i></i><i></i><i></i><i></i>
                    </div>
                    <div class="scanner-launch-copy">
                        <span class="operation-chip">Cámara · lectura continua</span>
                        <h2>Escáner de código de barras</h2>
                        <p>Usa la cámara trasera, enfoque continuo y validación doble para reducir lecturas erróneas.</p>
                        <ul class="scanner-capabilities">
                            <li>EAN, UPC, Code 128, Code 39, ITF y QR</li>
                            <li>Linterna y zoom cuando el dispositivo lo permite</li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-primary btn-scan-launch" id="scannerBtn">
                        <span class="btn-camera-icon" aria-hidden="true"></span>
                        Iniciar escáner
                    </button>
                </section>

                <section class="manual-entry-card">
                    <span class="operation-chip operation-chip-muted">Teclado o lector USB</span>
                    <h2>Captura manual</h2>
                    <p>Escribe el SKU o deja el cursor aquí para usar una pistola lectora.</p>
                <form method="GET" class="quick-search-form" id="searchForm">
                    <div class="search-box">
                        <span class="manual-barcode-icon" aria-hidden="true"></span>
                        <input
                            type="text" 
                            name="code" 
                            id="codeInput" 
                            placeholder="Código o SKU del producto"
                            autofocus 
                            autocomplete="off"
                            class="quick-search-input"
                        >
                        <button type="submit" class="btn btn-secondary">Localizar</button>
                    </div>
                </form>
                    <div class="manual-entry-hint"><kbd>F2</kbd> enfocar · <kbd>Enter</kbd> buscar · <kbd>F3</kbd> cámara</div>
                </section>
            </div>

            <div id="scannerFullscreen" class="scanner-fullscreen" role="dialog" aria-modal="true" aria-labelledby="scannerFullscreenTitle">
                <div class="scanner-fullscreen-header">
                    <div>
                        <span class="scanner-live"><i></i> Cámara activa</span>
                        <h3 id="scannerFullscreenTitle">Lector de inventario</h3>
                    </div>
                    <button type="button" class="scanner-close-btn" id="scannerCloseBtn" aria-label="Cerrar escáner">×</button>
                </div>

                <div class="scanner-session-bar">
                    <div class="scanner-mode-selector" aria-label="Tipo de movimiento predeterminado">
                        <button type="button" class="scanner-mode-btn active" data-mode="entrada">
                            <span aria-hidden="true">↓</span> Entrada
                        </button>
                        <button type="button" class="scanner-mode-btn" data-mode="salida">
                            <span aria-hidden="true">↑</span> Salida
                        </button>
                    </div>
                    <div class="scanner-session-count"><strong id="scanSessionCount">0</strong> movimientos en esta sesión</div>
                </div>

                <div class="scanner-viewport">
                    <div id="qr-reader" class="scanner-reader"></div>
                    <div class="scanner-reticle" aria-hidden="true">
                        <span class="reticle-corner c1"></span><span class="reticle-corner c2"></span>
                        <span class="reticle-corner c3"></span><span class="reticle-corner c4"></span>
                        <span class="scanner-laser"></span>
                    </div>
                    <div class="scanner-aim-copy">Centra un solo código dentro del marco</div>
                </div>

                <div id="scanFeedback" class="scan-feedback" aria-live="polite">Preparando cámara…</div>

                <div class="scanner-control-dock">
                    <button type="button" class="scanner-tool-btn" id="scannerTorchBtn" disabled>
                        <span aria-hidden="true">☀</span><small>Linterna</small>
                    </button>
                    <div class="scanner-camera-control">
                        <label for="scannerCameraSelect">Cámara</label>
                        <select id="scannerCameraSelect" class="scanner-camera-select">
                            <option value="">Detectando cámaras…</option>
                        </select>
                    </div>
                    <div class="scanner-zoom-control" id="scannerZoomControl" hidden>
                        <label for="scannerZoomRange">Zoom <output id="scannerZoomValue">1×</output></label>
                        <input type="range" id="scannerZoomRange" min="1" max="1" step="0.1" value="1">
                    </div>
                </div>
            </div>

            <div id="scanResultModal" class="scan-modal" role="dialog" aria-modal="true" aria-labelledby="scanResultTitle">
                <div class="scan-modal-panel">
                    <div class="scan-modal-grabber" aria-hidden="true"></div>
                    <div class="scan-result-heading">
                        <span class="scan-result-check" id="scanResultIcon" aria-hidden="true">✓</span>
                        <div><span class="eyebrow">Lectura confirmada</span><h3 id="scanResultTitle">Producto localizado</h3></div>
                    </div>
                    <p class="scan-modal-code" id="scanResultCode">Código: -</p>
                    <div class="scan-product-summary" id="scanProductSummary">
                        <div class="scan-product-visual" id="scanProductVisual"><span class="barcode-glyph"></span></div>
                        <div>
                            <p class="scan-product-name" id="scanProductName">Buscando producto…</p>
                            <p class="scan-product-meta" id="scanProductMeta"></p>
                            <p class="scan-product-stock" id="scanProductStock"></p>
                        </div>
                    </div>
                    <div class="scan-move-selector" id="scanMoveSelector">
                        <button type="button" class="scan-move-btn active" data-type="entrada" id="scanTypeEntrada"><span>↓</span> Entrada</button>
                        <button type="button" class="scan-move-btn" data-type="salida" id="scanTypeSalida"><span>↑</span> Salida</button>
                    </div>
                    <div class="scan-fields">
                        <div class="scan-field">
                            <label for="scanQuantityInput">Cantidad</label>
                            <div class="quantity-stepper">
                                <button type="button" id="scanQuantityMinus" aria-label="Restar uno">−</button>
                                <input type="number" id="scanQuantityInput" min="1" value="1" inputmode="numeric">
                                <button type="button" id="scanQuantityPlus" aria-label="Sumar uno">+</button>
                            </div>
                        </div>
                        <div class="scan-field">
                            <label for="scanReasonInput">Motivo <span>opcional</span></label>
                            <input type="text" id="scanReasonInput" placeholder="Compra, entrega a área, ajuste…">
                        </div>
                    </div>
                    <div id="scanModalFeedback" class="scan-modal-feedback" aria-live="polite"></div>
                    <div class="scan-modal-actions">
                        <button type="button" class="btn btn-primary btn-register-scan" id="scanRegisterBtn">Confirmar movimiento</button>
                        <div class="scan-secondary-actions">
                            <button type="button" class="btn btn-secondary" id="scanAgainBtn">Escanear otro</button>
                            <button type="button" class="btn btn-secondary" id="scanUseCodeBtn">Ver ficha</button>
                        </div>
                        <button type="button" class="scan-cancel-link" id="scanCancelBtn">Cancelar lectura</button>
                    </div>
                </div>
            </div>
            
            <?php if ($product): ?>
                <div class="product-card-large">
                    <div class="product-header">
                        <h2><?php echo e($product['name']); ?></h2>
                        <span class="product-code"><?php echo e($product['code']); ?></span>
                    </div>
                    
                    <div class="product-details">
                        <div class="detail-item">
                            <span class="detail-label">Categoría:</span>
                            <span class="detail-value"><?php echo e($product['category_name'] ?? 'Sin categoría'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Stock Actual:</span>
                            <span class="detail-value">
                                <?php if ($product['quantity'] <= $product['min_stock']): ?>
                                    <span class="badge badge-danger large"><?php echo $product['quantity']; ?> unidades</span>
                                <?php else: ?>
                                    <span class="badge badge-success large"><?php echo $product['quantity']; ?> unidades</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Stock Mínimo:</span>
                            <span class="detail-value"><?php echo $product['min_stock']; ?> unidades</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Precio Unitario:</span>
                            <span class="detail-value">$<?php echo number_format($product['unit_price'], 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="quick-movement-form">
                        <h3>Registrar Movimiento</h3>
                        <form method="POST" id="movementForm">
                            <input type="hidden" name="quick_movement" value="1">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <div class="movement-type-selector">
                                <label class="radio-card">
                                    <input type="radio" name="type" value="entrada" checked>
                                    <div class="radio-card-content">
                                        <span class="radio-icon">↓</span>
                                        <span class="radio-label">Entrada</span>
                                    </div>
                                </label>
                                <label class="radio-card">
                                    <input type="radio" name="type" value="salida">
                                    <div class="radio-card-content">
                                        <span class="radio-icon">↑</span>
                                        <span class="radio-label">Salida</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity">Cantidad</label>
                                <input type="number" id="quantity" name="quantity" required min="1" value="1" class="large-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="reason">Razón (Opcional)</label>
                                <input type="text" id="reason" name="reason" placeholder="Ej: Compra, Uso interno..." class="large-input">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg">✓ Registrar Movimiento</button>
                                <button type="button" onclick="resetForm()" class="btn btn-lg">Nueva Busqueda</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="keyboard-shortcuts">
                <h3>Atajos de Teclado</h3>
                <ul>
                    <li><kbd>F2</kbd> - Enfocar búsqueda</li>
                    <li><kbd>F3</kbd> - Activar/Desactivar escaner movil</li>
                    <li><kbd>Tab</kbd> - Navegar entre campos</li>
                    <li><kbd>Enter</kbd> - Buscar / Registrar</li>
                    <li><kbd>Esc</kbd> - Nueva búsqueda</li>
                </ul>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/inventory-scanner.js?v=1.1"></script>
    <?php if ($product): ?>
    <script>
        document.getElementById('quantity')?.focus();
        document.getElementById('quantity')?.select();
    </script>
    <?php endif; ?>
</body>
</html>
