<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh', 'contabilidad']);

$usuario = obtenerUsuarioActual();
$mensaje = '';
$error = '';

// Obtener ID de la nómina
$nomina_id = $_GET['id'] ?? 0;

if (!$nomina_id) {
    header('Location: gestionar.php');
    exit;
}

// Procesar cambio de estado
if (isset($_POST['cambiar_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    $fecha_pago = ($nuevo_estado == 'pagado') ? date('Y-m-d') : null;
    
    try {
        $stmt = $pdo->prepare("UPDATE nominas SET estado = ?, fecha_pago = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $fecha_pago, $nomina_id]);
        
        registrarActividad('Cambiar Estado Nómina', "Estado cambiado a '$nuevo_estado' para nómina ID: $nomina_id");
        $mensaje = "Estado actualizado exitosamente";
    } catch (PDOException $e) {
        $error = "Error al actualizar estado: " . $e->getMessage();
    }
}

// Obtener datos de la nómina
try {
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            e.nombres,
            e.apellidos,
            e.cedula,
            e.telefono,
            e.email,
            e.direccion,
            e.fecha_ingreso,
            cat.nombre as categoria,
            car.nombre as cargo,
            tc.nombre as tipo_contrato,
            c.salario_base,
            c.fecha_inicio as contrato_inicio,
            c.fecha_fin as contrato_fin
        FROM nominas n
        INNER JOIN empleados e ON n.empleado_id = e.id
        INNER JOIN categorias cat ON e.categoria_id = cat.id
        INNER JOIN cargos car ON e.cargo_id = car.id
        INNER JOIN contratos c ON e.id = c.empleado_id AND c.activo = 1
        INNER JOIN tipos_contrato tc ON c.tipo_contrato_id = tc.id
        WHERE n.id = ?
    ");
    $stmt->execute([$nomina_id]);
    $nomina = $stmt->fetch();
    
    if (!$nomina) {
        header('Location: gestionar.php');
        exit;
    }
    
    // Obtener detalles de bonificaciones y deducciones
    $stmt_detalles = $pdo->prepare("
        SELECT 
            nd.*,
            cp.nombre as concepto_nombre,
            cp.tipo as concepto_tipo
        FROM nomina_detalles nd
        INNER JOIN conceptos_pago cp ON nd.concepto_id = cp.id
        WHERE nd.empleado_id = ? 
        AND MONTH(nd.fecha) = ? 
        AND YEAR(nd.fecha) = ?
        ORDER BY cp.tipo, cp.nombre
    ");
    $stmt_detalles->execute([$nomina['empleado_id'], $nomina['periodo_mes'], $nomina['periodo_anio']]);
    $detalles = $stmt_detalles->fetchAll();
    
    // Separar bonificaciones y deducciones
    $bonificaciones = [];
    $deducciones = [];
    
    foreach ($detalles as $detalle) {
        if ($detalle['concepto_tipo'] == 'bonificacion') {
            $bonificaciones[] = $detalle;
        } else {
            $deducciones[] = $detalle;
        }
    }
    
} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
    $nomina = null;
}

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Nómina - SISRH</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            background: rgba(255,255,255,0.2);
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .payroll-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .company-info {
            margin-bottom: 2rem;
        }
        
        .company-info h2 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .company-info p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .payroll-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .employee-info {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #333;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .salary-breakdown {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .breakdown-header {
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .breakdown-content {
            padding: 2rem;
        }
        
        .breakdown-section {
            margin-bottom: 2rem;
        }
        
        .breakdown-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            color: #333;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e1e5e9;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .breakdown-item:last-child {
            border-bottom: none;
        }
        
        .breakdown-item.total {
            font-weight: 600;
            font-size: 1.1rem;
            border-top: 2px solid #333;
            margin-top: 1rem;
            padding-top: 1rem;
        }
        
        .amount {
            font-weight: 500;
        }
        
        .amount.positive {
            color: #28a745;
        }
        
        .amount.negative {
            color: #dc3545;
        }
        
        .amount.total {
            color: #333;
            font-size: 1.2rem;
        }
        
        .status-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .status.pendiente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status.pagado {
            background: #d4edda;
            color: #155724;
        }
        
        .status.anulado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: transform 0.2s;
            display: inline-block;
            text-align: center;
            margin-right: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .print-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 50px;
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        
        .print-button:hover {
            transform: scale(1.1);
        }
        
        @media print {
            .header,
            .nav-links,
            .status-section,
            .print-button {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .container {
                margin: 0;
                padding: 0;
                max-width: none;
            }
            
            .payroll-header,
            .employee-info,
            .salary-breakdown {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detalle de Nómina</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="gestionar.php">📋 Gestionar Nóminas</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($nomina): ?>
            <div class="payroll-header">
                <div class="company-info">
                    <h2>SISRH - Sistema Integral de Salarios y RRHH</h2>
                    <p>Recibo de Salario</p>
                </div>
                
                <div class="payroll-title">
                    <h3>Período: <?php echo $meses[$nomina['periodo_mes']] . ' ' . $nomina['periodo_anio']; ?></h3>
                </div>
            </div>
            
            <div class="employee-info">
                <h3 style="margin-bottom: 1.5rem; color: #333;">Información del Empleado</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nombre Completo</div>
                        <div class="info-value"><?php echo htmlspecialchars($nomina['apellidos'] . ', ' . $nomina['nombres']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Cédula de Identidad</div>
                        <div class="info-value"><?php echo htmlspecialchars($nomina['cedula']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Categoría</div>
                        <div class="info-value"><?php echo htmlspecialchars($nomina['categoria']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Cargo</div>
                        <div class="info-value"><?php echo htmlspecialchars($nomina['cargo']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Tipo de Contrato</div>
                        <div class="info-value"><?php echo htmlspecialchars($nomina['tipo_contrato']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Fecha de Ingreso</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($nomina['fecha_ingreso'])); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="salary-breakdown">
                <div class="breakdown-header">
                    <h3>Desglose Salarial</h3>
                </div>
                
                <div class="breakdown-content">
                    <div class="breakdown-section">
                        <div class="section-title">Ingresos</div>
                        
                        <div class="breakdown-item">
                            <span>Salario Base</span>
                            <span class="amount positive">₲ <?php echo number_format($nomina['salario_bruto'], 0, ',', '.'); ?></span>
                        </div>
                        
                        <?php if (!empty($bonificaciones)): ?>
                            <?php foreach ($bonificaciones as $bonif): ?>
                                <div class="breakdown-item">
                                    <span><?php echo htmlspecialchars($bonif['concepto_nombre']); ?></span>
                                    <span class="amount positive">₲ <?php echo number_format($bonif['monto'], 0, ',', '.'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="breakdown-item total">
                            <span>Total Ingresos</span>
                            <span class="amount positive">₲ <?php echo number_format($nomina['salario_bruto'] + $nomina['total_bonificaciones'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <div class="breakdown-section">
                        <div class="section-title">Descuentos</div>
                        
                        <div class="breakdown-item">
                            <span>Aporte IPS Personal (9%)</span>
                            <span class="amount negative">₲ <?php echo number_format($nomina['aporte_ips_personal'], 0, ',', '.'); ?></span>
                        </div>
                        
                        <?php if (!empty($deducciones)): ?>
                            <?php foreach ($deducciones as $deduc): ?>
                                <div class="breakdown-item">
                                    <span><?php echo htmlspecialchars($deduc['concepto_nombre']); ?></span>
                                    <span class="amount negative">₲ <?php echo number_format($deduc['monto'], 0, ',', '.'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="breakdown-item total">
                            <span>Total Descuentos</span>
                            <span class="amount negative">₲ <?php echo number_format($nomina['aporte_ips_personal'] + $nomina['total_deducciones'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <div class="breakdown-section">
                        <div class="section-title">Información Patronal</div>
                        
                        <div class="breakdown-item">
                            <span>Aporte IPS Patronal (16.5%)</span>
                            <span class="amount">₲ <?php echo number_format($nomina['aporte_ips_patronal'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <div class="breakdown-section">
                        <div class="breakdown-item total">
                            <span><strong>SALARIO NETO A COBRAR</strong></span>
                            <span class="amount total">₲ <?php echo number_format($nomina['salario_neto'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="status-section">
                <h3 style="margin-bottom: 1rem; color: #333;">Estado y Acciones</h3>
                
                <div>
                    <span class="status <?php echo $nomina['estado']; ?>">
                        <?php echo ucfirst($nomina['estado']); ?>
                    </span>
                    
                    <?php if ($nomina['fecha_pago']): ?>
                        <p style="margin-top: 0.5rem; color: #666;">
                            Fecha de pago: <?php echo date('d/m/Y', strtotime($nomina['fecha_pago'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php if ($usuario['rol'] == 'admin' || $usuario['rol'] == 'rrhh'): ?>
                    <div class="actions">
                        <?php if ($nomina['estado'] == 'pendiente'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="nuevo_estado" value="pagado">
                                <button type="submit" name="cambiar_estado" class="btn btn-success" 
                                        onclick="return confirm('¿Marcar como pagado?')">
                                    ✅ Marcar como Pagado
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="nuevo_estado" value="anulado">
                                <button type="submit" name="cambiar_estado" class="btn btn-danger" 
                                        onclick="return confirm('¿Anular esta nómina?')">
                                    ❌ Anular
                                </button>
                            </form>
                        <?php elseif ($nomina['estado'] == 'anulado'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="nuevo_estado" value="pendiente">
                                <button type="submit" name="cambiar_estado" class="btn btn-warning" 
                                        onclick="return confirm('¿Reactivar esta nómina?')">
                                    🔄 Reactivar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <button class="print-button" onclick="window.print()" title="Imprimir recibo">
                🖨️
            </button>
        <?php endif; ?>
    </div>
</body>
</html>