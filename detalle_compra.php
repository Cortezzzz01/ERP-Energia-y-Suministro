<?php
session_start();
include 'conexion.php';
$alert = "";

$ticket_preseleccionado = $_GET['ticket'] ?? '';

// Obtenemos la tasa de la sesión para PHP
$tasa_hoy = $_SESSION['tasa_hoy'] ?? 36.6243;

// 1. Traer las compras recientes
$sql_compras = "SELECT TOP 50 c.Id_Compra, c.Fecha, 
                (SELECT ISNULL(SUM(cp.Cantidad * p.Precio_Unitario), 0) 
                 FROM COMPRA_PRODUCTO cp 
                 INNER JOIN PRODUCTO p ON cp.Id_Producto = p.Id_Producto 
                 WHERE cp.Id_Compra = c.Id_Compra) AS TotalProductosUSD, 
                p.Nombre AS Proveedor 
                FROM COMPRA c
                INNER JOIN PROVEEDOR p ON c.Id_Proveedor = p.Id_Proveedor
                ORDER BY c.Id_Compra DESC";
$consulta_compras = sqlsrv_query($conn, $sql_compras);

// 1.5 Traer materiales registrados (Calculando a NIO)
$sql_detalles = "SELECT cp.Id_Compra, p.Nombre, cp.Cantidad, 
                 ISNULL(cp.Precio_Unitario_NIO, (p.Precio_Unitario * ?)) as Precio_NIO
                 FROM COMPRA_PRODUCTO cp
                 INNER JOIN PRODUCTO p ON cp.Id_Producto = p.Id_Producto
                 WHERE cp.Id_Compra IN (SELECT TOP 50 Id_Compra FROM COMPRA ORDER BY Id_Compra DESC)";
$res_detalles = sqlsrv_query($conn, $sql_detalles, array($tasa_hoy));

$mapa_detalles = [];
if ($res_detalles !== false) {
    while($row = sqlsrv_fetch_array($res_detalles, SQLSRV_FETCH_ASSOC)) {
        $id_c = $row['Id_Compra'];
        $mapa_detalles[$id_c][] = [
            'nombre' => $row['Nombre'],
            'cantidad' => $row['Cantidad'],
            'subtotal' => number_format($row['Cantidad'] * $row['Precio_NIO'], 2, '.', '')
        ];
    }
}
$json_detalles = json_encode($mapa_detalles, JSON_UNESCAPED_UNICODE);

// 2. Traer productos
$sql_productos = "SELECT Id_Producto, Nombre, Precio_Unitario FROM PRODUCTO";
$consulta_productos = sqlsrv_query($conn, $sql_productos);
$opciones_productos = "";
if ($consulta_productos !== false) {
    while($prod = sqlsrv_fetch_array($consulta_productos, SQLSRV_FETCH_ASSOC)) {
        // En lugar de enviar el USD, enviamos ya calculado el NIO al frontend
        $precio_nio = $prod['Precio_Unitario'] * $tasa_hoy;
        $precio_formateado = number_format($precio_nio, 2, '.', '');
        $opciones_productos .= "<option value='".$prod['Id_Producto']."' data-precio='".$precio_formateado."'>".addslashes($prod['Nombre'])."</option>";
    }
}

// 3. Procesar el formulario multifila
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_compra     = $_POST['id_compra'] ?? '';
    $ids_productos = $_POST['id_producto'] ?? [];
    $cantidades    = $_POST['cantidad'] ?? [];
    
    if (empty($id_compra) || empty($ids_productos)) {
        $alert = "<div class='alert alert-custom-error shadow-sm mb-4'>⚠️ Selecciona el ticket y al menos un producto.</div>";
    } else {
        $errores = 0;
        $insertados = 0;

        for ($i = 0; $i < count($ids_productos); $i++) {
            $id_prod = $ids_productos[$i];
            $cant    = $cantidades[$i];

            if (!empty($id_prod) && !empty($cant)) {
                $check_sql = "SELECT Cantidad FROM COMPRA_PRODUCTO WHERE Id_Compra = ? AND Id_Producto = ?";
                $check_stmt = sqlsrv_query($conn, $check_sql, array($id_compra, $id_prod));
                
                if (sqlsrv_has_rows($check_stmt)) {
                    $sql = "UPDATE COMPRA_PRODUCTO SET Cantidad = Cantidad + ? WHERE Id_Compra = ? AND Id_Producto = ?";
                    $params = array($cant, $id_compra, $id_prod);
                } else {
                    $sql = "INSERT INTO COMPRA_PRODUCTO (Id_Compra, Id_Producto, Cantidad) VALUES (?, ?, ?)";
                    $params = array($id_compra, $id_prod, $cant);
                }

                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) { $insertados++; } else { $errores++; }
            }
        }

        if ($errores == 0) {
            // Actualizar Precio_Unitario_NIO de los detalles
            $sql_update_detalles = "UPDATE cp
                                    SET cp.Precio_Unitario_NIO = p.Precio_Unitario * ?
                                    FROM COMPRA_PRODUCTO cp
                                    INNER JOIN PRODUCTO p ON cp.Id_Producto = p.Id_Producto
                                    WHERE cp.Id_Compra = ?";
            sqlsrv_query($conn, $sql_update_detalles, array($tasa_hoy, $id_compra));

            // Actualizar Total_NIO en cabecera
            $sql_update_total = "UPDATE COMPRA SET Total_NIO = (
                                    (SELECT ISNULL(SUM(cp.Cantidad * p.Precio_Unitario), 0) 
                                     FROM COMPRA_PRODUCTO cp 
                                     INNER JOIN PRODUCTO p ON cp.Id_Producto = p.Id_Producto 
                                     WHERE cp.Id_Compra = ?)
                                 ) * ? WHERE Id_Compra = ?";
            sqlsrv_query($conn, $sql_update_total, array($id_compra, $tasa_hoy, $id_compra));

            $alert = "<div class='alert alert-custom-success shadow-sm mb-4'><i class='fa-solid fa-check-circle me-2'></i>¡$insertados productos añadidos! Totales recalculados con éxito.</div>";
            echo "<meta http-equiv='refresh' content='1.5'>"; 
        } else {
            $alert = "<div class='alert alert-custom-warning shadow-sm mb-4'>⚠️ Hubo $errores errores al guardar.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir Detalles - Energía y Suministros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); padding: 2rem 0; }
        .dark-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(245, 158, 11, 0.05); }
        .card-header-dark { background: transparent; border-bottom: 1px solid var(--border-color); padding: 1.5rem; display: flex; align-items: center; }
        .icon-box { background: linear-gradient(135deg, var(--warning), #d97706); color: white; padding: 12px 15px; border-radius: 10px; font-size: 1.5rem; margin-right: 15px; box-shadow: 0 0 15px rgba(245, 158, 11, 0.3); }
        .card-title-dark { color: var(--text-main); font-weight: 600; margin: 0; font-size: 1.25rem; text-transform: uppercase; }
        .form-label { font-weight: 500; color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.4rem; }
        
        .input-group-text { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-muted); border-right: none; }
        .form-control, .form-select { background-color: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-main); border-left: none; }
        .form-select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e"); }
        .form-control:focus, .form-select:focus { background-color: var(--bg-dark); color: var(--text-main); border-color: var(--warning); box-shadow: none; }
        .input-group:focus-within .input-group-text, .input-group:focus-within .form-control, .input-group:focus-within .form-select { border-color: var(--warning); }
        
        .btn-glow { background: linear-gradient(135deg, var(--warning), #d97706); border: none; font-weight: bold; letter-spacing: 1px; padding: 12px; border-radius: 8px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3); color: white; transition: all 0.3s ease; }
        .btn-glow:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5); color: white; }
        
        .btn-outline-custom { border: 1px solid var(--border-color); color: var(--text-muted); transition: all 0.3s; background: transparent; }
        .btn-outline-custom:hover { border-color: var(--warning); color: var(--warning); background: rgba(245, 158, 11, 0.05); }
        .btn-outline-dashed { border: 1px dashed var(--warning); color: var(--warning); background: rgba(245, 158, 11, 0.05); border-radius: 8px; transition: all 0.3s; }
        .btn-outline-dashed:hover { background: rgba(245, 158, 11, 0.15); color: var(--warning); }

        .alert-custom-success { background-color: #10b9811a; border: 1px solid #10b98166; color: var(--success); }
        .alert-custom-error { background-color: #ef44441a; border: 1px solid #ef444466; color: var(--danger); }
        .alert-custom-warning { background-color: #f59e0b1a; border: 1px solid #f59e0b66; color: var(--warning); }
        
        .preview-box { background-color: var(--bg-dark); border: 1px dashed var(--border-color); border-radius: 8px; padding: 12px; margin-top: 10px; display: none; }
        .preview-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .preview-data { font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-bottom: 0; }
        
        .table-dark-custom { width: 100%; color: #e2e8f0; margin-bottom: 0; --bs-table-bg: transparent; }
        .table-dark-custom thead th { color: #94a3b8; border-bottom: 1px solid var(--border-color); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; padding: 8px; border-top: none; }
        .table-dark-custom tbody td { padding: 8px; vertical-align: middle; border-bottom: 1px solid rgba(255,255,255,0.05); color: #f8fafc; font-size: 0.9rem; }
        
        .subtotal-box { display: flex; align-items: center; justify-content: flex-end; background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 0 15px; height: 100%; color: var(--warning); font-weight: bold; font-size: 1.1rem; }
        .border-end { border-color: var(--border-color) !important; }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-9"> <?php echo $alert; ?>

                <div class="card dark-card">
                    <div class="card-header-dark">
                        <div class="icon-box"><i class="fa-solid fa-list-check"></i></div>
                        <div>
                            <h4 class="card-title-dark">PASO 2: AÑADIR MATERIALES</h4>
                            <p class="card-subtitle-dark mb-0" style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Ingresa los productos adquiridos para el ticket</p>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <form action="" method="POST" id="formMateriales">
                            
                            <div class="mb-4">
                                <label class="form-label">1. Ticket de Compra</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-receipt"></i></span>
                                    <select name="id_compra" id="select_ticket" class="form-select" onchange="mostrarVistaPrevia()" required>
                                        <option value="" disabled selected>Buscar compra reciente...</option>
                                        <?php 
                                        if ($consulta_compras !== false) {
                                            while($compra = sqlsrv_fetch_array($consulta_compras, SQLSRV_FETCH_ASSOC)) {
                                                $fecha_str = is_object($compra['Fecha']) ? $compra['Fecha']->format('d/m/Y') : $compra['Fecha'];
                                                $total_estimado_nio = $compra['TotalProductosUSD'] * $tasa_hoy;
                                                echo "<option value='".$compra['Id_Compra']."' 
                                                      data-prov='".htmlspecialchars($compra['Proveedor'])."' 
                                                      data-fecha='".$fecha_str."' 
                                                      data-total='".number_format($total_estimado_nio, 2, '.', '')."' 
                                                      >Ticket #".$compra['Id_Compra']." - Fecha: ".$fecha_str."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div id="vista_previa" class="preview-box">
                                    <div class="row text-center align-items-center">
                                        <div class="col-4 border-end">
                                            <span class="preview-label d-block">Proveedor</span>
                                            <p id="vp_prov" class="preview-data" style="color: var(--primary);">---</p>
                                        </div>
                                        <div class="col-4 border-end">
                                            <span class="preview-label d-block">Fecha</span>
                                            <p id="vp_fecha" class="preview-data">---</p>
                                        </div>
                                        <div class="col-4">
                                            <span class="preview-label d-block">Monto Estimado</span>
                                            <p id="vp_total_nio" class="preview-data" style="color: var(--warning); font-size: 1.2rem;">C$ 0.00</p>
                                        </div>
                                    </div>
                                    
                                    <div id="box_existentes" style="display: none; margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 15px;">
                                        <span class="d-block mb-2" style="color: #e2e8f0; font-weight: 500; font-size: 0.9rem;">
                                            <i class="fa-solid fa-box-archive me-2" style="color: var(--warning);"></i>Materiales ya registrados en este ticket:
                                        </span>
                                        <div class="table-responsive" style="background: rgba(0,0,0,0.2); border-radius: 8px;">
                                            <table class="table table-sm table-dark-custom m-0">
                                                <thead>
                                                    <tr>
                                                        <th>Material / Producto</th>
                                                        <th class="text-center">Cant.</th>
                                                        <th class="text-end">Subtotal (C$)</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tabla_existentes">
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <label class="form-label mt-4">2. Añadir Nuevos Materiales</label>
                            
                            <div id="contenedor-filas">
                                <div class="row fila-producto mb-3">
                                    <div class="col-md-5 mb-2">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-box-open"></i></span>
                                            <select name="id_producto[]" class="form-select select-producto" onchange="calcularSubtotal(this)" required>
                                                <option value="" data-precio="0" selected disabled>Elegir producto...</option>
                                                <?php echo $opciones_productos; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                            <input type="number" name="cantidad[]" class="form-control input-cantidad" placeholder="Cant." min="1" onkeyup="calcularSubtotal(this)" onchange="calcularSubtotal(this)" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <div class="subtotal-box">C$ <span class="monto-subtotal">0.00</span></div>
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <button type="button" class="btn btn-outline-danger w-100 h-100 p-0" style="border-radius: 8px;" disabled><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-5">
                                <button type="button" class="btn btn-outline-dashed w-100 py-2" onclick="agregarFila()">
                                    <i class="fa-solid fa-plus me-2"></i>Añadir otra fila en blanco
                                </button>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-glow btn-lg">
                                    <i class="fa-solid fa-save me-2"></i>GUARDAR NUEVOS MATERIALES
                                </button>
                                <hr style="margin: 10px 0; border-color: var(--border-color);">
                                <div class="row g-3">
                                    <div class="col-6"><a href="compras.php" class="btn btn-outline-custom w-100 py-2 text-center">Crear Otro Ticket</a></div>
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
        let baseTicketTotal = 0;

        function mostrarVistaPrevia() {
            const select = document.getElementById("select_ticket");
            const opcionSeleccionada = select.options[select.selectedIndex];
            const cajaVistaPrevia = document.getElementById("vista_previa");
            const boxExistentes = document.getElementById("box_existentes");
            const tablaExistentes = document.getElementById("tabla_existentes");

            if (select.value !== "") {
                const idTicket = select.value;
                document.getElementById("vp_prov").innerText = opcionSeleccionada.getAttribute("data-prov");
                document.getElementById("vp_fecha").innerText = opcionSeleccionada.getAttribute("data-fecha");
                
                baseTicketTotal = parseFloat(opcionSeleccionada.getAttribute("data-total")) || 0;
                cajaVistaPrevia.style.display = "block";
                
                if (detallesGuardados[idTicket] && detallesGuardados[idTicket].length > 0) {
                    let htmlFilas = "";
                    detallesGuardados[idTicket].forEach(item => {
                        htmlFilas += `<tr>
                            <td><i class="fa-solid fa-check me-2" style="color: var(--primary); opacity: 0.7;"></i><span style="color: #e2e8f0;">${item.nombre}</span></td>
                            <td class="text-center fw-bold" style="color: #f8fafc;">${item.cantidad}</td>
                            <td class="text-end" style="color: #94a3b8; font-family: monospace;">C$ ${item.subtotal}</td>
                        </tr>`;
                    });
                    tablaExistentes.innerHTML = htmlFilas;
                    boxExistentes.style.display = "block";
                } else {
                    boxExistentes.style.display = "none";
                }

                recalcularTotalTicket();
            } else {
                cajaVistaPrevia.style.display = "none";
                boxExistentes.style.display = "none";
                baseTicketTotal = 0;
            }
        }

        window.onload = function() { mostrarVistaPrevia(); };

        function agregarFila() {
            const contenedor = document.getElementById('contenedor-filas');
            const nuevaFila = document.createElement('div');
            nuevaFila.className = 'row fila-producto mb-3';
            nuevaFila.innerHTML = `
                <div class="col-md-5 mb-2">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-box-open"></i></span>
                        <select name="id_producto[]" class="form-select select-producto" onchange="calcularSubtotal(this)" required>
                            <option value="" data-precio="0" selected disabled>Elegir producto...</option>
                            ${opcionesSelect}
                        </select>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                        <input type="number" name="cantidad[]" class="form-control input-cantidad" placeholder="Cant." min="1" onkeyup="calcularSubtotal(this)" onchange="calcularSubtotal(this)" required>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="subtotal-box">C$ <span class="monto-subtotal">0.00</span></div>
                </div>
                <div class="col-md-1 mb-2">
                    <button type="button" class="btn btn-outline-danger w-100 h-100 p-0" onclick="eliminarFila(this)" style="border-radius: 8px;"><i class="fa-solid fa-trash"></i></button>
                </div>
            `;
            contenedor.appendChild(nuevaFila);
        }

        function eliminarFila(boton) {
            boton.closest('.fila-producto').remove();
            recalcularTotalTicket(); 
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
            let subtotal = precio * cantidad;
            spanSubtotal.innerText = subtotal.toFixed(2);
            recalcularTotalTicket();
        }

        function recalcularTotalTicket() {
            let totalNuevosMateriales = 0;
            const spans = document.querySelectorAll('.monto-subtotal');
            spans.forEach(span => { totalNuevosMateriales += parseFloat(span.innerText) || 0; });
            
            let granTotalNIO = baseTicketTotal + totalNuevosMateriales;

            document.getElementById("vp_total_nio").innerText = "C$ " + granTotalNIO.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    </script>
</body>
</html>