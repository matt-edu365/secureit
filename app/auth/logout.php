<?php
require __DIR__ . '/../lib.php';

secureit_clear_auth_context();

if (secureit_entra_is_enabled()) {
    header('Location: ' . secureit_entra_logout_url(), true, 302);
    exit;
}

header('Location: /login.php', true, 302);
exit;
