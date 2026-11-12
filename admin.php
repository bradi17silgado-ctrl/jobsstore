<?php
// Acceso directo al panel. La autenticación se verifica en /admin.
header('Location: /admin', true, 302);
exit;
