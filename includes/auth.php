<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function paginasPublicas(): array
{
    return ['login', 'registro', 'logout', 'importar', 'index'];
}

function esPaginaPublica(): bool
{
    return in_array(basename($_SERVER['PHP_SELF'], '.php'), paginasPublicas(), true);
}

function getUsuarioId(): int
{
    return (int) ($_SESSION['usuario_id'] ?? 0);
}

function getUsuarioActual(): ?array
{
    if (!getUsuarioId()) {
        return null;
    }

    static $usuario = null;
    if ($usuario === null) {
        $stmt = getDB()->prepare('SELECT id, nombre, email FROM usuarios WHERE id = ?');
        $stmt->execute([getUsuarioId()]);
        $usuario = $stmt->fetch() ?: null;
        if (!$usuario) {
            logoutUsuario();
        }
    }

    return $usuario;
}

function requireAuth(): void
{
    if (!getUsuarioId()) {
        flash('error', 'Inicia sesión para continuar.');
        redirect('login.php');
    }
}

function loginUsuario(string $email, string $password): bool
{
    $stmt = getDB()->prepare('SELECT id, nombre, email, password FROM usuarios WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];

    return true;
}

function registrarUsuario(string $nombre, string $email, string $password): array
{
    $nombre = trim($nombre);
    $email = strtolower(trim($email));

    if ($nombre === '') {
        return ['ok' => false, 'error' => 'El nombre es obligatorio.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'El correo no es válido.'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
    }

    $db = getDB();
    $existe = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
    $existe->execute([$email]);
    if ($existe->fetch()) {
        return ['ok' => false, 'error' => 'Ya existe una cuenta con ese correo.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$nombre, $email, $hash]);
    $usuarioId = (int) $db->lastInsertId();

    seedTiposPagoUsuario($usuarioId);

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['usuario_nombre'] = $nombre;

    return ['ok' => true];
}

function logoutUsuario(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function seedTiposPagoUsuario(int $usuarioId): void
{
    $stmt = getDB()->prepare('INSERT INTO tipos_pago (usuario_id, nombre, color) VALUES (?, ?, ?)');
    foreach (tiposPagoDefault() as [$nombre, $color]) {
        try {
            $stmt->execute([$usuarioId, $nombre, $color]);
        } catch (PDOException) {
            // Ignorar duplicados.
        }
    }
}

if (!esPaginaPublica()) {
    requireAuth();
}
