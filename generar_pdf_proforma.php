<?php
session_start();
require('fpdf.php');
include 'conexion.php';

$nombre_generador = $_SESSION['nombre'] ?? 'Sistema';
$id_proforma = $_GET['id'] ?? '';
if (empty($id_proforma)) { die("ID de proforma no proporcionado."); }

$tasa = $_SESSION['tasa_hoy'] ?? 36.6243;

$sql_cabecera = "SELECT p.*, e.Nombre AS Nombre_Encargado 
                 FROM PROFORMA p 
                 INNER JOIN ENCARGADO e ON p.Id_Encargado = e.Id_Encargado 
                 WHERE p.Id_Proforma = ?";
$res_cabecera = sqlsrv_query($conn, $sql_cabecera, array($id_proforma));
$proforma = sqlsrv_fetch_array($res_cabecera, SQLSRV_FETCH_ASSOC);
if (!$proforma) { die("Error: La proforma solicitada no existe."); }

class PDF extends FPDF {
    public $generador;
    function Header() {
        if(file_exists('Ener.png')){ $this->Image('Ener.png', 10, 8, 55); }
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(110, 10, utf8_decode('ENERGÍA Y SUMINISTROS S.A.'), 0, 1, 'R');
        $this->SetFont('Arial', '', 9);
        $this->Cell(190, 5, utf8_decode('RUC: J0310000000001'), 0, 1, 'R');
        $this->Cell(190, 5, utf8_decode('Managua - Tipitapa, Nicaragua'), 0, 1, 'R');
        $this->Cell(190, 5, utf8_decode('Tel: +505 8603 9467 | info@energiaysuministros.com'), 0, 1, 'R');
        $this->Ln(15);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(95, 10, utf8_decode('Página ').$this->PageNo().'/{nb}', 0, 0, 'L');
        $this->Cell(95, 10, utf8_decode('Generado por: ' . $this->generador), 0, 0, 'R');
    }
}

$pdf = new PDF();
$pdf->generador = $nombre_generador;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

$pdf->SetFillColor(30, 41, 59);
$pdf->SetTextColor(255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 10, utf8_decode("PROFORMA / COTIZACIÓN DE MATERIALES #") . $id_proforma, 1, 1, 'C', true);
$pdf->Ln(5);

$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(120, 7, utf8_decode("CLIENTE / DATOS DEL PROYECTO:"), 0, 0);
$pdf->Cell(70, 7, utf8_decode("FECHA DE EMISIÓN:"), 0, 1);
$pdf->SetFont('Arial', '', 10);
$x = $pdf->GetX(); $y = $pdf->GetY();
$pdf->MultiCell(115, 6, utf8_decode($proforma['Datos']), 0, 'L');
$pdf->SetXY($x + 120, $y);
$fecha = is_object($proforma['Fecha']) ? $proforma['Fecha']->format('d/m/Y') : $proforma['Fecha'];
$pdf->Cell(70, 6, $fecha, 0, 1);
$pdf->SetY($y + 14);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, utf8_decode("ATENDIDO POR: "), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(150, 6, utf8_decode($proforma['Nombre_Encargado']), 0, 1);
$pdf->Ln(5);

// Tabla limpia en C$
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(100, 8, utf8_decode("DESCRIPCIÓN DEL MATERIAL"), 1, 0, 'C', true);
$pdf->Cell(20, 8, utf8_decode("CANT."), 1, 0, 'C', true);
$pdf->Cell(35, 8, utf8_decode("P.UNIT C\$"), 1, 0, 'C', true);
$pdf->Cell(35, 8, utf8_decode("SUBTOTAL C\$"), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$sql_items = "SELECT p.Nombre, p.Precio_Unitario, pp.Cantidad 
              FROM PROFORMA_PRODUCTO pp 
              INNER JOIN PRODUCTO p ON pp.Id_Producto = p.Id_Producto 
              WHERE pp.Id_Proforma = ?";
$res_items = sqlsrv_query($conn, $sql_items, array($id_proforma));

$subtotal_nio = 0;

while ($item = sqlsrv_fetch_array($res_items, SQLSRV_FETCH_ASSOC)) {
    $precio_usd  = $item['Precio_Unitario'];
    $precio_nio  = $precio_usd * $tasa;
    $cant        = $item['Cantidad'];
    $sub_nio     = $cant * $precio_nio;
    $subtotal_nio += $sub_nio;

    $pdf->Cell(100, 8, utf8_decode($item['Nombre']), 1, 0, 'L');
    $pdf->Cell(20, 8, $cant, 1, 0, 'C');
    $pdf->Cell(35, 8, "C\$ " . number_format($precio_nio, 2), 1, 0, 'R');
    $pdf->Cell(35, 8, "C\$ " . number_format($sub_nio, 2), 1, 1, 'R');
}

$iva_nio      = $subtotal_nio * 0.15;
$total_nio    = $subtotal_nio + $iva_nio;

$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(140, 7, "SUBTOTAL:", 0, 0, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 7, "C\$ " . number_format($subtotal_nio, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(140, 7, "IVA (15%):", 0, 0, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 7, "C\$ " . number_format($iva_nio, 2), 0, 1, 'R');

$pdf->SetDrawColor(30, 41, 59);
$pdf->SetLineWidth(0.5);
$pdf->Line($pdf->GetX() + 130, $pdf->GetY(), 200, $pdf->GetY());
$pdf->SetLineWidth(0.2);
$pdf->SetDrawColor(0, 0, 0);
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0);
$pdf->Cell(140, 10, "TOTAL ESTIMADO:", 0, 0, 'R');
$pdf->Cell(50, 10, "C\$ " . number_format($total_nio, 2), 0, 1, 'R');

$pdf->Ln(3);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(190, 4, utf8_decode("* Precios sujetos a cambios sin previo aviso. Esta proforma tiene una vigencia comercial de 15 días hábiles."), 0, 1, 'C');

if ($proforma['Estado'] == 'Anulado') {
    $pdf->SetTextColor(239, 68, 68);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Ln(5);
    $pdf->Cell(190, 10, utf8_decode("COTIZACIÓN RECHAZADA / ANULADA"), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(190, 5, utf8_decode("Motivo: " . $proforma['Motivo_Anulacion']), 0, 1, 'C');
}

$pdf->SetTextColor(0);
$pdf->Ln(20);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 10, '______________________________', 0, 0, 'C');
$pdf->Cell(95, 10, '______________________________', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, utf8_decode('ENTREGA CONFORME'), 0, 0, 'C');
$pdf->Cell(95, 6, utf8_decode('RECIBE CONFORME'), 0, 1, 'C');

$pdf->Output('I', 'Proforma_Cotizacion_'.$id_proforma.'.pdf');
?>