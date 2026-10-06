<?php
session_start();
include 'conexion.php';

$id_proforma = $_GET['id'] ?? '';

if (empty($id_proforma)) {
    die("<div class='container mt-4'><div class='alert alert-danger shadow-sm'>⚠️ Error: No se especificó el número de proforma.</div></div>");
}

$sql_cabecera = "SELECT p.*, e.Nombre AS Nombre_Encargado 
                 FROM PROFORMA p 
                 INNER JOIN ENCARGADO e ON p.Id_Encargado = e.Id_Encargado 
                 WHERE p.Id_Proforma = ?";
$res_cabecera = sqlsrv_query($conn, $sql_cabecera, array($id_proforma));
$proforma = sqlsrv_fetch_array($res_cabecera, SQLSRV_FETCH_ASSOC);

if (!$proforma) { die("Error: La proforma no existe."); }

$es_anulada = ($proforma['Estado'] == 'Anulado');
$es_convertida = ($proforma['Estado'] == 'Convertida');

$sql_clientes = "SELECT Id_Cliente, Nombre FROM CLIENTE ORDER BY Nombre ASC";
$res_clientes = sqlsrv_query($conn, $sql_clientes);

$sql_items = "SELECT p.Nombre, p.Precio_Unitario, pp.Cantidad 
              FROM PROFORMA_PRODUCTO pp
              INNER JOIN PRODUCTO p ON pp.Id_Producto = p.Id_Producto
              WHERE pp.Id_Proforma = ?";
$consulta_items = sqlsrv_query($conn, $sql_items, array($id_proforma));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles de Proforma #<?php echo $id_proforma; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">

    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', system-ui, sans-serif; color: var(--text-main); padding: 2rem 1rem; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); overflow: hidden; max-width: 900px; margin: 0 auto; transition: all 0.3s; }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header-title-wrapper { display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; }
        .card-title-dark { color: #f8fafc; font-weight: 600; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        
        .btn-outline-custom { border: 1px solid var(--border-color); color: #94a3b8; padding: 8px 16px; border-radius: 8px; text-decoration: none; background: transparent; transition: all 0.2s; }
        .btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); background: rgba(59,130,246,0.1); }
        
        .btn-convert { background: linear-gradient(135deg, var(--success), #166534); border: none; color: white; padding: 8px 16px; border-radius: 8px; font-weight: 600; transition: all 0.3s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); cursor: pointer; }
        .btn-convert:hover { transform: translateY(-2px); filter: brightness(1.1); color: white; }

        .table-dark-custom { width: 100%; color: #e2e8f0; margin-bottom: 0; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--primary); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; padding: 15px; }
        .table-dark-custom tbody tr { border-bottom: 1px solid var(--border-color); transition: background-color 0.2s; }
        .table-dark-custom tbody tr:hover { background-color: rgba(16, 185, 129, 0.05); }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent !important; border: none; color: #f8fafc !important; }
        .qty-badge { background-color: rgba(59, 130, 246, 0.1); color: #38bdf8; border: 1px solid rgba(59, 130, 246, 0.3); padding: 4px 12px; border-radius: 6px; font-weight: bold; }
        
        /* DESGLOSE TOTALES */
        .totales-wrapper { background-color: rgba(0, 0, 0, 0.2); padding: 20px; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
        .total-row { display: flex; width: 100%; max-width: 320px; justify-content: space-between; font-size: 0.95rem; }
        .total-row .label-total { color: #94a3b8; font-weight: 600; text-transform: uppercase; }
        .total-row .value-total { color: #f8fafc; font-weight: 500; font-family: monospace; font-size: 1.05rem;}
        
        .total-row.grand-total-nio { border-top: 1px dashed var(--border-color); padding-top: 10px; margin-top: 5px; align-items: center; }
        .total-row.grand-total-nio .label-total { color: #10b981; font-weight: bold; font-size: 1.1rem; }
        .total-row.grand-total-nio .value-total { color: #10b981; font-weight: bold; font-size: 1.4rem; font-family: monospace; }
        
        /* PANEL INLINE */
        .panel-facturacion { display: none; background: linear-gradient(180deg, rgba(14, 165, 233, 0.02) 0%, rgba(14, 165, 233, 0.08) 100%); border-top: 1px solid var(--primary); padding: 25px; transition: all 0.3s ease; }
        .form-select-custom { background-color: #0f172a; border: 1px solid #1e293b; color: #f8fafc; padding: 12px; border-radius: 8px; width: 100%; outline: none; transition: all 0.3s; font-size: 0.95rem; }
        .form-select-custom:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
        
        .texto-brillante { color: #f8fafc !important; }
        .texto-secundario { color: #cbd5e1 !important; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        <div class="dark-card mt-3">
            <div class="card-header-dark">
                <div class="header-title-wrapper">
                    <div class="icon-box"><i class="fa-solid fa-file-invoice"></i></div>
                    <h4 class="card-title-dark">Detalle de Cotización #<?php echo $id_proforma; ?></h4>
                </div>
                <div class="d-flex gap-2">
                    <a href="ver_proformas.php" class="btn-outline-custom"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
                    
                    <?php if(!$es_anulada && !$es_convertida): ?>
                        <button type="button" class="btn-convert" id="btnDesplegarFactura" onclick="mostrarPanelFacturacion()">
                            <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Facturar Cotización
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark-custom align-middle">
                    <thead>
                        <tr>
                            <th width="45%">Material / Producto Cotizado</th>
                            <th width="15%" class="text-center">Cantidad</th>
                            <th width="20%" class="text-end">Precio Unitario (C$)</th>
                            <th width="20%" class="text-end">Subtotal (C$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal_general_nio = 0;
                        while($item = sqlsrv_fetch_array($consulta_items, SQLSRV_FETCH_ASSOC)): 
                            // Toma el precio directo asumiendo que ya es Córdoba
                            $precio_nio = $item['Precio_Unitario'];
                            $subtotal_linea_nio = $item['Cantidad'] * $precio_nio;
                            $subtotal_general_nio += $subtotal_linea_nio;
                        ?>
                        <tr>
                            <td class="fw-semibold">
                                <i class="fa-solid fa-box-open text-success opacity-75 me-2"></i><?php echo htmlspecialchars($item['Nombre']); ?>
                            </td>
                            <td class="text-center"><span class="qty-badge"><?php echo $item['Cantidad']; ?></span></td>
                            <td class="text-end font-monospace">C$ <?php echo number_format($precio_nio, 2); ?></td>
                            <td class="text-end font-monospace fw-bold">C$ <?php echo number_format($subtotal_linea_nio, 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php 
                $iva_nio = $subtotal_general_nio * 0.15;
                $total_final_nio = $subtotal_general_nio + $iva_nio;
            ?>
            <div class="totales-wrapper">
                <div class="total-row">
                    <span class="label-total">Subtotal:</span>
                    <span class="value-total">C$ <?php echo number_format($subtotal_general_nio, 2); ?></span>
                </div>
                <div class="total-row">
                    <span class="label-total">IVA (15%):</span>
                    <span class="value-total">C$ <?php echo number_format($iva_nio, 2); ?></span>
                </div>
                
                <div class="total-row grand-total-nio">
                    <span class="label-total">Total Estimado:</span>
                    <span class="value-total">C$ <?php echo number_format($total_final_nio, 2); ?></span>
                </div>
            </div>

            <div id="panelFacturacion" class="panel-facturacion">
                <form action="convertir_proforma.php" method="POST">
                    <input type="hidden" name="id_proforma" value="<?php echo $id_proforma; ?>">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 style="color: var(--primary); font-weight: 600; margin: 0;">
                            <i class="fa-solid fa-file-export me-2"></i>Paso Final: Emitir Factura Oficial
                        </h5>
                        <button type="button" class="btn texto-secundario" onclick="ocultarPanelFacturacion()"><i class="fa-solid fa-xmark fs-5"></i></button>
                    </div>

                    <p class="texto-secundario mb-4" style="font-size: 0.95rem;">La proforma fue registrada bajo el nombre de proyecto: <strong class="texto-brillante">"<?php echo htmlspecialchars($proforma['Datos']); ?>"</strong></p>
                    
                    <div class="row g-3 align-items-end">
                        <div class="col-md-7">
                            <label class="form-label texto-brillante text-uppercase fw-bold mb-2" style="font-size: 0.85rem; letter-spacing: 0.5px;">Seleccione el Cliente Real al que se le facturará</label>
                            <select name="id_cliente" class="form-select-custom" required>
                                <option value="">-- Seleccionar Cliente Registrado --</option>
                                <?php while($cli = sqlsrv_fetch_array($res_clientes, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?php echo $cli['Id_Cliente']; ?>"><?php echo htmlspecialchars($cli['Nombre']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <a href="clientes.php" target="_blank" class="btn btn-outline-custom w-100 d-flex justify-content-center align-items-center texto-secundario" style="height: 48px; border-color: #334155;" title="Registrar nuevo cliente">
                                <i class="fa-solid fa-user-plus me-2"></i>Nuevo
                            </a>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn-convert w-100 d-flex justify-content-center align-items-center" style="height: 48px; font-size: 1rem;">
                                <i class="fa-solid fa-check-double me-2"></i>Confirmar
                            </button>
                        </div>
                    </div>
                    
                    <div class="texto-secundario mt-4" style="font-size: 0.85rem; border-left: 3px solid var(--primary); padding-left: 12px; background: rgba(14, 165, 233, 0.05); padding-top: 8px; padding-bottom: 8px; border-radius: 0 6px 6px 0;">
                        <i class="fa-regular fa-lightbulb text-warning me-1"></i> Si el cliente es nuevo, dale clic al botón "Nuevo" para registrarlo en otra pestaña. Al terminar, regresa aquí y recarga la página.
                    </div>
                </form>
            </div>
            
        </div>
    </div>

    <script>
        function mostrarPanelFacturacion() {
            document.getElementById('panelFacturacion').style.display = 'block';
            document.getElementById('btnDesplegarFactura').style.display = 'none';
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }

        function ocultarPanelFacturacion() {
            document.getElementById('panelFacturacion').style.display = 'none';
            document.getElementById('btnDesplegarFactura').style.display = 'inline-block';
        }
    </script>
</body>
</html>