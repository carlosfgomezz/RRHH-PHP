<?php
require_once '../config/auth.php';
verificarRol(['admin', 'rrhh']);

$usuario = obtenerUsuarioActual();

// Obtener lista de empleados
try {
    $query = "
        SELECT 
            e.id,
            e.cedula,
            e.nombres,
            e.apellidos,
            e.telefono,
            e.email,
            e.fecha_ingreso,
            e.salario_base,
            e.activo,
            c.nombre as categoria,
            ca.nombre as cargo
        FROM empleados e
        LEFT JOIN categorias c ON e.categoria_id = c.id
        LEFT JOIN cargos ca ON e.cargo_id = ca.id
        ORDER BY e.apellidos, e.nombres
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $empleados = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $empleados = [];
    $error = "Error al cargar empleados: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Empleados - SISRH</title>
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
        
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: transform 0.2s;
            display: inline-block;
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
        
        .search-box {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-box input {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 0.9rem;
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
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status.activo {
            background: #d4edda;
            color: #155724;
        }
        
        .status.inactivo {
            background: #f8d7da;
            color: #721c24;
        }
        
        .actions-cell {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .btn-info {
            background: #17a2b8;
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
        <h1>Gestionar Empleados</h1>
        <div class="nav-links">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="nuevo.php">➕ Nuevo Empleado</a>
        </div>
    </div>
    
    <div class="container">
        <div class="actions">
            <h2>Lista de Empleados</h2>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Buscar empleado..." onkeyup="filtrarTabla()">
                <a href="nuevo.php" class="btn btn-primary">➕ Nuevo Empleado</a>
            </div>
        </div>
        
        <div class="table-container">
            <?php if (empty($empleados)): ?>
                <div class="empty-state">
                    <div class="icon">👥</div>
                    <h3>No hay empleados registrados</h3>
                    <p>Comienza agregando tu primer empleado al sistema.</p>
                    <a href="nuevo.php" class="btn btn-primary" style="margin-top: 1rem;">➕ Agregar Empleado</a>
                </div>
            <?php else: ?>
                <table class="table" id="empleadosTable">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre Completo</th>
                            <th>Cargo</th>
                            <th>Categoría</th>
                            <th>Salario Base</th>
                            <th>Fecha Ingreso</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $empleado): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($empleado['cedula']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($empleado['apellidos'] . ', ' . $empleado['nombres']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($empleado['email'] ?? 'Sin email'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($empleado['cargo'] ?? 'Sin cargo'); ?></td>
                                <td><?php echo htmlspecialchars($empleado['categoria'] ?? 'Sin categoría'); ?></td>
                                <td>₲ <?php echo number_format($empleado['salario_base'], 0, ',', '.'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?></td>
                                <td>
                                    <span class="status <?php echo $empleado['activo'] ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $empleado['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <a href="ver.php?id=<?php echo $empleado['id']; ?>" class="btn btn-info btn-sm">👁️ Ver</a>
                                        <a href="editar.php?id=<?php echo $empleado['id']; ?>" class="btn btn-warning btn-sm">✏️ Editar</a>
                                        <?php if ($empleado['activo']): ?>
                                            <a href="desactivar.php?id=<?php echo $empleado['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Desactivar empleado?')">❌ Desactivar</a>
                                        <?php else: ?>
                                            <a href="activar.php?id=<?php echo $empleado['id']; ?>" class="btn btn-primary btn-sm">✅ Activar</a>
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
    
    <script>
        function filtrarTabla() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('empleadosTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 1; j++) {
                    if (cells[j].textContent.toLowerCase().includes(filter)) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        }
    </script>
</body>
</html>