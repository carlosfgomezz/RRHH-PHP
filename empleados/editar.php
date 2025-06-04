<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh']);

$usuario = obtenerUsuarioActual();
$error = '';
$mensaje = '';

// Obtener ID del empleado
$empleado_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$empleado_id) {
    header('Location: gestionar.php');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombres = trim($_POST['nombres']);
        $apellidos = trim($_POST['apellidos']);
        $cedula = trim($_POST['cedula']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);
        $direccion = trim($_POST['direccion']);
        $categoria_id = $_POST['categoria_id'];
        $cargo_id = $_POST['cargo_id'];
        $fecha_ingreso = $_POST['fecha_ingreso'];
        $salario_base = floatval($_POST['salario_base']);
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Validaciones
        if (empty($nombres) || empty($apellidos) || empty($cedula)) {
            throw new Exception("Los campos nombres, apellidos y cédula son obligatorios");
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El email no es válido");
        }
        
        if ($salario_base <= 0) {
            throw new Exception("El salario base debe ser mayor a 0");
        }
        
        if (empty($categoria_id) || empty($cargo_id)) {
            throw new Exception("Debe seleccionar una categoría y un cargo");
        }
        
        // Verificar si la cédula ya existe (excluyendo el empleado actual)
        $stmt_check = $pdo->prepare("SELECT id FROM empleados WHERE cedula = ? AND id != ?");
        $stmt_check->execute([$cedula, $empleado_id]);
        if ($stmt_check->fetch()) {
            throw new Exception("Ya existe un empleado con esa cédula");
        }
        
        // Verificar si el email ya existe (excluyendo el empleado actual)
        if (!empty($email)) {
            $stmt_check_email = $pdo->prepare("SELECT id FROM empleados WHERE email = ? AND id != ?");
            $stmt_check_email->execute([$email, $empleado_id]);
            if ($stmt_check_email->fetch()) {
                throw new Exception("Ya existe un empleado con ese email");
            }
        }
        
        $pdo->beginTransaction();
        
        // Actualizar empleado
        $stmt = $pdo->prepare("
            UPDATE empleados SET 
                nombres = ?, apellidos = ?, cedula = ?, fecha_nacimiento = ?, 
                telefono = ?, email = ?, direccion = ?, categoria_id = ?, 
                cargo_id = ?, fecha_ingreso = ?, salario_base = ?, activo = ?,
                fecha_modificacion = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nombres, $apellidos, $cedula, $fecha_nacimiento,
            $telefono, $email, $direccion, $categoria_id,
            $cargo_id, $fecha_ingreso, $salario_base, $activo, $empleado_id
        ]);
        
        // Registrar en historial laboral
        $stmt_historial = $pdo->prepare("
            INSERT INTO historial_laboral (empleado_id, accion, descripcion, fecha, usuario_id)
            VALUES (?, 'modificacion', 'Datos del empleado actualizados', NOW(), ?)
        ");
        $stmt_historial->execute([$empleado_id, $usuario['id']]);
        
        $pdo->commit();
        
        registrarActividad($pdo, $usuario['id'], 'editar_empleado', "Empleado editado: $nombres $apellidos");
        $mensaje = "Empleado actualizado exitosamente";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error de base de datos: " . $e->getMessage();
    }
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

// Obtener categorías y cargos
try {
    $stmt_categorias = $pdo->prepare("SELECT id, nombre FROM categorias ORDER BY nombre");
    $stmt_categorias->execute();
    $categorias = $stmt_categorias->fetchAll();
    
    $stmt_cargos = $pdo->prepare("SELECT id, nombre FROM cargos ORDER BY nombre");
    $stmt_cargos->execute();
    $cargos = $stmt_cargos->fetchAll();
    
} catch (PDOException $e) {
    $categorias = [];
    $cargos = [];
    $error = "Error al cargar datos: " . $e->getMessage();
}

// Obtener historial laboral
try {
    $stmt_historial = $pdo->prepare("
        SELECT h.*, u.nombre_usuario
        FROM historial_laboral h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.empleado_id = ?
        ORDER BY h.fecha DESC
        LIMIT 10
    ");
    $stmt_historial->execute([$empleado_id]);
    $historial = $stmt_historial->fetchAll();
    
} catch (PDOException $e) {
    $historial = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empleado - SISRH</title>
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
        
        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
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
        
        .card-header h2 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .employee-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .employee-info h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
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
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Editar Empleado</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="gestionar.php">👥 Gestionar Empleados</a>
            <a href="nuevo.php">➕ Nuevo Empleado</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($empleado): ?>
            <div class="grid">
                <div class="card">
                    <div class="card-header">
                        <h2>✏️ Editar Datos del Empleado</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo $empleado['id']; ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="nombres">Nombres <span class="required">*</span>:</label>
                                    <input type="text" id="nombres" name="nombres" 
                                           value="<?php echo htmlspecialchars($empleado['nombres']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="apellidos">Apellidos <span class="required">*</span>:</label>
                                    <input type="text" id="apellidos" name="apellidos" 
                                           value="<?php echo htmlspecialchars($empleado['apellidos']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cedula">Cédula <span class="required">*</span>:</label>
                                    <input type="text" id="cedula" name="cedula" 
                                           value="<?php echo htmlspecialchars($empleado['cedula']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" 
                                           value="<?php echo $empleado['fecha_nacimiento']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="telefono">Teléfono:</label>
                                    <input type="text" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($empleado['telefono']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email:</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($empleado['email']); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="direccion">Dirección:</label>
                                    <textarea id="direccion" name="direccion"><?php echo htmlspecialchars($empleado['direccion']); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="categoria_id">Categoría <span class="required">*</span>:</label>
                                    <select id="categoria_id" name="categoria_id" required>
                                        <option value="">Seleccionar categoría...</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?php echo $categoria['id']; ?>" 
                                                    <?php echo ($empleado['categoria_id'] == $categoria['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cargo_id">Cargo <span class="required">*</span>:</label>
                                    <select id="cargo_id" name="cargo_id" required>
                                        <option value="">Seleccionar cargo...</option>
                                        <?php foreach ($cargos as $cargo): ?>
                                            <option value="<?php echo $cargo['id']; ?>" 
                                                    <?php echo ($empleado['cargo_id'] == $cargo['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cargo['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha_ingreso">Fecha de Ingreso <span class="required">*</span>:</label>
                                    <input type="date" id="fecha_ingreso" name="fecha_ingreso" 
                                           value="<?php echo $empleado['fecha_ingreso']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="salario_base">Salario Base (₲) <span class="required">*</span>:</label>
                                    <input type="number" id="salario_base" name="salario_base" 
                                           value="<?php echo $empleado['salario_base']; ?>" 
                                           min="0" step="1000" required>
                                </div>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="activo" name="activo" 
                                       <?php echo $empleado['activo'] ? 'checked' : ''; ?>>
                                <label for="activo">Empleado activo</label>
                            </div>
                            
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">💾 Actualizar Empleado</button>
                                <a href="gestionar.php" class="btn btn-secondary">❌ Cancelar</a>
                                <a href="ver.php?id=<?php echo $empleado['id']; ?>" class="btn btn-success">👁️ Ver Detalles</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div>
                    <!-- Información Actual -->
                    <div class="card">
                        <div class="card-header">
                            <h2>📋 Información Actual</h2>
                        </div>
                        <div class="card-body">
                            <div class="employee-info">
                                <h3><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['apellidos']); ?></h3>
                                <span class="badge <?php echo $empleado['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $empleado['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </div>
                            
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Cédula:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($empleado['cedula']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Categoría:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($empleado['categoria_nombre']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Cargo:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($empleado['cargo_nombre']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Salario:</span>
                                    <span class="info-value">₲ <?php echo number_format($empleado['salario_base'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Fecha Ingreso:</span>
                                    <span class="info-value"><?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Antigüedad:</span>
                                    <span class="info-value">
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
                    </div>
                    
                    <!-- Historial Laboral -->
                    <div class="card">
                        <div class="card-header">
                            <h2>📝 Historial Laboral</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($historial)): ?>
                                <div class="empty-state">
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
            </div>
        <?php else: ?>
            <div class="alert alert-error">Empleado no encontrado</div>
        <?php endif; ?>
    </div>
</body>
</html>