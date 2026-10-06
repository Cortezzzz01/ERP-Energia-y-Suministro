<?php
session_start();
require('fpdf.php');
include 'conexion.php';

$nombre_generador = $_SESSION['nombre'] ?? "Administrador";
$id_factura = $_GET['id'] ?? '';
if (empty($id_factura)) { die("ID de factura no proporcionado."); }

$sql_cabecera = "SELECT f.*, e.Nombre AS Nombre_Encargado, c.Nombre AS Nombre_Cliente
                 FROM FACTURA f 
                 INNER JOIN ENCARGADO e ON f.Id_Encargado = e.Id_Encargado
                 LEFT JOIN CLIENTE c ON f.Id_Cliente = c.Id_Cliente
                 WHERE f.Id_Factura = ?";
$res_cabecera = sqlsrv_query($conn, $sql_cabecera, array($id_factura));
$factura = sqlsrv_fetch_array($res_cabecera, SQLSRV_FETCH_ASSOC);

// Tasa desde sesión o base de datos
$tasa_db = $factura['Tasa_Cambio'] ?? 0;
$tasa = ($tasa_db > 0) ? $tasa_db : ($_SESSION['tasa_hoy'] ?? 36.6243);

class PDF extends FPDF {
    public $generador;
    function Header() {
        if(file_exists('Ener.png')){ $this->Image('Ener.png', 10, 8, 33); }
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
$pdf->Cell(190, 10, utf8_decode("FACTURA DE SERVICIOS #") . $id_factura, 1, 1, 'C', true);
$pdf->Ln(5);

$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 7, utf8_decode("CLIENTE / DATOS:"), 0, 0);
$pdf->Cell(95, 7, utf8_decode("ENCARGADO DEL PROYECTO:"), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(95, 6, utf8_decode($factura['Datos']), 0, 'L');
$pdf->SetY($pdf->GetY() - 6);
$pdf->SetX(105);
$pdf->Cell(95, 6, utf8_decode($factura['Nombre_Encargado']), 0, 1);
$pdf->Ln(10);

// Tabla limpiada de la doble moneda
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(100, 8, utf8_decode("DESCRIPCIÓN DEL MATERIAL"), 1, 0, 'C', true);
$pdf->Cell(20, 8, utf8_decode("CANT."), 1, 0, 'C', true);
$pdf->Cell(35, 8, utf8_decode("P.UNIT C\$"), 1, 0, 'C', true);
$pdf->Cell(35, 8, utf8_decode("SUBTOTAL C\$"), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$sql_items = "SELECT p.Nombre, p.Precio_Unitario, fp.Cantidad,
                     ISNULL(fp.Precio_Unitario_NIO, p.Precio_Unitario * ?) AS Precio_NIO
              FROM FACTURA_PRODUCTO fp 
              INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto 
              WHERE fp.Id_Factura = ?";
$res_items = sqlsrv_query($conn, $sql_items, array($tasa, $id_factura));

$subtotal_nio = 0;

while ($item = sqlsrv_fetch_array($res_items, SQLSRV_FETCH_ASSOC)) {
    $precio_nio  = $item['Precio_NIO'];
    $cant        = $item['Cantidad'];
    $sub_nio     = $cant * $precio_nio;
    $subtotal_nio += $sub_nio;

    $pdf->Cell(100, 8, utf8_decode($item['Nombre']), 1, 0, 'L');
    $pdf->Cell(20, 8, $cant, 1, 0, 'C');
    $pdf->Cell(35, 8, "C\$ " . number_format($precio_nio, 2), 1, 0, 'R');
    $pdf->Cell(35, 8, "C\$ " . number_format($sub_nio, 2), 1, 1, 'R');
}

$iva_nio   = $subtotal_nio * 0.15;
$total_nio = $subtotal_nio + $iva_nio;

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
$pdf->Cell(140, 10, "TOTAL A PAGAR:", 0, 0, 'R');
$pdf->Cell(50, 10, "C\$ " . number_format($total_nio, 2), 0, 1, 'R');

if ($factura['Estado'] == 'Anulado') {
    $pdf->SetTextColor(239, 68, 68);
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Ln(10);
    $pdf->Cell(190, 10, utf8_decode("DOCUMENTO ANULADO"), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 5, utf8_decode("Motivo: " . $factura['Motivo_Anulacion']), 0, 1, 'C');
}

$pdf->SetTextColor(0);
$pdf->Ln(25);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 10, '______________________________', 0, 0, 'C');
$pdf->Cell(95, 10, '______________________________', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, utf8_decode('ENTREGA CONFORME'), 0, 0, 'C');
$pdf->Cell(95, 6, utf8_decode('RECIBE CONFORME'), 0, 1, 'C');

$pdf->Output('I', 'Factura_'.$id_factura.'.pdf');
?>