<?php
/* Preserve the legacy dashboard URL by forwarding it to the protected admin folder endpoint. */
declare(strict_types=1);

header('Location: admin/admin-dashboard.php', true, 302);
exit;
