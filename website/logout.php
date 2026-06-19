<?php
require __DIR__ . '/_theme.php';
secureit_clear_auth_context();

header('Location: login.php', true, 302);
exit;
