<?php
require_once 'config/auth.php';
verificarAutenticacion();

$usuario = obtenerUsuarioActual();

// Obtener estadísticas del dashboard
try {
    // Total de empleados activos
    $query = "SELECT COUNT(*) as total FROM empleados WHERE activo = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $total_empleados = $stmt->fetch()['total'];
    
    // Nóminas del mes actual
    $query = "SELECT COUNT(*) as total FROM nominas WHERE periodo_mes = MONTH(NOW()) AND periodo_anio = YEAR(NOW())";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $nominas_mes = $stmt->fetch()['total'];
    
    // Contratos activos
    $query = "SELECT COUNT(*) as total FROM contratos WHERE activo = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $contratos_activos = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $total_empleados = 0;
    $nominas_mes = 0;
    $contratos_activos = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SISRH</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --light-bg: #f8fafc;
            --card-shadow: 0 10px 25px rgba(0,0,0,0.08);
            --card-shadow-hover: 0 20px 40px rgba(0,0,0,0.12);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light-bg);
            line-height: 1.6;
            color: #334155;
            overflow-x: hidden;
        }
        
        .header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
            pointer-events: none;
        }
        
        .header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }
        
        .header h1::before {
            content: '🏢';
            font-size: 2rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .user-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1.25rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-badge::before {
            content: '👤';
            font-size: 1.1rem;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 0.75rem 1.25rem;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .logout-btn::before {
            content: '🚪';
            font-size: 1.1rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 3rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .welcome::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-20px, -20px) rotate(180deg); }
        }
        
        .welcome h2 {
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 2.25rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        
        .welcome p {
            color: #64748b;
            font-size: 1.2rem;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.8);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stat-card:nth-child(2)::before {
            background: var(--success-gradient);
        }
        
        .stat-card:nth-child(3)::before {
            background: var(--warning-gradient);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .stat-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background: var(--success-gradient);
            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background: var(--warning-gradient);
            box-shadow: 0 8px 20px rgba(67, 233, 123, 0.3);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .module-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.8);
        }
        
        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .module-card:nth-child(2)::before {
            background: var(--success-gradient);
        }
        
        .module-card:nth-child(3)::before {
            background: var(--warning-gradient);
        }
        
        .module-card:nth-child(4)::before {
            background: var(--danger-gradient);
        }
        
        .module-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .module-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .module-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.3);
        }
        
        .module-card:nth-child(2) .module-icon {
            background: var(--success-gradient);
            box-shadow: 0 6px 15px rgba(79, 172, 254, 0.3);
        }
        
        .module-card:nth-child(3) .module-icon {
            background: var(--warning-gradient);
            box-shadow: 0 6px 15px rgba(67, 233, 123, 0.3);
        }
        
        .module-card:nth-child(4) .module-icon {
            background: var(--danger-gradient);
            box-shadow: 0 6px 15px rgba(250, 112, 154, 0.3);
        }
        
        .module-title {
            color: #1e293b;
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
        }
        
        .module-description {
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .module-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .module-link {
            color: #475569;
            text-decoration: none;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            transition: var(--transition);
            background: rgba(248, 250, 252, 0.8);
            border: 1px solid rgba(226, 232, 240, 0.8);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        
        .module-link:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.3);
            color: #667eea;
            transform: translateX(4px);
        }
        
        .module-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .welcome {
                padding: 2rem;
            }
            
            .welcome h2 {
                font-size: 1.8rem;
            }
            
            .stats-grid,
            .modules-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .stat-card,
            .module-card {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SISRH - Dashboard</h1>
        <div class="user-info">
            <div class="user-badge"><?php echo htmlspecialchars($usuario['username']); ?> (<?php echo ucfirst($usuario['rol']); ?>)</div>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>Bienvenido al Sistema Integral de Salarios y Recursos Humanos</h2>
            <p>Gestiona empleados, nóminas, contratos y reportes de manera eficiente y segura.</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_empleados; ?></div>
                <div class="stat-label">Empleados Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number"><?php echo $nominas_mes; ?></div>
                <div class="stat-label">Nóminas del Mes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <div class="stat-number"><?php echo $contratos_activos; ?></div>
                <div class="stat-label">Contratos Activos</div>
            </div>
        </div>
        
        <div class="modules-grid">
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="module-title">Gestión de Empleados</h3>
                </div>
                <p class="module-description">Administra la información de empleados, categorías y cargos de manera integral.</p>
                <div class="module-links">
                    <a href="empleados/gestionar.php" class="module-link">
                        <i class="fas fa-list"></i>
                        Ver Empleados
                    </a>
                    <a href="empleados/nuevo.php" class="module-link">
                        <i class="fas fa-user-plus"></i>
                        Nuevo Empleado
                    </a>
                </div>
            </div>
            
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3 class="module-title">Gestión de Nóminas</h3>
                </div>
                <p class="module-description">Genera y administra las nóminas mensuales con cálculos automáticos de salarios y deducciones.</p>
                <div class="module-links">
                    <a href="nominas/gestionar.php" class="module-link">
                        <i class="fas fa-list-alt"></i>
                        Ver Nóminas
                    </a>
                    <a href="nominas/generar.php" class="module-link">
                        <i class="fas fa-calculator"></i>
                        Generar Nómina
                    </a>
                </div>
            </div>
            
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3 class="module-title">Reportes</h3>
                </div>
                <p class="module-description">Genera reportes detallados de nóminas, aportes IPS y análisis estadísticos completos.</p>
                <div class="module-links">
                    <a href="reportes/nomina.php" class="module-link">
                        <i class="fas fa-chart-line"></i>
                        Reporte de Nómina
                    </a>
                    <a href="reportes/ips.php" class="module-link">
                        <i class="fas fa-university"></i>
                        Reporte de IPS
                    </a>
                </div>
            </div>
            
            <?php if ($usuario['rol'] == 'admin'): ?>
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3 class="module-title">Administración</h3>
                </div>
                <p class="module-description">Configuración del sistema, gestión de usuarios y parámetros administrativos.</p>
                <div class="module-links">
                    <a href="admin/usuarios.php" class="module-link">
                        <i class="fas fa-user-cog"></i>
                        Gestionar Usuarios
                    </a>
                    <a href="admin/configuracion.php" class="module-link">
                        <i class="fas fa-sliders-h"></i>
                        Configuración
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>