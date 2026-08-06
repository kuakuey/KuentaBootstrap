<?php
require_once __DIR__ . '/includes/functions.php';

if (getUsuarioId()) {
    redirect('calendario.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (loginUsuario($email, $password)) {
        redirect('calendario.php');
    }

    $error = 'Correo o contraseña incorrectos.';
}

$pageTitle = 'Iniciar sesión';
?>
<!DOCTYPE html>
<html lang="es-EC">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — Cuentas Hogar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <main class="container" style="max-width: 420px;">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h1 class="h3 mb-1">Cuentas Hogar</h1>
                    <p class="text-muted mb-0">Ecuador · Organiza tus pagos mensuales</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endif; ?>

                <?php
                $flash = getFlash();
                if ($flash):
                    $flashType = $flash['type'] === 'error' ? 'danger' : $flash['type'];
                ?>
                    <div class="alert alert-<?= h($flashType) ?>">
                        <?= h($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>

                <p class="text-center mt-3 mb-0 small">
                    ¿No tienes cuenta? <a href="registro.php">Regístrate</a>
                </p>
            </div>
        </div>
    </main>
</body>
</html>
