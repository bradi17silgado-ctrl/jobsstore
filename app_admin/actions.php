
<?php
if (!defined('JOBS_APP')) {
    http_response_code(403);
    exit('Acceso denegado');
}

/*
 * JOBS STORE
 * Controlador administrativo
 * Login, logout, productos, pedidos y reseñas
 */

// =====================================
// INICIAR SESIÓN ADMINISTRATIVA
// =====================================

if ($path === '/admin/login' && $method === 'POST') {

    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $adminPassword = envv('ADMIN_PASSWORD');

    if (!is_string($adminPassword) || $adminPassword === '') {
        message('Acceso administrativo no configurado.');
        go('/admin/login');
        exit;
    }

    if (hash_equals($adminPassword, $password)) {

        session_regenerate_id(true);

        $_SESSION['admin'] = true;

        go('/admin');
        exit;
    }

    message('Contraseña incorrecta.');
    go('/admin/login');
    exit;
}

// =====================================
// CERRAR SESIÓN
// =====================================

if ($path === '/admin/salir' && $method === 'POST') {

    require_admin();
    verify_csrf();

    unset($_SESSION['admin']);

    session_regenerate_id(true);

    go('/');
    exit;
}

// =====================================
// GUARDAR PRODUCTOS
// =====================================

if ($path === '/admin/producto/guardar' && $method === 'POST') {

    require_admin();
    verify_csrf();

    $d = db();

    if (!$d) {
        message('No se pudo conectar con MySQL.');
        go('/admin');
        exit;
    }

    try {
        initdb($d);

        $id = trim((string) ($_POST['id'] ?? ''));
        $nuevo = ($id === '');

        if ($nuevo) {
            $id = bin2hex(random_bytes(6));
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $categoria = trim((string) ($_POST['categoria'] ?? 'gorra'));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $imagen = trim((string) ($_POST['imagen'] ?? ''));

        $precioRaw = $_POST['precio'] ?? '';
        $stockRaw = $_POST['stock'] ?? '';

        $precio = filter_var(
            $precioRaw,
            FILTER_VALIDATE_FLOAT
        );

        $stock = filter_var(
            $stockRaw,
            FILTER_VALIDATE_INT
        );

        if (
            $nombre === '' ||
            $precio === false ||
            $precio < 0 ||
            $stock === false ||
            $stock < 0 ||
            !preg_match(
                '~^productos/[a-zA-Z0-9_.-]+$~',
                $imagen
            )
        ) {
            message('Revisa los datos del producto.');
            go('/admin');
            exit;
        }

        $destacado = isset($_POST['destacado']) ? 1 : 0;
        $visible = isset($_POST['visible']) ? 1 : 0;

        $params = [
            $nombre,
            $categoria,
            $descripcion,
            $precio,
            $imagen,
            $stock,
            $destacado,
            $visible
        ];

        if ($nuevo) {

            $sql = 'INSERT INTO jobs_productos
                (id, nombre, categoria, descripcion,
                 precio, imagen, stock, destacado, visible)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $stmt = $d->prepare($sql);
            $stmt->execute(array_merge([$id], $params));

        } else {

            $sql = 'UPDATE jobs_productos
                SET nombre = ?,
                    categoria = ?,
                    descripcion = ?,
                    precio = ?,
                    imagen = ?,
                    stock = ?,
                    destacado = ?,
                    visible = ?
                WHERE id = ?';

            $stmt = $d->prepare($sql);
            $stmt->execute(array_merge($params, [$id]));
        }

        message('Producto guardado correctamente.');

    } catch (Throwable $e) {

        error_log('Error al guardar producto: ' . $e->getMessage());

        message('No fue posible guardar el producto.');
    }

    go('/admin');
    exit;
}

// =====================================
// ACTUALIZAR ESTADO DE PEDIDOS
// =====================================

if ($path === '/admin/pedido/estado' && $method === 'POST') {

    require_admin();
    verify_csrf();

    $estado = (string) ($_POST['estado'] ?? '');
    $id = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $estadosPermitidos = [
        'Pendiente',
        'Confirmado',
        'Enviado',
        'Entregado',
        'Cancelado'
    ];

    if (
        !in_array($estado, $estadosPermitidos, true) ||
        $id === false ||
        $id === null ||
        $id <= 0
    ) {
        message('Datos del pedido inválidos.');
        go('/admin');
        exit;
    }

    try {

        $d = db();

        if (!$d) {
            throw new RuntimeException('Sin conexión MySQL');
        }

        $stmt = $d->prepare(
            'UPDATE jobs_pedidos SET estado = ? WHERE id = ?'
        );

        $stmt->execute([$estado, $id]);

        message('Estado del pedido actualizado.');

    } catch (Throwable $e) {

        error_log('Error al actualizar pedido: ' . $e->getMessage());

        message('No se pudo actualizar el pedido.');
    }

    go('/admin');
    exit;
}

// =====================================
// APROBAR O RECHAZAR RESEÑAS
// =====================================

if ($path === '/admin/resena/aprobar' && $method === 'POST') {

    require_admin();
    verify_csrf();

    $id = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $aprobar = (string) ($_POST['aprobar'] ?? '');

    if (
        $id === false ||
        $id === null ||
        $id <= 0 ||
        !in_array($aprobar, ['0', '1'], true)
    ) {
        message('Datos de reseña inválidos.');
        go('/admin');
        exit;
    }

    try {

        $d = db();

        if (!$d) {
            throw new RuntimeException('Sin conexión MySQL');
        }

        $stmt = $d->prepare(
            'UPDATE jobs_resenas SET aprobada = ? WHERE id = ?'
        );

        $stmt->execute([(int) $aprobar, $id]);

        message('Reseña actualizada correctamente.');

    } catch (Throwable $e) {

        error_log('Error al actualizar reseña: ' . $e->getMessage());

        message('No se pudo actualizar la reseña.');
    }

    go('/admin');
    exit;
}

// No agregar cierre PHP para evitar salida accidental.
