<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Centro de Ayuda - Energía y Suministros S.A.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos_globales.css">
    
    <style>
        body { 
            background-color: transparent; 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            color: var(--text-main);
            padding: 40px 40px 40px 85px; 
        }

        .help-container { max-width: 1000px; margin: 0 auto; }
        
        .section-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .help-title { color: var(--primary); font-weight: 800; text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px; }
        
        .step-number {
            background: var(--primary);
            color: white;
            width: 30px; height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center; justify-content: center;
            font-weight: bold; margin-right: 10px;
        }

        .feature-box {
            background: rgba(59, 130, 246, 0.05);
            border-left: 4px solid var(--primary);
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 8px 8px 0;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
            color: white;
        }

        .btn-print {
            position: fixed;
            top: 40px; right: 40px;
            z-index: 100;
        }

        /* MAGIA PARA LA IMPRESORA */
        @media print {
            .btn-print, .no-print { display: none !important; }
            body { background-color: white !important; color: black !important; padding: 0 !important; }
            .section-card { border: none !important; box-shadow: none !important; background: white !important; color: black !important; padding: 15px 0 !important; margin-bottom: 20px !important;}
            .help-title { color: black !important; border-bottom: 2px solid black !important; }
            .step-number { background: black !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .feature-box { border-left: 4px solid black !important; background: transparent !important; }
            .text-primary { color: black !important; }
            .text-danger { color: black !important; }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="btn btn-primary-custom btn-print no-print">
        <i class="fa-solid fa-print me-2"></i>Imprimir Manual
    </button>

    <div class="help-container">
        
        <div class="section-card">
            <h2 class="help-title"><i class="fa-solid fa-book-open me-3"></i>Manual de Usuario v1.0</h2>
            <p>Bienvenido al Sistema de Planificación de Recursos Empresariales (ERP) de <strong>Energía y Suministros S.A.</strong>. Esta guía le ayudará a dominar las funciones de inventario, facturación y control de personal.</p>
        </div>

        <div class="section-card">
            <h4><i class="fa-solid fa-database text-primary me-2"></i> 1. Gestión de Catálogos (Ingreso de Datos)</h4>
            <p>Antes de operar con dinero o inventario, debe asegurarse de que los datos base de la empresa estén registrados:</p>
            <ul>
                <li><strong>Productos:</strong> Permite añadir nuevos materiales eléctricos al catálogo. <em>Nota: Aquí no se suma stock, solo se crea el perfil del producto. El stock aumentará únicamente al registrar compras.</em></li>
                <li><strong>Proveedores y Clientes:</strong> Registre la información de las empresas con las que opera. El sistema cuenta con bloqueo automático de duplicados.</li>
                <li><strong>Personal (Encargados):</strong> Es vital asignar un <em>Usuario (Login)</em> único a cada trabajador, ya que este dato los identificará permanentemente en las operaciones del sistema.</li>
            </ul>
        </div>

        <div class="section-card">
            <h4><i class="fa-solid fa-gears text-primary me-2"></i> 2. Flujo de Inventario (Operaciones)</h4>
            <div class="feature-box">
                <strong><i class="fa-solid fa-circle-info me-2"></i>Regla de Oro:</strong> Las operaciones se realizan siempre en dos pasos.
            </div>
            <p><span class="step-number">1</span> <strong>Registrar Cabecera:</strong> En la pantalla "Registrar Compra" o "Registrar Factura", usted crea el documento general seleccionando la fecha, el proveedor/cliente y un concepto.</p>
            <p><span class="step-number">2</span> <strong>Añadir Materiales:</strong> Una vez guardada la cabecera, diríjase a "Añadir a Ticket" o "Añadir a Factura" para cargar los productos físicos uno por uno al documento recién creado.</p>
            <p><em>* El sistema se encarga de sumar (compras) o restar (facturas) el stock automáticamente de su almacén al momento de guardar cada producto.</em></p>
        </div>

        <div class="section-card">
            <h4><i class="fa-solid fa-ban text-danger me-2"></i> 3. Anulaciones y Reversión de Stock</h4>
            <p>Si se cometió un error administrativo o un proyecto fue cancelado de último minuto, el sistema cuenta con trazabilidad avanzada. Diríjase a los historiales de Compras o Facturas y utilice el botón rojo <strong>Anular</strong>:</p>
            <ul>
                <li>El sistema le exigirá un <strong>Motivo de Anulación</strong> por seguridad y control de auditoría.</li>
                <li><strong>Automatización:</strong> Al anular una factura, los productos se devolverán inmediatamente al inventario. Al anular una compra, se restarán.</li>
                <li>Las operaciones anuladas se marcarán en rojo, no sumarán ingresos en el Dashboard y quedarán archivadas para consulta.</li>
            </ul>
        </div>

        <div class="section-card">
            <h4><i class="fa-solid fa-chart-line text-primary me-2"></i> 4. Monitoreo de Almacén (Dashboard)</h4>
            <p>La pantalla principal (Inicio) es su centro de comando. Los montos financieros se calculan en tiempo real basándose en las operaciones activas.</p>
            <ul>
                <li><strong>Alertas de Stock Crítico:</strong> El sistema monitorea el almacén 24/7. Si un producto baja de 10 unidades, la tarjeta de alertas cambiará a <span class="text-danger">color rojo</span>.</li>
                <li>El producto aparecerá detallado en la tabla inferior, donde puede hacer clic en <strong>Reabastecer</strong> para saltar directamente al módulo de compras e inyectar nuevo material al inventario.</li>
            </ul>
        </div>

    </div>

</body>
</html>