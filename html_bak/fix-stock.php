<?php
/**
 * Script de corrección de stock duplicado (uso único)
 *
 * Recalcula la cantidad de TODOS los productos sumando sus entradas
 * y restando sus salidas desde el historial de movimientos.
 *
 * EJECUTA ESTE ARCHIVO UNA SOLA VEZ y luego elimínalo por seguridad.
 */

require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$conn    = getDBConnection();
$results = [];
$dry_run = !isset($_POST['confirm']);

// Obtener todos los productos
$res = mysqli_query($conn, "SELECT id, code, name, quantity FROM products ORDER BY name ASC");
$all_products = [];
while ($row = mysqli_fetch_assoc($res)) {
    $all_products[] = $row;
}

foreach ($all_products as $p) {
    $pid = (int)$p['id'];

    // Calcular stock real desde movimientos
    $sql = "SELECT
                COALESCE(SUM(CASE WHEN type = 'entrada' THEN quantity ELSE 0 END), 0) AS total_entrada,
                COALESCE(SUM(CASE WHEN type = 'salida'  THEN quantity ELSE 0 END), 0) AS total_salida
            FROM movements
            WHERE product_id = $pid";
    $mres = mysqli_query($conn, $sql);
    $mrow = mysqli_fetch_assoc($mres);

    $correct_qty = (int)$mrow['total_entrada'] - (int)$mrow['total_salida'];
    $current_qty = (int)$p['quantity'];
    $diff        = $current_qty - $correct_qty;

    if ($diff !== 0 && !$dry_run) {
        mysqli_query($conn, "UPDATE products SET quantity = $correct_qty WHERE id = $pid");
    }

    $results[] = [
        'id'        => $p['id'],
        'code'      => $p['code'],
        'name'      => $p['name'],
        'current'   => $current_qty,
        'correct'   => $correct_qty,
        'diff'      => $diff,
        'needs_fix' => $diff !== 0,
    ];
}

$affected = array_filter($results, fn($r) => $r['needs_fix']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corrección de Stock Duplicado</title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
    <style>
        .ok    { color: #065f46; }
        .bad   { color: #991b1b; font-weight: 700; }
        .fixed { background: #d1fae5; }
        .wrong { background: #fee2e2; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="page-header">
            <h1>🔧 Corrección de Stock Duplicado</h1>
        </div>

        <?php if ($dry_run): ?>
            <div class="alert alert-warning">
                <strong>Modo Vista Previa</strong> — Aún no se han aplicado cambios.
                Revisa la tabla y haz clic en <strong>"Aplicar Correcciones"</strong> para confirmar.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <strong>✓ Correcciones aplicadas exitosamente.</strong>
                Elimina este archivo del servidor: <code>fix-stock.php</code>
            </div>
        <?php endif; ?>

        <?php if (count($affected) === 0 && $dry_run): ?>
            <div class="alert alert-success">
                ✅ Todos los productos tienen el stock correcto. No se necesitan correcciones.
            </div>
        <?php endif; ?>

        <?php if (count($results) > 0): ?>
        <div class="table-responsive" style="margin-bottom: 24px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Stock Actual (BD)</th>
                        <th>Stock Correcto (movimientos)</th>
                        <th>Diferencia</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr class="<?php echo $r['needs_fix'] ? 'wrong' : ''; ?>">
                            <td><?php echo htmlspecialchars($r['code']); ?></td>
                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo $r['current']; ?></td>
                            <td><strong><?php echo $r['correct']; ?></strong></td>
                            <td class="<?php echo $r['diff'] != 0 ? 'bad' : 'ok'; ?>">
                                <?php echo $r['diff'] > 0 ? '+' . $r['diff'] : $r['diff']; ?>
                            </td>
                            <td>
                                <?php if (!$r['needs_fix']): ?>
                                    <span class="badge badge-success">✓ OK</span>
                                <?php elseif (!$dry_run): ?>
                                    <span class="badge badge-success">✓ Corregido</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Necesita corrección</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($dry_run && count($affected) > 0): ?>
            <form method="POST">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger btn-lg"
                        onclick="return confirm('¿Aplicar correcciones a <?php echo count($affected); ?> producto(s)?')">
                    ⚠️ Aplicar Correcciones (<?php echo count($affected); ?> productos)
                </button>
                <a href="dashboard.php" class="btn btn-lg">Cancelar</a>
            </form>
        <?php endif; ?>

        <?php if (!$dry_run): ?>
            <a href="products.php" class="btn btn-primary btn-lg">← Ir a Productos</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
