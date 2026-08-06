<?php
require_once __DIR__ . '/includes/functions.php';

if (getUsuarioId()) {
    redirect('calendario.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password !== $password2) {
        $errors[] = 'Las contraseñas no coinciden.';
    }

    if (empty($errors)) {
        $result = registrarUsuario($nombre, $email, $password);
        if ($result['ok']) {
            flash('success', '¡Cuenta creada! Ya puedes organizar tus pagos.');
            redirect('calendario.php');
        }
        $errors[] = $result['error'];
    }
}

$pageTitle = 'Crear cuenta';
?>
<!DOCTYPE html>
<html lang="es-EC">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Cuentas Hogar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <main class="container" style="max-width: 420px;">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h1 class="h3 mb-1">Crear cuenta</h1>
                    <p class="text-muted mb-0">Tus pagos, tu calendario, privados</p>
                </div>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label for="password2" class="form-label">Confirmar contraseña</label>
                        <input type="password" class="form-control" id="password2" name="password2" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Registrarme</button>
                </form>

                <p class="text-center mt-3 mb-0 small">
                    ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
                </p>
            </div>
        </div>
    </main>
</body>
</html>
