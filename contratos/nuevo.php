<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh']);

$usuario = obtenerUsuarioActual();
$mensaje = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empleado_id = $_POST['empleado_id'] ?? '';
    $tipo_contrato_id = $_POST['tipo_contrato_id'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $salario = $_POST['salario'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Validaciones
    if (empty($empleado_id)) {
        $error = "Debe seleccionar un empleado";
    } elseif (empty($tipo_contrato_id)) {
        $error = "Debe seleccionar un tipo de contrato";
    } elseif (empty($fecha_inicio)) {
        $error = "La fecha de inicio es obligatoria";
    } elseif (empty($fecha_fin)) {
        $error = "La fecha de fin es obligatoria";
    } elseif (empty($salario) || !is_numeric($salario) || $salario <= 0) {
        $error = "El salario debe ser un número mayor a 0";
    } elseif (strtotime($fecha_fin) <= strtotime($fecha_inicio)) {
        $error = "La fecha de fin debe ser posterior a la fecha de inicio";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verificar si el empleado ya tiene un contrato activo en el período
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total
                FROM contratos
                WHERE empleado_id = ?
                AND (
                    (fecha_inicio <= ? AND fecha_fin >= ?) OR
                    (fecha_inicio <= ? AND fecha_fin >= ?) OR
                    (fecha_inicio >= ? AND fecha_fin <= ?)
                )
            ");
            $stmt->execute([
                $empleado_id,
                $fecha_inicio, $fecha_inicio,
                $fecha_fin, $fecha_fin,
                $fecha_inicio, $fecha_fin
            ]);
            $contrato_existente = $stmt->fetch();
            
            if ($contrato_existente['total'] > 0) {
                throw new Exception("El empleado ya tiene un contrato activo en el período seleccionado");
            }
            
            // Obtener datos del empleado
            $stmt = $pdo->prepare("
                SELECT nombres, apellidos, salario_base
                FROM empleados
                WHERE id = ? AND activo = 1
            ");
            $stmt->execute([$empleado_id]);
            $empleado = $stmt->fetch();
            
            if (!$empleado) {
                throw new Exception("Empleado no encontrado o inactivo");
            }
            
            // Obtener nombre del tipo de contrato
            $stmt = $pdo->prepare("SELECT nombre FROM tipos_contrato WHERE id = ?");
            $stmt->execute([$tipo_contrato_id]);
            $tipo_contrato = $stmt->fetch();
            
            // Insertar contrato
            $stmt = $pdo->prepare("
                INSERT INTO contratos (
                    empleado_id, tipo_contrato_id, fecha_inicio, fecha_fin, 
                    salario, observaciones, fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $empleado_id, $tipo_contrato_id, $fecha_inicio, 
                $fecha_fin, $salario, $observaciones
            ]);
            
            $contrato_id = $pdo->lastInsertId();
            
            // Actualizar salario base del empleado si es diferente
            if ($empleado['salario_base'] != $salario) {
                $stmt = $pdo->prepare("
                    UPDATE empleados 
                    SET salario_base = ?, fecha_modificacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$salario, $empleado_id]);
                
                // Registrar cambio de salario en historial
                $descripcion_salario = "Salario actualizado de ₲ " . number_format($empleado['salario_base'], 0, ',', '.') . 
                                     " a ₲ " . number_format($salario, 0, ',', '.') . " por nuevo contrato";
                
                $stmt = $pdo->prepare("
                    INSERT INTO historial_laboral (empleado_id, accion, descripcion, usuario_id, fecha)
                    VALUES (?, 'cambio_salario', ?, ?, NOW())
                ");
                $stmt->execute([$empleado_id, $descripcion_salario, $usuario['id']]);
            }
            
            // Registrar en historial laboral
            $descripcion = "Contrato creado: {$tipo_contrato['nombre']} del " . 
                          date('d/m/Y', strtotime($fecha_inicio)) . " al " . 
                          date('d/m/Y', strtotime($fecha_fin)) . 
                          " con salario de ₲ " . number_format($salario, 0, ',', '.');
            
            $stmt = $pdo->prepare("
                INSERT INTO historial_laboral (empleado_id, accion, descripcion, usuario_id, fecha)
                VALUES (?, 'creacion_contrato', ?, ?, NOW())
            ");
            $stmt->execute([$empleado_id, $descripcion, $usuario['id']]);
            
            // Registrar en logs
            registrarActividad($usuario['id'], 'creacion_contrato', 
                "Contrato creado para empleado: {$empleado['nombres']} {$empleado['apellidos']}");
            
            $pdo->commit();
            $mensaje = "Contrato creado exitosamente";
            
            // Limpiar formulario
            $empleado_id = '';
            $tipo_contrato_id = '';
            $fecha_inicio = '';
            $fecha_fin = '';
            $salario = '';
            $observaciones = '';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al crear contrato: " . $e->getMessage();
        }
    }
}

// Obtener empleados activos
try {
    $stmt = $pdo->prepare("
        SELECT 
            e.id, e.nombres, e.apellidos, e.cedula, e.salario_base,
            c.nombre as categoria_nombre,
            car.nombre as cargo_nombre
        FROM empleados e
        LEFT JOIN categorias c ON e.categoria_id = c.id
        LEFT JOIN cargos car ON e.cargo_id = car.id
        WHERE e.activo = 1
        ORDER BY e.apellidos, e.nombres
    ");
    $stmt->execute();
    $empleados = $stmt->fetchAll();
} catch (PDOException $e) {
    $empleados = [];
}

// Obtener tipos de contrato
try {
    $stmt = $pdo->prepare("SELECT id, nombre, descripcion FROM tipos_contrato ORDER BY nombre");
    $stmt->execute();
    $tipos_contrato = $stmt->fetchAll();
} catch (PDOException $e) {
    $tipos_contrato = [];
}

// Obtener configuración del salario mínimo
try {
    $stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'salario_minimo'");
    $stmt->execute();
    $config = $stmt->fetch();
    $salario_minimo = $config ? $config['valor'] : 2550000;
} catch (PDOException $e) {
    $salario_minimo = 2550000;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Contrato - SISRH</title>
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
            max-width: 800px;
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
        
        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .form-header {
            padding: 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .form-header h2 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-body {
            padding: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-group label .required {
            color: #dc3545;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .form-control:invalid {
            border-color: #dc3545;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .employee-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 0.5rem;
            display: none;
        }
        
        .employee-info.show {
            display: block;
        }
        
        .employee-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .employee-detail:last-child {
            margin-bottom: 0;
        }
        
        .employee-detail .label {
            font-weight: 600;
            color: #333;
        }
        
        .employee-detail .value {
            color: #666;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e5e9;
        }
        
        .salary-suggestion {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            padding: 0.75rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        
        .salary-suggestion .title {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 0.25rem;
        }
        
        .contract-preview {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 5px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .contract-preview h4 {
            color: #333;
            margin-bottom: 0.75rem;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }
        
        .preview-item {
            display: flex;
            flex-direction: column;
        }
        
        .preview-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .preview-value {
            font-size: 0.9rem;
            color: #333;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Nuevo Contrato</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="gestionar.php">📄 Gestionar Contratos</a>
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
        
        <div class="form-card">
            <div class="form-header">
                <h2>📄 Crear Nuevo Contrato</h2>
            </div>
            
            <div class="form-body">
                <form method="POST" action="" id="contratoForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="empleado_id">Empleado <span class="required">*</span></label>
                            <select id="empleado_id" name="empleado_id" class="form-control" required onchange="mostrarInfoEmpleado()">
                                <option value="">Seleccionar empleado...</option>
                                <?php foreach ($empleados as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" 
                                            data-nombres="<?php echo htmlspecialchars($emp['nombres']); ?>"
                                            data-apellidos="<?php echo htmlspecialchars($emp['apellidos']); ?>"
                                            data-cedula="<?php echo htmlspecialchars($emp['cedula']); ?>"
                                            data-salario="<?php echo $emp['salario_base']; ?>"
                                            data-categoria="<?php echo htmlspecialchars($emp['categoria_nombre']); ?>"
                                            data-cargo="<?php echo htmlspecialchars($emp['cargo_nombre']); ?>"
                                            <?php echo $empleado_id == $emp['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['apellidos'] . ', ' . $emp['nombres'] . ' (' . $emp['cedula'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="empleadoInfo" class="employee-info"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_contrato_id">Tipo de Contrato <span class="required">*</span></label>
                            <select id="tipo_contrato_id" name="tipo_contrato_id" class="form-control" required>
                                <option value="">Seleccionar tipo...</option>
                                <?php foreach ($tipos_contrato as $tipo): ?>
                                    <option value="<?php echo $tipo['id']; ?>" 
                                            <?php echo $tipo_contrato_id == $tipo['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha de Inicio <span class="required">*</span></label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" 
                                   value="<?php echo htmlspecialchars($fecha_inicio); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required onchange="calcularFechaFin()">
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_fin">Fecha de Fin <span class="required">*</span></label>
                            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" 
                                   value="<?php echo htmlspecialchars($fecha_fin); ?>" required onchange="actualizarPreview()">
                            <div class="form-text">La fecha de fin debe ser posterior a la fecha de inicio</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="salario">Salario <span class="required">*</span></label>
                            <input type="number" id="salario" name="salario" class="form-control" 
                                   value="<?php echo htmlspecialchars($salario); ?>" 
                                   min="<?php echo $salario_minimo; ?>" step="1000" required onchange="actualizarPreview()">
                            <div class="form-text">Salario mínimo: ₲ <?php echo number_format($salario_minimo, 0, ',', '.'); ?></div>
                            <div id="salarioSugerencia" class="salary-suggestion" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="observaciones">Observaciones</label>
                            <textarea id="observaciones" name="observaciones" class="form-control" 
                                      rows="3" placeholder="Observaciones adicionales sobre el contrato..."><?php echo htmlspecialchars($observaciones); ?></textarea>
                        </div>
                    </div>
                    
                    <div id="contratoPreview" class="contract-preview" style="display: none;">
                        <h4>📋 Vista Previa del Contrato</h4>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <span class="preview-label">Empleado</span>
                                <span class="preview-value" id="previewEmpleado">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Tipo de Contrato</span>
                                <span class="preview-value" id="previewTipo">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Período</span>
                                <span class="preview-value" id="previewPeriodo">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Duración</span>
                                <span class="preview-value" id="previewDuracion">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Salario</span>
                                <span class="preview-value" id="previewSalario">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="gestionar.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">💾 Crear Contrato</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function mostrarInfoEmpleado() {
            const select = document.getElementById('empleado_id');
            const infoDiv = document.getElementById('empleadoInfo');
            const salarioInput = document.getElementById('salario');
            const sugerenciaDiv = document.getElementById('salarioSugerencia');
            
            if (select.value) {
                const option = select.selectedOptions[0];
                const nombres = option.dataset.nombres;
                const apellidos = option.dataset.apellidos;
                const cedula = option.dataset.cedula;
                const salario = option.dataset.salario;
                const categoria = option.dataset.categoria;
                const cargo = option.dataset.cargo;
                
                infoDiv.innerHTML = `
                    <div class="employee-detail">
                        <span class="label">Nombre Completo:</span>
                        <span class="value">${apellidos}, ${nombres}</span>
                    </div>
                    <div class="employee-detail">
                        <span class="label">Cédula:</span>
                        <span class="value">${cedula}</span>
                    </div>
                    <div class="employee-detail">
                        <span class="label">Categoría:</span>
                        <span class="value">${categoria || 'No asignada'}</span>
                    </div>
                    <div class="employee-detail">
                        <span class="label">Cargo:</span>
                        <span class="value">${cargo || 'No asignado'}</span>
                    </div>
                    <div class="employee-detail">
                        <span class="label">Salario Actual:</span>
                        <span class="value">₲ ${parseInt(salario).toLocaleString('es-PY')}</span>
                    </div>
                `;
                infoDiv.classList.add('show');
                
                // Sugerir salario actual
                if (!salarioInput.value) {
                    salarioInput.value = salario;
                }
                
                // Mostrar sugerencia de salario
                sugerenciaDiv.innerHTML = `
                    <div class="title">💡 Sugerencia</div>
                    <div>Salario actual del empleado: ₲ ${parseInt(salario).toLocaleString('es-PY')}</div>
                `;
                sugerenciaDiv.style.display = 'block';
                
            } else {
                infoDiv.classList.remove('show');
                sugerenciaDiv.style.display = 'none';
            }
            
            actualizarPreview();
        }
        
        function calcularFechaFin() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin');
            
            if (fechaInicio) {
                // Sugerir fecha fin a 1 año
                const inicio = new Date(fechaInicio);
                inicio.setFullYear(inicio.getFullYear() + 1);
                
                if (!fechaFin.value) {
                    fechaFin.value = inicio.toISOString().split('T')[0];
                }
                
                // Establecer fecha mínima
                const minFin = new Date(fechaInicio);
                minFin.setDate(minFin.getDate() + 1);
                fechaFin.min = minFin.toISOString().split('T')[0];
            }
            
            actualizarPreview();
        }
        
        function actualizarPreview() {
            const empleadoSelect = document.getElementById('empleado_id');
            const tipoSelect = document.getElementById('tipo_contrato_id');
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const salario = document.getElementById('salario').value;
            const preview = document.getElementById('contratoPreview');
            
            if (empleadoSelect.value && tipoSelect.value && fechaInicio && fechaFin && salario) {
                const empleadoOption = empleadoSelect.selectedOptions[0];
                const tipoOption = tipoSelect.selectedOptions[0];
                
                const empleadoNombre = `${empleadoOption.dataset.apellidos}, ${empleadoOption.dataset.nombres}`;
                const tipoNombre = tipoOption.text;
                
                // Calcular duración
                const inicio = new Date(fechaInicio);
                const fin = new Date(fechaFin);
                const diffTime = Math.abs(fin - inicio);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                const diffMonths = Math.round(diffDays / 30.44);
                const diffYears = Math.round(diffDays / 365.25);
                
                let duracion = '';
                if (diffYears >= 1) {
                    duracion = `${diffYears} año${diffYears > 1 ? 's' : ''}`;
                    if (diffMonths % 12 > 0) {
                        duracion += ` y ${diffMonths % 12} mes${diffMonths % 12 > 1 ? 'es' : ''}`;
                    }
                } else {
                    duracion = `${diffMonths} mes${diffMonths > 1 ? 'es' : ''}`;
                }
                
                document.getElementById('previewEmpleado').textContent = empleadoNombre;
                document.getElementById('previewTipo').textContent = tipoNombre;
                document.getElementById('previewPeriodo').textContent = 
                    `${new Date(fechaInicio).toLocaleDateString('es-PY')} - ${new Date(fechaFin).toLocaleDateString('es-PY')}`;
                document.getElementById('previewDuracion').textContent = duracion;
                document.getElementById('previewSalario').textContent = 
                    `₲ ${parseInt(salario).toLocaleString('es-PY')}`;
                
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Validación en tiempo real
        document.getElementById('contratoForm').addEventListener('input', function(e) {
            if (e.target.type === 'date' || e.target.name === 'salario') {
                actualizarPreview();
            }
        });
        
        // Validación de fechas
        document.getElementById('fecha_fin').addEventListener('change', function() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = this.value;
            
            if (fechaInicio && fechaFin && new Date(fechaFin) <= new Date(fechaInicio)) {
                alert('La fecha de fin debe ser posterior a la fecha de inicio');
                this.value = '';
                actualizarPreview();
            }
        });
        
        // Formatear salario mientras se escribe
        document.getElementById('salario').addEventListener('input', function() {
            const value = this.value.replace(/\D/g, '');
            if (value) {
                // Opcional: formatear con separadores de miles mientras se escribe
                // this.value = parseInt(value).toLocaleString('es-PY');
            }
        });
    </script>
</body>
</html>