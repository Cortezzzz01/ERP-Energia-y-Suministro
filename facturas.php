<?php
session_start();
include 'conexion.php';
$alert = "";

// 1. OBTENER ENCARGADOS, CLIENTES Y SERVICIOS PARA LOS MENÚS
$sql_encargados = "SELECT Id_Encargado, Nombre FROM ENCARGADO ORDER BY Nombre ASC";
$consulta_enc = sqlsrv_query($conn, $sql_encargados);

$sql_clientes = "SELECT Id_Cliente, Nombre FROM CLIENTE ORDER BY Nombre ASC";
$consulta_cli = sqlsrv_query($conn, $sql_clientes);

// --- Traer las órdenes de servicio para el trigger
$sql_servicios = "SELECT Id_Servicio, Nombre_Servicio FROM SERVICIO ORDER BY Id_Servicio DESC";
$consulta_serv = sqlsrv_query($conn, $sql_servicios);

// 2. TRAER PRODUCTOS CON SU STOCK REAL CALCULADO
$sql_productos = "SELECT p.Id_Producto, p.Nombre, p.Precio_Unitario,
    (ISNULL((SELECT SUM(cp.Cantidad) FROM COMPRA_PRODUCTO cp INNER JOIN COMPRA c ON cp.Id_Compra = c.Id_Compra WHERE cp.Id_Producto = p.Id_Producto AND c.Estado != 'Anulado'), 0) -
     ISNULL((SELECT SUM(fp.Cantidad) FROM FACTURA_PRODUCTO fp INNER JOIN FACTURA f ON fp.Id_Factura = f.Id_Factura WHERE fp.Id_Producto = p.Id_Producto AND f.Estado != 'Anulado'), 0)) AS Stock_Actual
    FROM PRODUCTO p 
    ORDER BY p.Nombre ASC";
$res_productos = sqlsrv_query($conn, $sql_productos);

$productos_array = [];
while ($prod = sqlsrv_fetch_array($res_productos, SQLSRV_FETCH_ASSOC)) {
    $productos_array[] = $prod;
}

// 3. MOTOR DE GUARDADO FUSIONADO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $datos        = $_POST['datos'] ?? '';
    $id_servicio  = $_POST['id_servicio'] ?? ''; 
    $id_encargado = $_POST['id_encargado'] ?? '';
    $id_cliente   = $_POST['id_cliente'] ?? '';
    $tipo_pago    = $_POST['tipo_pago'] ?? 'Contado'; 
    $productos    = $_POST['productos'] ?? [];
    $cantidades   = $_POST['cantidades'] ?? [];
    $mano_obra    = $_POST['mano_obra'] ?? 0;  

    $tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243;

    if (empty($datos) || empty($id_encargado) || empty($id_cliente) || empty($productos) || empty($id_servicio)) {
        $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>⚠️ Por favor, completa todos los campos y añade al menos un material.</div>";
    } else {
        $estado_pago = ($tipo_pago === 'Crédito') ? 'Pendiente' : 'Pagado';
        $fecha_vencimiento = null;
        if ($tipo_pago === 'Crédito') {
            $fecha_vencimiento = date('Y-m-d', strtotime('+30 days'));
        }

        // --- Al guardar, convertimos silenciosamente la mano de obra a "Dólar Base" para mantener tu estructura DB intacta.
        $mano_obra_usd = $mano_obra / $tasa_hoy;

        $sql = "INSERT INTO FACTURA (Datos, Total, Id_Encargado, Id_Cliente, Tipo_Pago, Estado_Pago, Fecha_Vencimiento, Id_Servicio, Mano_Obra, Tasa_Cambio, Total_NIO) OUTPUT INSERTED.Id_Factura VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
        $params = array($datos, $id_encargado, $id_cliente, $tipo_pago, $estado_pago, $fecha_vencimiento, $id_servicio, $mano_obra_usd, $tasa_hoy);
        
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            sqlsrv_fetch($stmt);
            $id_generado = sqlsrv_get_field($stmt, 0);

            $sql_detalle = "INSERT INTO FACTURA_PRODUCTO (Id_Factura, Id_Producto, Cantidad) VALUES (?, ?, ?)";
            
            foreach ($productos as $key => $id_prod) {
                if (!empty($id_prod) && !empty($cantidades[$key])) {
                    sqlsrv_query($conn, $sql_detalle, array($id_generado, $id_prod, $cantidades[$key]));
                }
            }

            // --- ACTUALIZAR EL TOTAL REAL EN LA BASE DE DATOS ---
            $sql_update_total = "UPDATE FACTURA SET Total = (
                                    (SELECT ISNULL(SUM(fp.Cantidad * p.Precio_Unitario), 0) 
                                     FROM FACTURA_PRODUCTO fp 
                                     INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto 
                                     WHERE fp.Id_Factura = ?) + ?
                                 ) * 1.15 WHERE Id_Factura = ?";
            sqlsrv_query($conn, $sql_update_total, array($id_generado, $mano_obra_usd, $id_generado));
            
            $sql_update_nio = "UPDATE FACTURA SET Total_NIO = Total * ? WHERE Id_Factura = ?";
            sqlsrv_query($conn, $sql_update_nio, array($tasa_hoy, $id_generado));

            $sql_update_detalles = "UPDATE fp
                                    SET fp.Precio_Unitario_NIO = p.Precio_Unitario * ?
                                    FROM FACTURA_PRODUCTO fp
                                    INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto
                                    WHERE fp.Id_Factura = ?";
            sqlsrv_query($conn, $sql_update_detalles, array($tasa_hoy, $id_generado));

            $alert = "<div class='alert alert-custom-success shadow-sm mb-4' style='border-left: 4px solid var(--success);'>
                        <h5 class='mb-2 fw-bold'><i class='fa-solid fa-circle-check me-2'></i>¡Factura #$id_generado registrada y despachada con éxito!</h5>
                        <p class='mb-3 small' style='color: #cbd5e1;'>El inventario se ha actualizado y la factura está en estado: <strong>$estado_pago</strong>.</p>
                        <div class='d-flex gap-2'>
                            <a href='ver_ticket_factura.php?id=$id_generado' target='_blank' class='btn btn-success fw-bold text-white' style='box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); border: none;'><i class='fa-solid fa-print me-2'></i>Imprimir Factura</a>
                            <a href='ver_facturas.php' class='btn btn-outline-light'><i class='fa-solid fa-list me-2'></i>Ir al Historial</a>
                        </div>
                      </div>";
                      
            $usuario_actual = $_SESSION['nombre'] ?? 'Administrador'; 
            $accion_log = 'CREAR FACTURA';
            $detalle_log = "Se emitió la factura #$id_generado vinculada a la orden de servicio #$id_servicio.";
            sqlsrv_query($conn, "INSERT INTO BITACORA_AUDITORIA (Usuario, Accion, Detalle) VALUES (?, ?, ?)", array($usuario_actual, $accion_log, $detalle_log));

        } else {
            $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>
                        <strong>⚠️ Error al registrar:</strong><br>
                        <pre class='text-danger mt-2'>" . print_r(sqlsrv_errors(), true) . "</pre>
                      </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Factura Directa - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); padding: 2rem 1rem; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(16, 185, 129, 0.05); max-width: 1000px; margin: 0 auto; overflow: hidden; }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header-title-wrapper { display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--success), #059669); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(16, 185, 129, 0.3); }
        .card-title-dark { color: #f8fafc; font-weight: 600; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        
        .form-label-custom { color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .form-control-custom { width: 100%; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; padding: 10px 14px; color: #f8fafc; transition: all 0.3s; outline: none; }
        .form-control-custom:focus { border-color: var(--success); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
        .form-control-custom:disabled { background-color: rgba(0,0,0,0.2); opacity: 0.7; }
        
        .table-dark-custom { width: 100%; color: #e2e8f0; margin-bottom: 0; --bs-table-bg: transparent; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--success); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; padding: 15px; border-top: none; }
        .table-dark-custom tbody tr { transition: background-color 0.2s; background-color: transparent !important; }
        .table-dark-custom tbody tr:hover { background-color: rgba(16, 185, 129, 0.05) !important; }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent !important; border-bottom: 1px solid var(--border-color); color: #f8fafc !important; }
        
        .btn-primary-custom { background: linear-gradient(135deg, var(--success), #059669); border: none; color: white; padding: 12px 24px; border-radius: 10px; font-weight: 600; transition: all 0.3s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn-primary-custom:hover { transform: translateY(-2px); filter: brightness(1.1); color: white; }
        .btn-outline-custom { border: 1px solid var(--border-color); color: #94a3b8; padding: 8px 16px; border-radius: 8px; text-decoration: none; background: transparent; transition: all 0.2s; }
        .btn-outline-custom:hover { border-color: var(--success); color: var(--success); background: rgba(16,185,129,0.1); }
        
        .stock-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; }
        .stock-ok { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .stock-empty { background-color: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }

        .totales-box { background-color: rgba(0, 0, 0, 0.2); padding: 20px; border-radius: 10px; border: 1px solid var(--border-color); width: 100%; max-width: 350px; margin-left: auto; display: flex; flex-direction: column; gap: 8px; }
        .total-row { display: flex; justify-content: space-between; font-size: 0.95rem; }
        .total-row span:first-child { color: #94a3b8; font-weight: 600; text-transform: uppercase; }
        .total-row span:last-child { color: #f8fafc; font-weight: 500; font-family: monospace; font-size: 1.05rem; }
        .total-row.final { border-top: 1px solid var(--border-color); padding-top: 10px; margin-top: 4px; align-items: center; }
        .total-row.final span:first-child { color: #f8fafc; font-weight: bold; font-size: 1.1rem; }
        .total-row.final span:last-child { color: var(--success); font-weight: bold; font-size: 1.3rem; }
        
        .alert-custom-success { background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.4); color: #10b981; }
        .alert-custom-error { background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; }
        .alert-custom-warning { background-color: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.4); color: #fbbf24; }
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        <form action="" method="POST" onsubmit="desactivarBoton()">
            <div class="dark-card mt-3">
                
                <?php echo $alert; ?>
                
                <div class="card-header-dark">
                    <div class="header-title-wrapper">
                        <div class="icon-box"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                        <h4 class="card-title-dark">Facturación Directa de Servicio</h4>
                    </div>
                    <a href="ver_facturas.php" class="btn-outline-custom"><i class="fa-solid fa-arrow-left me-1"></i>Ver Historial</a>
                </div>

                <div class="p-4 row g-3">
                    
                    <div class="col-md-12 mb-2">
                        <label class="form-label-custom">Descripción General del Servicio</label>
                        <textarea name="datos" class="form-control-custom" rows="2" placeholder="Ej: Venta de materiales e instalación eléctrica..." required></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-custom">Orden Asignada</label>
                        <select name="id_servicio" class="form-control-custom" required>
                            <option value="">-- Seleccione Orden --</option>
                            <?php while($serv = sqlsrv_fetch_array($consulta_serv, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?php echo $serv['Id_Servicio']; ?>">#<?php echo $serv['Id_Servicio'] . " - " . htmlspecialchars($serv['Nombre_Servicio'] ?? ''); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label-custom">Cliente</label>
                        <select name="id_cliente" class="form-control-custom" required>
                            <option value="">-- Seleccione --</option>
                            <?php while($cli = sqlsrv_fetch_array($consulta_cli, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?php echo $cli['Id_Cliente']; ?>"><?php echo htmlspecialchars($cli['Nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label-custom">Vendedor</label>
                        <select name="id_encargado" class="form-control-custom" required>
                            <option value="">-- Seleccione --</option>
                            <?php while($enc = sqlsrv_fetch_array($consulta_enc, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?php echo $enc['Id_Encargado']; ?>"><?php echo htmlspecialchars($enc['Nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label-custom">Pago</label>
                        <select name="tipo_pago" class="form-control-custom" required>
                            <option value="Contado" selected>Contado</option>
                            <option value="Crédito">Crédito a 30 Días</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label-custom">Mano de Obra (C$)</label>
                        <input type="number" step="0.01" min="0" name="mano_obra" id="input_mano_obra" class="form-control-custom" placeholder="Ej: 500.00" value="0.00" oninput="calcularTotales()" required>
                    </div>

                    <div class="col-12 mt-4 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 text-uppercase" style="color: #cbd5e1; font-size: 1.1rem; font-weight: 600; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-boxes-stacked me-2" style="color: var(--success);"></i>Materiales Despachados
                        </h5>
                        <button type="button" class="btn btn-sm" style="border: 1px solid var(--success); color: var(--success); border-radius: 8px;" onclick="agregarFila()">
                            <i class="fa-solid fa-plus me-1"></i>Agregar Material
                        </button>
                    </div>

                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-dark-custom align-middle" id="tablaMateriales">
                                <thead>
                                    <tr>
                                        <th width="45%">Material / Producto</th>
                                        <th width="15%" class="text-center">Stock Disp.</th>
                                        <th width="12%" class="text-center">Cantidad</th>
                                        <th width="13%" class="text-end">Precio Unit. (C$)</th>
                                        <th width="13%" class="text-end">Subtotal (C$)</th>
                                        <th width="2%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <div class="totales-box">
                            <div class="total-row"><span>Materiales:</span><span id="txtMateriales">C$ 0.00</span></div>
                            <div class="total-row"><span>Mano de Obra:</span><span id="txtManoObraDisplay">C$ 0.00</span></div>
                            <div class="total-row"><span>Subtotal:</span><span id="txtSubtotal">C$ 0.00</span></div>
                            <div class="total-row"><span>IVA (15%):</span><span id="txtIva">C$ 0.00</span></div>
                            <div class="total-row final"><span>Total Factura:</span><span id="txtTotal">C$ 0.00</span></div>
                        </div>
                    </div>

                    <div class="col-12 text-end mt-4">
                        <button type="submit" id="btnGuardar" class="btn-primary-custom"><i class="fa-solid fa-check-double me-2"></i>Emitir Factura y Descontar Stock</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        const listaProductos = <?php echo json_encode($productos_array); ?>;
        const tasaHoy = <?php echo $_SESSION['tasa_hoy'] ?? 36.6243; ?>; 
        let contadorFilas = 0;

        function agregarFila() {
            contadorFilas++;
            const tbody = document.querySelector("#tablaMateriales tbody");
            
            let opciones = `<option value="">-- Seleccione Material --</option>`;
            listaProductos.forEach(p => {
                const alertaStock = p.Stock_Actual <= 0 ? ' [AGOTADO]' : '';
                const precioNIO = parseFloat(p.Precio_Unitario) * tasaHoy;
                opciones += `<option value="${p.Id_Producto}" data-precio="${precioNIO}" data-stock="${p.Stock_Actual}">${p.Nombre}${alertaStock}</option>`;
            });

            const filaHtml = `
                <tr id="fila_${contadorFilas}">
                    <td>
                        <select name="productos[]" class="form-control-custom" required onchange="actualizarInfoProducto(${contadorFilas})">
                            ${opciones}
                        </select>
                    </td>
                    <td class="text-center">
                        <span id="badge_stock_${contadorFilas}" class="stock-badge stock-empty">--</span>
                    </td>
                    <td>
                        <input type="number" name="cantidades[]" id="cant_${contadorFilas}" class="form-control-custom text-center" min="1" value="1" required oninput="validarStock(this)" disabled>
                    </td>
                    <td class="text-end font-monospace" style="color: #94a3b8;" id="precio_${contadorFilas}">C$ 0.00</td>
                    <td class="text-end font-monospace fw-bold" style="color: var(--success);" id="subtotal_${contadorFilas}">C$ 0.00</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm text-danger" title="Eliminar fila" style="background: transparent; border: none; font-size: 1.1rem;" onclick="eliminarFila(${contadorFilas})"><i class="fa-solid fa-trash-can"></i></button>
                    </td>
                </tr>
            `;
            tbody.insertAdjacentHTML('beforeend', filaHtml);
        }

        function actualizarInfoProducto(index) {
            const select = document.querySelector(`#fila_${index} select`);
            const option = select.options[select.selectedIndex];
            
            const badgeStock = document.getElementById(`badge_stock_${index}`);
            const inputCant = document.getElementById(`cant_${index}`);
            const tdPrecio = document.getElementById(`precio_${index}`);

            if(!select.value) {
                badgeStock.className = "stock-badge stock-empty";
                badgeStock.innerText = "--";
                inputCant.disabled = true;
                tdPrecio.innerText = "C$ 0.00";
                return;
            }

            const stock = parseInt(option.getAttribute("data-stock"));
            const precio = parseFloat(option.getAttribute("data-precio"));

            badgeStock.innerText = stock;
            
            if(stock <= 0) {
                badgeStock.className = "stock-badge stock-empty";
                inputCant.disabled = true;
                inputCant.value = 0;
            } else {
                badgeStock.className = "stock-badge stock-ok";
                inputCant.disabled = false;
                inputCant.max = stock; 
                inputCant.value = 1;
            }

            tdPrecio.innerText = "C$ " + precio.toFixed(2);
            calcularTotales();
        }
        
        function validarStock(input) {
            const maxStock = parseInt(input.max);
            const currentValue = parseInt(input.value);
            
            if (currentValue > maxStock) {
                alert("⚠️ No puedes despachar más cantidad de la que existe físicamente en bodega.");
                input.value = maxStock;
            }
            if (currentValue < 1 || isNaN(currentValue)) {
                input.value = 1;
            }
            
            calcularTotales();
        }

        function eliminarFila(index) {
            document.getElementById(`fila_${index}`).remove();
            calcularTotales();
        }

        function calcularTotales() {
            let totalMateriales = 0;
            const filas = document.querySelectorAll("#tablaMateriales tbody tr");

            filas.forEach(tr => {
                const idFila = tr.id.split("_")[1];
                const select = tr.querySelector("select");
                const option = select.options[select.selectedIndex];
                const inputCant = document.getElementById(`cant_${idFila}`);
                const tdSubtotal = document.getElementById(`subtotal_${idFila}`);

                if(select.value && inputCant.value) {
                    const precio = parseFloat(option.getAttribute("data-precio"));
                    const cantidad = parseInt(inputCant.value);
                    const subtotalLinea = precio * cantidad;
                    
                    totalMateriales += subtotalLinea;
                    tdSubtotal.innerText = "C$ " + subtotalLinea.toFixed(2);
                } else {
                    tdSubtotal.innerText = "C$ 0.00";
                }
            });

            const manoObra = parseFloat(document.getElementById("input_mano_obra").value) || 0;
            const subtotalGeneral = totalMateriales + manoObra;
            const iva = subtotalGeneral * 0.15;
            const total = subtotalGeneral + iva;

            document.getElementById("txtMateriales").innerText = "C$ " + totalMateriales.toFixed(2);
            document.getElementById("txtManoObraDisplay").innerText = "C$ " + manoObra.toFixed(2);
            document.getElementById("txtSubtotal").innerText = "C$ " + subtotalGeneral.toFixed(2);
            document.getElementById("txtIva").innerText = "C$ " + iva.toFixed(2);
            document.getElementById("txtTotal").innerText = "C$ " + total.toFixed(2);
        }

        function desactivarBoton() {
            const btn = document.getElementById('btnGuardar');
            setTimeout(() => {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Emitiendo Factura...';
            }, 50);
        }

        document.addEventListener("DOMContentLoaded", () => { agregarFila(); });
    </script>
</body>
</html>