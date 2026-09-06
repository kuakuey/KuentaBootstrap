<?php
$avanzadoTab = $avanzadoTab ?? '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Avanzado</h1>
        <p class="text-muted mb-0">Configura personas y tipos de pago</p>
    </div>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $avanzadoTab === 'personas' ? 'active' : '' ?>" href="personas.php">Personas</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $avanzadoTab === 'tipos-pago' ? 'active' : '' ?>" href="tipos-pago.php">Tipos de pago</a>
    </li>
</ul>
