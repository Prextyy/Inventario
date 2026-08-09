<?php
/**
 * Funciones para Sistema de Solicitudes de Productos
 * Utiliza mysqli (compatible con la instalación del sistema)
 */

// Obtener todas las áreas
function getAreas() {
    $conn = getDBConnection();
    $result = mysqli_query($conn, "SELECT * FROM areas ORDER BY name ASC");
    $areas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $areas[] = $row;
    }
    return $areas;
}

// Obtener área de un usuario
function getUserArea($user_id) {
    $conn = getDBConnection();
    $user_id = (int)$user_id;
    $result = mysqli_query($conn, "SELECT area_id FROM users WHERE id = $user_id");
    $row = mysqli_fetch_assoc($result);
    return $row['area_id'] ?? null;
}

// Actualizar área de usuario
function setUserArea($user_id, $area_id) {
    $conn = getDBConnection();
    $user_id = (int)$user_id;
    $area_id = (int)$area_id;
    return mysqli_query($conn, "UPDATE users SET area_id = $area_id WHERE id = $user_id");
}

// Crear solicitud de producto del inventario
function createProductRequest($product_id, $area_id, $quantity, $notes = '') {
    $conn = getDBConnection();
    $product_id = $product_id ? (int)$product_id : 'NULL';
    $area_id = (int)$area_id;
    $user_id = getCurrentUserId();
    $quantity = (int)$quantity;
    $notes = sanitize($notes);

    $sql = "INSERT INTO product_requests
            (product_id, area_id, user_id, quantity, status, notes)
            VALUES ($product_id, $area_id, $user_id, $quantity, 'pendiente', '$notes')";

    if (mysqli_query($conn, $sql)) {
        return mysqli_insert_id($conn);
    }
    return false;
}

// Crear solicitud de producto personalizado (no existe)
function createCustomProductRequest($product_name, $product_desc, $area_id, $quantity, $notes = '') {
    $conn = getDBConnection();
    $product_name = sanitize($product_name);
    $product_desc = sanitize($product_desc);
    $area_id = (int)$area_id;
    $user_id = getCurrentUserId();
    $quantity = (int)$quantity;
    $notes = sanitize($notes);

    $sql = "INSERT INTO custom_product_requests
            (product_name, product_description, area_id, user_id, quantity, status, notes)
            VALUES ('$product_name', '$product_desc', $area_id, $user_id, $quantity, 'pendiente', '$notes')";

    if (mysqli_query($conn, $sql)) {
        return mysqli_insert_id($conn);
    }
    return false;
}

// Obtener solicitudes pendientes de un área
function getAreaRequests($area_id = null, $status = 'pendiente') {
    $conn = getDBConnection();
    $area_id = $area_id ? (int)$area_id : (int)getUserArea(getCurrentUserId());
    $status = sanitize($status);

    $sql = "SELECT pr.*, p.name as product_name, p.code, a.name as area_name, u.username
            FROM product_requests pr
            LEFT JOIN products p ON pr.product_id = p.id
            LEFT JOIN areas a ON pr.area_id = a.id
            LEFT JOIN users u ON pr.user_id = u.id
            WHERE pr.area_id = $area_id AND pr.status = '$status'
            ORDER BY pr.created_at DESC";

    $result = mysqli_query($conn, $sql);
    $requests = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
    return $requests;
}

// Obtener todas las solicitudes (admin)
function getAllRequests($status = 'pendiente', $request_type = 'product') {
    $conn = getDBConnection();
    $status = sanitize($status);

    if ($request_type === 'product') {
        $sql = "SELECT pr.*, p.name as product_name, p.code, a.name as area_name, u.username
                FROM product_requests pr
                LEFT JOIN products p ON pr.product_id = p.id
                LEFT JOIN areas a ON pr.area_id = a.id
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.status = '$status'
                ORDER BY pr.created_at DESC";
    } else {
        $sql = "SELECT cpr.*, a.name as area_name, u.username
                FROM custom_product_requests cpr
                LEFT JOIN areas a ON cpr.area_id = a.id
                LEFT JOIN users u ON cpr.user_id = u.id
                WHERE cpr.status = '$status'
                ORDER BY cpr.created_at DESC";
    }

    $result = mysqli_query($conn, $sql);
    $requests = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
    return $requests;
}

// Obtener una solicitud específica
function getRequest($id, $type = 'product') {
    $conn = getDBConnection();
    $id = (int)$id;

    if ($type === 'product') {
        $sql = "SELECT pr.*, p.name as product_name, p.code, a.name as area_name, u.username
                FROM product_requests pr
                LEFT JOIN products p ON pr.product_id = p.id
                LEFT JOIN areas a ON pr.area_id = a.id
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.id = $id";
    } else {
        $sql = "SELECT cpr.*, a.name as area_name, u.username
                FROM custom_product_requests cpr
                LEFT JOIN areas a ON cpr.area_id = a.id
                LEFT JOIN users u ON cpr.user_id = u.id
                WHERE cpr.id = $id";
    }

    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result);
}

// Aprobar solicitud
function approveRequest($id, $type = 'product') {
    $conn = getDBConnection();
    $id = (int)$id;
    $table = $type === 'product' ? 'product_requests' : 'custom_product_requests';

    return mysqli_query($conn, "UPDATE $table SET status = 'aprobado' WHERE id = $id AND status = 'pendiente'");
}

// Rechazar solicitud
function rejectRequest($id, $type = 'product') {
    $conn = getDBConnection();
    $id = (int)$id;
    $table = $type === 'product' ? 'product_requests' : 'custom_product_requests';

    return mysqli_query($conn, "UPDATE $table SET status = 'rechazado' WHERE id = $id AND status = 'pendiente'");
}

// Marcar como entregado/completado
function completeRequest($id, $type = 'product') {
    $conn = getDBConnection();
    $id = (int)$id;

    if ($type === 'product') {
        $status_value = 'entregado';
        $table = 'product_requests';
    } else {
        $status_value = 'completado';
        $table = 'custom_product_requests';
    }

    return mysqli_query($conn, "UPDATE $table SET status = '$status_value' WHERE id = $id AND status = 'aprobado'");
}

// Actualizar cantidad de solicitud
function updateRequestQuantity($id, $quantity, $type = 'product') {
    $conn = getDBConnection();
    $id = (int)$id;
    $quantity = (int)$quantity;
    $table = $type === 'product' ? 'product_requests' : 'custom_product_requests';

    return mysqli_query($conn, "UPDATE $table SET quantity = $quantity WHERE id = $id");
}

// Obtener estadísticas de solicitudes
function getRequestStats() {
    $conn = getDBConnection();

    $stats = [];

    // Solicitudes pendientes
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM product_requests WHERE status = 'pendiente'");
    $stats['pending_product'] = mysqli_fetch_assoc($result)['total'];

    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM custom_product_requests WHERE status = 'pendiente'");
    $stats['pending_custom'] = mysqli_fetch_assoc($result)['total'];

    // Solicitudes aprobadas hoy
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM product_requests WHERE status = 'aprobado' AND DATE(created_at) = CURDATE()");
    $stats['approved_today'] = mysqli_fetch_assoc($result)['total'];

    return $stats;
}

// =====================================================
// FUNCIONES DE CARRITO
// =====================================================

// Inicializar carrito en sesi�n
function initCart() {
    if (!isset($_SESSION['request_cart'])) {
        $_SESSION['request_cart'] = [];
    }
}

// Agregar producto al carrito
function addToCart($product_id, $quantity, $product_name = '', $product_code = '') {
    initCart();

    $product_id = (int)$product_id;
    $quantity = (int)$quantity;

    $product = getProduct($product_id);
    $currentQuantity = (int)($_SESSION['request_cart'][$product_id]['quantity'] ?? 0);
    if (!$product || $quantity < 1 || $currentQuantity + $quantity > (int)$product['quantity']) {
        return false;
    }

    if (isset($_SESSION['request_cart'][$product_id])) {
        $_SESSION['request_cart'][$product_id]['quantity'] += $quantity;
    } else {
        $_SESSION['request_cart'][$product_id] = [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'product_code' => $product_code,
            'quantity' => $quantity
        ];
    }

    return true;
}

// Quitar producto del carrito
function removeFromCart($product_id) {
    initCart();
    $product_id = (int)$product_id;

    if (isset($_SESSION['request_cart'][$product_id])) {
        unset($_SESSION['request_cart'][$product_id]);
    }
}

// Actualizar cantidad en carrito
function updateCartItem($product_id, $quantity) {
    initCart();
    $product_id = (int)$product_id;
    $quantity = (int)$quantity;

    if ($quantity <= 0) {
        removeFromCart($product_id);
        return true;
    } elseif (isset($_SESSION['request_cart'][$product_id])) {
        $product = getProduct($product_id);
        if (!$product || $quantity > (int)$product['quantity']) {
            return false;
        }
        $_SESSION['request_cart'][$product_id]['quantity'] = $quantity;
        return true;
    }

    return false;
}

// Obtener carrito
function getCart() {
    initCart();
    return $_SESSION['request_cart'];
}

// Vaciar carrito
function clearCart() {
    $_SESSION['request_cart'] = [];
}

// Crear solicitud desde carrito (UNA sola solicitud con todos los productos)
function createRequestFromCart($area_id) {
    $conn = getDBConnection();
    $user_id = getCurrentUserId();
    $area_id = (int)$area_id;

    initCart();

    if (empty($_SESSION['request_cart'])) {
        return false;
    }

    // Calcular cantidad total de items
    $total_quantity = 0;
    $items_list = [];

    foreach ($_SESSION['request_cart'] as $item) {
        $requestedQuantity = (int)$item['quantity'];
        $product = getProduct((int)$item['product_id']);
        if (!$product || $requestedQuantity < 1 || $requestedQuantity > (int)$product['quantity']) {
            return false;
        }
        $total_quantity += $requestedQuantity;
        $items_list[] = [
            'product_id' => (int)$item['product_id'],
            'product_name' => $item['product_name'],
            'quantity' => $requestedQuantity
        ];
    }

    // Guardar items como JSON en el campo notes
    $items_json = mysqli_real_escape_string($conn, json_encode($items_list));

    // Crear UNA sola solicitud con el primer producto como referencia
    $first_product_id = (int)$_SESSION['request_cart'][array_key_first($_SESSION['request_cart'])]['product_id'];

    $sql = "INSERT INTO product_requests (product_id, area_id, user_id, quantity, notes, status)
            VALUES ($first_product_id, $area_id, $user_id, $total_quantity, 'ITEMS_JSON:$items_json', 'pendiente')";

    if (mysqli_query($conn, $sql)) {
        $request_id = mysqli_insert_id($conn);
        clearCart();
        return [$request_id];
    }

    return false;
}

// Actualizar cantidad entregada (con descuento de inventario para múltiples productos)
function updateDeliveredQuantity($request_id, $delivered_quantity) {
    $conn = getDBConnection();
    $request_id = (int)$request_id;
    $delivered_quantity = (int)$delivered_quantity;

    // Obtener la solicitud con sus detalles
    $result = mysqli_query($conn, "SELECT product_id, quantity, notes FROM product_requests WHERE id = $request_id");
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return false;
    }

    $items = [];

    // Verificar si hay items guardados como JSON
    if (strpos($row['notes'], 'ITEMS_JSON:') === 0) {
        $json_str = substr($row['notes'], 11);
        $items = json_decode($json_str, true) ?? [];
    } else {
        // Si no hay JSON, crear un único item del producto
        $items = [[
            'product_id' => $row['product_id'],
            'quantity' => $row['quantity']
        ]];
    }

    // Calcular prorrata: si se entregan menos del solicitado, aplicar proporcionalmente
    $requested_total = $row['quantity'];
    $delivery_ratio = $requested_total > 0 ? ($delivered_quantity / $requested_total) : 1;

    // Descontar del inventario para cada producto
    foreach ($items as $item) {
        $product_id = (int)$item['product_id'];
        $item_quantity = (int)$item['quantity'];
        // Calcular cuánto de este item se entrega (proporcionalmente)
        $to_deliver = max(1, round($item_quantity * $delivery_ratio));

        $sql_discount = "UPDATE products SET quantity = quantity - $to_deliver WHERE id = $product_id";
        if (!mysqli_query($conn, $sql_discount)) {
            return false;
        }
    }

    // Actualizar solicitud
    $sql_update = "UPDATE product_requests SET quantity = $delivered_quantity, status = 'entregado' WHERE id = $request_id";

    return mysqli_query($conn, $sql_update);
}

// Actualizar cantidad entregada por cada producto (nuevo)
function updateDeliveredQuantityByProduct($request_id, $delivered_items) {
    $conn = getDBConnection();
    $request_id = (int)$request_id;

    // Obtener la solicitud con sus detalles y el nombre del área
    $result = mysqli_query($conn, "SELECT pr.product_id, pr.quantity, pr.notes, pr.area_id, pr.status, a.name as area_name
                                   FROM product_requests pr
                                   LEFT JOIN areas a ON pr.area_id = a.id
                                   WHERE pr.id = $request_id");
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return false;
    }
    if ($row['status'] !== 'aprobado') {
        return false;
    }

    $area_name = $row['area_name'] ?? 'Sin Área';
    $items = [];

    // Verificar si hay items guardados como JSON
    if (strpos($row['notes'], 'ITEMS_JSON:') === 0) {
        $json_str = substr($row['notes'], 11);
        $items = json_decode($json_str, true) ?? [];
    } else {
        // Si no hay JSON, crear un único item del producto
        $items = [[
            'product_id' => $row['product_id'],
            'quantity' => $row['quantity']
        ]];
    }

    $total_delivered = 0;
    $user_id = (int)getCurrentUserId();

    mysqli_begin_transaction($conn);
    try {
        foreach ($items as $item) {
            $product_id = (int)$item['product_id'];
            $requested = (int)$item['quantity'];
            $to_deliver = (int)($delivered_items[$product_id] ?? 0);

            if ($to_deliver < 0 || $to_deliver > $requested) {
                throw new RuntimeException('Cantidad de entrega invalida.');
            }
            if ($to_deliver === 0) {
                continue;
            }

            $stockStatement = mysqli_prepare($conn, 'SELECT quantity FROM products WHERE id = ? FOR UPDATE');
            mysqli_stmt_bind_param($stockStatement, 'i', $product_id);
            mysqli_stmt_execute($stockStatement);
            $stockResult = mysqli_stmt_get_result($stockStatement);
            $stock = $stockResult ? mysqli_fetch_assoc($stockResult) : null;
            mysqli_stmt_close($stockStatement);

            if (!$stock || $to_deliver > (int)$stock['quantity']) {
                throw new RuntimeException('Existencia insuficiente para completar la entrega.');
            }

            $movementStatement = mysqli_prepare($conn, "INSERT INTO movements (product_id, type, quantity, reason, user_id) VALUES (?, 'salida', ?, ?, ?)");
            mysqli_stmt_bind_param($movementStatement, 'iisi', $product_id, $to_deliver, $area_name, $user_id);
            if (!mysqli_stmt_execute($movementStatement)) {
                throw new RuntimeException('No se pudo guardar el movimiento.');
            }
            mysqli_stmt_close($movementStatement);

            $newStock = (int)$stock['quantity'] - $to_deliver;
            $updateStock = mysqli_prepare($conn, 'UPDATE products SET quantity = ? WHERE id = ?');
            mysqli_stmt_bind_param($updateStock, 'ii', $newStock, $product_id);
            if (!mysqli_stmt_execute($updateStock)) {
                throw new RuntimeException('No se pudo actualizar la existencia.');
            }
            mysqli_stmt_close($updateStock);
            $total_delivered += $to_deliver;
        }

        if ($total_delivered < 1) {
            throw new RuntimeException('La entrega debe incluir al menos un producto.');
        }

        $updateRequest = mysqli_prepare($conn, "UPDATE product_requests SET quantity = ?, status = 'entregado' WHERE id = ? AND status = 'aprobado'");
        mysqli_stmt_bind_param($updateRequest, 'ii', $total_delivered, $request_id);
        if (!mysqli_stmt_execute($updateRequest) || mysqli_stmt_affected_rows($updateRequest) !== 1) {
            throw new RuntimeException('No se pudo actualizar la solicitud.');
        }
        mysqli_stmt_close($updateRequest);

        mysqli_commit($conn);
        return true;
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        error_log('Error al entregar solicitud: ' . $error->getMessage());
        return false;
    }
}

?>
