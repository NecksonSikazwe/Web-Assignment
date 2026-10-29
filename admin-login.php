<?php
/* Preserve the legacy administrator login URL while keeping its implementation in admin/. */
declare(strict_types=1);

header('Location: admin/admin-login.php', true, 302);
exit;
