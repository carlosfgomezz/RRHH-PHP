<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh']);

$usuario = obtenerUsuarioActual();
$mensaje = '';
$error = '';

// Obtener categorías y cargos
try {
    $query_categorias = "SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre";
    $stmt_categorias = $pdo->prepare($query_categorias);
    $stmt_categorias->execute();
    $categorias = $stmt_categorias->fetchAll();
    
    $query_cargos = "SELECT * FROM cargos WHERE activo = 1 ORDER BY nombre";
    $stmt_cargos = $pdo->prepare($query_cargos);
    $stmt_cargos->execute();
    $cargos = $stmt_cargos->fetchAll();
    
} catch (PDOException $e) {
    $categorias = [];
    $cargos = [];
    $error = "Error al cargar datos: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cedula = trim($_POST['cedula']);
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $direccion = trim($_POST['direccion']);
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $categoria_id = $_POST['categoria_id'];
    $cargo_id = $_POST['cargo_id'];
    $salario_base = str_replace(['.', ','], '', $_POST['salario_base']);
    
    if (!empty($cedula) && !empty($nombres) && !empty($apellidos) && !empty($fecha_nacimiento) && 
        !empty($fecha_ingreso) && !empty($categoria_id) && !empty($cargo_id) && !empty($salario_base)) {
        
        try {
            // Verificar si la cédula ya existe
            $query_check = "SELECT id FROM empleados WHERE cedula = ?";
            $stmt_check = $pdo->prepare($query_check);
            $stmt_check->execute([$cedula]);
            
            if ($stmt_check->fetch()) {
                $error = "Ya existe un empleado con esa cédula";
            } else {
                // Insertar nuevo empleado
                $query = "INSERT INTO empleados (cedula, nombres, apellidos, fecha_nacimiento, telefono, email,
                         direccion, fecha_ingreso, categoria_id, cargo_id, salario_base)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    $cedula, $nombres, $apellidos, $fecha_nacimiento, $telefono, $email,
                    $direccion, $fecha_ingreso, $categoria_id, $cargo_id, $salario_base
                ]);
                
                $empleado_id = $pdo->lastInsertId();
                
                // Registrar en historial laboral
                $query_historial = "INSERT INTO historial_laboral (empleado_id, accion, fecha_accion,
                                   cargo_nuevo_id, salario_nuevo, observaciones, usuario_id)
                                   VALUES (?, 'ingreso', ?, ?, ?, ?, ?)";
                
                $stmt_historial = $pdo->prepare($query_historial);
                $stmt_historial->execute([
                    $empleado_id, $fecha_ingreso, $cargo_id, $salario_base,
                    "Ingreso inicial al sistema", $usuario['id']
                ]);
                
                registrarActividad('Crear Empleado', "Empleado creado: $nombres $apellidos (Cédula: $cedula)");
                
                $mensaje = "Empleado creado exitosamente";
                
                // Limpiar formulario
                $_POST = [];
            }
        } catch (PDOException $e) {
            $error = "Error al crear empleado: " . $e->getMessage();
        }
    } else {
        $error = "Por favor complete todos los campos obligatorios";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Empleado - SISRH</title>
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
        
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-header {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .form-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: #666;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
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
        
        .form-group label .required {
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100());
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
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
        
        .salary-input {
            position: relative;
        }
        
        .salary-input::before {
            content: '₲';
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-weight: bold;
        }
        
        .salary-input input {
            padding-left: 2rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Nuevo Empleado</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="gestionar.php">📋 Ver Empleados</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h2>Registrar Nuevo Empleado</h2>
                <p>Complete la información del empleado</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="cedula">Cédula <span class="required">*</span></label>
                        <input type="text" id="cedula" name="cedula" required 
                               value="<?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="nombres">Nombres <span class="required">*</span></label>
                        <input type="text" id="nombres" name="nombres" required 
                               value="<?php echo htmlspecialchars($_POST['nombres'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="apellidos">Apellidos <span class="required">*</span></label>
                        <input type="text" id="apellidos" name="apellidos" required 
                               value="<?php echo htmlspecialchars($_POST['apellidos'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de Nacimiento <span class="required">*</span></label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required 
                               value="<?php echo htmlspecialchars($_POST['fecha_nacimiento'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" 
                               value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="direccion">Dirección</label>
                        <textarea id="direccion" name="direccion"><?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_ingreso">Fecha de Ingreso <span class="required">*</span></label>
                        <input type="date" id="fecha_ingreso" name="fecha_ingreso" required 
                               value="<?php echo htmlspecialchars($_POST['fecha_ingreso'] ?? date('Y-m-d')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria_id">Categoría <span class="required">*</span></label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Seleccione una categoría</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                        <?php echo (($_POST['categoria_id'] ?? '') == $categoria['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cargo_id">Cargo <span class="required">*</span></label>
                        <select id="cargo_id" name="cargo_id" required>
                            <option value="">Seleccione un cargo</option>
                            <?php foreach ($cargos as $cargo): ?>
                                <option value="<?php echo $cargo['id']; ?>" 
                                        <?php echo (($_POST['cargo_id'] ?? '') == $cargo['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cargo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="salario_base">Salario Base <span class="required">*</span></label>
                        <div class="salary-input">
                            <input type="text" id="salario_base" name="salario_base" required 
                                   placeholder="2.550.000" 
                                   value="<?php echo htmlspecialchars($_POST['salario_base'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Guardar Empleado</button>
                    <a href="gestionar.php" class="btn btn-secondary">❌ Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Formatear salario mientras se escribe
        document.getElementById('salario_base').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                value = parseInt(value).toLocaleString('es-PY');
                e.target.value = value;
            }
        });
    </script>
</body>
</html>