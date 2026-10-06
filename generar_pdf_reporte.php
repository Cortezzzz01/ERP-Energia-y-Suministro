<?php
session_start();
require('fpdf.php');
include 'conexion.php';

// Validar que solo el administrador pueda generar el reporte
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador');
if (!$es_admin) {
    die("Acceso Denegado. Solo los administradores pueden exportar reportes financieros.");
}

$nombre_generador = $_SESSION['nombre'] ?? 'Administrador';
$rango = $_GET['rango'] ?? 'mes';

$tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243;
$tasa_sql = (float)$tasa_hoy;

$fecha_inicio = '';
$fecha_fin = '';
$titulo_periodo = '';

// Lógica de fechas
if ($rango === 'semana') {
    $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
    $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
    $titulo_periodo = "ESTA SEMANA";
} elseif ($rango === 'semana_pasada') {
    $fecha_inicio = date('Y-m-d', strtotime('last week monday'));
    $fecha_fin = date('Y-m-d', strtotime('last week sunday'));
    $titulo_periodo = "SEMANA PASADA";
} elseif ($rango === 'mes') {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-t');
    $titulo_periodo = "ESTE MES";
} elseif ($rango === 'mes_pasado') {
    $fecha_inicio = date('Y-m-01', strtotime('last month'));
    $fecha_fin = date('Y-m-t', strtotime('last month'));
    $titulo_periodo = "MES PASADO";
} elseif ($rango === 'ano') {
    $fecha_inicio = date('Y-01-01');
    $fecha_fin = date('Y-12-31');
    $titulo_periodo = "ESTE ANIO";
} elseif ($rango === 'custom') {
    $fecha_inicio = $_GET['inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fin'] ?? date('Y-m-t');
    $titulo_periodo = "RANGO PERSONALIZADO";
} else {
    $fecha_inicio = '2020-01-01';
    $fecha_fin = '2099-12-31';
    $titulo_periodo = "HISTORICO GENERAL";
}

$param_fechas = [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'];

// --- CONSULTAS A LA BASE DE DATOS ---

// Totales de Facturas (Ingresos)
$sql_ventas = "SELECT ISNULL(SUM(CASE WHEN ISNULL(Total_NIO, 0) > 0 THEN Total_NIO ELSE Total * $tasa_sql END), 0) AS Ingresos FROM FACTURA WHERE Estado != 'Anulado' AND Fecha BETWEEN ? AND ?";
$res_ventas = sqlsrv_query($conn, $sql_ventas, $param_fechas);
$total_ventas = ($res_ventas) ? sqlsrv_fetch_array($res_ventas, SQLSRV_FETCH_ASSOC)['Ingresos'] : 0;

// Totales de Compras (Egresos)
$sql_compras = "SELECT ISNULL(SUM(Total_NIO), 0) AS Inversion FROM COMPRA WHERE Estado != 'Anulado' AND Fecha BETWEEN ? AND ?";
$res_compras = sqlsrv_query($conn, $sql_compras, $param_fechas);
$total_compras = ($res_compras) ? sqlsrv_fetch_array($res_compras, SQLSRV_FETCH_ASSOC)['Inversion'] : 0;

// Flujo
$flujo_neto = $total_ventas - $total_compras;


// --- ESTRUCTURA DEL PDF ---
class PDF extends FPDF {
    public $generador;
    function Header() {
        if(file_exists('Ener.png')){ $this->Image('Ener.png', 10, 8, 33); }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(80);
        $this->Cell(110, 8, utf8_decode('ENERGÍA Y SUMINISTROS S.A.'), 0, 1, 'R');
        $this->SetFont('Arial', '', 9);
        $this->Cell(190, 4, utf8_decode('RUC: J0310000000001 | Reportes Financieros'), 0, 1, 'R');
        $this->Cell(190, 4, utf8_decode('Managua, Nicaragua'), 0, 1, 'R');
        $this->Ln(12);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(95, 10, utf8_decode('Página ').$this->PageNo().'/{nb}', 0, 0, 'L');
        $this->Cell(95, 10, utf8_decode('Emitido por: ' . $this->generador), 0, 0, 'R');
    }
}

$pdf = new PDF();
$pdf->generador = $nombre_generador;
$pdf->AliasNbPages();
$pdf->AddPage();

// Banner Principal
$pdf->SetFillColor(30, 41, 59);
$pdf->SetTextColor(255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 10, utf8_decode("REPORTE FINANCIERO DE OPERACIONES"), 1, 1, 'C', true);
$pdf->Ln(5);

// Información del Período
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(45, 7, utf8_decode("PERIODO EVALUADO:"), 0, 0);
$pdf->SetFont('Arial', '', 10);
$fecha_mostrar = ($rango == 'todo') ? "Todo el registro" : date('d/m/Y', strtotime($fecha_inicio)) . " al " . date('d/m/Y', strtotime($fecha_fin));
$pdf->Cell(145, 7, utf8_decode($titulo_periodo . " (" . $fecha_mostrar . ")"), 0, 1);
$pdf->Ln(5);

// ==========================================
// SECCIÓN 1: RESUMEN FINANCIERO (TOTALES)
// ==========================================
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(140, 8, utf8_decode("INDICADOR GLOBAL (MONEDA NACIONAL)"), 1, 0, 'L', true);
$pdf->Cell(50, 8, utf8_decode("MONTO TOTAL C\$"), 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(140, 8, utf8_decode("(+) Ingresos por Facturación de Servicios y Materiales"), 1, 0, 'L');
$pdf->Cell(50, 8, "C$ " . number_format($total_ventas, 2), 1, 1, 'R');

$pdf->Cell(140, 8, utf8_decode("(-) Egresos por Compras de Materiales a Proveedores"), 1, 0, 'L');
$pdf->Cell(50, 8, "C$ " . number_format($total_compras, 2), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(140, 8, utf8_decode("(=) FLUJO NETO DEL PERIODO"), 1, 0, 'L');
$pdf->SetTextColor($flujo_neto >= 0 ? 22 : 220, $flujo_neto >= 0 ? 163 : 38, $flujo_neto >= 0 ? 74 : 38); 
$pdf->Cell(50, 8, "C$ " . number_format($flujo_neto, 2), 1, 1, 'R');
$pdf->SetTextColor(0);
$pdf->Ln(12);

// ==========================================
// SECCIÓN 2: TOP 10 MATERIALES VENDIDOS
// ==========================================
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(16, 185, 129);
$pdf->Cell(190, 6, utf8_decode("EXACTAMENTE EN QUÉ: TOP MATERIALES MÁS VENDIDOS"), 0, 1, 'L');
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(110, 7, utf8_decode("DESCRIPCIÓN DEL MATERIAL"), 1, 0, 'L', true);
$pdf->Cell(30, 7, utf8_decode("CANT."), 1, 0, 'C', true);
$pdf->Cell(50, 7, utf8_decode("TOTAL GENERADO"), 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 9);
$sql_top_ventas = "SELECT TOP 8 p.Nombre, SUM(fp.Cantidad) as CantidadVendida, 
                   SUM(fp.Cantidad * ISNULL(fp.Precio_Unitario_NIO, p.Precio_Unitario * $tasa_sql)) as Generado
                   FROM FACTURA_PRODUCTO fp
                   INNER JOIN FACTURA f ON fp.Id_Factura = f.Id_Factura
                   INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto
                   WHERE f.Estado != 'Anulado' AND f.Fecha BETWEEN ? AND ?" . " GROUP BY p.Id_Producto, p.Nombre ORDER BY Generado DESC";
$res_top_ventas = sqlsrv_query($conn, $sql_top_ventas, $param_fechas);

if($res_top_ventas && sqlsrv_has_rows($res_top_ventas)) {
    while($v = sqlsrv_fetch_array($res_top_ventas, SQLSRV_FETCH_ASSOC)) {
        $pdf->Cell(110, 7, utf8_decode($v['Nombre']), 1, 0, 'L');
        $pdf->Cell(30, 7, $v['CantidadVendida'], 1, 0, 'C');
        $pdf->Cell(50, 7, "C$ " . number_format($v['Generado'], 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(190, 7, utf8_decode("No hay movimientos de venta registrados."), 1, 1, 'C');
}
$pdf->Ln(10);

// ==========================================
// SECCIÓN 3: TOP 10 MATERIALES COMPRADOS
// ==========================================
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(245, 158, 11);
$pdf->Cell(190, 6, utf8_decode("EXACTAMENTE EN QUÉ: TOP INVERSIONES EN COMPRA"), 0, 1, 'L');
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(110, 7, utf8_decode("DESCRIPCIÓN DEL MATERIAL"), 1, 0, 'L', true);
$pdf->Cell(30, 7, utf8_decode("CANT."), 1, 0, 'C', true);
$pdf->Cell(50, 7, utf8_decode("TOTAL INVERTIDO"), 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 9);
$sql_top_compras = "SELECT TOP 8 p.Nombre, SUM(cp.Cantidad) as CantidadComprada, 
                    SUM(cp.Cantidad * ISNULL(cp.Precio_Unitario_NIO, p.Precio_Unitario * $tasa_sql)) as Invertido
                    FROM COMPRA_PRODUCTO cp
                    INNER JOIN COMPRA c ON cp.Id_Compra = c.Id_Compra
                    INNER JOIN PRODUCTO p ON cp.Id_Producto = p.Id_Producto
                    WHERE c.Estado != 'Anulado' AND c.Fecha BETWEEN ? AND ?" . " GROUP BY p.Id_Producto, p.Nombre ORDER BY Invertido DESC";
$res_top_compras = sqlsrv_query($conn, $sql_top_compras, $param_fechas);

if($res_top_compras && sqlsrv_has_rows($res_top_compras)) {
    while($c = sqlsrv_fetch_array($res_top_compras, SQLSRV_FETCH_ASSOC)) {
        $pdf->Cell(110, 7, utf8_decode($c['Nombre']), 1, 0, 'L');
        $pdf->Cell(30, 7, $c['CantidadComprada'], 1, 0, 'C');
        $pdf->Cell(50, 7, "C$ " . number_format($c['Invertido'], 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(190, 7, utf8_decode("No hay movimientos de compra registrados."), 1, 1, 'C');
}

$pdf->Output('I', 'Reporte_Financiero_'.$rango.'.pdf');
?>