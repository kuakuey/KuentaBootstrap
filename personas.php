<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $color = $_POST['color'] ?? '#0d6efd';

        if ($nombre !== '') {
            try {
                $stmt = getDB()->prepare('INSERT INTO personas (usuario_id, nombre, color) VALUES (?, ?, ?)');
                $stmt->execute([getUsuarioId(), $nombre, $color]);
                flash('success', 'Persona agregada.');
            } catch (PDOException $e) {
                flash('error', 'Ya existe una persona con ese nombre.');
            }
        }
    }

    if ($action === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $color = $_POST['color'] ?? '#0d6efd';

        if ($id && $nombre !== '') {
            try {
                $stmt = getDB()->prepare('UPDATE personas SET nombre = ?, color = ? WHERE id = ? AND usuario_id = ?');
                $stmt->execute([$nombre, $color, $id, getUsuarioId()]);
                flash('success', 'Persona actualizada.');
            } catch (PDOException $e) {
                flash('error', 'Ya existe una persona con ese nombre.');
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare('UPDATE personas SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Estado actualizado.');
    }

    if ($action === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare('DELETE FROM personas WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Persona eliminada.');
    }

    redirect('personas.php');
}

$personas = getPersonas(false);
$pageTitle = 'Avanzado';
require __DIR__ . '/includes/header.php';
$avanzadoTab = 'personas';
require __DIR__ . '/includes/avanzado-tabs.php';
?>

<p class="text-muted mb-4">Separa pagos por persona (ej. Cristhian, Jessy). En el header, <strong>Todos</strong> muestra todo sin filtros.</p>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Nueva persona</h2>
                <form method="post">
                    <input type="hidden" name="action" value="crear">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Cristhian, Jessy..." required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="color" class="form-label">Color</label>
                        <input type="color" class="form-control form-control-color" id="color" name="color" value="#0d6efd">
                    </div>
                    <button type="submit" class="btn btn-primary">Agregar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Personas registradas</h2>
                <?php if (empty($personas)): ?>
                    <p class="text-muted mb-0">Aún no hay personas. Agrega Cristhian, Jessy u otras.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($personas as $persona): ?>
                            <div class="list-group-item px-0 <?= $persona['activo'] ? '' : 'opacity-50' ?>">
                                <form method="post" class="row g-2 align-items-center">
                                    <input type="hidden" name="action" value="editar">
                                    <input type="hidden" name="id" value="<?= (int) $persona['id'] ?>">
                                    <div class="col-auto">
                                        <span class="color-dot" style="background: <?= h($persona['color']) ?>"></span>
                                    </div>
                                    <div class="col">
                                        <input type="text" class="form-control form-control-sm" name="nombre" value="<?= h($persona['nombre']) ?>" required>
                                    </div>
                                    <div class="col-auto">
                                        <input type="color" class="form-control form-control-color form-control-sm" name="color" value="<?= h($persona['color']) ?>">
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Guardar</button>
                                    </div>
                                </form>
                                <div class="d-flex gap-1 mt-2">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $persona['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $persona['activo'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta persona? Las cuentas quedarán sin asignar.')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int) $persona['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
