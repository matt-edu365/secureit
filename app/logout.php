<?php
require __DIR__ . '/lib.php';
secureit_clear_auth_context();

header('Location: login.php', true, 302);
exit;
