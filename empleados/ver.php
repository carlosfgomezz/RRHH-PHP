<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh', 'contabilidad']);

$usuario = obtenerUsuarioActual();
$error = '';

// Obtener ID del empleado
$empleado_id = $_GET['id'] ?? null;

if (!$empleado_id) {
    header('Location: gestionar.php');
    exit;
}

// Obtener datos del empleado
try {
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre as categoria_nombre, p.nombre as cargo_nombre
        FROM empleados e
        LEFT JOIN categorias c ON e.categoria_id = c.id
        LEFT JOIN cargos p ON e.cargo_id = p.id
        WHERE e.id = ?
    ");
    $stmt->execute([$empleado_id]);
    $empleado = $stmt->fetch();
    
    if (!$empleado) {
        header('Location: gestionar.php');
        exit;
    }
    
} catch (PDOException $e) {
    $error = "Error al cargar datos del empleado: " . $e->getMessage();
    $empleado = null;
}

// Obtener contratos del empleado
try {
    $stmt_contratos = $pdo->prepare("
        SELECT c.*, tc.nombre as tipo_contrato_nombre
        FROM contratos c
        INNER JOIN tipos_contrato tc ON c.tipo_contrato_id = tc.id
        WHERE c.empleado_id = ?
        ORDER BY c.fecha_inicio DESC
    ");
    $stmt_contratos->execute([$empleado_id]);
    $contratos = $stmt_contratos->fetchAll();
    
} catch (PDOException $e) {
    $contratos = [];
}

// Obtener historial laboral
try {
    $stmt_historial = $pdo->prepare("
        SELECT h.*, u.nombre_usuario
        FROM historial_laboral h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.empleado_id = ?
        ORDER BY h.fecha DESC
        LIMIT 20
    ");
    $stmt_historial->execute([$empleado_id]);
    $historial = $stmt_historial->fetchAll();
    
} catch (PDOException $e) {
    $historial = [];
}

// Obtener nóminas del empleado (últimas 12)
try {
    $stmt_nominas = $pdo->prepare("
        SELECT 
            n.*,
            CONCAT(n.periodo_mes, '/', n.periodo_anio) as periodo
        FROM nominas n
        WHERE n.empleado_id = ?
        ORDER BY n.periodo_anio DESC, n.periodo_mes DESC
        LIMIT 12
    ");
    $stmt_nominas->execute([$empleado_id]);
    $nominas = $stmt_nominas->fetchAll();
    
    // Calcular estadísticas de nóminas
    $total_nominas = count($nominas);
    $total_bruto = 0;
    $total_neto = 0;
    $total_ips_personal = 0;
    
    foreach ($nominas as $nomina) {
        $total_bruto += $nomina['salario_bruto'];
        $total_neto += $nomina['salario_neto'];
        $total_ips_personal += $nomina['aporte_ips_personal'];
    }
    
    $promedio_bruto = $total_nominas > 0 ? $total_bruto / $total_nominas : 0;
    $promedio_neto = $total_nominas > 0 ? $total_neto / $total_nominas : 0;
    
} catch (PDOException $e) {
    $nominas = [];
    $total_nominas = 0;
    $total_bruto = 0;
    $total_neto = 0;
    $total_ips_personal = 0;
    $promedio_bruto = 0;
    $promedio_neto = 0;
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
    <title>Detalles del Empleado - SISRH</title>
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .employee-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .employee-info {
            flex: 1;
        }
        
        .employee-name {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .employee-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }
        
        .employee-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
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
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            background: #f8f9fa;
        }
        
        .card-header h3 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .historial-item {
            padding: 0.75rem;
            border-left: 3px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 0.5rem;
            border-radius: 0 5px 5px 0;
        }
        
        .historial-accion {
            font-weight: 600;
            color: #333;
            text-transform: capitalize;
        }
        
        .historial-descripcion {
            color: #666;
            font-size: 0.9rem;
            margin: 0.25rem 0;
        }
        
        .historial-meta {
            font-size: 0.8rem;
            color: #999;
        }
        
        .contract-item {
            padding: 1rem;
            border: 1px solid #e1e5e9;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .contract-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .contract-type {
            font-weight: 600;
            color: #333;
        }
        
        .contract-status {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-activo {
            background: #28a745;
            color: white;
        }
        
        .status-vencido {
            background: #dc3545;
            color: white;
        }
        
        .status-proximo {
            background: #ffc107;
            color: #212529;
        }
        
        .contract-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        @media print {
            .header,
            .employee-actions,
            .btn {
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
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            .employee-header {
                flex-direction: column;
                text-align: center;
            }
            
            .employee-actions {
                margin-top: 1rem;
                flex-direction: row;
                justify-content: center;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .employee-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detalles del Empleado</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="gestionar.php">👥 Gestionar Empleados</a>
            <a href="nuevo.php">➕ Nuevo Empleado</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($empleado): ?>
            <div class="employee-header">
                <div class="employee-info">
                    <h1 class="employee-name"><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['apellidos']); ?></h1>
                    
                    <div class="employee-details">
                        <div class="detail-item">
                            <span class="detail-label">Cédula</span>
                            <span class="detail-value"><?php echo htmlspecialchars($empleado['cedula']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Categoría</span>
                            <span class="detail-value"><?php echo htmlspecialchars($empleado['categoria_nombre']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Cargo</span>
                            <span class="detail-value"><?php echo htmlspecialchars($empleado['cargo_nombre']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Salario Base</span>
                            <span class="detail-value">₲ <?php echo number_format($empleado['salario_base'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Fecha Ingreso</span>
                            <span class="detail-value"><?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Antigüedad</span>
                            <span class="detail-value">
                                <?php 
                                $fecha_ingreso = new DateTime($empleado['fecha_ingreso']);
                                $hoy = new DateTime();
                                $antiguedad = $hoy->diff($fecha_ingreso);
                                echo $antiguedad->y . ' años, ' . $antiguedad->m . ' meses';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="employee-actions">
                    <div class="badge <?php echo $empleado['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $empleado['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                    </div>
                    
                    <?php if (in_array($usuario['rol'], ['admin', 'rrhh'])): ?>
                        <a href="editar.php?id=<?php echo $empleado['id']; ?>" class="btn btn-warning">✏️ Editar</a>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</a>
                </div>
            </div>
            
            <div class="grid">
                <!-- Información Personal -->
                <div class="card">
                    <div class="card-header">
                        <h3>👤 Información Personal</h3>
                    </div>
                    <div class="card-body">
                        <div class="employee-details">
                            <?php if ($empleado['fecha_nacimiento']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Fecha de Nacimiento</span>
                                    <span class="detail-value"><?php echo date('d/m/Y', strtotime($empleado['fecha_nacimiento'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Edad</span>
                                    <span class="detail-value">
                                        <?php 
                                        $fecha_nacimiento = new DateTime($empleado['fecha_nacimiento']);
                                        $hoy = new DateTime();
                                        $edad = $hoy->diff($fecha_nacimiento);
                                        echo $edad->y . ' años';
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($empleado['telefono']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Teléfono</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($empleado['telefono']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($empleado['email']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($empleado['email']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($empleado['direccion']): ?>
                                <div class="detail-item" style="grid-column: 1 / -1;">
                                    <span class="detail-label">Dirección</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($empleado['direccion']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-item">
                                <span class="detail-label">Fecha de Registro</span>
                                <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($empleado['fecha_creacion'])); ?></span>
                            </div>
                            
                            <?php if ($empleado['fecha_modificacion']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Última Modificación</span>
                                    <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($empleado['fecha_modificacion'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Estadísticas de Nóminas -->
                <div class="card">
                    <div class="card-header">
                        <h3>💰 Estadísticas de Nóminas</h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $total_nominas; ?></div>
                                <div class="stat-label">Nóminas</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">₲ <?php echo number_format($promedio_bruto, 0, ',', '.'); ?></div>
                                <div class="stat-label">Promedio Bruto</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">₲ <?php echo number_format($promedio_neto, 0, ',', '.'); ?></div>
                                <div class="stat-label">Promedio Neto</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">₲ <?php echo number_format($total_ips_personal, 0, ',', '.'); ?></div>
                                <div class="stat-label">Total IPS Personal</div>
                            </div>
                        </div>
                        
                        <?php if (!empty($nominas)): ?>
                            <h4 style="margin-bottom: 1rem;">Últimas Nóminas:</h4>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Período</th>
                                        <th class="text-right">Salario Bruto</th>
                                        <th class="text-right">Salario Neto</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($nominas, 0, 6) as $nomina): ?>
                                        <tr>
                                            <td><?php echo $meses[$nomina['periodo_mes']] . ' ' . $nomina['periodo_anio']; ?></td>
                                            <td class="text-right">₲ <?php echo number_format($nomina['salario_bruto'], 0, ',', '.'); ?></td>
                                            <td class="text-right">₲ <?php echo number_format($nomina['salario_neto'], 0, ',', '.'); ?></td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $nomina['estado'] === 'pagado' ? 'badge-success' : ($nomina['estado'] === 'anulado' ? 'badge-danger' : 'badge-warning'); ?>">
                                                    <?php echo ucfirst($nomina['estado']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="icon">💰</div>
                                <p>No hay nóminas registradas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Contratos -->
                <div class="card">
                    <div class="card-header">
                        <h3>📄 Contratos</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($contratos)): ?>
                            <div class="empty-state">
                                <div class="icon">📄</div>
                                <p>No hay contratos registrados</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($contratos as $contrato): ?>
                                <?php
                                $hoy = new DateTime();
                                $fecha_fin = new DateTime($contrato['fecha_fin']);
                                $dias_restantes = $hoy->diff($fecha_fin)->days;
                                
                                if ($fecha_fin < $hoy) {
                                    $status_class = 'status-vencido';
                                    $status_text = 'Vencido';
                                } elseif ($dias_restantes <= 30) {
                                    $status_class = 'status-proximo';
                                    $status_text = 'Próximo a vencer';
                                } else {
                                    $status_class = 'status-activo';
                                    $status_text = 'Activo';
                                }
                                ?>
                                <div class="contract-item">
                                    <div class="contract-header">
                                        <span class="contract-type"><?php echo htmlspecialchars($contrato['tipo_contrato_nombre']); ?></span>
                                        <span class="contract-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </div>
                                    <div class="contract-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Fecha Inicio</span>
                                            <span class="detail-value"><?php echo date('d/m/Y', strtotime($contrato['fecha_inicio'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Fecha Fin</span>
                                            <span class="detail-value"><?php echo date('d/m/Y', strtotime($contrato['fecha_fin'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Salario</span>
                                            <span class="detail-value">₲ <?php echo number_format($contrato['salario'], 0, ',', '.'); ?></span>
                                        </div>
                                        <?php if ($contrato['observaciones']): ?>
                                            <div class="detail-item" style="grid-column: 1 / -1;">
                                                <span class="detail-label">Observaciones</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($contrato['observaciones']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Historial Laboral -->
                <div class="card">
                    <div class="card-header">
                        <h3>📝 Historial Laboral</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historial)): ?>
                            <div class="empty-state">
                                <div class="icon">📝</div>
                                <p>No hay registros en el historial laboral</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($historial as $registro): ?>
                                <div class="historial-item">
                                    <div class="historial-accion"><?php echo htmlspecialchars($registro['accion']); ?></div>
                                    <div class="historial-descripcion"><?php echo htmlspecialchars($registro['descripcion']); ?></div>
                                    <div class="historial-meta">
                                        <?php echo date('d/m/Y H:i', strtotime($registro['fecha'])); ?>
                                        <?php if ($registro['nombre_usuario']): ?>
                                            - por <?php echo htmlspecialchars($registro['nombre_usuario']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-error">Empleado no encontrado</div>
        <?php endif; ?>
    </div>
</body>
</html>