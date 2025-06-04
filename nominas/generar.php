<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh']);

$usuario = obtenerUsuarioActual();
$mensaje = '';
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
    
    $salario_minimo = $config['salario_minimo'] ?? 2550000;
    $aporte_ips_personal = $config['aporte_ips_personal'] ?? 9;
    $aporte_ips_patronal = $config['aporte_ips_patronal'] ?? 16.5;
    
} catch (PDOException $e) {
    $error = "Error al cargar configuración: " . $e->getMessage();
    $salario_minimo = 2550000;
    $aporte_ips_personal = 9;
    $aporte_ips_patronal = 16.5;
}

// Procesar generación de nómina
if (isset($_POST['generar_nomina'])) {
    $periodo_mes = $_POST['periodo_mes'];
    $periodo_anio = $_POST['periodo_anio'];
    $empleados_seleccionados = $_POST['empleados'] ?? [];
    
    if (empty($empleados_seleccionados)) {
        $error = "Debe seleccionar al menos un empleado";
    } else {
        try {
            $pdo->beginTransaction();
            
            $nominas_generadas = 0;
            $nominas_existentes = 0;
            
            foreach ($empleados_seleccionados as $empleado_id) {
                // Verificar si ya existe nómina para este empleado en este período
                $stmt_check = $pdo->prepare("
                    SELECT id FROM nominas 
                    WHERE empleado_id = ? AND periodo_mes = ? AND periodo_anio = ?
                ");
                $stmt_check->execute([$empleado_id, $periodo_mes, $periodo_anio]);
                
                if ($stmt_check->fetch()) {
                    $nominas_existentes++;
                    continue;
                }
                
                // Obtener datos del empleado
                $stmt_emp = $pdo->prepare("
                    SELECT e.*, salario, ct.nombre as tipo_contrato
                    FROM empleados e
                    INNER JOIN contratos c ON e.id = c.empleado_id AND c.activo = 1
                    INNER JOIN tipos_contrato ct ON c.tipo_contrato_id = ct.id
                    WHERE e.id = ? AND e.activo = 1
                ");
                $stmt_emp->execute([$empleado_id]);
                $empleado = $stmt_emp->fetch();
                
                if (!$empleado) {
                    continue;
                }
                
                // Calcular salario bruto
                $salario_bruto = $empleado['salario_base'];
                
                // Obtener bonificaciones del período
                $stmt_bonif = $pdo->prepare("
                    SELECT SUM(nd.monto) as total_bonificaciones
                    FROM nomina_detalles nd
                    INNER JOIN conceptos_pago cp ON nd.concepto_id = cp.id
                    WHERE nd.empleado_id = ? AND cp.tipo = 'bonificacion'
                    AND MONTH(nd.fecha) = ? AND YEAR(nd.fecha) = ?
                ");
                $stmt_bonif->execute([$empleado_id, $periodo_mes, $periodo_anio]);
                $bonificaciones = $stmt_bonif->fetchColumn() ?: 0;
                
                // Obtener deducciones del período
                $stmt_deduc = $pdo->prepare("
                    SELECT SUM(nd.monto) as total_deducciones
                    FROM nomina_detalles nd
                    INNER JOIN conceptos_pago cp ON nd.concepto_id = cp.id
                    WHERE nd.empleado_id = ? AND cp.tipo = 'deduccion'
                    AND MONTH(nd.fecha) = ? AND YEAR(nd.fecha) = ?
                ");
                $stmt_deduc->execute([$empleado_id, $periodo_mes, $periodo_anio]);
                $deducciones = $stmt_deduc->fetchColumn() ?: 0;
                
                // Calcular aportes IPS
                $base_calculo_ips = $salario_bruto + $bonificaciones;
                $ips_personal = round($base_calculo_ips * ($aporte_ips_personal / 100));
                $ips_patronal = round($base_calculo_ips * ($aporte_ips_patronal / 100));
                
                // Calcular salario neto
                $salario_neto = $salario_bruto + $bonificaciones - $deducciones - $ips_personal;
                
                // Insertar nómina
                $stmt_nomina = $pdo->prepare("
                    INSERT INTO nominas (
                        empleado_id, periodo_mes, periodo_anio, salario_bruto,
                        aporte_ips_personal, aporte_ips_patronal, total_bonificaciones,
                        total_deducciones, salario_neto, estado, fecha_creacion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())
                ");
                
                $stmt_nomina->execute([
                    $empleado_id, $periodo_mes, $periodo_anio, $salario_bruto,
                    $ips_personal, $ips_patronal, $bonificaciones,
                    $deducciones, $salario_neto
                ]);
                
                $nominas_generadas++;
            }
            
            $pdo->commit();
            
            $mensaje = "Nóminas generadas exitosamente: $nominas_generadas";
            if ($nominas_existentes > 0) {
                $mensaje .= ". Nóminas ya existentes: $nominas_existentes";
            }
            
            registrarActividad('Generar Nóminas', "Generadas $nominas_generadas nóminas para período $periodo_mes/$periodo_anio");
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al generar nóminas: " . $e->getMessage();
        }
    }
}

// Obtener empleados activos con contratos
try {
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.nombres,
            e.apellidos,
            e.cedula,
            cat.nombre as categoria,
            car.nombre as cargo,
            salario,
            tc.nombre as tipo_contrato
        FROM empleados e
        INNER JOIN contratos c ON e.id = c.empleado_id AND c.activo = 1
        INNER JOIN categorias cat ON e.categoria_id = cat.id
        INNER JOIN cargos car ON e.cargo_id = car.id
        INNER JOIN tipos_contrato tc ON c.tipo_contrato_id = tc.id
        WHERE e.activo = 1
        ORDER BY e.apellidos, e.nombres
    ");
    $stmt->execute();
    $empleados = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $empleados = [];
    $error = "Error al cargar empleados: " . $e->getMessage();
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
    <title>Generar Nóminas - SISRH</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group select,
        .form-group input {
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .config-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .config-info h4 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .config-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .employees-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .employees-header {
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .select-all {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .employees-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .employee-item {
            padding: 1rem 2rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.2s;
        }
        
        .employee-item:hover {
            background: #f8f9fa;
        }
        
        .employee-item:last-child {
            border-bottom: none;
        }
        
        .employee-checkbox {
            width: 18px;
            height: 18px;
        }
        
        .employee-info {
            flex: 1;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            align-items: center;
        }
        
        .employee-name {
            font-weight: 500;
            color: #333;
        }
        
        .employee-detail {
            color: #666;
            font-size: 0.9rem;
        }
        
        .salary-amount {
            font-weight: 600;
            color: #28a745;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            transition: transform 0.2s;
            display: inline-block;
            text-align: center;
            font-weight: 500;
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
        
        .btn-block {
            width: 100%;
            margin-top: 2rem;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .employee-info {
                grid-template-columns: 1fr;
                gap: 0.25rem;
            }
            
            .config-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Generar Nóminas</h1>
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
        
        <form method="POST">
            <div class="form-container">
                <h3 style="margin-bottom: 1.5rem; color: #333;">Configuración del Período</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="periodo_mes">Mes:</label>
                        <select id="periodo_mes" name="periodo_mes" required>
                            <?php foreach ($meses as $num => $nombre): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($num == date('n')) ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="periodo_anio">Año:</label>
                        <select id="periodo_anio" name="periodo_anio" required>
                            <?php for ($i = date('Y'); $i >= date('Y') - 2; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == date('Y')) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="config-info">
                    <h4>Configuración Actual del Sistema</h4>
                    <div class="config-grid">
                        <div class="config-item">
                            <span>Salario Mínimo:</span>
                            <strong>₲ <?php echo number_format($salario_minimo, 0, ',', '.'); ?></strong>
                        </div>
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
            
            <div class="employees-section">
                <div class="employees-header">
                    <h3>Seleccionar Empleados</h3>
                    <div class="select-all">
                        <input type="checkbox" id="select_all" onchange="toggleAllEmployees()">
                        <label for="select_all">Seleccionar todos</label>
                    </div>
                </div>
                
                <?php if (empty($empleados)): ?>
                    <div class="empty-state">
                        <div class="icon">👥</div>
                        <h3>No hay empleados disponibles</h3>
                        <p>No se encontraron empleados activos con contratos vigentes.</p>
                    </div>
                <?php else: ?>
                    <div class="employees-list">
                        <?php foreach ($empleados as $empleado): ?>
                            <div class="employee-item">
                                <input type="checkbox" name="empleados[]" value="<?php echo $empleado['id']; ?>" 
                                       class="employee-checkbox" id="emp_<?php echo $empleado['id']; ?>">
                                <div class="employee-info">
                                    <div>
                                        <div class="employee-name">
                                            <?php echo htmlspecialchars($empleado['apellidos'] . ', ' . $empleado['nombres']); ?>
                                        </div>
                                        <div class="employee-detail">CI: <?php echo htmlspecialchars($empleado['cedula']); ?></div>
                                    </div>
                                    <div class="employee-detail"><?php echo htmlspecialchars($empleado['categoria']); ?></div>
                                    <div class="employee-detail"><?php echo htmlspecialchars($empleado['cargo']); ?></div>
                                    <div class="employee-detail"><?php echo htmlspecialchars($empleado['tipo_contrato']); ?></div>
                                    <div class="salary-amount">₲ <?php echo number_format($empleado['salario_base'], 0, ',', '.'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="padding: 2rem;">
                        <button type="submit" name="generar_nomina" class="btn btn-success btn-block">
                            💰 Generar Nóminas Seleccionadas
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <script>
        function toggleAllEmployees() {
            const selectAll = document.getElementById('select_all');
            const checkboxes = document.querySelectorAll('.employee-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        // Actualizar el estado del checkbox "Seleccionar todos"
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.employee-checkbox');
            const selectAll = document.getElementById('select_all');
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    const noneChecked = Array.from(checkboxes).every(cb => !cb.checked);
                    
                    if (allChecked) {
                        selectAll.checked = true;
                        selectAll.indeterminate = false;
                    } else if (noneChecked) {
                        selectAll.checked = false;
                        selectAll.indeterminate = false;
                    } else {
                        selectAll.checked = false;
                        selectAll.indeterminate = true;
                    }
                });
            });
        });
    </script>
</body>
</html>