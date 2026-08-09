<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/requests.php';

requireLogin();

$uploads_dir = __DIR__ . '/uploads/products/';
if (!is_dir($uploads_dir)) {
    @mkdir($uploads_dir, 0755, true);
}

$message = '';
$error = '';
$user_id = getCurrentUserId();
$user_area_id = getUserArea($user_id);

if (!$user_area_id && !isAdmin()) {
    $error = 'Tu usuario no está asignado a ningún área. Contacta al administrador.';
}

// Procesamiento AJAX de carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    header('Content-Type: application/json');
    
    if ($action === 'add-to-cart') {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        if ($product_id && $quantity > 0) {
            $product = getProduct($product_id);
            if ($product) {
                if (addToCart($product_id, $quantity, $product['name'], $product['code'])) {
                    $cart = getCart();
                    echo json_encode(['success' => true, 'cart_count' => count($cart)]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'La cantidad solicitada supera la existencia disponible']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        }
        exit;
    } elseif ($action === 'remove-from-cart') {
        $product_id = (int)$_POST['product_id'];
        if ($product_id) {
            removeFromCart($product_id);
            $cart = getCart();
            echo json_encode(['success' => true, 'cart_count' => count($cart)]);
        }
        exit;
    } elseif ($action === 'update-cart') {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)($_POST['quantity'] ?? 0);
        if ($product_id && $quantity > 0) {
            if (updateCartItem($product_id, $quantity)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'La cantidad supera la existencia disponible']);
            }
        }
        exit;
    } elseif ($action === 'get-cart') {
        $cart = getCart();
        echo json_encode(['cart' => array_values($cart), 'count' => count($cart)]);
        exit;
    } elseif ($action === 'send-request') {
        $cart = getCart();
        if (!empty($cart) && $user_area_id) {
            if (createRequestFromCart($user_area_id)) {
                clearCart();
                echo json_encode(['success' => true, 'message' => '¡Solicitud enviada exitosamente!']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al enviar la solicitud']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Carrito vacío o área no asignada']);
        }
        exit;
    } elseif ($action === 'search-products') {
        $term = trim($_POST['term'] ?? '');
        $filtered = getProducts($term, '', 24);
        echo json_encode([
            'success' => true,
            'products' => array_map(function($p) {
                return [
                    'id' => (int)$p['id'],
                    'name' => $p['name'],
                    'code' => $p['code'],
                    'quantity' => (int)$p['quantity'],
                    'min_stock' => (int)$p['min_stock'],
                    'image' => $p['image'] ?? ''
                ];
            }, $filtered)
        ]);
        exit;
    }
}

// Obtener solicitudes del usuario/área
$area_requests = $user_area_id ? getAreaRequests($user_area_id) : [];
$products = getProducts('', '', 24);
$total_available_products = countProducts();
$cart = getCart();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Productos - <?php echo SITE_NAME; ?></title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body class="my-requests-page">
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header requests-hero">
            <span class="eyebrow">Centro de solicitudes</span>
            <h1>Solicitar productos</h1>
            <p>Busca articulos, arma tu pedido y envialo para aprobacion.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo e($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <?php if ($user_area_id || isAdmin()): ?>
            <div class="tab-buttons requests-tabs">
                <button class="tab-btn active" onclick="switchTab('productos', this)">Productos</button>
                <button class="tab-btn" onclick="switchTab('solicitudes', this)">Mis Solicitudes</button>
            </div>
            
            <!-- TAB: PRODUCTOS DISPONIBLES CON CARRITO -->
            <div id="tab-productos" class="main-layout requests-layout">
                <div class="products-panel">
                    <div class="products-toolbar">
                        <input type="text" id="products-search" class="ajax-search-input" placeholder="Buscar producto por nombre o código..." autocomplete="off">
                        <div id="search-status" class="search-status"></div>
                    </div>
                    <div class="panel-heading"><h2>Productos disponibles</h2><span>Mostrando hasta 24 de <?php echo number_format($total_available_products); ?></span></div>
                    <div class="product-grid" id="product-grid">
                        <?php foreach ($products as $p): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if ($p['image'] && file_exists(__DIR__ . '/' . $p['image'])): ?>
                                        <img src="<?php echo e($p['image']); ?>" alt="<?php echo e($p['name']); ?>" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="product-placeholder" aria-hidden="true">F</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-name"><?php echo e($p['name']); ?></div>
                                    <div class="product-code"><?php echo e($p['code']); ?></div>
                                    <div class="product-qty">
                                        <span>Stock:</span>
                                        <span class="qty-badge <?php echo $p['quantity'] > 0 ? ($p['quantity'] > $p['min_stock'] ? 'available' : 'low') : 'none'; ?>">
                                            <?php echo $p['quantity']; ?>
                                        </span>
                                    </div>
                                    <form class="add-to-cart-form" onsubmit="addToCart(event, <?php echo $p['id']; ?>, '<?php echo e($p['name']); ?>')">
                                        <input type="number" name="quantity" value="1" min="1" max="<?php echo $p['quantity']; ?>" required>
                                        <button type="submit" class="btn-cart">Agregar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- CARRITO SIDEBAR -->
                <div class="cart-sidebar cart-panel">
                    <div class="cart-header">Carrito (<span id="cart-count"><?php echo count($cart); ?></span>)</div>
                    
                    <div id="cart-container">
                        <?php if (empty($cart)): ?>
                            <div class="cart-empty">El carrito está vacío</div>
                        <?php else: ?>
                            <div class="cart-items">
                                <?php foreach ($cart as $item): ?>
                                    <div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                                        <div class="cart-item-name"><?php echo e($item['product_name']); ?></div>
                                        <div class="cart-item-qty">
                                            <input type="number" value="<?php echo $item['quantity']; ?>" min="1" onchange="updateCartItem(<?php echo $item['product_id']; ?>, this.value)">
                                            <span>unidades</span>
                                        </div>
                                        <button type="button" class="btn-remove-cart" onclick="removeFromCart(<?php echo $item['product_id']; ?>)">Quitar</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="cart-total">Total: <?php echo count($cart); ?> producto(s)</div>
                            <button type="button" class="btn-send-request" onclick="sendRequest()">Enviar Solicitud</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- TAB: MIS SOLICITUDES -->
            <div id="tab-solicitudes" class="requests-history" style="display: none;">
                <h2>Mis Solicitudes</h2>
                <?php if (count($area_requests) > 0): ?>
                    <div class="request-history-list">
                        <?php foreach ($area_requests as $req): ?>
                            <div class="request-history-item">
                                <div class="request-history-head">
                                    <div class="request-history-title">
                                        <strong><?php echo e($req['product_name'] ?? 'Producto no disponible'); ?></strong>
                                        <span class="badge badge-<?php echo $req['status'] === 'pendiente' ? 'warning' : ($req['status'] === 'aprobado' ? 'info' : ($req['status'] === 'entregado' ? 'success' : 'danger')); ?>">
                                            <?php echo ucfirst($req['status']); ?>
                                        </span>
                                    </div>
                                    <span class="request-history-date"><?php echo date('d/m/Y', strtotime($req['created_at'])); ?></span>
                                </div>
                                <p class="request-history-meta">Cantidad: <strong><?php echo $req['quantity']; ?> unidades</strong></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No tienes solicitudes aún.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        function switchTab(tab, button) {
            // Ocultar todos
            document.querySelectorAll('[id^="tab-"]').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Mostrar seleccionado
            document.getElementById('tab-' + tab).style.display = tab === 'productos' ? 'grid' : 'block';
            button.classList.add('active');
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderProducts(products) {
            const grid = document.getElementById('product-grid');
            if (!grid) return;

            if (!products.length) {
                grid.innerHTML = '<div class="alert alert-info">No se encontraron productos con ese criterio.</div>';
                return;
            }

            let html = '';
            products.forEach((p) => {
                const qtyClass = p.quantity > 0 ? (p.quantity > p.min_stock ? 'available' : 'low') : 'none';
                const imageHtml = p.image
                    ? `<img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}" loading="lazy" decoding="async">`
                    : '<span class="product-placeholder" aria-hidden="true">F</span>';

                html += `
                    <div class="product-card">
                        <div class="product-image">${imageHtml}</div>
                        <div class="product-info">
                            <div class="product-name">${escapeHtml(p.name)}</div>
                            <div class="product-code">${escapeHtml(p.code)}</div>
                            <div class="product-qty">
                                <span>Stock:</span>
                                <span class="qty-badge ${qtyClass}">${p.quantity}</span>
                            </div>
                            <form class="add-to-cart-form" onsubmit="addToCart(event, ${p.id}, '${escapeHtml(p.name)}')">
                                <input type="number" name="quantity" value="1" min="1" max="${p.quantity}" required>
                                <button type="submit" class="btn-cart">Agregar</button>
                            </form>
                        </div>
                    </div>
                `;
            });

            grid.innerHTML = html;
        }

        let searchTimer = null;

        async function searchProductsAjax(term) {
            const status = document.getElementById('search-status');
            if (status) {
                status.textContent = term ? 'Buscando...' : '';
            }

            try {
                const response = await fetch('my-requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=search-products&term=${encodeURIComponent(term)}`
                });

                const data = await response.json();
                if (data.success) {
                    renderProducts(data.products || []);
                    if (status) {
                        status.textContent = term ? `${(data.products || []).length} resultado(s)` : '';
                    }
                }
            } catch (error) {
                if (status) {
                    status.textContent = 'No se pudo completar la búsqueda';
                }
            }
        }
        
        async function addToCart(e, productId, productName) {
            e.preventDefault();
            const quantity = e.target.quantity.value;
            
            try {
                const response = await fetch('my-requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=add-to-cart&product_id=${productId}&quantity=${quantity}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    updateCartDisplay();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Error al agregar al carrito: ' + error.message);
            }
        }
        
        async function removeFromCart(productId) {
            if (confirm('¿Quitar este producto del carrito?')) {
                try {
                    const response = await fetch('my-requests.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=remove-from-cart&product_id=${productId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        updateCartDisplay();
                    }
                } catch (error) {
                    alert('Error al quitar del carrito: ' + error.message);
                }
            }
        }
        
        async function updateCartItem(productId, quantity) {
            if (quantity <= 0) return;
            
            try {
                const response = await fetch('my-requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=update-cart&product_id=${productId}&quantity=${quantity}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    updateCartDisplay();
                } else {
                    alert('Error: ' + (data.error || 'No se pudo actualizar la cantidad'));
                }
            } catch (error) {
                alert('Error al actualizar carrito: ' + error.message);
            }
        }
        
        async function updateCartDisplay() {
            try {
                const response = await fetch('my-requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get-cart'
                });
                
                const data = await response.json();
                const cartContainer = document.getElementById('cart-container');
                const cartCount = document.getElementById('cart-count');
                cartCount.textContent = data.count;
                
                if (data.count === 0) {
                    cartContainer.innerHTML = '<div class="cart-empty">El carrito está vacío</div>';
                } else {
                    let html = '<div class="cart-items">';
                    
                    data.cart.forEach(item => {
                        html += `
                            <div class="cart-item" data-product-id="${item.product_id}">
                                <div class="cart-item-name">${item.product_name}</div>
                                <div class="cart-item-qty">
                                    <input type="number" value="${item.quantity}" min="1" onchange="updateCartItem(${item.product_id}, this.value)">
                                    <span>unidades</span>
                                </div>
                                <button type="button" class="btn-remove-cart" onclick="removeFromCart(${item.product_id})">Quitar</button>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    html += `<div class="cart-total">Total: ${data.count} producto(s)</div>`;
                    html += '<button type="button" class="btn-send-request" onclick="sendRequest()">Enviar Solicitud</button>';
                    
                    cartContainer.innerHTML = html;
                }
            } catch (error) {
                alert('Error al actualizar carrito: ' + error.message);
            }
        }
        
        async function sendRequest() {
            if (confirm('¿Enviar todos los productos del carrito como una solicitud?')) {
                try {
                    const response = await fetch('my-requests.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=send-request'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        alert(data.message);
                        updateCartDisplay();
                        // Recargar solicitudes
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                } catch (error) {
                    alert('Error al enviar solicitud: ' + error.message);
                }
            }
        }

        const searchInput = document.getElementById('products-search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value.trim();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    searchProductsAjax(term);
                }, 250);
            });
        }
    </script>
</body>
</html>
