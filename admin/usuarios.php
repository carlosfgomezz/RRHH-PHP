<?php
require_once '../config/auth.php';
verificarRol(['admin']);

$usuario = obtenerUsuarioActual();
$mensaje = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        if ($accion === 'crear') {
            $username = trim($_POST['nombre_usuario']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $rol = $_POST['rol'];
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            // Validaciones
            if (empty($username) || empty($email) || empty($password) || empty($rol)) {
                throw new Exception("Todos los campos son obligatorios");
            }
            
            if (strlen($password) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El email no es válido");
            }
            
            // Verificar si el usuario ya existe
            $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
            $stmt_check->execute([$username, $email]);
            if ($stmt_check->fetch()) {
                throw new Exception("Ya existe un usuario con ese nombre de usuario o email");
            }
            
            // Crear usuario
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (username, email, password, rol, activo, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $email, $password_hash, $rol, $activo]);
            
            registrarActividad($pdo, $usuario['id'], 'crear_usuario', "Usuario creado: $username");
            $mensaje = "Usuario creado exitosamente";
            
        } elseif ($accion === 'editar') {
            $id = $_POST['id'];
            $username = trim($_POST['nombre_usuario']);
            $email = trim($_POST['email']);
            $rol = $_POST['rol'];
            $activo = isset($_POST['activo']) ? 1 : 0;
            $nueva_password = $_POST['nueva_password'] ?? '';
            
            // Validaciones
            if (empty($username) || empty($email) || empty($rol)) {
                throw new Exception("Todos los campos son obligatorios");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El email no es válido");
            }
            
            // Verificar si el usuario existe y no es duplicado
            $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE (username = ? OR email = ?) AND id != ?");
            $stmt_check->execute([$username, $email, $id]);
            if ($stmt_check->fetch()) {
                throw new Exception("Ya existe otro usuario con ese nombre de usuario o email");
            }
            
            // Actualizar usuario
            if (!empty($nueva_password)) {
                if (strlen($nueva_password) < 6) {
                    throw new Exception("La nueva contraseña debe tener al menos 6 caracteres");
                }
                $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET username = ?, email = ?, password = ?, rol = ?, activo = ?, fecha_modificacion = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$username, $email, $password_hash, $rol, $activo, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET username = ?, email = ?, rol = ?, activo = ?, fecha_modificacion = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$username, $email, $rol, $activo, $id]);
            }
            
            registrarActividad($pdo, $usuario['id'], 'editar_usuario', "Usuario editado: $username");
            $mensaje = "Usuario actualizado exitosamente";
            
        } elseif ($accion === 'eliminar') {
            $id = $_POST['id'];
            
            // No permitir eliminar el propio usuario
            if ($id == $usuario['id']) {
                throw new Exception("No puedes eliminar tu propio usuario");
            }
            
            // Obtener información del usuario antes de eliminar
            $stmt_info = $pdo->prepare("SELECT username FROM usuarios WHERE id = ?");
            $stmt_info->execute([$id]);
            $usuario_info = $stmt_info->fetch();
            
            if (!$usuario_info) {
                throw new Exception("Usuario no encontrado");
            }
            
            // Eliminar usuario
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            
            registrarActividad($pdo, $usuario['id'], 'eliminar_usuario', "Usuario eliminado: " . $usuario_info['username']);
            $mensaje = "Usuario eliminado exitosamente";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
}

// Obtener lista de usuarios
try {
    $filtro_rol = $_GET['rol'] ?? '';
    $filtro_estado = $_GET['estado'] ?? '';
    $buscar = $_GET['buscar'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if ($filtro_rol) {
        $where_conditions[] = "rol = ?";
        $params[] = $filtro_rol;
    }
    
    if ($filtro_estado !== '') {
        $where_conditions[] = "activo = ?";
        $params[] = $filtro_estado;
    }
    
    if ($buscar) {
        $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $query = "
        SELECT 
            id,
            username AS nombre_usuario,
            email,
            rol,
            activo,
            fecha_creacion,
            ultimo_acceso
        FROM usuarios 
        $where_clause
        ORDER BY fecha_creacion DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $usuarios = [];
    $error = "Error al cargar usuarios: " . $e->getMessage();
}

$roles = [
    'admin' => 'Administrador',
    'rrhh' => 'Recursos Humanos',
    'contabilidad' => 'Contabilidad',
    'empleado' => 'Empleado'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - SISRH</title>
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
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            color: #333;
            margin: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: end;
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
        
        .form-group input,
        .form-group select {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
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
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
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
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        .badge-primary {
            background: #007bff;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .badge-info {
            background: #17a2b8;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e1e5e9;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Gestión de Usuarios</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="configuracion.php">⚙️ Configuración</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Usuarios del Sistema</h2>
                <button onclick="abrirModal('modalCrear')" class="btn btn-success">➕ Nuevo Usuario</button>
            </div>
            
            <div class="card-body">
                <form method="GET" class="filters">
                    <div class="form-group">
                        <label for="buscar">Buscar:</label>
                        <input type="text" id="buscar" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Usuario o email...">
                    </div>
                    
                    <div class="form-group">
                        <label for="rol">Rol:</label>
                        <select id="rol" name="rol">
                            <option value="">Todos los roles</option>
                            <?php foreach ($roles as $valor => $nombre): ?>
                                <option value="<?php echo $valor; ?>" <?php echo ($filtro_rol === $valor) ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="1" <?php echo ($filtro_estado === '1') ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo ($filtro_estado === '0') ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    </div>
                </form>
                
                <?php if (empty($usuarios)): ?>
                    <div class="empty-state">
                        <div class="icon">👥</div>
                        <h3>No hay usuarios</h3>
                        <p>No se encontraron usuarios con los filtros seleccionados.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th>Último Acceso</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usr): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($usr['nombre_usuario']); ?></strong>
                                        <?php if ($usr['id'] == $usuario['id']): ?>
                                            <span class="badge badge-info">TÚ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($usr['email']); ?></td>
                                    <td>
                                        <?php 
                                        $rol_class = [
                                            'admin' => 'badge-danger',
                                            'rrhh' => 'badge-primary',
                                            'contabilidad' => 'badge-warning',
                                            'empleado' => 'badge-success'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $rol_class[$usr['rol']] ?? 'badge-primary'; ?>">
                                            <?php echo $roles[$usr['rol']] ?? $usr['rol']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $usr['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $usr['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($usr['fecha_creacion'])); ?></td>
                                    <td>
                                        <?php if ($usr['ultimo_acceso']): ?>
                                            <?php echo date('d/m/Y H:i', strtotime($usr['ultimo_acceso'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Nunca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="actions">
                                            <button onclick="editarUsuario(<?php echo htmlspecialchars(json_encode($usr)); ?>)" 
                                                    class="btn btn-warning btn-sm">✏️ Editar</button>
                                            <?php if ($usr['id'] != $usuario['id']): ?>
                                                <button onclick="confirmarEliminar(<?php echo $usr['id']; ?>, '<?php echo htmlspecialchars($usr['nombre_usuario']); ?>')" 
                                                        class="btn btn-danger btn-sm">🗑️ Eliminar</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Crear Usuario -->
    <div id="modalCrear" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Crear Nuevo Usuario</h3>
                <button type="button" class="close" onclick="cerrarModal('modalCrear')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="form-group">
                        <label for="crear_nombre_usuario">Nombre de Usuario:</label>
                        <input type="text" id="crear_nombre_usuario" name="nombre_usuario" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="crear_email">Email:</label>
                        <input type="email" id="crear_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="crear_password">Contraseña:</label>
                        <input type="password" id="crear_password" name="password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="crear_rol">Rol:</label>
                        <select id="crear_rol" name="rol" required>
                            <option value="">Seleccionar rol...</option>
                            <?php foreach ($roles as $valor => $nombre): ?>
                                <option value="<?php echo $valor; ?>"><?php echo $nombre; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="crear_activo" name="activo" checked>
                        <label for="crear_activo">Usuario activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal('modalCrear')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Editar Usuario -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Usuario</h3>
                <button type="button" class="close" onclick="cerrarModal('modalEditar')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" id="editar_id" name="id">
                    
                    <div class="form-group">
                        <label for="editar_nombre_usuario">Nombre de Usuario:</label>
                        <input type="text" id="editar_nombre_usuario" name="nombre_usuario" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_email">Email:</label>
                        <input type="email" id="editar_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_nueva_password">Nueva Contraseña (opcional):</label>
                        <input type="password" id="editar_nueva_password" name="nueva_password" minlength="6">
                        <small>Dejar en blanco para mantener la contraseña actual</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_rol">Rol:</label>
                        <select id="editar_rol" name="rol" required>
                            <?php foreach ($roles as $valor => $nombre): ?>
                                <option value="<?php echo $valor; ?>"><?php echo $nombre; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="editar_activo" name="activo">
                        <label for="editar_activo">Usuario activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal('modalEditar')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Actualizar Usuario</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Confirmar Eliminación -->
    <div id="modalEliminar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmar Eliminación</h3>
                <button type="button" class="close" onclick="cerrarModal('modalEliminar')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" id="eliminar_id" name="id">
                    
                    <p>¿Estás seguro de que deseas eliminar el usuario <strong id="eliminar_nombre"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal('modalEliminar')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar Usuario</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editarUsuario(usuario) {
            document.getElementById('editar_id').value = usuario.id;
            document.getElementById('editar_nombre_usuario').value = usuario.nombre_usuario;
            document.getElementById('editar_email').value = usuario.email;
            document.getElementById('editar_rol').value = usuario.rol;
            document.getElementById('editar_activo').checked = usuario.activo == 1;
            document.getElementById('editar_nueva_password').value = '';
            
            abrirModal('modalEditar');
        }
        
        function confirmarEliminar(id, nombre) {
            document.getElementById('eliminar_id').value = id;
            document.getElementById('eliminar_nombre').textContent = nombre;
            
            abrirModal('modalEliminar');
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modales = document.querySelectorAll('.modal');
            modales.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>