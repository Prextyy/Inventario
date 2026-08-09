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

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$tab = $_GET['tab'] ?? 'product';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'] ?? null;
    $request_type = $_POST['request_type'] ?? 'product';
    $action_type = $_POST['action'] ?? null;
    
    if ($action_type === 'approve' && $request_id) {
        if (approveRequest($request_id, $request_type)) {
            $message = 'Solicitud aprobada exitosamente';
        } else {
            $error = 'Error al aprobar la solicitud';
        }
    } elseif ($action_type === 'reject' && $request_id) {
        if (rejectRequest($request_id, $request_type)) {
            $message = 'Solicitud rechazada';
        } else {
            $error = 'Error al rechazar la solicitud';
        }
    } elseif ($action_type === 'deliver' && $request_id) {
        // Entrega por producto individual
        $request_id = (int)$_POST['request_id'];
        $delivered_items = $_POST['delivered_items'] ?? [];
        
        if (!empty($delivered_items)) {
            if (updateDeliveredQuantityByProduct($request_id, $delivered_items)) {
                $message = 'Solicitud entregada y inventario actualizado exitosamente';
            } else {
                $error = 'Error al procesar la entrega';
            }
        } else {
            $error = 'Ingresa cantidades válidas para los productos';
        }
    } elseif ($action_type === 'complete' && $request_id) {
        if (completeRequest($request_id, $request_type)) {
            $message = 'Solicitud marcada como completada';
        } else {
            $error = 'Error al completar la solicitud';
        }
    }
}

$stats = getRequestStats();
$pending_product_requests = getAllRequests('pendiente', 'product');
$pending_custom_requests = getAllRequests('pendiente', 'custom');
$approved_requests = getAllRequests('aprobado', 'product');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Solicitudes - <?php echo SITE_NAME; ?></title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Gestion de Solicitudes</h1>
            <p>Aprueba, rechaza y gestiona todas las solicitudes de productos</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card-small">
                <div class="stat-number"><?php echo $stats['pending_product']; ?></div>
                <div class="stat-label">Solicitudes de Productos Pendientes</div>
            </div>
            <div class="stat-card-small">
                <div class="stat-number"><?php echo $stats['pending_custom']; ?></div>
                <div class="stat-label">Solicitudes Personalizadas Pendientes</div>
            </div>
            <div class="stat-card-small">
                <div class="stat-number"><?php echo $stats['approved_today']; ?></div>
                <div class="stat-label">Aprobadas Hoy</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <a href="manage-requests.php?tab=product" class="tab-link <?php echo $tab === 'product' ? 'active' : ''; ?>">
                Solicitudes de Productos (<?php echo $stats['pending_product']; ?>)
            </a>
            <a href="manage-requests.php?tab=custom" class="tab-link <?php echo $tab === 'custom' ? 'active' : ''; ?>">
                Solicitudes Personalizadas (<?php echo $stats['pending_custom']; ?>)
            </a>
        </div>
        
        <!-- Solicitudes de Productos -->
        <?php if ($tab === 'product'): ?>
            <h2>Solicitudes Pendientes de Productos</h2>
            
            <?php if (count($pending_product_requests) > 0): ?>
                <?php 
                // Agrupar solicitudes por departamento
                $by_area = [];
                foreach ($pending_product_requests as $req) {
                    $area = $req['area_name'] ?? 'Sin Área';
                    if (!isset($by_area[$area])) {
                        $by_area[$area] = [];
                    }
                    $by_area[$area][] = $req;
                }
                ?>
                
                <?php foreach ($by_area as $area_name => $requests): ?>
                    <div style="background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h3 style="margin-top: 0; color: #333; border-bottom: 3px solid #667eea; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <?php echo htmlspecialchars($area_name); ?>
                            <span style="background: #667eea; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                <?php echo count($requests); ?> solicitud(es)
                            </span>
                        </h3>
                        
                        <div style="display: grid; gap: 12px;">
                            <?php foreach ($requests as $req): ?>
                                <?php
                                // Detectar si hay items JSON guardados
                                $items = [];
                                if (strpos($req['notes'], 'ITEMS_JSON:') === 0) {
                                    $json_str = substr($req['notes'], 11);
                                    $items = json_decode($json_str, true) ?? [];
                                }
                                
                                // Si no hay items JSON, crear uno de la solicitud actual
                                if (empty($items) && $req['product_id']) {
                                    $items = [[
                                        'product_id' => $req['product_id'],
                                        'product_name' => $req['product_name'],
                                        'quantity' => $req['quantity']
                                    ]];
                                }
                                ?>
                                
                                <div style="background: #f9fafb; padding: 16px; border-radius: 8px; border-left: 4px solid #667eea;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                        <div>
                                            <div style="font-weight: 600; color: #333; margin-bottom: 4px;">
                                                Solicitud #<?php echo $req['id']; ?>
                                            </div>
                                            <div style="font-size: 12px; color: #666;">
                                                Usuario: <strong><?php echo htmlspecialchars($req['username']); ?></strong> • 
                                                Fecha: <strong><?php echo date('d/m/Y H:i', strtotime($req['created_at'])); ?></strong>
                                            </div>
                                        </div>
                                        <span style="background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                            <?php echo count($items); ?> producto(s)
                                        </span>
                                    </div>
                                    
                                    <!-- Listado de productos en la solicitud -->
                                    <div style="background: white; border-radius: 6px; padding: 12px; margin-bottom: 12px; border: 1px solid #e5e7eb;">
                                        <?php foreach ($items as $idx => $item): ?>
                                            <div style="padding: 8px 0; <?php echo $idx < count($items) - 1 ? 'border-bottom: 1px solid #e5e7eb;' : ''; ?>">
                                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                                    <div>
                                                        <div style="font-weight: 600; color: #333; font-size: 13px;">
                                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                                        </div>
                                                    </div>
                                                    <div style="font-size: 12px; color: #667eea; font-weight: 600;">
                                                        <?php echo $item['quantity']; ?> unidades
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Acciones -->
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <button type="button" class="action-btn btn-approve" style="background: #d1fae5; color: #065f46; padding: 8px 12px; cursor: pointer; border: none; border-radius: 6px; font-weight: 600;" onclick="openEditModal(<?php echo $req['id']; ?>, 'Solicitud #<?php echo $req['id']; ?>', <?php echo $req['quantity']; ?>, 'product', <?php echo htmlspecialchars(json_encode($items)); ?>)">
                                            ✓ Revisar & Entregar
                                        </button>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <input type="hidden" name="request_type" value="product">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" style="width: 100%; background: #fee2e2; color: #991b1b; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;" onclick="return confirm('¿Rechazar esta solicitud?')">
                                                ✕ Rechazar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-success">✓ No hay solicitudes de productos pendientes.</div>
            <?php endif; ?>
            
            <h2 style="margin-top: 40px;">Solicitudes Aprobadas (Sin Entregar)</h2>
            
            <?php $approved = getAllRequests('aprobado', 'product'); ?>
            <?php if (count($approved) > 0): ?>
                <?php 
                // Agrupar aprobadas por departamento
                $approved_by_area = [];
                foreach ($approved as $req) {
                    $area = $req['area_name'] ?? 'Sin Área';
                    if (!isset($approved_by_area[$area])) {
                        $approved_by_area[$area] = [];
                    }
                    $approved_by_area[$area][] = $req;
                }
                ?>
                
                <?php foreach ($approved_by_area as $area_name => $requests): ?>
                    <div style="background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 5px solid #10b981;">
                        <h3 style="margin-top: 0; color: #333; border-bottom: 3px solid #10b981; padding-bottom: 12px;">
                            <?php echo htmlspecialchars($area_name); ?> (Aprobadas)
                        </h3>
                        
                        <div style="display: grid; gap: 12px;">
                            <?php foreach ($requests as $req): ?>
                                <?php
                                // Detectar si hay items JSON guardados
                                $items = [];
                                if (strpos($req['notes'], 'ITEMS_JSON:') === 0) {
                                    $json_str = substr($req['notes'], 11);
                                    $items = json_decode($json_str, true) ?? [];
                                }
                                
                                // Si no hay items JSON, crear uno de la solicitud actual
                                if (empty($items) && $req['product_id']) {
                                    $items = [[
                                        'product_id' => $req['product_id'],
                                        'product_name' => $req['product_name'],
                                        'quantity' => $req['quantity']
                                    ]];
                                }
                                ?>
                                
                                <div style="background: #f0fdf4; padding: 16px; border-radius: 8px; border-left: 4px solid #10b981;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                        <div>
                                            <div style="font-weight: 600; color: #333;">
                                                Solicitud #<?php echo $req['id']; ?>
                                            </div>
                                            <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                                <?php echo count($items); ?> producto(s)
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Listado de productos -->
                                    <div style="background: white; border-radius: 6px; padding: 12px; margin-bottom: 12px; border: 1px solid #d1e7dd;">
                                        <?php foreach ($items as $idx => $item): ?>
                                            <div style="padding: 6px 0; <?php echo $idx < count($items) - 1 ? 'border-bottom: 1px solid #d1e7dd;' : ''; ?>">
                                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                                    <div style="font-weight: 600; color: #333; font-size: 13px;">
                                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                                    </div>
                                                    <div style="font-size: 12px; color: #10b981; font-weight: 600;">
                                                        <?php echo $item['quantity']; ?> un.
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <button type="button" class="btn btn-success btn-sm"
                                            onclick='openEditModal(<?php echo (int)$req['id']; ?>, <?php echo json_encode($req['product_name'] ?? 'Solicitud', JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo (int)$req['quantity']; ?>, "product", <?php echo htmlspecialchars(json_encode($items), ENT_QUOTES, 'UTF-8'); ?>)'>
                                        Revisar cantidades y entregar
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No hay solicitudes aprobadas sin entregar.</p>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Solicitudes Personalizadas -->
            <h2>Solicitudes Personalizadas (Productos no en Inventario)</h2>
            
            <?php if (count($pending_custom_requests) > 0): ?>
                <?php foreach ($pending_custom_requests as $req): ?>
                    <div class="request-row">
                        <div class="request-header">
                            <div class="request-title">
                                <?php echo htmlspecialchars($req['product_name']); ?>
                            </div>
                            <span class="status-badge status-<?php echo htmlspecialchars($req['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($req['status'])); ?>
                            </span>
                        </div>
                        
                        <div class="request-details">
                            <div class="detail-item">
                                <div class="detail-label">Área</div>
                                <div class="detail-value"><?php echo htmlspecialchars($req['area_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Usuario</div>
                                <div class="detail-value"><?php echo htmlspecialchars($req['username']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Cantidad</div>
                                <div class="detail-value"><?php echo $req['quantity']; ?> unidades</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Fecha Solicitud</div>
                                <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($req['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($req['product_description']): ?>
                            <p style="margin: 8px 0 12px 0; padding: 8px; background: #f9fafb; border-radius: 4px; font-size: 12px; color: #666;">
                                <strong>Descripción:</strong> <?php echo htmlspecialchars($req['product_description']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($req['notes']): ?>
                            <p style="margin: 8px 0 12px 0; padding: 8px; background: #f9fafb; border-radius: 4px; font-size: 12px; color: #666;">
                                <strong>Notas:</strong> <?php echo htmlspecialchars($req['notes']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="request-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <input type="hidden" name="request_type" value="custom">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="action-btn btn-approve">✓ Aprobar</button>
                            </form>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Rechazar esta solicitud?');">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <input type="hidden" name="request_type" value="custom">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="action-btn btn-reject">✕ Rechazar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-success">✓ No hay solicitudes personalizadas pendientes.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Modal para editar cantidad por producto -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h2 style="margin-top: 0; color: #333;">Revisar Solicitud y Entregar</h2>
            
            <p style="font-size: 12px; color: #666; margin-bottom: 16px;">
                Edita la cantidad a entregar para cada producto:
            </p>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="request_type" value="product">
                <input type="hidden" name="action" value="deliver">
                
                <div id="modalProductsList" style="margin-bottom: 20px; background: #f9fafb; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb; display: grid; gap: 12px;">
                    <!-- Se llena con JavaScript -->
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="closeEditModal()" style="flex: 1; background: #e5e7eb; color: #333; border: none; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit" style="flex: 1; background: #10b981; color: white; border: none; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                        ✓ Entregar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(requestId, productName, requestedQty, requestType, items) {
            // Crear HTML con campos individuales por producto
            let itemsHtml = '';
            if (Array.isArray(items) && items.length > 0) {
                items.forEach((item, idx) => {
                    itemsHtml += `
                        <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #ddd;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div style="font-weight: 600; color: #333;">${item.product_name}</div>
                                <span style="font-size: 11px; color: #999;">Solicitado: ${item.quantity} un.</span>
                            </div>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <label style="font-size: 12px; color: #666; white-space: nowrap;">Entregar:</label>
                                <input type="number" name="delivered_items[${item.product_id}]" value="${item.quantity}" min="0" max="${item.quantity}" style="width: 60px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;" required>
                                <span style="font-size: 11px; color: #999;">unidades</span>
                            </div>
                        </div>
                    `;
                });
            } else {
                itemsHtml += `
                    <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #ddd;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="font-weight: 600; color: #333;">${productName}</div>
                            <span style="font-size: 11px; color: #999;">Solicitado: ${requestedQty} un.</span>
                        </div>
                        <div style="display: flex; gap: 6px; align-items: center;">
                            <label style="font-size: 12px; color: #666; white-space: nowrap;">Entregar:</label>
                            <input type="number" name="delivered_items[1]" value="${requestedQty}" min="0" max="${requestedQty}" style="width: 60px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;" required>
                            <span style="font-size: 11px; color: #999;">unidades</span>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('modalProductsList').innerHTML = itemsHtml;
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('editModal').style.display = 'flex';
            // Focus en el primer input
            const firstInput = document.querySelector('input[type="number"]');
            if (firstInput) firstInput.focus();
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Cerrar modal cuando haga click fuera
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // Permitir cerrar con tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
