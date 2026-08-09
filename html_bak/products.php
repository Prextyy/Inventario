<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/requests.php';

requireAdmin();

// Asegurar que el directorio de imágenes existe
$uploads_dir = __DIR__ . '/uploads/products/';
if (!is_dir($uploads_dir)) {
    @mkdir($uploads_dir, 0755, true);
}

$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Insertar el producto con cantidad 0; el movimiento de "Stock inicial"
        // es quien actualiza la cantidad, evitando que se duplique.
        $productData = $_POST;
        $initialQty  = (int)($productData['quantity'] ?? 0);
        $productData['quantity'] = 0;

        $product_id = addProduct($productData);
        if ($product_id) {
            // Si hay cantidad inicial, registrar entrada
            if ($initialQty > 0) {
                registerMovement($product_id, 'entrada', $initialQty, 'Stock inicial');
            }
            header('Location: products.php?msg=added');
            exit;
        } else {
            $error = 'Error al agregar el producto. Verifica que el código no exista.';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? null;
        if ($id && updateProduct($id, $_POST)) {
            header('Location: products.php?msg=updated');
            exit;
        } else {
            $error = 'Error al actualizar el producto.';
        }
    } elseif ($action === 'movement') {
        $product_id = $_POST['product_id'];
        $type = $_POST['type'];
        $quantity = $_POST['quantity'];
        $reason = $_POST['reason'] ?? '';
        
        if (registerMovement($product_id, $type, $quantity, $reason)) {
            header('Location: products.php?msg=movement');
            exit;
        } else {
            $error = 'Error al registrar el movimiento.';
        }
    } elseif ($action === 'upload-image' && isset($_POST['product_id'])) {
        $product_id = $_POST['product_id'];
        if (isset($_FILES['product_image'])) {
            $result = uploadProductImage($product_id, $_FILES['product_image']);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $message = 'Imagen actualizada exitosamente.';
            }
        } else {
            $error = 'No se seleccionó imagen.';
        }
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        if (deleteProduct((int)$_POST['id'])) {
            header('Location: products.php?msg=deleted');
            exit;
        }
        $error = 'No se pudo eliminar el producto. Puede tener movimientos o solicitudes asociados.';
    }
}

// Mensajes
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = 'Producto agregado exitosamente.';
            break;
        case 'updated':
            $message = 'Producto actualizado exitosamente.';
            break;
        case 'deleted':
            $message = 'Producto eliminado exitosamente.';
            break;
        case 'movement':
            $message = 'Movimiento registrado exitosamente.';
            break;
    }
}

// Obtener datos según la acción
$categories = getCategories();
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

if ($action === 'list') {
    $per_page = 40;
    $current_page = max(1, (int)($_GET['page'] ?? 1));
    $total_products = countProducts($search, $category_filter);
    $total_pages = max(1, (int)ceil($total_products / $per_page));
    $current_page = min($current_page, $total_pages);
    $products = getProducts($search, $category_filter, $per_page, ($current_page - 1) * $per_page);
} elseif ($action === 'edit' && isset($_GET['id'])) {
    $product = getProduct($_GET['id']);
    if (!$product) {
        header('Location: products.php');
        exit;
    }
    $movements = getMovements($_GET['id'], 20);
} elseif ($action === 'movement' && isset($_GET['id'])) {
    $product = getProduct($_GET['id']);
    if (!$product) {
        header('Location: products.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - <?php echo SITE_NAME; ?></title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo e($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
            <div class="page-header">
                <div><span class="eyebrow">Catalogo maestro</span><h1>Gestion de productos</h1><p><?php echo number_format($total_products); ?> productos encontrados</p></div>
                <div class="page-actions">
                    <a href="quick-entry.php" class="btn btn-scan-outline"><span class="mini-barcode" aria-hidden="true"></span> Escanear</a>
                    <a href="products.php?action=add" class="btn btn-primary">+ Nuevo producto</a>
                </div>
            </div>
            
            <div class="filters-bar">
                <form method="GET" class="filters-form">
                    <input type="text" name="search" placeholder="Buscar por nombre o código..." value="<?php echo e($search); ?>" class="search-input">
                    <select name="category" class="filter-select">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <?php if ($search || $category_filter): ?>
                        <a href="products.php" class="btn">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Cantidad</th>
                            <th>Stock Mín.</th>
                            <th>Precio Unit.</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td data-label="Codigo"><span class="sku-value"><?php echo e($product['code']); ?></span></td>
                                    <td data-label="Nombre"><?php echo e($product['name']); ?></td>
                                    <td data-label="Categoria"><?php echo e($product['category_name'] ?? 'Sin categoría'); ?></td>
                                    <td data-label="Cantidad">
                                        <?php if ($product['quantity'] <= $product['min_stock']): ?>
                                            <span class="badge badge-danger"><?php echo $product['quantity']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?php echo $product['quantity']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Stock minimo"><?php echo $product['min_stock']; ?></td>
                                    <td data-label="Precio">$<?php echo number_format($product['unit_price'], 2); ?></td>
                                    <td data-label="Acciones" class="actions">
                                        <a href="products.php?action=movement&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-info" title="Movimiento">Mov.</a>
                                        <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary" title="Editar">Editar</a>
                                        <form method="POST" action="products.php?action=delete" class="inline-form" onsubmit="return confirm('¿Eliminar este producto? Esta accion no se puede deshacer.')">
                                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No hay productos registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination" aria-label="Paginacion de productos">
                    <?php
                    $query = ['search' => $search, 'category' => $category_filter];
                    $pageUrl = function($page) use ($query) {
                        return 'products.php?' . http_build_query(array_merge($query, ['page' => $page]));
                    };
                    ?>
                    <a class="pagination-link <?php echo $current_page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $current_page > 1 ? e($pageUrl($current_page - 1)) : '#'; ?>">← Anterior</a>
                    <span>Pagina <?php echo $current_page; ?> de <?php echo $total_pages; ?></span>
                    <a class="pagination-link <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $current_page < $total_pages ? e($pageUrl($current_page + 1)) : '#'; ?>">Siguiente →</a>
                </nav>
            <?php endif; ?>
            
        <?php elseif ($action === 'add'): ?>
            <div class="page-header">
                <h1>Nuevo Producto</h1>
                <a href="products.php" class="btn">← Volver</a>
            </div>
            
            <div class="form-container">
                <form method="POST" class="product-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="code">Código *</label>
                            <input type="text" id="code" name="code" required value="<?php echo generateProductCode(); ?>">
                            <div class="help-text">Código único del producto</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Categoría</label>
                            <select id="category_id" name="category_id">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Nombre del Producto *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">Cantidad Inicial</label>
                            <input type="number" id="quantity" name="quantity" value="0" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="min_stock">Stock Mínimo</label>
                            <input type="number" id="min_stock" name="min_stock" value="5" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="unit_price">Precio Unitario</label>
                            <input type="number" id="unit_price" name="unit_price" value="0" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Producto</button>
                        <a href="products.php" class="btn">Cancelar</a>
                    </div>
                </form>
            </div>
            
        <?php elseif ($action === 'edit'): ?>
            <div class="page-header">
                <h1>Editar Producto</h1>
                <a href="products.php" class="btn">← Volver</a>
            </div>
            
            <div class="form-container">
                <form method="POST" action="products.php?action=edit" class="product-form">
                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="code">Código *</label>
                            <input type="text" id="code" name="code" required value="<?php echo e($product['code']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Categoría</label>
                            <select id="category_id" name="category_id">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Nombre del Producto *</label>
                        <input type="text" id="name" name="name" required value="<?php echo e($product['name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description" rows="3"><?php echo e($product['description']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Cantidad Actual</label>
                            <div class="readonly-field"><?php echo $product['quantity']; ?> unidades</div>
                            <div class="help-text">Usa "Movimiento" para cambiar la cantidad</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="min_stock">Stock Mínimo</label>
                            <input type="number" id="min_stock" name="min_stock" value="<?php echo $product['min_stock']; ?>" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="unit_price">Precio Unitario</label>
                            <input type="number" id="unit_price" name="unit_price" value="<?php echo $product['unit_price']; ?>" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Actualizar Producto</button>
                        <a href="products.php" class="btn">Cancelar</a>
                    </div>
                </form>
                
                <div class="section-divider"></div>
                
                <h2>Imagen del Producto</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
                    <div>
                        <div style="background: #f5f5f5; padding: 0; border-radius: 8px; overflow: hidden;">
                            <?php if ($product['image'] && file_exists($product['image'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo e($product['name']); ?>" style="width: 100%; height: auto; display: block;">
                            <?php else: ?>
                                <div style="width: 100%; padding: 40px; text-align: center; color: #999; font-size: 48px;">IMG</div>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 12px; color: #666; margin-top: 8px;">Imagen actual del producto</p>
                    </div>
                    <div>
                        <form method="POST" action="products.php?action=upload-image" enctype="multipart/form-data" style="background: white; padding: 16px; border-radius: 8px;">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <div class="form-group">
                                <label for="product_image">Cargar Nueva Imagen</label>
                                <input type="file" id="product_image" name="product_image" accept="image/*" required>
                                <div class="help-text">Formatos: JPG, PNG, GIF, WebP. Máximo 5MB</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Guardar Imagen</button>
                        </form>
                    </div>
                </div>
                
                <div class="section-divider"></div>
                
                <h2>Historial de Movimientos</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Razón</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($movements) > 0): ?>
                                <?php foreach ($movements as $mov): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['created_at'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $mov['type'] === 'entrada' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo ucfirst($mov['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $mov['quantity']; ?></td>
                                        <td><?php echo e($mov['reason']); ?></td>
                                        <td><?php echo e($mov['username'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No hay movimientos registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif ($action === 'movement'): ?>
            <div class="page-header">
                <h1>📊 Registrar Movimiento</h1>
                <a href="products.php" class="btn">← Volver</a>
            </div>
            
            <div class="product-info-card">
                <h3><?php echo e($product['name']); ?></h3>
                <p><strong>Código:</strong> <?php echo e($product['code']); ?></p>
                <p><strong>Stock Actual:</strong> <span class="badge badge-info"><?php echo $product['quantity']; ?> unidades</span></p>
            </div>
            
            <div class="form-container">
                <form method="POST" class="product-form">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    
                    <div class="form-group">
                        <label for="type">Tipo de Movimiento *</label>
                        <select id="type" name="type" required>
                            <option value="entrada">Entrada (Agregar al inventario)</option>
                            <option value="salida">Salida (Retirar del inventario)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Cantidad *</label>
                        <input type="number" id="quantity" name="quantity" required min="1" autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Razón / Comentario</label>
                        <textarea id="reason" name="reason" rows="3" placeholder="Ej: Compra, Prestado a..., Uso interno, etc."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Registrar Movimiento</button>
                        <a href="products.php" class="btn">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
