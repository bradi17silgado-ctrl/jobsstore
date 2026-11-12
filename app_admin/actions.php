
<?php
if (!defined('JOBS_APP')) {
    http_response_code(403);
    exit('Acceso denegado');
}

// CONTROLADOR ADMINISTRATIVO

// INICIAR SESIÓN
if ($path === '/admin/login' && $method === 'POST') {
    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $adminPassword = envv('ADMIN_PASSWORD');

    if ($adminPassword === '') {
        message('Contraseña administrativa no configurada.');
        go('/admin/login');
    }

    if (hash_equals($adminPassword, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        go('/admin');
    }

    message('Contraseña incorrecta.');
    go('/admin/login');
}

// CERRAR SESIÓN
if ($path === '/admin/salir' && $method === 'POST') {
    verify_csrf();
    unset($_SESSION['admin']);
    session_regenerate_id(true);
    go('/');
}

// GUARDAR PRODUCTOS
if ($path === '/admin/producto/guardar' && $method === 'POST') {
    require_admin();
    verify_csrf();

    $d = db();

    if (!$d) {
        message('Configura MySQL para editar productos.');
        go('/admin');
    }

    try {
        initdb($d);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        message('No se pudo inicializar la base de datos.');
        go('/admin');
    }

    $id = trim((string) ($_POST['id'] ?? ''));
    $new = $id === '';

    if ($new) {
        $id = bin2hex(random_bytes(6));
    }

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $precio = (float) ($_POST['precio'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $imagen = trim((string) ($_POST['imagen'] ?? ''));

    if (
        $nombre === '' ||
        $precio < 0 ||
        $stock < 0 ||
        !preg_match('~^productos/[a-zA-Z0-9_.-]+$~', $imagen)
    ) {
        message('Revisa nombre, precio, stock e imagen.');
        go('/admin');
    }

    $params = [
        $nombre,
        trim((string) ($_POST['categoria'] ?? 'gorra')),
        trim((string) ($_POST['descripcion'] ?? '')),
        $precio,
        $imagen,
        $stock,
        (int) isset($_POST['destacado']),
        (int) isset($_POST['visible'])
    ];

    if ($new) {
        $d->prepare(
            'INSERT INTO jobs_productos
            (id,nombre,categoria,descripcion,precio,imagen,stock,destacado,visible)
            VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute(array_merge([$id], $params));
    } else {
        $d->prepare(
            'UPDATE jobs_productos
            SET nombre=?,categoria=?,descripcion=?,precio=?,
            imagen=?,stock=?,destacado=?,visible=?
            WHERE id=?'
        )->execute(array_merge($params, [$id]));
    }

    message('Producto guardado.');
    go('/admin');
}

// ACTUALIZAR ESTADO DEL PEDIDO
if ($path === '/admin/pedido/estado' && $method === 'POST') {
    require_admin();
    verify_csrf();

    $estado = (string) ($_POST['estado'] ?? '');
    $d = db();

    if (
        in_array(
            $estado,
            ['Pendiente', 'Confirmado', 'Enviado', 'Entregado', 'Cancelado'],
            true
        ) && $d
    ) {
        $q = $d->prepare(
            'UPDATE jobs_pedidos SET estado=? WHERE id=?'
        );

        $q->execute([
            $estado,
            (int) ($_POST['id'] ?? 0)
        ]);
    }

    go('/admin');
}

// APROBAR RESEÑAS
if ($path === '/admin/resena/aprobar' && $method === 'POST') {
    require_admin();
    verify_csrf();

    $d = db();

    if ($d) {
        $q = $d->prepare(
            'UPDATE jobs_resenas SET aprobada=? WHERE id=?'
        );

        $q->execute([
            (int) ($_POST['aprobar'] ?? 0),
            (int) ($_POST['id'] ?? 0)
        ]);
    }

    go('/admin');
}
