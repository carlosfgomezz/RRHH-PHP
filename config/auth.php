<?php
session_start();
require_once 'database.php';

// Verificar si el usuario está autenticado
function verificarAutenticacion() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: index.php');
        exit();
    }
}

// Verificar rol específico
function verificarRol($roles_permitidos) {
    verificarAutenticacion();
    
    if (!in_array($_SESSION['rol'], $roles_permitidos)) {
        header('Location: dashboard.php');
        exit();
    }
}

// Obtener información del usuario actual
function obtenerUsuarioActual() {
    if (isset($_SESSION['usuario_id'])) {
        return [
            'id' => $_SESSION['usuario_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'rol' => $_SESSION['rol']
        ];
    }
    return null;
}

// Cerrar sesión
function cerrarSesion() {
    global $pdo;
    
    if (isset($_SESSION['usuario_id'])) {
        // Marcar sesión como inactiva en la base de datos
        $query = "UPDATE sesiones SET fecha_fin = NOW(), activa = 0 WHERE usuario_id = ? AND activa = 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['usuario_id']]);
    }
    
    session_destroy();
    header('Location: index.php');
    exit();
}

// Registrar actividad del usuario
function registrarActividad($accion, $descripcion = '') {
    global $pdo;
    
    if (isset($_SESSION['usuario_id'])) {
        $query = "INSERT INTO logs (usuario_id, accion, descripcion, ip_address) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $_SESSION['usuario_id'],
            $accion,
            $descripcion,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }
}
?>