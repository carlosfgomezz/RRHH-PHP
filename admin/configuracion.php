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
        if ($accion === 'actualizar_config') {
            $configuraciones = [
                'salario_minimo' => floatval($_POST['salario_minimo']),
                'aporte_ips_personal' => floatval($_POST['aporte_ips_personal']),
                'aporte_ips_patronal' => floatval($_POST['aporte_ips_patronal']),
                'nombre_empresa' => trim($_POST['nombre_empresa']),
                'ruc_empresa' => trim($_POST['ruc_empresa']),
                'direccion_empresa' => trim($_POST['direccion_empresa']),
                'telefono_empresa' => trim($_POST['telefono_empresa']),
                'email_empresa' => trim($_POST['email_empresa'])
            ];
            
            // Validaciones
            if ($configuraciones['salario_minimo'] <= 0) {
                throw new Exception("El salario mínimo debe ser mayor a 0");
            }
            
            if ($configuraciones['aporte_ips_personal'] < 0 || $configuraciones['aporte_ips_personal'] > 100) {
                throw new Exception("El aporte IPS personal debe estar entre 0 y 100%");
            }
            
            if ($configuraciones['aporte_ips_patronal'] < 0 || $configuraciones['aporte_ips_patronal'] > 100) {
                throw new Exception("El aporte IPS patronal debe estar entre 0 y 100%");
            }
            
            if (empty($configuraciones['nombre_empresa'])) {
                throw new Exception("El nombre de la empresa es obligatorio");
            }
            
            if (!empty($configuraciones['email_empresa']) && !filter_var($configuraciones['email_empresa'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El email de la empresa no es válido");
            }
            
            $pdo->beginTransaction();
            
            // Actualizar configuraciones
            foreach ($configuraciones as $clave => $valor) {
                $stmt = $pdo->prepare("
                    INSERT INTO configuracion (clave, valor, fecha_modificacion) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor), fecha_modificacion = VALUES(fecha_modificacion)
                ");
                $stmt->execute([$clave, $valor]);
            }
            
            $pdo->commit();
            
            registrarActividad($pdo, $usuario['id'], 'actualizar_configuracion', 'Configuración del sistema actualizada');
            $mensaje = "Configuración actualizada exitosamente";
            
        } elseif ($accion === 'limpiar_logs') {
            $dias = intval($_POST['dias_logs']);
            
            if ($dias <= 0) {
                throw new Exception("El número de días debe ser mayor a 0");
            }
            
            $stmt = $pdo->prepare("DELETE FROM logs WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$dias]);
            $registros_eliminados = $stmt->rowCount();
            
            registrarActividad($pdo, $usuario['id'], 'limpiar_logs', "Logs eliminados: $registros_eliminados registros anteriores a $dias días");
            $mensaje = "Se eliminaron $registros_eliminados registros de logs";
            
        } elseif ($accion === 'crear_backup') {
            $nombre_archivo = 'backup_sisrh_' . date('Y-m-d_H-i-s') . '.sql';
            $ruta_backup = '../backups/' . $nombre_archivo;
            
            // Crear directorio de backups si no existe
            if (!is_dir('../backups')) {
                mkdir('../backups', 0755, true);
            }
            
            // Obtener configuración de base de datos
            $host = DB_HOST;
            $database = DB_NAME;
            $username = DB_USER;
            $password = DB_PASS;
            
            // Comando mysqldump
            $comando = "mysqldump --host=$host --user=$username --password=$password $database > $ruta_backup";
            
            // Ejecutar backup
            $resultado = shell_exec($comando . ' 2>&1');
            
            if (file_exists($ruta_backup) && filesize($ruta_backup) > 0) {
                $tamaño = filesize($ruta_backup);
                
                // Registrar backup en la base de datos
                $stmt = $pdo->prepare("
                    INSERT INTO backups (nombre_archivo, ruta, tamaño, fecha_creacion) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$nombre_archivo, $ruta_backup, $tamaño]);
                
                registrarActividad($pdo, $usuario['id'], 'crear_backup', "Backup creado: $nombre_archivo");
                $mensaje = "Backup creado exitosamente: $nombre_archivo";
            } else {
                throw new Exception("Error al crear el backup: $resultado");
            }
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error de base de datos: " . $e->getMessage();
    }
}

// Obtener configuración actual
try {
    $stmt = $pdo->prepare("SELECT clave, valor FROM configuracion");
    $stmt->execute();
    $config_rows = $stmt->fetchAll();
    
    $config = [];
    foreach ($config_rows as $row) {
        $config[$row['clave']] = $row['valor'];
    }
    
} catch (PDOException $e) {
    $config = [];
    $error = "Error al cargar configuración: " . $e->getMessage();
}

// Obtener estadísticas del sistema
try {
    // Estadísticas de usuarios
    $stmt_usuarios = $pdo->prepare("SELECT COUNT(*) as total, SUM(activo) as activos FROM usuarios");
    $stmt_usuarios->execute();
    $stats_usuarios = $stmt_usuarios->fetch();
    
    // Estadísticas de empleados
    $stmt_empleados = $pdo->prepare("SELECT COUNT(*) as total, SUM(activo) as activos FROM empleados");
    $stmt_empleados->execute();
    $stats_empleados = $stmt_empleados->fetch();
    
    // Estadísticas de nóminas
    $stmt_nominas = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'pagado' THEN 1 ELSE 0 END) as pagadas,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
        FROM nominas
    ");
    $stmt_nominas->execute();
    $stats_nominas = $stmt_nominas->fetch();
    
    // Tamaño de la base de datos
    $stmt_db_size = $pdo->prepare("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = ?
    ");
    $stmt_db_size->execute([DB_NAME]);
    $db_size = $stmt_db_size->fetch()['size_mb'] ?? 0;
    
    // Logs recientes
    $stmt_logs = $pdo->prepare("
        SELECT COUNT(*) as total,
               COUNT(CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as ultima_semana
        FROM logs
    ");
    $stmt_logs->execute();
    $stats_logs = $stmt_logs->fetch();
    
    // Backups
    $stmt_backups = $pdo->prepare("
        SELECT COUNT(*) as total, MAX(fecha_creacion) as ultimo_backup
        FROM backups
    ");
    $stmt_backups->execute();
    $stats_backups = $stmt_backups->fetch();
    
} catch (PDOException $e) {
    $stats_usuarios = ['total' => 0, 'activos' => 0];
    $stats_empleados = ['total' => 0, 'activos' => 0];
    $stats_nominas = ['total' => 0, 'pagadas' => 0, 'pendientes' => 0];
    $db_size = 0;
    $stats_logs = ['total' => 0, 'ultima_semana' => 0];
    $stats_backups = ['total' => 0, 'ultimo_backup' => null];
}

// Obtener lista de backups
try {
    $stmt_lista_backups = $pdo->prepare("
        SELECT nombre_archivo, ruta, tamaño, fecha_creacion
        FROM backups 
        ORDER BY fecha_creacion DESC 
        LIMIT 10
    ");
    $stmt_lista_backups->execute();
    $lista_backups = $stmt_lista_backups->fetchAll();
    
} catch (PDOException $e) {
    $lista_backups = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - SISRH</title>
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
        
        .form-group {
            margin-bottom: 1rem;
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
        
        .form-group small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        
        .btn-block {
            width: 100%;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
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
        
        .backup-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .backup-item:last-child {
            border-bottom: none;
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-name {
            font-weight: 500;
            color: #333;
        }
        
        .backup-details {
            font-size: 0.8rem;
            color: #666;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .warning-box .icon {
            color: #856404;
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #666;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Configuración del Sistema</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="usuarios.php">👥 Usuarios</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="grid">
            <!-- Configuración General -->
            <div class="card">
                <div class="card-header">
                    <h3>⚙️ Configuración General</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="actualizar_config">
                        
                        <div class="form-group">
                            <label for="nombre_empresa">Nombre de la Empresa:</label>
                            <input type="text" id="nombre_empresa" name="nombre_empresa" 
                                   value="<?php echo htmlspecialchars($config['nombre_empresa'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="ruc_empresa">RUC:</label>
                            <input type="text" id="ruc_empresa" name="ruc_empresa" 
                                   value="<?php echo htmlspecialchars($config['ruc_empresa'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="direccion_empresa">Dirección:</label>
                            <textarea id="direccion_empresa" name="direccion_empresa" rows="3"><?php echo htmlspecialchars($config['direccion_empresa'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="telefono_empresa">Teléfono:</label>
                                <input type="text" id="telefono_empresa" name="telefono_empresa" 
                                       value="<?php echo htmlspecialchars($config['telefono_empresa'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email_empresa">Email:</label>
                                <input type="email" id="email_empresa" name="email_empresa" 
                                       value="<?php echo htmlspecialchars($config['email_empresa'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="salario_minimo">Salario Mínimo (₲):</label>
                            <input type="number" id="salario_minimo" name="salario_minimo" 
                                   value="<?php echo $config['salario_minimo'] ?? 2550000; ?>" 
                                   min="0" step="1000" required>
                            <small>Salario mínimo vigente en Paraguay</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="aporte_ips_personal">IPS Personal (%):</label>
                                <input type="number" id="aporte_ips_personal" name="aporte_ips_personal" 
                                       value="<?php echo $config['aporte_ips_personal'] ?? 9; ?>" 
                                       min="0" max="100" step="0.1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="aporte_ips_patronal">IPS Patronal (%):</label>
                                <input type="number" id="aporte_ips_patronal" name="aporte_ips_patronal" 
                                       value="<?php echo $config['aporte_ips_patronal'] ?? 16.5; ?>" 
                                       min="0" max="100" step="0.1" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">💾 Guardar Configuración</button>
                    </form>
                </div>
            </div>
            
            <!-- Estadísticas del Sistema -->
            <div class="card">
                <div class="card-header">
                    <h3>📊 Estadísticas del Sistema</h3>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_usuarios['total']; ?></div>
                            <div class="stat-label">Usuarios Total</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_usuarios['activos']; ?></div>
                            <div class="stat-label">Usuarios Activos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_empleados['total']; ?></div>
                            <div class="stat-label">Empleados Total</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_empleados['activos']; ?></div>
                            <div class="stat-label">Empleados Activos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_nominas['total']; ?></div>
                            <div class="stat-label">Nóminas Total</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_nominas['pagadas']; ?></div>
                            <div class="stat-label">Nóminas Pagadas</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tamaño de Base de Datos:</label>
                        <div class="stat-item mb-0">
                            <div class="stat-number"><?php echo $db_size; ?> MB</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gestión de Logs -->
            <div class="card">
                <div class="card-header">
                    <h3>📝 Gestión de Logs</h3>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_logs['total']; ?></div>
                            <div class="stat-label">Total Logs</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_logs['ultima_semana']; ?></div>
                            <div class="stat-label">Última Semana</div>
                        </div>
                    </div>
                    
                    <div class="warning-box">
                        <span class="icon">⚠️</span>
                        <strong>Atención:</strong> Esta acción eliminará permanentemente los logs antiguos.
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="limpiar_logs">
                        
                        <div class="form-group">
                            <label for="dias_logs">Eliminar logs anteriores a:</label>
                            <select id="dias_logs" name="dias_logs" required>
                                <option value="30">30 días</option>
                                <option value="60">60 días</option>
                                <option value="90">90 días</option>
                                <option value="180">6 meses</option>
                                <option value="365">1 año</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-warning btn-block" 
                                onclick="return confirm('¿Estás seguro de eliminar los logs antiguos?')">🗑️ Limpiar Logs</button>
                    </form>
                </div>
            </div>
            
            <!-- Gestión de Backups -->
            <div class="card">
                <div class="card-header">
                    <h3>💾 Gestión de Backups</h3>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats_backups['total']; ?></div>
                            <div class="stat-label">Total Backups</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php if ($stats_backups['ultimo_backup']): ?>
                                    <?php echo date('d/m/Y', strtotime($stats_backups['ultimo_backup'])); ?>
                                <?php else: ?>
                                    Nunca
                                <?php endif; ?>
                            </div>
                            <div class="stat-label">Último Backup</div>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="accion" value="crear_backup">
                        <button type="submit" class="btn btn-success btn-block">📦 Crear Backup</button>
                    </form>
                    
                    <?php if (!empty($lista_backups)): ?>
                        <h4 style="margin: 1.5rem 0 1rem 0;">Backups Recientes:</h4>
                        <div class="backup-list">
                            <?php foreach ($lista_backups as $backup): ?>
                                <div class="backup-item">
                                    <div class="backup-info">
                                        <div class="backup-name"><?php echo htmlspecialchars($backup['nombre_archivo']); ?></div>
                                        <div class="backup-details">
                                            <?php echo date('d/m/Y H:i', strtotime($backup['fecha_creacion'])); ?> - 
                                            <?php echo number_format($backup['tamaño'] / 1024 / 1024, 2); ?> MB
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted" style="margin-top: 1rem;">No hay backups disponibles</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>