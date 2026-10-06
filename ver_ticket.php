<?php
session_start();
include 'conexion.php';

$id_compra = $_GET['id'] ?? '';

if (empty($id_compra)) {
    die("<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'>⚠️ Error: No se especificó el número de ticket. <br><a href='ver_compras.php' style='color: var(--danger); text-decoration: underline; margin-top: 10px; display: inline-block;'>Volver al historial</a></div></div>");
}

$sql_info = "SELECT Estado, Motivo_Anulacion, Tasa_Cambio FROM COMPRA WHERE Id_Compra = ?";
$consulta_info = sqlsrv_query($conn, $sql_info, array($id_compra));
$info_compra = sqlsrv_fetch_array($consulta_info, SQLSRV_FETCH_ASSOC);

$es_anulada = ($info_compra && $info_compra['Estado'] == 'Anulado');
$motivo_anulacion = $info_compra['Motivo_Anulacion'] ?? 'No se especificó un motivo.';

// --- Rescate inteligente para tickets antiguos ---
$tasa_db = $info_compra['Tasa_Cambio'] ?? 0;
$tasa_aplicada = ($tasa_db > 0) ? $tasa_db : ($_SESSION['tasa_hoy'] ?? 36.6243);

$sql = "SELECT p.Nombre, p.Precio_Unitario, cp.Cantidad, cp.Precio_Unitario_NIO 
        FROM COMPRA_PRODUCTO cp
        INNER JOIN PRODUCTO p ON cp.Id_Producto = p.Id_Producto
        WHERE cp.Id_Compra = ?";

$params = array($id_compra);
$consulta = sqlsrv_query($conn, $sql, $params);

if ($consulta === false) {
    die("<div class='container mt-4'><div class='alert alert-custom-error shadow-sm'><strong>⚠️ Error:</strong><br><pre class='mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre></div></div>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles del Ticket #<?php echo htmlspecialchars($id_compra); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">

    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); padding: 2rem 1rem; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(59, 130, 246, 0.05); overflow: hidden; max-width: 900px; margin: 0 auto; }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header-title-wrapper { display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(59, 130, 246, 0.3); }
        .card-title-dark { color: var(--text-main); font-weight: 600; letter-spacing: 0.5px; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        
        .btn-outline-custom { border: 1px solid var(--border-color); color: var(--text-muted); transition: all 0.3s; background: transparent; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); background: rgba(59, 130, 246, 0.1); }

        .btn-primary-custom { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; color: white; padding: 8px 16px; border-radius: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2); text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); color: white; }

        .table-dark-custom { width: 100%; margin-bottom: 0; color: var(--text-main); }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: var(--text-muted); border-bottom: 2px solid var(--primary); border-top: none; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; padding: 15px; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); }
        .table-dark-custom tbody tr:hover { background-color: rgba(59, 130, 246, 0.05); }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent; border: none; color: var(--text-main); }
        
        .tabla-anulada { opacity: 0.6; }
        .product-name { color: var(--text-main) !important; font-weight: 600; font-size: 1.05rem; }
        .product-name i { color: var(--primary); opacity: 0.8; }
        .qty-badge { background-color: rgba(59, 130, 246, 0.1); color: var(--primary); border: 1px solid rgba(59, 130, 246, 0.3); padding: 4px 12px; border-radius: 6px; font-weight: bold; }
        .price-text { font-size: 1rem; color: var(--text-main); font-family: monospace;}
        .total-text { font-size: 1.05rem; color: #f59e0b; font-weight: bold; font-family: monospace;}

        /* DESGLOSE TOTALES MULTIMONEDA - AHORA SOLO C$ */
        .totales-wrapper { background-color: rgba(0, 0, 0, 0.15); padding: 20px; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
        .total-row { display: flex; width: 100%; min-width: 420px; justify-content: space-between; font-size: 0.95rem; align-items: center; gap: 15px; }
        .total-row .label { color: var(--text-muted); font-weight: 600; text-transform: uppercase; white-space: nowrap; }
        .total-row .value { color: var(--text-main); font-weight: 500; font-family: monospace; font-size: 1.05rem; text-align: right;}
        
        .total-row.grand-total-nio { border-top: 1px dashed var(--border-color); padding-top: 10px; margin-top: 5px; }
        .total-row.grand-total-nio .label { color: #10b981; font-weight: bold; font-size: 1.1rem; white-space: nowrap; }
        .total-row.grand-total-nio .value { color: #10b981; font-weight: bold; font-size: 1.4rem; font-family: monospace; text-align: right; }

        .banner-anulado { background-color: rgba(239, 68, 68, 0.1); border-left: 5px solid var(--danger); color: #f87171; padding: 20px; margin: 20px; border-radius: 8px; display: flex; align-items: center; }
        .banner-anulado i { font-size: 2rem; margin-right: 20px; color: var(--danger); }
        .banner-anulado .banner-title { font-weight: 800; letter-spacing: 1px; margin-bottom: 5px; text-transform: uppercase; }
        .banner-anulado .banner-text { color: #cbd5e1; font-size: 0.95rem; margin: 0; }

        .alert-custom-error { background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.4); color: var(--danger); padding: 15px; border-radius: 10px; }
        .alert-custom-warning { background-color: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.4); color: var(--warning); padding: 15px; border-radius: 10px; margin: 20px; }
        .alert-custom-warning a { color: var(--warning); font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        
        <div class="dark-card">
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box" style="<?php echo $es_anulada ? 'background: linear-gradient(135deg, var(--danger), #991b1b); box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);' : ''; ?>">
                        <i class="fa-solid <?php echo $es_anulada ? 'fa-ban' : 'fa-receipt'; ?>"></i>
                    </div>
                    <h4 class="card-title-dark">Materiales del Ticket #<?php echo htmlspecialchars($id_compra); ?></h4>
                </div>
                <div>
                    <a href="ver_compras.php" class="btn-outline-custom">
                        <i class="fa-solid fa-arrow-left me-1"></i>Volver
                    </a>
                    <a href="generar_pdf_compra.php?id=<?php echo $id_compra; ?>" target="_blank" class="btn-primary-custom ms-2">
                        <i class="fa-solid fa-file-pdf me-2"></i>Descargar PDF
                    </a>
                </div>
            </div>

            <?php if($es_anulada): ?>
            <div class="banner-anulado shadow-sm">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <div class="banner-title">Operación Anulada</div>
                    <p class="banner-text"><strong>Motivo registrado:</strong> <?php echo htmlspecialchars($motivo_anulacion); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="table-responsive <?php echo $es_anulada ? 'tabla-anulada' : ''; ?>">
                <table class="table table-dark-custom align-middle">
                    <thead>
                        <tr>
                            <th width="45%">Material / Producto</th>
                            <th width="15%" class="text-center">Cantidad</th>
                            <th width="20%" class="text-end">Precio Unitario (C$)</th>
                            <th width="20%" class="text-end">Subtotal (C$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hay_productos = false;
                        $subtotal_general_nio = 0;

                        while($fila = sqlsrv_fetch_array($consulta, SQLSRV_FETCH_ASSOC)): 
                            $hay_productos = true;
                            
                            $precio_usd = $fila['Precio_Unitario'];
                            // Usar el precio NIO si existe, si no calcularlo
                            $precio_nio = ($fila['Precio_Unitario_NIO'] > 0) ? $fila['Precio_Unitario_NIO'] : ($precio_usd * $tasa_aplicada);
                            
                            $subtotal_linea_nio = $fila['Cantidad'] * $precio_nio;
                            $subtotal_general_nio += $subtotal_linea_nio;
                        ?>
                        <tr>
                            <td class="product-name">
                                <i class="fa-solid fa-box me-2"></i><?php echo htmlspecialchars($fila['Nombre']); ?>
                            </td>
                            <td class="text-center">
                                <span class="qty-badge"><?php echo $fila['Cantidad']; ?></span>
                            </td>
                            <td class="text-end price-text">
                                C$ <?php echo number_format($precio_nio, 2); ?>
                            </td>
                            <td class="text-end total-text">
                                C$ <?php echo number_format($subtotal_linea_nio, 2); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if($hay_productos): 
                $iva_nio = $subtotal_general_nio * 0.15;
                $total_final_nio = $subtotal_general_nio + $iva_nio;
            ?>
            <div class="totales-wrapper <?php echo $es_anulada ? 'tabla-anulada' : ''; ?>">
                <div class="total-row">
                    <span class="label">Subtotal:</span>
                    <span class="value">C$ <?php echo number_format($subtotal_general_nio, 2); ?></span>
                </div>
                <div class="total-row">
                    <span class="label">IVA (15%):</span>
                    <span class="value">C$ <?php echo number_format($iva_nio, 2); ?></span>
                </div>
                
                <div class="total-row grand-total-nio">
                    <span class="label">Total Facturado:</span>
                    <span class="value">C$ <?php echo number_format($total_final_nio, 2); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if(!$hay_productos): ?>
                <div class="alert-custom-warning text-center">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Este ticket aún no tiene materiales registrados. <br>
                    <?php if(!$es_anulada): ?>
                    <a href="detalle_compra.php">Añadir materiales aquí</a>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        </div>
    </div>

</body>
</html>