<?php
session_start();
include 'conexion.php';
$alert = "";

$factura_preseleccionada = $_GET['factura'] ?? '';

// 1. Traer facturas recientes
$sql_facturas = "SELECT TOP 50 f.Id_Factura, f.Datos, 
                 (SELECT ISNULL(SUM(fp.Cantidad * p.Precio_Unitario), 0) * 1.15 
                  FROM FACTURA_PRODUCTO fp 
                  INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto 
                  WHERE fp.Id_Factura = f.Id_Factura) AS Total, 
                 c.Nombre AS Cliente 
                 FROM FACTURA f
                 LEFT JOIN CLIENTE c ON f.Id_Cliente = c.Id_Cliente
                 ORDER BY f.Id_Factura DESC";
$consulta_facturas = sqlsrv_query($conn, $sql_facturas);

// 1.5 Traer materiales ya despachados
$sql_detalles = "SELECT fp.Id_Factura, p.Nombre, fp.Cantidad, p.Precio_Unitario 
                 FROM FACTURA_PRODUCTO fp
                 INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto
                 WHERE fp.Id_Factura IN (SELECT TOP 50 Id_Factura FROM FACTURA ORDER BY Id_Factura DESC)";
$res_detalles = sqlsrv_query($conn, $sql_detalles);
$mapa_detalles = [];
if ($res_detalles !== false) {
    while($row = sqlsrv_fetch_array($res_detalles, SQLSRV_FETCH_ASSOC)) {
        $id_f = $row['Id_Factura'];
        $mapa_detalles[$id_f][] = [
            'nombre' => $row['Nombre'],
            'cantidad' => $row['Cantidad'],
            'subtotal' => number_format($row['Cantidad'] * $row['Precio_Unitario'], 2, '.', '')
        ];
    }
}
$json_detalles = json_encode($mapa_detalles, JSON_UNESCAPED_UNICODE);

// 2. Traer los productos
$sql_productos = "SELECT p.Id_Producto, p.Nombre, p.Precio_Unitario,
    (ISNULL((SELECT SUM(cp.Cantidad) FROM COMPRA_PRODUCTO cp INNER JOIN COMPRA c ON cp.Id_Compra = c.Id_Compra WHERE cp.Id_Producto = p.Id_Producto AND c.Estado != 'Anulado'), 0) -
     ISNULL((SELECT SUM(fp.Cantidad) FROM FACTURA_PRODUCTO fp INNER JOIN FACTURA f ON fp.Id_Factura = f.Id_Factura WHERE fp.Id_Producto = p.Id_Producto AND f.Estado != 'Anulado'), 0)) AS Stock_Actual
    FROM PRODUCTO p 
    ORDER BY p.Nombre ASC";
$consulta_productos = sqlsrv_query($conn, $sql_productos);
$opciones_productos = "";

if ($consulta_productos !== false) {
    while($prod = sqlsrv_fetch_array($consulta_productos, SQLSRV_FETCH_ASSOC)) {
        $precio = number_format($prod['Precio_Unitario'], 2, '.', '');
        $stock = $prod['Stock_Actual'];
        $alertaStock = ($stock <= 0) ? ' [AGOTADO]' : '';
        $opciones_productos .= "<option value='".$prod['Id_Producto']."' data-precio='".$precio."' data-stock='".$stock."'>".addslashes($prod['Nombre']).$alertaStock."</option>";
    }
}

// 3. Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_factura    = $_POST['id_factura'] ?? '';
    $ids_productos = $_POST['id_producto'] ?? [];
    $cantidades    = $_POST['cantidad'] ?? [];

    $tasa_hoy = $_SESSION['tasa_hoy'] ?? 0;

    if (empty($id_factura) || empty($ids_productos)) {
        $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>⚠️ Selecciona la factura y al menos un material.</div>";
    } else {
        $errores = 0;
        $insertados = 0;

        for ($i = 0; $i < count($ids_productos); $i++) {
            $id_prod = $ids_productos[$i];
            $cant    = $cantidades[$i];

            if (!empty($id_prod) && !empty($cant)) {
                $check_sql = "SELECT Cantidad FROM FACTURA_PRODUCTO WHERE Id_Factura = ? AND Id_Producto = ?";
                $check_stmt = sqlsrv_query($conn, $check_sql, array($id_factura, $id_prod));
                
                if (sqlsrv_has_rows($check_stmt)) {
                    $sql = "UPDATE FACTURA_PRODUCTO SET Cantidad = Cantidad + ? WHERE Id_Factura = ? AND Id_Producto = ?";
                    $params = array($cant, $id_factura, $id_prod);
                } else {
                    $sql = "INSERT INTO FACTURA_PRODUCTO (Id_Factura, Id_Producto, Cantidad) VALUES (?, ?, ?)";
                    $params = array($id_factura, $id_prod, $cant);
                }
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) { $insertados++; } else { $errores++; }
            }
        }

        // --- MAGIA MULTIMONEDA: RECALCULAR TOTALES ---
        if ($errores == 0) {
            // 1. Recalcular el Total General en USD
            $sql_update_total = "UPDATE FACTURA SET 
                                    Total = (
                                        (SELECT ISNULL(SUM(fp.Cantidad * p.Precio_Unitario), 0) FROM FACTURA_PRODUCTO fp INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto WHERE fp.Id_Factura = ?) + ISNULL(Mano_Obra, 0)
                                    ) * 1.15
                                 WHERE Id_Factura = ?";
            sqlsrv_query($conn, $sql_update_total, array($id_factura, $id_factura));

            // 2. Actualizar la cabecera en NIO (Córdobas)
            $sql_update_nio = "UPDATE FACTURA SET Total_NIO = Total * ? WHERE Id_Factura = ?";
            sqlsrv_query($conn, $sql_update_nio, array($tasa_hoy, $id_factura));

            // 3. Actualizar Precio Unitario NIO de los materiales
            $sql_update_detalles = "UPDATE fp
                                    SET fp.Precio_Unitario_NIO = p.Precio_Unitario * ?
                                    FROM FACTURA_PRODUCTO fp
                                    INNER JOIN PRODUCTO p ON fp.Id_Producto = p.Id_Producto
                                    WHERE fp.Id_Factura = ?";
            sqlsrv_query($conn, $sql_update_detalles, array($tasa_hoy, $id_factura));

            $alert = "<div class='alert alert-custom-success shadow-sm mb-4'><i class='fa-solid fa-check-circle me-2'></i>¡$insertados materiales asignados! Montos actualizados.</div>";
            echo "<meta http-equiv='refresh' content='1.5'>"; 
        } else {
            $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>⚠️ Hubo errores al actualizar la base de datos.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir Materiales a Factura - Energía y Suministros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); padding: 2rem 0; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px #10b9811a; }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; align-items: center; }
        .icon-box { background: var(--success); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px #10b98166; }
        .card-title-dark { color: var(--text-main); font-weight: 600; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        .form-label { font-weight: 500; color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.4rem; }
        
        .input-group-text { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-muted); border-right: none; }
        .form-control, .form-select { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-main); border-left: none; }
        .form-select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e"); }
        .form-control:focus, .form-select:focus { background-color: var(--bg-dark); color: var(--text-main); border-color: var(--success); box-shadow: 0 0 10px #10b98133; }
        .input-group:focus-within .input-group-text, .input-group:focus-within .form-control, .input-group:focus-within .form-select { border-color: var(--success); }
        
        .btn-glow { background: var(--success); border: none; font-weight: bold; letter-spacing: 1px; padding: 12px; border-radius: 8px; box-shadow: 0 4px 15px #10b98166; color: white; transition: all 0.3s ease; }
        .btn-glow:hover { transform: translateY(-2px); box-shadow: 0 6px 20px #10b98199; color: white; filter: brightness(1.1); }
        
        .btn-outline-custom { border: 1px solid var(--border-color); color: var(--text-muted); transition: all 0.3s; background: transparent; }
        .btn-outline-custom:hover { border-color: var(--success); color: var(--success); background: #10b9811a; }
        .btn-outline-dashed { border: 1px dashed var(--success); color: var(--success); background: #10b9811a; border-radius: 8px; transition: all 0.3s; }
        
        .alert-custom-success { background-color: #10b9811a; border: 1px solid #10b98166; color: var(--success); }
        .alert-custom-error { background-color: #ef44441a; border: 1px solid #ef444466; color: var(--danger); }
        .alert-custom-warning { background-color: #f59e0b1a; border: 1px solid #f59e0b66; color: var(--warning); }
        
        .preview-box { background-color: var(--bg-dark); border: 1px dashed var(--border-color); border-radius: 8px; padding: 12px; margin-top: 10px; display: none; }
        .preview-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .preview-data { font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-bottom: 0; }
        
        .table-dark-custom { width: 100%; color: #e2e8f0; margin-bottom: 0; --bs-table-bg: transparent; }
        .table-dark-custom thead th { color: #94a3b8; border-bottom: 1px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 8px; border-top: none; }
        .table-dark-custom tbody td { padding: 8px; vertical-align: middle; border-bottom: 1px solid rgba(255,255,255,0.05); color: #f8fafc; font-size: 0.9rem; }

        .stock-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; display: inline-block; text-align: center; }
        .stock-ok { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .stock-empty { background-color: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .font-monospace { font-family: monospace; font-size: 1.05rem; }
        .border-end { border-color: var(--border-color) !important; }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-11 col-lg-10"> 
                <?php echo $alert; ?>
                <div class="card dark-card">
                    <div class="card-header-dark">
                        <div class="icon-box"><i class="fa-solid fa-dolly"></i></div>
                        <div>
                            <h4 class="card-title-dark">PASO 2: DESPACHAR MATERIALES</h4>
                            <p class="card-subtitle-dark mb-0" style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Selecciona los productos utilizados en el servicio</p>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <form action="" method="POST" id="formMaterialesFactura">
                            
                            <div class="mb-4">
                                <label class="form-label">1. Factura a Clientes</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                                    <select name="id_factura" id="select_factura" class="form-select" onchange="mostrarVistaPrevia()" required>
                                        <option value="" disabled selected>Buscar factura reciente...</option>
                                        <?php 
                                        if ($consulta_facturas !== false) {
                                            while($fac = sqlsrv_fetch_array($consulta_facturas, SQLSRV_FETCH_ASSOC)) {
                                                $nombre_cliente = $fac['Cliente'] ?? 'Sin Cliente Asignado';
                                                echo "<option value='".$fac['Id_Factura']."' 
                                                      data-cliente='".htmlspecialchars($nombre_cliente)."' 
                                                      data-datos='".htmlspecialchars($fac['Datos'])."' 
                                                      data-total='".number_format($fac['Total'] ?? 0, 2, '.', '')."' 
                                                      >Factura #".$fac['Id_Factura']." - ".$nombre_cliente."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div id="vista_previa" class="preview-box">
                                    <div class="row align-items-center">
                                        <div class="col-4 border-end text-center">
                                            <span class="preview-label d-block">Cliente</span>
                                            <p id="vp_cliente" class="preview-data" style="color: var(--primary);">---</p>
                                        </div>
                                        <div class="col-4 border-end text-center">
                                            <span class="preview-label d-block">Concepto</span>
                                            <p id="vp_datos" class="preview-data text-truncate" style="max-width: 150px; margin: 0 auto;">---</p>
                                        </div>
                                        <div class="col-4 text-center">
                                            <span class="preview-label d-block">Monto Estimado</span>
                                            <p id="vp_total" class="preview-data" style="color: var(--success); font-size: 1.2rem;">$0.00</p>
                                            <p id="vp_total_nio" class="preview-data mt-1" style="color: var(--text-muted); font-size: 0.9rem;">C$ 0.00</p>
                                        </div>
                                    </div>

                                    <div id="box_existentes" style="display: none; margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 15px;">
                                        <span class="d-block mb-2" style="color: #e2e8f0; font-weight: 500; font-size: 0.9rem;">
                                            <i class="fa-solid fa-box-archive me-2" style="color: var(--success);"></i>Materiales ya despachados:
                                        </span>
                                        <div class="table-responsive" style="background: rgba(0,0,0,0.2); border-radius: 8px;">
                                            <table class="table table-sm table-dark-custom m-0">
                                                <thead>
                                                    <tr>
                                                        <th>Material / Producto</th>
                                                        <th class="text-center">Cant.</th>
                                                        <th class="text-end">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tabla_existentes">
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <label class="form-label mt-4">2. Materiales Utilizados</label>
                            
                            <div class="table-responsive mb-3">
                                <table class="table table-dark-custom align-middle" style="border: 1px solid var(--border-color); border-radius: 8px;" id="tablaAñadirMateriales">
                                    <thead style="background-color: var(--bg-dark);">
                                        <tr>
                                            <th width="40%" style="color: #94a3b8; border-bottom: 2px solid var(--success); padding: 12px 15px;">Material / Producto</th>
                                            <th width="15%" class="text-center" style="color: #94a3b8; border-bottom: 2px solid var(--success); padding: 12px 15px;">Stock Disp.</th>
                                            <th width="12%" class="text-center" style="color: #94a3b8; border-bottom: 2px solid var(--success); padding: 12px 15px;">Cantidad</th>
                                            <th width="15%" class="text-end" style="color: #94a3b8; border-bottom: 2px solid var(--success); padding: 12px 15px;">Precio Unit.</th>
                                            <th width="13%" class="text-end" style="color: #94a3b8; border-bottom: 2px solid var(--success); padding: 12px 15px;">Subtotal</th>
                                            <th width="5%" class="text-center" style="color: #94a3b8; border-bottom: 2px solid var(--success); padding: 12px 15px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="contenedor-filas">
                                    </tbody>
                                </table>
                            </div>

                            <div class="mb-5">
                                <button type="button" class="btn btn-outline-dashed w-100 py-2" onclick="agregarFila()">
                                    <i class="fa-solid fa-plus me-2"></i>Añadir otro material
                                </button>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-glow btn-lg">
                                    <i class="fa-solid fa-check-double me-2"></i>DESCONTAR DEL INVENTARIO
                                </button>
                                <hr style="margin: 10px 0; border-color: var(--border-color);">
                                <div class="row g-3">
                                    <div class="col-6"><a href="facturas.php" class="btn btn-outline-custom w-100 py-2 text-center">Nueva Factura</a></div>
                                    <div class="col-6"><a href="index.php" target="_parent" class="btn btn-outline-custom w-100 py-2 text-center">Menú Principal</a></div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const opcionesSelect = `<?php echo $opciones_productos; ?>`;
        const detallesGuardados = <?php echo $json_detalles ? $json_detalles : '{}'; ?>;
        const tasaHoy = <?php echo $_SESSION['tasa_hoy'] ?? 0; ?>;
        let baseFacturaTotal = 0;

        function mostrarVistaPrevia() {
            const select = document.getElementById("select_factura");
            const opcionSeleccionada = select.options[select.selectedIndex];
            const cajaVistaPrevia = document.getElementById("vista_previa");
            const boxExistentes = document.getElementById("box_existentes");
            const tablaExistentes = document.getElementById("tabla_existentes");

            if (select.value !== "") {
                const idFactura = select.value;
                document.getElementById("vp_cliente").innerText = opcionSeleccionada.getAttribute("data-cliente");
                document.getElementById("vp_datos").innerText = opcionSeleccionada.getAttribute("data-datos");
                
                baseFacturaTotal = parseFloat(opcionSeleccionada.getAttribute("data-total")) || 0;
                cajaVistaPrevia.style.display = "block";
                
                if (detallesGuardados[idFactura] && detallesGuardados[idFactura].length > 0) {
                    let htmlFilas = "";
                    detallesGuardados[idFactura].forEach(item => {
                        htmlFilas += `<tr>
                            <td><i class="fa-solid fa-check me-2" style="color: var(--success); opacity: 0.7;"></i><span style="color: #e2e8f0;">${item.nombre}</span></td>
                            <td class="text-center fw-bold" style="color: #f8fafc;">${item.cantidad}</td>
                            <td class="text-end" style="color: #94a3b8; font-family: monospace;">$${item.subtotal}</td>
                        </tr>`;
                    });
                    tablaExistentes.innerHTML = htmlFilas;
                    boxExistentes.style.display = "block";
                } else {
                    boxExistentes.style.display = "none";
                }
                recalcularTotalFactura();
            } else {
                cajaVistaPrevia.style.display = "none";
                boxExistentes.style.display = "none";
                baseFacturaTotal = 0;
            }
        }

        window.onload = function() { mostrarVistaPrevia(); agregarFila(); };

        function agregarFila() {
            const contenedor = document.getElementById('contenedor-filas');
            const nuevaFila = document.createElement('tr'); 
            nuevaFila.className = 'fila-producto';
            nuevaFila.innerHTML = `
                <td>
                    <select name="id_producto[]" class="form-select select-producto" style="min-width: 200px;" onchange="actualizarInfoProducto(this)" required>
                        <option value="" data-precio="0" data-stock="0" selected disabled>-- Seleccione Material --</option>
                        ${opcionesSelect}
                    </select>
                </td>
                <td class="text-center"><span class="stock-badge stock-empty badge-stock-ui">--</span></td>
                <td><input type="number" name="cantidad[]" class="form-control input-cantidad text-center" min="1" value="1" onkeyup="validarStock(this)" onchange="validarStock(this)" required disabled></td>
                <td class="text-end"><span class="monto-precio font-monospace" style="color: #94a3b8;">$0.00</span></td>
                <td class="text-end"><span class="monto-subtotal font-monospace fw-bold" style="color: var(--success);">$0.00</span></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm text-danger p-0 mt-1" onclick="eliminarFila(this)" style="font-size: 1.2rem; background: transparent; border: none;"><i class="fa-solid fa-trash-can"></i></button>
                </td>
            `;
            contenedor.appendChild(nuevaFila);
        }

        function actualizarInfoProducto(selectElement) {
            const fila = selectElement.closest('.fila-producto');
            const badgeStock = fila.querySelector('.badge-stock-ui');
            const inputCantidad = fila.querySelector('.input-cantidad');
            const spanPrecio = fila.querySelector('.monto-precio');
            const option = selectElement.options[selectElement.selectedIndex];
            
            if(!selectElement.value) {
                badgeStock.className = "stock-badge stock-empty badge-stock-ui";
                badgeStock.innerText = "--";
                inputCantidad.disabled = true;
                spanPrecio.innerText = "$0.00";
                calcularSubtotal(inputCantidad);
                return;
            }

            const stock = parseInt(option.getAttribute("data-stock"));
            const precio = parseFloat(option.getAttribute("data-precio"));
            
            badgeStock.innerText = stock;
            spanPrecio.innerText = "$" + precio.toFixed(2);
            
            if(stock <= 0) {
                badgeStock.className = "stock-badge stock-empty badge-stock-ui";
                inputCantidad.disabled = true;
                inputCantidad.value = 0;
            } else {
                badgeStock.className = "stock-badge stock-ok badge-stock-ui";
                inputCantidad.disabled = false;
                inputCantidad.max = stock; 
                inputCantidad.value = 1;
            }
            calcularSubtotal(inputCantidad);
        }

        function validarStock(inputElement) {
            const maxStock = parseInt(inputElement.max);
            let currentValue = parseInt(inputElement.value);
            if (currentValue > maxStock) {
                alert("⚠️ ¡Cuidado! No puedes despachar más cantidad de la que existe físicamente en bodega.");
                inputElement.value = maxStock;
            }
            calcularSubtotal(inputElement);
        }

        function calcularSubtotal(elemento) {
            const fila = elemento.closest('.fila-producto');
            const selectProducto = fila.querySelector('.select-producto');
            const inputCantidad = fila.querySelector('.input-cantidad');
            const spanSubtotal = fila.querySelector('.monto-subtotal');
            
            let precio = 0;
            if(selectProducto.selectedIndex > 0) {
                precio = parseFloat(selectProducto.options[selectProducto.selectedIndex].getAttribute('data-precio')) || 0;
            }
            
            let cantidad = parseFloat(inputCantidad.value) || 0;
            spanSubtotal.innerText = "$" + (precio * cantidad).toFixed(2);
            recalcularTotalFactura();
        }

        function recalcularTotalFactura() {
            let subtotalNuevosMateriales = 0;
            const spans = document.querySelectorAll('.monto-subtotal');
            spans.forEach(span => {
                subtotalNuevosMateriales += parseFloat(span.innerText.replace('$', '')) || 0;
            });
            
            let ivaNuevosMateriales = subtotalNuevosMateriales * 0.15;
            let totalNuevosConIva = subtotalNuevosMateriales + ivaNuevosMateriales;
            let granTotal = baseFacturaTotal + totalNuevosConIva;
            let granTotalNIO = granTotal * tasaHoy;
            
            document.getElementById("vp_total").innerText = "$" + granTotal.toFixed(2);
            document.getElementById("vp_total_nio").innerText = "C$ " + granTotalNIO.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        
        function eliminarFila(btnElement) {
            btnElement.closest('.fila-producto').remove();
            recalcularTotalFactura();
        }
    </script>
</body>
</html>