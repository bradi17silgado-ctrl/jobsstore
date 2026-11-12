<?php
if (!defined('JOBS_APP')) { http_response_code(403); exit('Acceso denegado'); }
// Controlador: operaciones administrativas (POST) con CSRF y sesión.
if ($path === '/admin/login' && $method === 'POST') {
    verify_csrf();
    $password=(string)($_POST['password']??'');
    $adminPassword=envv('ADMIN_PASSWORD');
    if ($adminPassword==='') { message('Configura ADMIN_PASSWORD en Render.'); go('/admin/login'); }
    if (hash_equals($adminPassword,$password)) { session_regenerate_id(true); $_SESSION['admin']=true; go('/admin'); }
    message('Contraseña incorrecta.'); go('/admin/login');
}
if($path==='/admin/salir'&&$method==='POST'){verify_csrf();unset($_SESSION['admin']);go('/');}
if ($path === '/admin/producto/guardar' && $method === 'POST') {
    require_admin();
    verify_csrf();
    $d=db();
    if (!$d) { message('Configura MySQL de Railway para guardar productos e imágenes.'); go('/admin'); }
    try {
        initdb($d);
        $id=trim((string)($_POST['id']??''));
        $nuevo=$id==='';
        if ($nuevo) $id=bin2hex(random_bytes(6));
        if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $id)) throw new RuntimeException('Identificador inválido.');
        $nombre=trim((string)($_POST['nombre']??''));
        $categoria=(string)($_POST['categoria']??'gorra');
        $descripcion=trim((string)($_POST['descripcion']??''));
        $precio=filter_var($_POST['precio']??null,FILTER_VALIDATE_FLOAT);
        $stock=filter_var($_POST['stock']??null,FILTER_VALIDATE_INT);
        if ($nombre==='' || mb_strlen($nombre)>160 || !in_array($categoria,['gorra','reloj'],true) || $precio===false || $precio<0 || $stock===false || $stock<0) {
            throw new RuntimeException('Revisa nombre, categoría, precio y stock.');
        }
        $imagen=null;
        if (!$nuevo) {
            $q=$d->prepare('SELECT imagen FROM jobs_productos WHERE id=?');
            $q->execute([$id]);
            $imagen=$q->fetchColumn();
            if ($imagen===false) throw new RuntimeException('El producto ya no existe.');
        }
        $upload=$_FILES['imagen_archivo']??null;
        $hasUpload=is_array($upload) && ($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
        $bytes=null; $mime=null;
        if ($hasUpload) {
            if ($upload['error']!==UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) throw new RuntimeException('No se pudo recibir la imagen. Máximo 3 MB.');
            if ($upload['size']<1 || $upload['size']>3*1024*1024) throw new RuntimeException('La imagen debe pesar máximo 3 MB.');
            $info=@getimagesize($upload['tmp_name']);
            $allowed=['image/jpeg','image/png','image/webp'];
            $finfo=new finfo(FILEINFO_MIME_TYPE);
            $mime=$finfo->file($upload['tmp_name']);
            if (!$info || !in_array($mime,$allowed,true) || ($info['mime']??'')!==$mime || $info[0]>5000 || $info[1]>5000) {
                throw new RuntimeException('Selecciona una imagen JPG, PNG o WEBP válida (máximo 5000 px).');
            }
            $bytes=file_get_contents($upload['tmp_name']);
            if ($bytes===false) throw new RuntimeException('No se pudo leer la imagen.');
            $imagen='db:'.$id;
        }
        if ($imagen===null || $imagen==='') throw new RuntimeException('Selecciona una fotografía para el nuevo producto.');
        $params=[$nombre,$categoria,$descripcion,$precio,$imagen,$stock,(int)isset($_POST['destacado']),(int)isset($_POST['visible'])];
        $d->beginTransaction();
        if ($nuevo) {
            $q=$d->prepare('INSERT INTO jobs_productos(id,nombre,categoria,descripcion,precio,imagen,stock,destacado,visible) VALUES (?,?,?,?,?,?,?,?,?)');
            $q->execute(array_merge([$id],$params));
        } else {
            $q=$d->prepare('UPDATE jobs_productos SET nombre=?,categoria=?,descripcion=?,precio=?,imagen=?,stock=?,destacado=?,visible=? WHERE id=?');
            $q->execute(array_merge($params,[$id]));
        }
        if ($bytes!==null) {
            $q=$d->prepare('INSERT INTO jobs_imagenes(producto_id,mime,contenido) VALUES (?,?,?) ON DUPLICATE KEY UPDATE mime=VALUES(mime), contenido=VALUES(contenido)');
            $q->bindValue(1,$id); $q->bindValue(2,$mime); $q->bindValue(3,$bytes,PDO::PARAM_LOB); $q->execute();
        }
        $d->commit();
        message($nuevo?'Producto creado correctamente.':'Producto actualizado correctamente.');
        go('/admin');
    } catch (Throwable $e) {
        if ($d->inTransaction()) $d->rollBack();
        error_log('Guardar producto: '.$e->getMessage());
        message($e instanceof RuntimeException?$e->getMessage():'No se pudo guardar el producto. Revisa los logs de Render.');
        go('/admin');
    }
}
if($path==='/admin/pedido/estado'&&$method==='POST'){require_admin();verify_csrf();$estado=(string)($_POST['estado']??'');if(in_array($estado,['Pendiente','Confirmado','Enviado','Entregado','Cancelado'],true)&&db()){$q=db()->prepare('UPDATE jobs_pedidos SET estado=? WHERE id=?');$q->execute([$estado,(int)($_POST['id']??0)]);}go('/admin');}
if($path==='/admin/resena/aprobar'&&$method==='POST'){require_admin();verify_csrf();if(db()){$q=db()->prepare('UPDATE jobs_resenas SET aprobada=? WHERE id=?');$q->execute([(int)($_POST['aprobar']??0),(int)($_POST['id']??0)]);}go('/admin');}
