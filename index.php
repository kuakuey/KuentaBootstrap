<?php
require_once __DIR__ . '/includes/functions.php';

redirect(getUsuarioId() ? 'calendario.php' : 'login.php');
