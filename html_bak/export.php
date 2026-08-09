<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$products = getProducts();
$type = $_GET['type'] ?? '';

if ($type === 'pdf') {
    require_once 'includes/pdf-generator.php';
    generateInventoryPDF($products, getStats());
    exit;
}

if (in_array($type, ['catalogo', 'completo'], true)) {
    $complete = $type === 'completo';
    $filename = ($complete ? 'inventario_completo_' : 'catalogo_productos_') . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    $headers = $complete
        ? ['Codigo', 'Producto', 'Categoria', 'Descripcion', 'Cantidad', 'Stock minimo', 'Precio unitario', 'Valor total']
        : ['Codigo', 'Producto', 'Categoria', 'Descripcion'];
    fputcsv($output, $headers);

    $safeCell = function ($value) {
        $value = (string)$value;
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    };

    foreach ($products as $product) {
        $row = [
            $safeCell($product['code']),
            $safeCell($product['name']),
            $safeCell($product['category_name'] ?? 'Sin categoria'),
            $safeCell($product['description'] ?? ''),
        ];
        if ($complete) {
            $row[] = (int)$product['quantity'];
            $row[] = (int)$product['min_stock'];
            $row[] = number_format((float)$product['unit_price'], 2, '.', '');
            $row[] = number_format((float)$product['unit_price'] * (int)$product['quantity'], 2, '.', '');
        }
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b1220">
    <title>Reportes - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <div>
                <span class="eyebrow">Datos y analisis</span>
                <h1>Reportes</h1>
                <p>Descarga informacion actualizada para compartirla o analizarla.</p>
            </div>
        </div>

        <div class="export-options">
            <article class="export-card">
                <span class="export-format format-pdf">PDF</span>
                <h2>Reporte ejecutivo</h2>
                <p>Resumen imprimible con existencias, precios y valor del inventario.</p>
                <a href="export.php?type=pdf" class="btn btn-primary btn-block" target="_blank" rel="noopener">Abrir reporte PDF</a>
            </article>

            <article class="export-card">
                <span class="export-format format-csv">CSV</span>
                <h2>Catalogo compartible</h2>
                <p>Lista de productos sin cantidades ni precios, compatible con Excel.</p>
                <a href="export.php?type=catalogo" class="btn btn-success btn-block">Descargar catalogo</a>
            </article>

            <article class="export-card">
                <span class="export-format format-data">CSV+</span>
                <h2>Inventario completo</h2>
                <p>Incluye existencias, minimos, precios y valores calculados.</p>
                <a href="export.php?type=completo" class="btn btn-info btn-block">Descargar inventario</a>
            </article>
        </div>

        <div class="info-card">
            <strong>Consejo:</strong> Los archivos CSV se abren directamente en Excel, Google Sheets y LibreOffice.
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
