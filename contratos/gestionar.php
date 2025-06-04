<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh']);

$usuario = obtenerUsuarioActual();
$mensaje = '';
$error = '';

// Manejar eliminación de contrato
if ($_POST['accion'] ?? '' === 'eliminar' && isset($_POST['contrato_id'])) {
    try {
        $pdo->beginTransaction();
        
        // Obtener datos del contrato antes de eliminar
        $stmt = $pdo->prepare("
            SELECT c.*, e.nombres, e.apellidos, tc.nombre as tipo_contrato
            FROM contratos c
            INNER JOIN empleados e ON c.empleado_id = e.id
            INNER JOIN tipos_contrato tc ON c.tipo_contrato_id = tc.id
            WHERE c.id = ?
        ");
        $stmt->execute([$_POST['contrato_id']]);
        $contrato = $stmt->fetch();
        
        if ($contrato) {
            // Eliminar contrato
            $stmt = $pdo->prepare("DELETE FROM contratos WHERE id = ?");
            $stmt->execute([$_POST['contrato_id']]);
            
            // Registrar en historial laboral
            $descripcion = "Contrato eliminado: {$contrato['tipo_contrato']} del " . 
                          date('d/m/Y', strtotime($contrato['fecha_inicio'])) . " al " . 
                          date('d/m/Y', strtotime($contrato['fecha_fin']));
            
            $stmt = $pdo->prepare("
                INSERT INTO historial_laboral (empleado_id, accion, descripcion, usuario_id, fecha)
                VALUES (?, 'eliminacion_contrato', ?, ?, NOW())
            ");
            $stmt->execute([$contrato['empleado_id'], $descripcion, $usuario['id']]);
            
            // Registrar en logs
            registrarActividad($usuario['id'], 'eliminacion_contrato', 
                "Contrato eliminado para empleado: {$contrato['nombres']} {$contrato['apellidos']}");
            
            $pdo->commit();
            $mensaje = "Contrato eliminado exitosamente";
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error al eliminar contrato: " . $e->getMessage();
    }
}

// Filtros
$filtro_empleado = $_GET['empleado'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$buscar = $_GET['buscar'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if ($filtro_empleado) {
    $where_conditions[] = "c.empleado_id = ?";
    $params[] = $filtro_empleado;
}

if ($filtro_tipo) {
    $where_conditions[] = "c.tipo_contrato_id = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_estado) {
    if ($filtro_estado === 'activo') {
        $where_conditions[] = "c.fecha_fin >= CURDATE()";
    } elseif ($filtro_estado === 'vencido') {
        $where_conditions[] = "c.fecha_fin < CURDATE()";
    } elseif ($filtro_estado === 'proximo_vencer') {
        $where_conditions[] = "c.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
}

if ($buscar) {
    $where_conditions[] = "(e.nombres LIKE ? OR e.apellidos LIKE ? OR e.cedula LIKE ?)";
    $buscar_param = "%$buscar%";
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $params[] = $buscar_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener contratos
try {
    $sql = "
        SELECT 
            c.*,
            e.nombres,
            e.apellidos,
            e.cedula,
            tc.nombre as tipo_contrato_nombre,
            cat.nombre as categoria_nombre,
            car.nombre as cargo_nombre,
            CASE 
                WHEN c.fecha_fin < CURDATE() THEN 'vencido'
                WHEN c.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'proximo_vencer'
                ELSE 'activo'
            END as estado_contrato,
            DATEDIFF(c.fecha_fin, CURDATE()) as dias_restantes
        FROM contratos c
        INNER JOIN empleados e ON c.empleado_id = e.id
        INNER JOIN tipos_contrato tc ON c.tipo_contrato_id = tc.id
        LEFT JOIN categorias cat ON e.categoria_id = cat.id
        LEFT JOIN cargos car ON e.cargo_id = car.id
        $where_clause
        ORDER BY c.fecha_fin ASC, e.apellidos, e.nombres
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contratos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error al cargar contratos: " . $e->getMessage();
    $contratos = [];
}

// Obtener empleados para filtro
try {
    $stmt = $pdo->prepare("
        SELECT id, nombres, apellidos, cedula
        FROM empleados
        WHERE activo = 1
        ORDER BY apellidos, nombres
    ");
    $stmt->execute();
    $empleados = $stmt->fetchAll();
} catch (PDOException $e) {
    $empleados = [];
}

// Obtener tipos de contrato para filtro
try {
    $stmt = $pdo->prepare("SELECT id, nombre FROM tipos_contrato ORDER BY nombre");
    $stmt->execute();
    $tipos_contrato = $stmt->fetchAll();
} catch (PDOException $e) {
    $tipos_contrato = [];
}

// Calcular estadísticas
$total_contratos = count($contratos);
$contratos_activos = count(array_filter($contratos, fn($c) => $c['estado_contrato'] === 'activo'));
$contratos_vencidos = count(array_filter($contratos, fn($c) => $c['estado_contrato'] === 'vencido'));
$contratos_proximo_vencer = count(array_filter($contratos, fn($c) => $c['estado_contrato'] === 'proximo_vencer'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Contratos - SISRH</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        
        .stat-card.danger {
            border-left-color: #dc3545;
        }
        
        .stat-card.success {
            border-left-color: #28a745;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .filters-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .filters-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            color: #333;
            margin: 0;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem;
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
        
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-actions {
                justify-content: stretch;
            }
            
            .filters-actions .btn {
                flex: 1;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Gestionar Contratos</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="nuevo.php">➕ Nuevo Contrato</a>
            <a href="../empleados/gestionar.php">👥 Empleados</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_contratos; ?></div>
                <div class="stat-label">Total Contratos</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?php echo $contratos_activos; ?></div>
                <div class="stat-label">Contratos Activos</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $contratos_proximo_vencer; ?></div>
                <div class="stat-label">Próximos a Vencer</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-number"><?php echo $contratos_vencidos; ?></div>
                <div class="stat-label">Contratos Vencidos</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filters-card">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group">
                        <label for="buscar">Buscar Empleado:</label>
                        <input type="text" id="buscar" name="buscar" class="form-control" 
                               placeholder="Nombre, apellido o cédula" 
                               value="<?php echo htmlspecialchars($buscar); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="empleado">Empleado:</label>
                        <select id="empleado" name="empleado" class="form-control">
                            <option value="">Todos los empleados</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                        <?php echo $filtro_empleado == $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['apellidos'] . ', ' . $emp['nombres'] . ' (' . $emp['cedula'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo">Tipo de Contrato:</label>
                        <select id="tipo" name="tipo" class="form-control">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_contrato as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>" 
                                        <?php echo $filtro_tipo == $tipo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado" class="form-control">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activos</option>
                            <option value="proximo_vencer" <?php echo $filtro_estado === 'proximo_vencer' ? 'selected' : ''; ?>>Próximos a vencer</option>
                            <option value="vencido" <?php echo $filtro_estado === 'vencido' ? 'selected' : ''; ?>>Vencidos</option>
                        </select>
                    </div>
                </div>
                
                <div class="filters-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    <a href="gestionar.php" class="btn btn-warning">🔄 Limpiar</a>
                </div>
            </form>
        </div>
        
        <!-- Tabla de Contratos -->
        <div class="table-card">
            <div class="table-header">
                <h3>📄 Lista de Contratos (<?php echo count($contratos); ?>)</h3>
                <a href="nuevo.php" class="btn btn-success">➕ Nuevo Contrato</a>
            </div>
            
            <?php if (empty($contratos)): ?>
                <div class="empty-state">
                    <div class="icon">📄</div>
                    <h3>No hay contratos registrados</h3>
                    <p>No se encontraron contratos con los filtros aplicados</p>
                    <a href="nuevo.php" class="btn btn-primary">➕ Crear Primer Contrato</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Tipo de Contrato</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>Días Restantes</th>
                                <th class="text-right">Salario</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contratos as $contrato): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($contrato['apellidos'] . ', ' . $contrato['nombres']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($contrato['cedula']); ?></small>
                                        <?php if ($contrato['categoria_nombre']): ?>
                                            <br><small><?php echo htmlspecialchars($contrato['categoria_nombre']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($contrato['tipo_contrato_nombre']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($contrato['fecha_inicio'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($contrato['fecha_fin'])); ?></td>
                                    <td>
                                        <?php if ($contrato['dias_restantes'] < 0): ?>
                                            <span class="badge badge-danger">Vencido hace <?php echo abs($contrato['dias_restantes']); ?> días</span>
                                        <?php elseif ($contrato['dias_restantes'] <= 30): ?>
                                            <span class="badge badge-warning"><?php echo $contrato['dias_restantes']; ?> días</span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?php echo $contrato['dias_restantes']; ?> días</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">₲ <?php echo number_format($contrato['salario'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <?php
                                        $badge_class = '';
                                        $estado_texto = '';
                                        
                                        switch ($contrato['estado_contrato']) {
                                            case 'activo':
                                                $badge_class = 'badge-success';
                                                $estado_texto = 'Activo';
                                                break;
                                            case 'proximo_vencer':
                                                $badge_class = 'badge-warning';
                                                $estado_texto = 'Próximo a vencer';
                                                break;
                                            case 'vencido':
                                                $badge_class = 'badge-danger';
                                                $estado_texto = 'Vencido';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $estado_texto; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="actions">
                                            <a href="ver.php?id=<?php echo $contrato['id']; ?>" 
                                               class="btn btn-primary btn-sm" title="Ver detalles">👁️</a>
                                            <a href="editar.php?id=<?php echo $contrato['id']; ?>" 
                                               class="btn btn-warning btn-sm" title="Editar">✏️</a>
                                            <button onclick="confirmarEliminacion(<?php echo $contrato['id']; ?>, '<?php echo htmlspecialchars($contrato['nombres'] . ' ' . $contrato['apellidos'], ENT_QUOTES); ?>')" 
                                                    class="btn btn-danger btn-sm" title="Eliminar">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de confirmación de eliminación -->
    <div id="modalEliminar" class="modal">
        <div class="modal-content">
            <h3>⚠️ Confirmar Eliminación</h3>
            <p>¿Está seguro de que desea eliminar el contrato del empleado <strong id="nombreEmpleado"></strong>?</p>
            <p><small>Esta acción no se puede deshacer.</small></p>
            
            <form id="formEliminar" method="POST">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="contrato_id" id="contratoIdEliminar">
                
                <div class="modal-actions">
                    <button type="button" onclick="cerrarModal()" class="btn btn-warning">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar Contrato</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmarEliminacion(contratoId, nombreEmpleado) {
            document.getElementById('contratoIdEliminar').value = contratoId;
            document.getElementById('nombreEmpleado').textContent = nombreEmpleado;
            document.getElementById('modalEliminar').style.display = 'block';
        }
        
        function cerrarModal() {
            document.getElementById('modalEliminar').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('modalEliminar');
            if (event.target === modal) {
                cerrarModal();
            }
        }
        
        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModal();
            }
        });
    </script>
</body>
</html>