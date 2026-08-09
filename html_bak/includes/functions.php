<?php
require_once "config.php";

// Función para sanitizar inputs
function sanitize($data) {
    $conn = getDBConnection();
    return mysqli_real_escape_string($conn, trim($data));
}

// Función para escapar outputs
function e($data) {
    return htmlspecialchars($data, ENT_QUOTES, "UTF-8");
}

// Obtener todas las categorías
function getCategories() {
    $conn = getDBConnection();
    $sql = "SELECT * FROM categories ORDER BY name ASC";
    $result = mysqli_query($conn, $sql);
    
    $categories = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }
    }
    
    return $categories;
}

// Obtener todos los productos
function getProducts($search = '', $category = '', $limit = 0, $offset = 0) {
    $conn = getDBConnection();
    
    $where = [];
    if ($search) {
        $search = sanitize($search);
        $where[] = "(p.name LIKE '%$search%' OR p.code LIKE '%$search%' OR p.description LIKE '%$search%')";
    }
    
    if ($category) {
        $category = (int)$category;
        $where[] = "p.category_id = $category";
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    $limit = max(0, (int)$limit);
    $offset = max(0, (int)$offset);
    $limit_clause = $limit > 0 ? "LIMIT $limit OFFSET $offset" : "";
    
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            $where_clause 
            ORDER BY p.name ASC 
            $limit_clause";
    
    $result = mysqli_query($conn, $sql);
    
    $products = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
    }
    
    return $products;
}

function countProducts($search = '', $category = '') {
    $conn = getDBConnection();
    $where = [];

    if ($search) {
        $search = sanitize($search);
        $where[] = "(p.name LIKE '%$search%' OR p.code LIKE '%$search%' OR p.description LIKE '%$search%')";
    }
    if ($category) {
        $where[] = 'p.category_id = ' . (int)$category;
    }

    $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products p $where_clause");
    return $result ? (int)mysqli_fetch_assoc($result)['total'] : 0;
}

function getLowStockProducts($limit = 5) {
    $conn = getDBConnection();
    $limit = max(1, (int)$limit);
    $result = mysqli_query($conn, "SELECT * FROM products WHERE quantity <= min_stock ORDER BY quantity ASC, name ASC LIMIT $limit");
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

// Obtener un producto por ID
function getProduct($id) {
    $conn = getDBConnection();
    $id = (int)$id;
    
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = $id 
            LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    return $result ? mysqli_fetch_assoc($result) : null;
}

// Obtener un producto por código
function getProductByCode($code) {
    $conn = getDBConnection();
    $code = sanitize($code);
    
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.code = '$code' 
            LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    return $result ? mysqli_fetch_assoc($result) : null;
}

// Agregar producto
function addProduct($data) {
    $conn = getDBConnection();
    
    $code = sanitize($data['code']);
    $name = sanitize($data['name']);
    $description = sanitize($data['description'] ?? '');
    $category_id = (int)($data['category_id'] ?? 0);
    $quantity = (int)($data['quantity'] ?? 0);
    $min_stock = (int)($data['min_stock'] ?? 5);
    $unit_price = (float)($data['unit_price'] ?? 0);
    
    $sql = "INSERT INTO products (code, name, description, category_id, quantity, min_stock, unit_price) 
            VALUES ('$code', '$name', '$description', " . 
            ($category_id > 0 ? $category_id : "NULL") . ", $quantity, $min_stock, $unit_price)";
    
    if (mysqli_query($conn, $sql)) {
        return mysqli_insert_id($conn);
    }
    
    return false;
}

// Actualizar producto
function updateProduct($id, $data) {
    $conn = getDBConnection();
    $id = (int)$id;
    
    $code = sanitize($data['code']);
    $name = sanitize($data['name']);
    $description = sanitize($data['description'] ?? '');
    $category_id = (int)($data['category_id'] ?? 0);
    $min_stock = (int)($data['min_stock'] ?? 5);
    $unit_price = (float)($data['unit_price'] ?? 0);
    
    $sql = "UPDATE products SET 
            code = '$code', 
            name = '$name', 
            description = '$description', 
            category_id = " . ($category_id > 0 ? $category_id : "NULL") . ", 
            min_stock = $min_stock, 
            unit_price = $unit_price 
            WHERE id = $id";
    
    return mysqli_query($conn, $sql);
}

// Eliminar producto
function deleteProduct($id) {
    $conn = getDBConnection();
    $id = (int)$id;
    
    $sql = "DELETE FROM products WHERE id = $id";
    return mysqli_query($conn, $sql);
}

// Registrar movimiento - CORREGIDO
function registerMovement($product_id, $type, $quantity, $reason = '') {
    $conn = getDBConnection();
    $product_id = (int)$product_id;
    $type = (string)$type;
    $quantity = (int)$quantity;
    $reason = trim((string)$reason);
    $user_id = (int)getCurrentUserId();

    if ($product_id < 1 || $quantity < 1 || $user_id < 1 || !in_array($type, ['entrada', 'salida'], true)) {
        return false;
    }

    mysqli_begin_transaction($conn);
    try {
        $stockStatement = mysqli_prepare($conn, 'SELECT quantity FROM products WHERE id = ? FOR UPDATE');
        mysqli_stmt_bind_param($stockStatement, 'i', $product_id);
        mysqli_stmt_execute($stockStatement);
        $stockResult = mysqli_stmt_get_result($stockStatement);
        $product = $stockResult ? mysqli_fetch_assoc($stockResult) : null;
        mysqli_stmt_close($stockStatement);

        if (!$product || ($type === 'salida' && $quantity > (int)$product['quantity'])) {
            mysqli_rollback($conn);
            return false;
        }

        $movementStatement = mysqli_prepare($conn, 'INSERT INTO movements (product_id, type, quantity, reason, user_id) VALUES (?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($movementStatement, 'isisi', $product_id, $type, $quantity, $reason, $user_id);
        if (!mysqli_stmt_execute($movementStatement)) {
            throw new RuntimeException('No se pudo registrar el movimiento.');
        }
        mysqli_stmt_close($movementStatement);

        $newQuantity = $type === 'entrada'
            ? (int)$product['quantity'] + $quantity
            : (int)$product['quantity'] - $quantity;
        $updateStatement = mysqli_prepare($conn, 'UPDATE products SET quantity = ? WHERE id = ?');
        mysqli_stmt_bind_param($updateStatement, 'ii', $newQuantity, $product_id);
        if (!mysqli_stmt_execute($updateStatement)) {
            throw new RuntimeException('No se pudo actualizar la existencia.');
        }
        mysqli_stmt_close($updateStatement);

        mysqli_commit($conn);
        return true;
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        error_log('Error al registrar movimiento: ' . $error->getMessage());
        return false;
    }
}

// Obtener movimientos
function getMovements($product_id = null, $limit = 50) {
    $conn = getDBConnection();
    
    $where = $product_id ? "WHERE m.product_id = " . (int)$product_id : "";
    
    $sql = "SELECT m.*, p.name as product_name, p.code as product_code, u.username 
            FROM movements m 
            LEFT JOIN products p ON m.product_id = p.id 
            LEFT JOIN users u ON m.user_id = u.id 
            $where 
            ORDER BY m.created_at DESC 
            LIMIT $limit";
    
    $result = mysqli_query($conn, $sql);
    
    $movements = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $movements[] = $row;
        }
    }
    
    return $movements;
}

// Obtener estadísticas
function getStats() {
    $conn = getDBConnection();
    
    $stats = [];
    
    // Total de productos
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products");
    $stats['total_products'] = mysqli_fetch_assoc($result)['total'];
    
    // Productos con bajo stock
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE quantity <= min_stock");
    $stats['low_stock'] = mysqli_fetch_assoc($result)['total'];
    
    // Total de movimientos hoy
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM movements WHERE DATE(created_at) = CURDATE()");
    $stats['movements_today'] = mysqli_fetch_assoc($result)['total'];
    
    // Valor total del inventario
    $result = mysqli_query($conn, "SELECT SUM(quantity * unit_price) as total FROM products");
    $stats['total_value'] = mysqli_fetch_assoc($result)['total'] ?? 0;
    
    return $stats;
}

// Generar código único para producto
function generateProductCode($prefix = 'PROD') {
    $conn = getDBConnection();
    
    do {
        $code = $prefix . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $result = mysqli_query($conn, "SELECT id FROM products WHERE code = '" . sanitize($code) . "'");
    } while ($result && mysqli_num_rows($result) > 0);
    
    return $code;
}

// Subir imagen de producto - NUEVA
function uploadProductImage($product_id, $file) {
    $product_id = (int)$product_id;
    
    if (!isset($file['tmp_name']) || !$file['tmp_name']) {
        return ['error' => 'No se seleccionó archivo.'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Error al subir el archivo.'];
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['error' => 'Solo se aceptan imágenes (JPG, PNG, GIF, WebP).'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'La imagen es demasiado grande (máximo 5MB).'];
    }
    
    $upload_dir = __DIR__ . '/../uploads/products/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $file_ext = $extensions[$mime_type];
    $filename = 'product_' . $product_id . '_' . time() . '.' . $file_ext;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['error' => 'Error al guardar la imagen.'];
    }
    
    $conn = getDBConnection();
    
    // Eliminar imagen anterior si existe
    $result = mysqli_query($conn, "SELECT image FROM products WHERE id = $product_id");
    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['image'] && file_exists(__DIR__ . '/../' . $row['image'])) {
            @unlink(__DIR__ . '/../' . $row['image']);
        }
    }
    
    $image_path = 'uploads/products/' . $filename;
    $image_path_esc = mysqli_real_escape_string($conn, $image_path);
    
    if (mysqli_query($conn, "UPDATE products SET image = '$image_path_esc' WHERE id = $product_id")) {
        return ['success' => true, 'image' => $image_path];
    }
    
    return ['error' => 'Error al guardar en la base de datos.'];
}

// Obtener imagen de producto - NUEVA
function getProductImage($product_id) {
    $conn = getDBConnection();
    $product_id = (int)$product_id;
    
    $result = mysqli_query($conn, "SELECT image FROM products WHERE id = $product_id");
    $row = mysqli_fetch_assoc($result);
    
    return $row['image'] ?? null;
}
?>
