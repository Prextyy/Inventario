<?php
// Generador simple de PDF sin librerías externas

function generateInventoryPDF($products, $stats) {
    // Configurar headers para PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="inventario_' . date('Y-m-d') . '.pdf"');

    // Crear PDF básico
    $pdf = new SimplePDF();
    $pdf->addPage();

    // Encabezado
    $pdf->setFont('Helvetica', 'B', 16);
    $pdf->text(50, 50, SITE_NAME);

    $pdf->setFont('Helvetica', 'B', 14);
    $pdf->text(50, 70, 'Reporte de Inventario');

    $pdf->setFont('Helvetica', '', 10);
    $pdf->text(50, 85, 'Fecha: ' . date('d/m/Y H:i'));

    // Estadísticas
    $y = 110;
    $pdf->setFont('Helvetica', 'B', 11);
    $pdf->text(50, $y, 'Resumen:');

    $pdf->setFont('Helvetica', '', 10);
    $y += 15;
    $pdf->text(50, $y, 'Total de Productos: ' . $stats['total_products']);
    $y += 15;
    $pdf->text(50, $y, 'Productos con Stock Bajo: ' . $stats['low_stock']);
    $y += 15;
    $pdf->text(50, $y, 'Valor Total del Inventario: $' . number_format($stats['total_value'], 2));

    // Tabla de productos
    $y += 30;
    $pdf->setFont('Helvetica', 'B', 9);

    // Encabezados de tabla
    $pdf->text(50, $y, 'Codigo');
    $pdf->text(120, $y, 'Nombre');
    $pdf->text(280, $y, 'Categoria');
    $pdf->text(380, $y, 'Cantidad');
    $pdf->text(450, $y, 'Precio');

    $y += 5;
    $pdf->line(50, $y, 550, $y);

    // Datos
    $pdf->setFont('Helvetica', '', 8);
    $y += 15;

    foreach ($products as $product) {
        if ($y > 750) {
            $pdf->addPage();
            $y = 50;
        }

        $pdf->text(50, $y, substr($product['code'], 0, 15));
        $pdf->text(120, $y, substr($product['name'], 0, 30));
        $pdf->text(280, $y, substr($product['category_name'] ?? 'N/A', 0, 20));
        $pdf->text(380, $y, $product['quantity']);
        $pdf->text(450, $y, '$' . number_format($product['unit_price'], 2));

        $y += 15;
    }

    // Output
    $pdf->output();
}

class SimplePDF {
    private $pages = [];
    private $currentPage = -1;
    private $font = 'Helvetica';
    private $fontStyle = '';
    private $fontSize = 12;

    public function addPage() {
        $this->pages[] = [
            'content' => ''
        ];
        $this->currentPage++;
    }

    public function setFont($font, $style = '', $size = 12) {
        $this->font = $font;
        $this->fontStyle = $style;
        $this->fontSize = $size;
    }

    public function text($x, $y, $text) {
        $this->pages[$this->currentPage]['content'] .=
            "BT\n" .
            "/" . $this->font . ($this->fontStyle ? '-' . $this->fontStyle : '') . " " . $this->fontSize . " Tf\n" .
            "$x " . (842 - $y) . " Td\n" .
            "(" . $this->escapeText($text) . ") Tj\n" .
            "ET\n";
    }

    public function line($x1, $y1, $x2, $y2) {
        $y1 = 842 - $y1;
        $y2 = 842 - $y2;
        $this->pages[$this->currentPage]['content'] .=
            "$x1 $y1 m\n" .
            "$x2 $y2 l\n" .
            "S\n";
    }

    private function escapeText($text) {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', (string)$text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded ?: (string)$text);
    }

    public function output() {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageCount = count($this->pages);
        $pageRefs = '';
        for ($i = 0; $i < $pageCount; $i++) {
            $pageRefs .= (3 + $i * 2) . " 0 R ";
        }
        $objects[2] = "<< /Type /Pages /Kids [$pageRefs] /Count $pageCount >>";

        $objNum = 3;
        foreach ($this->pages as $page) {
            $contentObj = $objNum + 1;
            $objects[$objNum] = "<< /Type /Page /Parent 2 0 R /Resources << /Font << /Helvetica << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> /Helvetica-Bold << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >> >> >> /MediaBox [0 0 595 842] /Contents $contentObj 0 R >>";
            $content = $page['content'];
            $length = strlen($content);
            $objNum++;
            $objects[$objNum] = "<< /Length $length >>\nstream\n$content\nendstream";
            $objNum++;
        }

        ksort($objects);
        $pdfContent = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdfContent);
            $pdfContent .= "$number 0 obj\n$object\nendobj\n";
        }

        $xrefPos = strlen($pdfContent);
        $size = max(array_keys($objects)) + 1;
        $pdfContent .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdfContent .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }
        $pdfContent .= "trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF\n";

        echo $pdfContent;
    }
}
