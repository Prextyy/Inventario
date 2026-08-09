<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/requests.php';

requireLogin();

$stats = getStats();
$recent_movements = getMovements(null, 10);
$low_stock_items = getLowStockProducts(5);
$healthy_stock = max(0, (int)$stats['total_products'] - (int)$stats['low_stock']);
$stock_health_percent = (int)$stats['total_products'] > 0
    ? max(0, min(100, (int)round(($healthy_stock / (int)$stats['total_products']) * 100)))
    : 100;

// Obtener estadísticas de solicitudes para admin
$request_stats = isAdmin() ? getRequestStats() : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <section class="warehouse-hero">
            <div class="warehouse-hero-copy">
                <span class="eyebrow eyebrow-light">Centro de control de almacén</span>
                <h1>Inventario al momento</h1>
                <p>Hola, <?php echo e(getCurrentUsername()); ?>. Aquí tienes las existencias, alertas y movimientos que requieren atención.</p>
                <?php if (isAdmin()): ?>
                <div class="warehouse-hero-actions">
                    <a href="quick-entry.php" class="btn btn-warehouse-scan"><span class="mini-barcode" aria-hidden="true"></span> Escanear producto</a>
                    <a href="products.php?action=add" class="btn btn-warehouse-ghost">+ Dar de alta</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="stock-health-panel">
                <div class="stock-health-heading">
                    <span>Salud del catálogo</span>
                    <strong><?php echo $stock_health_percent; ?>%</strong>
                </div>
                <div class="stock-health-track"><span style="width: <?php echo $stock_health_percent; ?>%"></span></div>
                <div class="stock-health-breakdown">
                    <div><strong><?php echo number_format($healthy_stock); ?></strong><span>SKU estables</span></div>
                    <div class="stock-health-risk"><strong><?php echo number_format($stats['low_stock']); ?></strong><span>por reponer</span></div>
                </div>
                <small>Actualizado <?php echo date('d/m/Y'); ?></small>
            </div>
            <span class="warehouse-grid" aria-hidden="true"></span>
        </section>
        
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="fa-solid fa-box-open"></i></div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-label">SKU registrados</div>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['low_stock']); ?></div>
                    <div class="stat-label">Requieren reposición</div>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['movements_today']); ?></div>
                    <div class="stat-label">Movimientos hoy</div>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
                <div class="stat-details">
                    <div class="stat-value">$<?php echo number_format($stats['total_value'], 2); ?></div>
                    <div class="stat-label">Valor del inventario</div>
                </div>
            </div>
            
            <?php if (isAdmin() && $request_stats): ?>
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="fa-solid fa-inbox"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $request_stats['pending_product'] + $request_stats['pending_custom']; ?></div>
                        <div class="stat-label">Solicitudes Pendientes</div>
                    </div>
                    <a href="manage-requests.php" class="stat-link">Ver todas</a>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $request_stats['approved_today']; ?></div>
                        <div class="stat-label">Aprobadas Hoy</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-section dashboard-stock">
                <div class="section-header">
                    <div><span class="section-kicker">Atencion</span><h2>Productos con stock bajo</h2></div>
                    <?php if (isAdmin()): ?><a href="products.php" class="section-link">Ver inventario →</a><?php endif; ?>
                </div>
                <div class="table-responsive">
                    <?php if (count($low_stock_items) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Mínimo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($low_stock_items, 0, 5) as $product): ?>
                                    <tr>
                                        <td data-label="Codigo"><span class="sku-value"><?php echo e($product['code']); ?></span></td>
                                        <td data-label="Producto"><?php echo e($product['name']); ?></td>
                                        <td data-label="Cantidad"><span class="badge badge-danger"><?php echo $product['quantity']; ?></span></td>
                                        <td data-label="Minimo"><?php echo $product['min_stock']; ?></td>
                                        <td data-label="Accion">
                                            <?php if (isAdmin()): ?>
                                            <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-sm">Ver</a>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No hay productos con stock bajo.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-section dashboard-movements">
                <div class="section-header">
                    <div><span class="section-kicker">Actividad</span><h2>Movimientos recientes</h2></div>
                </div>
                <div class="movements-list">
                    <?php if (count($recent_movements) > 0): ?>
                        <?php foreach ($recent_movements as $mov): ?>
                            <div class="movement-item">
                                <div class="movement-icon <?php echo $mov['type'] === 'entrada' ? 'movement-in' : 'movement-out'; ?>">
                                    <i class="fa-solid <?php echo $mov['type'] === 'entrada' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                                </div>
                                <div class="movement-details">
                                    <div class="movement-title"><?php echo e($mov['product_name']); ?></div>
                                    <div class="movement-meta">
                                        <span><?php echo ucfirst($mov['type']); ?></span> |
                                        <span><?php echo $mov['quantity']; ?> unidades</span> |
                                        <span><?php echo date('d/m/Y H:i', strtotime($mov['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No hay movimientos recientes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <div class="section-header section-header-plain"><div><span class="section-kicker">Atajos</span><h2>Acciones rapidas</h2></div></div>
            <div class="action-buttons">
                <?php if (isAdmin()): ?>
                <a href="quick-entry.php" class="action-btn">
                    <i class="fa-solid fa-barcode"></i>
                    <span>Escanear movimiento</span>
                    <small>Cámara, lector USB o captura manual</small>
                </a>
                <a href="products.php?action=add" class="action-btn">
                    <i class="fa-solid fa-plus"></i>
                    <span>Nuevo Producto</span>
                    <small>Alta de articulos en inventario</small>
                </a>
                <a href="export.php" class="action-btn">
                    <i class="fa-solid fa-file-export"></i>
                    <span>Exportar Datos</span>
                    <small>Reportes en PDF y Excel</small>
                </a>
                <?php else: ?>
                <a href="my-requests.php" class="action-btn">
                    <i class="fa-solid fa-cart-plus"></i>
                    <span>Solicitar productos</span>
                    <small>Consulta existencias y crea una solicitud</small>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
