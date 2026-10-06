<?php
session_start();
include 'conexion.php';

// 1. Obtener la lista de encargados para el combo
$sql_encargados = "SELECT Id_Encargado, Nombre FROM ENCARGADO ORDER BY Nombre ASC";
$res_encargados = sqlsrv_query($conn, $sql_encargados);

// 2. CONSULTA ESTRELLA: Traer productos con su stock real calculado (Entradas - Salidas)
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Cotización - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">

    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', system-ui, sans-serif; color: var(--text-main); padding: 2rem 1rem; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); max-width: 1000px; margin: 0 auto; overflow: hidden; }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header-title-wrapper { display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; }
        .card-title-dark { color: #f8fafc; font-weight: 600; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        
        .form-label-custom { color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .form-control-custom { width: 100%; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; padding: 10px 14px; color: #f8fafc; transition: all 0.3s; outline: none; }
        .form-control-custom:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
        
        .table-dark-custom { width: 100%; color: #e2e8f0; margin-bottom: 0; }
        .table-dark-custom thead th { background-color: var(--bg-dark); color: #94a3b8; border-bottom: 2px solid var(--primary); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; padding: 15px; }
        .table-dark-custom tbody tr { border-bottom: 1px solid var(--border-color); transition: background-color 0.2s; }
        .table-dark-custom tbody tr:hover { background-color: rgba(59, 130, 246, 0.05); }
        .table-dark-custom tbody td { padding: 15px; vertical-align: middle; background-color: transparent !important; border: none; color: #f8fafc !important; }
        
        .btn-primary-custom { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; color: white; padding: 12px 24px; border-radius: 10px; font-weight: 600; transition: all 0.3s; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); }
        .btn-primary-custom:hover { transform: translateY(-2px); filter: brightness(1.1); color: white; }
        .btn-outline-custom { border: 1px solid var(--border-color); color: #94a3b8; padding: 8px 16px; border-radius: 8px; text-decoration: none; background: transparent; transition: all 0.2s; }
        .btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); }
        
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
    </style>
</head>
<body>

    <div class="container-fluid px-lg-5">
        <form action="guardar_proforma.php" method="POST">
            <div class="dark-card">
                
                <div class="card-header-dark">
                    <div class="header-title-wrapper">
                        <div class="icon-box"><i class="fa-solid fa-file-signature"></i></div>
                        <h4 class="card-title-dark">Crear Nueva Proforma / Cotización</h4>
                    </div>
                    <a href="ver_proformas.php" class="btn-outline-custom"><i class="fa-solid fa-arrow-left me-1"></i>Volver al Historial</a>
                </div>

                <div class="p-4 row g-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">Encargado de la Cotización</label>
                        <select name="id_encargado" class="form-control-custom" required>
                            <option value="">-- Seleccione Encargado --</option>
                            <?php while($enc = sqlsrv_query_fetch_array_check($res_encargados)): ?>
                                <option value="<?php echo $enc['Id_Encargado']; ?>"><?php echo htmlspecialchars($enc['Nombre']); ?></option>
                            <?php endwhile; 
                            function sqlsrv_query_fetch_array_check($res) { return $res ? sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) : false; }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Cliente / Datos del Proyecto</label>
                        <input type="text" name="datos_cliente" class="form-control-custom" placeholder="Ej: Luminaria en el barrio Galiza / Nombre del Cliente" required autocomplete="off">
                    </div>

                    <div class="col-12 mt-4 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 text-uppercase" style="color: #cbd5e1; font-size: 1.1rem; font-weight: 600; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-list me-2" style="color: var(--primary);"></i>Desglose de Materiales
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius: 8px;" onclick="agregarFila()">
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
                            <div class="total-row"><span>Subtotal:</span><span id="txtSubtotal">C$ 0.00</span></div>
                            <div class="total-row"><span>IVA (15%):</span><span id="txtIva">C$ 0.00</span></div>
                            <div class="total-row final"><span>Total Est:</span><span id="txtTotal">C$ 0.00</span></div>
                        </div>
                    </div>

                    <div class="col-12 text-end mt-4">
                        <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar e Imprimir Proforma</button>
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
                const alertaStock = p.Stock_Actual <= 0 ? ' [AGOTADO]' : ` [Disp: ${p.Stock_Actual}]`;
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
                        <input type="number" name="cantidades[]" id="cant_${contadorFilas}" class="form-control-custom text-center" min="1" value="1" required oninput="calcularTotales()" disabled>
                    </td>
                    <td class="text-end font-monospace" style="color: #94a3b8;" id="precio_${contadorFilas}">C$ 0.00</td>
                    <td class="text-end font-monospace fw-bold" style="color: var(--success);" id="subtotal_${contadorFilas}">C$ 0.00</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm text-danger" title="Eliminar fila" onclick="eliminarFila(${contadorFilas})"><i class="fa-solid fa-trash-can"></i></button>
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
                alert("⚠️ ¡Atención! Este material no cuenta con existencias físicas en bodega actualmente.");
            } else {
                badgeStock.className = "stock-badge stock-ok";
            }

            inputCant.disabled = false;
            tdPrecio.innerText = "C$ " + precio.toFixed(2);
            
            calcularTotales();
        }

        function eliminarFila(index) {
            document.getElementById(`fila_${index}`).remove();
            calcularTotales();
        }

        function calcularTotales() {
            let subtotalGeneral = 0;
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
                    
                    subtotalGeneral += subtotalLinea;
                    tdSubtotal.innerText = "C$ " + subtotalLinea.toFixed(2);
                } else {
                    tdSubtotal.innerText = "C$ 0.00";
                }
            });

            const iva = subtotalGeneral * 0.15;
            const total = subtotalGeneral + iva;

            document.getElementById("txtSubtotal").innerText = "C$ " + subtotalGeneral.toFixed(2);
            document.getElementById("txtIva").innerText = "C$ " + iva.toFixed(2);
            document.getElementById("txtTotal").innerText = "C$ " + total.toFixed(2);
        }

        document.addEventListener("DOMContentLoaded", () => { agregarFila(); });
    </script>
</body>
</html>