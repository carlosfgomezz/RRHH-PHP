<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh', 'contabilidad']);

$usuario = obtenerUsuarioActual();
$error = '';

// Obtener configuración
try {
    $stmt = $pdo->prepare("SELECT clave, valor FROM configuracion");
    $stmt->execute();
    $config_rows = $stmt->fetchAll();
    
    $config = [];
    foreach ($config_rows as $row) {
        $config[$row['clave']] = $row['valor'];
    }
    
    $aporte_ips_personal = $config['aporte_ips_personal'] ?? 9;
    $aporte_ips_patronal = $config['aporte_ips_patronal'] ?? 16.5;
    
} catch (PDOException $e) {
    $error = "Error al cargar configuración: " . $e->getMessage();
    $aporte_ips_personal = 9;
    $aporte_ips_patronal = 16.5;
}

// Filtros
$filtro_mes = $_GET['mes'] ?? date('n');
$filtro_ano = $_GET['ano'] ?? date('Y');
$filtro_empleado = $_GET['empleado'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';

// Obtener datos para filtros
try {
    // Empleados
    $stmt_empleados = $pdo->prepare("SELECT id, nombres, apellidos FROM empleados WHERE activo = 1 ORDER BY apellidos, nombres");
    $stmt_empleados->execute();
    $empleados = $stmt_empleados->fetchAll();
    
    // Categorías
    $stmt_categorias = $pdo->prepare("SELECT id, nombre FROM categorias ORDER BY nombre");
    $stmt_categorias->execute();
    $categorias = $stmt_categorias->fetchAll();
    
} catch (PDOException $e) {
    $empleados = [];
    $categorias = [];
    $error = "Error al cargar datos: " . $e->getMessage();
}

// Obtener datos de IPS
try {
    $where_conditions = [];
    $params = [];
    
    if ($filtro_mes) {
        $where_conditions[] = "n.periodo_mes = ?";
        $params[] = $filtro_mes;
    }
    
    if ($filtro_ano) {
        $where_conditions[] = "n.periodo_anio = ?";
        $params[] = $filtro_ano;
    }
    
    if ($filtro_empleado) {
        $where_conditions[] = "n.empleado_id = ?";
        $params[] = $filtro_empleado;
    }
    
    if ($filtro_categoria) {
        $where_conditions[] = "e.categoria_id = ?";
        $params[] = $filtro_categoria;
    }
    
    // Solo nóminas pagadas para el reporte IPS
    $where_conditions[] = "n.estado = 'pagado'";
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $query = "
        SELECT 
            n.id,
            n.periodo_mes,
            n.periodo_anio,
            n.salario_bruto,
            n.aporte_ips_personal,
            n.aporte_ips_patronal,
            n.total_bonificaciones,
            n.fecha_pago,
            e.nombres,
            e.apellidos,
            e.cedula as numero_documento,
            c.nombre as categoria,
            p.nombre as cargo,
            (n.salario_bruto + n.total_bonificaciones) as base_calculo
        FROM nominas n
        INNER JOIN empleados e ON n.empleado_id = e.id
        INNER JOIN categorias c ON e.categoria_id = c.id
        INNER JOIN cargos p ON e.cargo_id = p.id
        $where_clause
        ORDER BY e.apellidos, e.nombres
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $datos_ips = $stmt->fetchAll();
    
    // Calcular totales
    $total_base_calculo = 0;
    $total_ips_personal = 0;
    $total_ips_patronal = 0;
    $total_empleados = count($datos_ips);
    
    foreach ($datos_ips as $dato) {
        $total_base_calculo += $dato['base_calculo'];
        $total_ips_personal += $dato['aporte_ips_personal'];
        $total_ips_patronal += $dato['aporte_ips_patronal'];
    }
    
} catch (PDOException $e) {
    $datos_ips = [];
    $error = "Error al cargar datos de IPS: " . $e->getMessage();
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
    <title>Reporte IPS - SISRH</title>
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
        
        .report-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .report-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .report-header .subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .config-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            border-left: 4px solid #2196f3;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .config-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .filter-group select {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 0.9rem;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .summary-card.ips-personal {
            border-left: 4px solid #ff9800;
        }
        
        .summary-card.ips-patronal {
            border-left: 4px solid #4caf50;
        }
        
        .summary-card.total {
            border-left: 4px solid #2196f3;
        }
        
        .summary-card .title {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .summary-card .amount {
            color: #333;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .summary-card .percentage {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
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
            position: sticky;
            top: 0;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .totals-row {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        @media print {
            .header,
            .filters,
            .actions-bar {
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
            
            .report-header,
            .summary-cards,
            .table-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte IPS</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="nomina.php">📊 Reporte Nóminas</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="report-header">
            <h2>Reporte de Aportes al Instituto de Previsión Social (IPS)</h2>
            <p class="subtitle">Detalle de aportes patronales y personales</p>
            
            <div class="config-info">
                <strong>Configuración Actual:</strong>
                <div class="config-grid">
                    <div class="config-item">
                        <span>Aporte IPS Personal:</span>
                        <strong><?php echo $aporte_ips_personal; ?>%</strong>
                    </div>
                    <div class="config-item">
                        <span>Aporte IPS Patronal:</span>
                        <strong><?php echo $aporte_ips_patronal; ?>%</strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <h3>Filtros de Búsqueda</h3>
            <form method="GET" class="filter-grid">
                <div class="filter-group">
                    <label for="mes">Mes:</label>
                    <select id="mes" name="mes">
                        <?php foreach ($meses as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo ($filtro_mes == $num) ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="ano">Año:</label>
                    <select id="ano" name="ano">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($filtro_ano == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="empleado">Empleado:</label>
                    <select id="empleado" name="empleado">
                        <option value="">Todos los empleados</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?php echo $empleado['id']; ?>" <?php echo ($filtro_empleado == $empleado['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($empleado['apellidos'] . ', ' . $empleado['nombres']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="categoria">Categoría:</label>
                    <select id="categoria" name="categoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>" <?php echo ($filtro_categoria == $categoria['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($datos_ips)): ?>
            <div class="summary-cards">
                <div class="summary-card total">
                    <div class="title">Total Empleados</div>
                    <div class="amount"><?php echo $total_empleados; ?></div>
                </div>
                
                <div class="summary-card total">
                    <div class="title">Base de Cálculo Total</div>
                    <div class="amount">₲ <?php echo number_format($total_base_calculo, 0, ',', '.'); ?></div>
                </div>
                
                <div class="summary-card ips-personal">
                    <div class="title">Total IPS Personal</div>
                    <div class="amount">₲ <?php echo number_format($total_ips_personal, 0, ',', '.'); ?></div>
                    <div class="percentage"><?php echo $aporte_ips_personal; ?>% del salario</div>
                </div>
                
                <div class="summary-card ips-patronal">
                    <div class="title">Total IPS Patronal</div>
                    <div class="amount">₲ <?php echo number_format($total_ips_patronal, 0, ',', '.'); ?></div>
                    <div class="percentage"><?php echo $aporte_ips_patronal; ?>% del salario</div>
                </div>
                
                <div class="summary-card total">
                    <div class="title">Total Aportes IPS</div>
                    <div class="amount">₲ <?php echo number_format($total_ips_personal + $total_ips_patronal, 0, ',', '.'); ?></div>
                    <div class="percentage">Personal + Patronal</div>
                </div>
            </div>
            
            <div class="actions-bar">
                <h3>Detalle por Empleado (<?php echo count($datos_ips); ?> registros)</h3>
                <button onclick="window.print()" class="btn btn-success">🖨️ Imprimir Reporte</button>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <?php if (empty($datos_ips)): ?>
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h3>No hay datos para mostrar</h3>
                    <p>No se encontraron nóminas pagadas con los filtros seleccionados.</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Cédula</th>
                            <th>Categoría</th>
                            <th>Cargo</th>
                            <th class="text-right">Salario Base</th>
                            <th class="text-right">Bonificaciones</th>
                            <th class="text-right">Base Cálculo</th>
                            <th class="text-right">IPS Personal<br><small>(<?php echo $aporte_ips_personal; ?>%)</small></th>
                            <th class="text-right">IPS Patronal<br><small>(<?php echo $aporte_ips_patronal; ?>%)</small></th>
                            <th class="text-right">Total IPS</th>
                            <th class="text-center">Fecha Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_ips as $dato): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($dato['apellidos'] . ', ' . $dato['nombres']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($dato['numero_documento']); ?></td>
                                <td><?php echo htmlspecialchars($dato['categoria']); ?></td>
                                <td><?php echo htmlspecialchars($dato['cargo']); ?></td>
                                <td class="text-right">₲ <?php echo number_format($dato['salario_bruto'], 0, ',', '.'); ?></td>
                                <td class="text-right">₲ <?php echo number_format($dato['total_bonificaciones'], 0, ',', '.'); ?></td>
                                <td class="text-right"><strong>₲ <?php echo number_format($dato['base_calculo'], 0, ',', '.'); ?></strong></td>
                                <td class="text-right">₲ <?php echo number_format($dato['aporte_ips_personal'], 0, ',', '.'); ?></td>
                                <td class="text-right">₲ <?php echo number_format($dato['aporte_ips_patronal'], 0, ',', '.'); ?></td>
                                <td class="text-right"><strong>₲ <?php echo number_format($dato['aporte_ips_personal'] + $dato['aporte_ips_patronal'], 0, ',', '.'); ?></strong></td>
                                <td class="text-center"><?php echo date('d/m/Y', strtotime($dato['fecha_pago'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="totals-row">
                            <td colspan="6"><strong>TOTALES</strong></td>
                            <td class="text-right"><strong>₲ <?php echo number_format($total_base_calculo, 0, ',', '.'); ?></strong></td>
                            <td class="text-right"><strong>₲ <?php echo number_format($total_ips_personal, 0, ',', '.'); ?></strong></td>
                            <td class="text-right"><strong>₲ <?php echo number_format($total_ips_patronal, 0, ',', '.'); ?></strong></td>
                            <td class="text-right"><strong>₲ <?php echo number_format($total_ips_personal + $total_ips_patronal, 0, ',', '.'); ?></strong></td>
                            <td class="text-center">-</td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>