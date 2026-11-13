<?php
if (!defined('JOBS_APP')) { http_response_code(403); exit('Acceso denegado'); }
// Controlador: operaciones administrativas (POST) con CSRF y sesión.
if ($path === '/admin/login' && $method === 'POST') {
    verify_csrf();
    jobs_rate_limit('admin-login', 5, 900);
    $password=(string)($_POST['password']??'');
    if(strlen($password)>1024){message('Credenciales incorrectas.');go('/admin/login');}
    $hash=envv('ADMIN_PASSWORD_HASH');
    $legacy=envv('ADMIN_PASSWORD');
    $valid=$hash!=='' ? password_verify($password,$hash) : ($legacy!=='' && hash_equals($legacy,$password));
    if ($valid) {
        jobs_rate_limit_reset('admin-login');
        session_regenerate_id(true);
        $_SESSION['admin']=true;
        $_SESSION['admin_last_seen']=time();
        $_SESSION['csrf']=bin2hex(random_bytes(24));
        go('/admin');
    }
    message('Credenciales incorrectas.');go('/admin/login');
}
if($path==='/admin/salir'&&$method==='POST'){
    verify_csrf();
    $_SESSION=[];
    session_regenerate_id(true);
    go('/');
}
if ($path === '/admin/producto/guardar' && $method === 'POST') {
    require_admin();
    verify_csrf();
    $d=db();
    if (!$d) { message('Configura MySQL de Railway para guardar productos e imágenes.'); go('/admin/productos'); }
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
        $colorStocks=$_POST['colores']??[];
        if(!is_array($colorStocks))throw new RuntimeException('Colores inválidos.');
        $palette=['negro','blanco','gris','azul-marino','azul-rey','rojo','verde-militar','beige','cafe','rosado','dorado','plateado','morado','vinotinto'];
        $variants=[];
        foreach($colorStocks as $color=>$units){
            if(!is_string($color)||!in_array($color,$palette,true)||!is_scalar($units)||!preg_match('/^\d{1,6}$/D',(string)$units))throw new RuntimeException('Cantidad no válida para un color.');
            $n=(int)$units;
            if($n>99999)throw new RuntimeException('Cantidad máxima por color: 99999.');
            $variants[$color]=$n;
        }
        // Los artículos antiguos sin variantes conservan su inventario hasta que sean editados.
        $stock=$variants ? array_sum($variants) : filter_var($_POST['stock']??null,FILTER_VALIDATE_INT);
        if($stock>999999)throw new RuntimeException('Stock total demasiado alto.');
        if($nuevo && !$variants)throw new RuntimeException('Selecciona al menos un color y su cantidad.');
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
        if (!$nuevo && !$variants) {
            $existingVariants=$d->prepare('SELECT COUNT(*) FROM jobs_colores WHERE producto_id=?');
            $existingVariants->execute([$id]);
            if ((int)$existingVariants->fetchColumn()>0) throw new RuntimeException('Conserva al menos un color para este producto. Puedes dejar su cantidad en 0.');
        }
        $oldStocks=[];
        if(!$nuevo){
            $qOld=$d->prepare('SELECT color,stock FROM jobs_colores WHERE producto_id=?');$qOld->execute([$id]);
            foreach($qOld->fetchAll() as $r)$oldStocks[$r['color']]=(int)$r['stock'];
            if(!$oldStocks){$oldP=$d->prepare('SELECT stock FROM jobs_productos WHERE id=?');$oldP->execute([$id]);$oldStocks['']=(int)$oldP->fetchColumn();}
        }
        $params=[$nombre,$categoria,$descripcion,$precio,$imagen,$stock,(int)isset($_POST['destacado']),(int)isset($_POST['visible'])];
        $d->beginTransaction();
        if ($nuevo) {
            $q=$d->prepare('INSERT INTO jobs_productos(id,nombre,categoria,descripcion,precio,imagen,stock,destacado,visible) VALUES (?,?,?,?,?,?,?,?,?)');
            $q->execute(array_merge([$id],$params));
        } else {
            $q=$d->prepare('UPDATE jobs_productos SET nombre=?,categoria=?,descripcion=?,precio=?,imagen=?,stock=?,destacado=?,visible=? WHERE id=?');
            $q->execute(array_merge($params,[$id]));
        }
        $oldColorCount=$d->prepare('SELECT COUNT(*) FROM jobs_colores WHERE producto_id=?');$oldColorCount->execute([$id]);
        if($variants || (int)$oldColorCount->fetchColumn()>0){
            $del=$d->prepare('DELETE FROM jobs_colores WHERE producto_id=?');$del->execute([$id]);
            $insert=$d->prepare('INSERT INTO jobs_colores(producto_id,color,stock) VALUES(?,?,?)');
            foreach($variants as $color=>$units)$insert->execute([$id,$color,$units]);
        }
        // Las fotos son archivos reales por color; jamás se recolorea una imagen automáticamente.
        // Si falta fotografía para un color se utiliza la principal, sin simular otro color.
        $colorFiles=$_FILES['imagenes_color']??null;
        if(is_array($colorFiles) && isset($colorFiles['name']) && is_array($colorFiles['name'])){
            $upsert=$d->prepare('INSERT INTO jobs_color_imagenes (producto_id,color,mime,contenido) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE mime=VALUES(mime),contenido=VALUES(contenido)');
            foreach($colorFiles['name'] as $color=>$filename){
                if(!in_array($color,$palette,true))throw new RuntimeException('Color de imagen inválido.');
                $err=$colorFiles['error'][$color]??UPLOAD_ERR_NO_FILE;
                if($err===UPLOAD_ERR_NO_FILE)continue;
                if($err!==UPLOAD_ERR_OK || !isset($variants[$color]))throw new RuntimeException('No se pudo subir la imagen del color '.$color);
                $temp=$colorFiles['tmp_name'][$color]??'';
                $size=(int)($colorFiles['size'][$color]??0);
                if(!is_uploaded_file($temp) || $size<1 || $size>3*1024*1024)throw new RuntimeException('La imagen del color '.$color.' debe pesar máximo 3 MB.');
                $info=@getimagesize($temp);$type=(new finfo(FILEINFO_MIME_TYPE))->file($temp);
                if(!$info || !in_array($type,['image/jpeg','image/png','image/webp'],true) || ($info['mime']??'')!==$type || $info[0]>5000 || $info[1]>5000)throw new RuntimeException('Foto inválida en '.$color.' (JPG, PNG o WEBP).');
                $data=file_get_contents($temp);if($data===false)throw new RuntimeException('No se pudo leer la foto de '.$color);
                $upsert->bindValue(1,$id);$upsert->bindValue(2,$color);$upsert->bindValue(3,$type);$upsert->bindValue(4,$data,PDO::PARAM_LOB);$upsert->execute();
            }
        }
        // Eliminar imágenes huérfanas de variantes retiradas.
        if($variants){$markers=implode(',',array_fill(0,count($variants),'?'));$cleanup=$d->prepare('DELETE FROM jobs_color_imagenes WHERE producto_id=? AND color NOT IN ('.$markers.')');$cleanup->execute(array_merge([$id],array_keys($variants)));}
        if ($bytes!==null) {
            $q=$d->prepare('INSERT INTO jobs_imagenes(producto_id,mime,contenido) VALUES (?,?,?) ON DUPLICATE KEY UPDATE mime=VALUES(mime), contenido=VALUES(contenido)');
            $q->bindValue(1,$id); $q->bindValue(2,$mime); $q->bindValue(3,$bytes,PDO::PARAM_LOB); $q->execute();
        }
        $changedColors=array_unique(array_merge(array_keys($oldStocks),array_keys($variants)));
        if(!$variants)$changedColors=[''];
        foreach($changedColors as $variantColor){
            $before=(int)($oldStocks[$variantColor]??0);
            $after=(int)($variants[$variantColor]??($variantColor===''?$stock:0));
            if($before===$after)continue;
            jobs_registrar_movimiento($d,$id,$nombre,$variantColor?:null,'ajuste',abs($after-$before),$before,$after,null,null,null,$nuevo?'Inventario inicial al crear producto':'Ajuste desde edición del producto');
        }
        $d->commit();
        message($nuevo?'Producto creado correctamente.':'Producto actualizado correctamente.');
        go('/admin/productos');
    } catch (Throwable $e) {
        if ($d->inTransaction()) $d->rollBack();
        error_log('Guardar producto: '.$e->getMessage());
        message($e instanceof RuntimeException?$e->getMessage():'No se pudo guardar el producto. Revisa los logs de Render.');
        go('/admin/productos');
    }
}
// Ocultar o volver a publicar un producto sin borrar su información.
if ($path === '/admin/producto/visibilidad' && $method === 'POST') {
    require_admin();
    verify_csrf();
    $id=(string)($_POST['id']??'');
    $visible=(string)($_POST['visible']??'');
    if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/',$id) || !in_array($visible,['0','1'],true)) {
        message('Solicitud de visibilidad inválida.'); go('/admin/productos');
    }
    try {
        $d=db();
        if (!$d) throw new RuntimeException('No hay conexión a Railway.');
        initdb($d);
        $q=$d->prepare('UPDATE jobs_productos SET visible=? WHERE id=?');
        $q->execute([(int)$visible,$id]);
        message($q->rowCount()?'Visibilidad actualizada.':'No se encontró el producto o ya tenía ese estado.');
    } catch (Throwable $e) {
        error_log('Visibilidad producto: '.$e->getMessage());
        message('No se pudo actualizar la visibilidad.');
    }
    go('/admin/productos');
}
// Eliminación definitiva: la imagen y las reseñas asociadas se eliminan en la misma transacción.
// Los pedidos históricos conservan sus datos en items_json.
if ($path === '/admin/producto/eliminar' && $method === 'POST') {
    require_admin();
    verify_csrf();
    $id=(string)($_POST['id']??'');
    $confirm=(string)($_POST['confirmar']??'');
    if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/',$id) || $confirm!=='ELIMINAR') {
        message('Eliminación no confirmada.'); go('/admin/productos');
    }
    $d=db();
    if (!$d) { message('No hay conexión a Railway.'); go('/admin/productos'); }
    try {
        initdb($d);
        $d->beginTransaction();
        $q=$d->prepare('SELECT id FROM jobs_productos WHERE id=? FOR UPDATE');
        $q->execute([$id]);
        if (!$q->fetchColumn()) throw new RuntimeException('El producto ya no existe.');
        $q=$d->prepare('DELETE FROM jobs_imagenes WHERE producto_id=?'); $q->execute([$id]);
        $q=$d->prepare('DELETE FROM jobs_resenas WHERE producto_id=?'); $q->execute([$id]);
        $q=$d->prepare('DELETE FROM jobs_color_imagenes WHERE producto_id=?');$q->execute([$id]);
        $q=$d->prepare('DELETE FROM jobs_colores WHERE producto_id=?'); $q->execute([$id]);
        $q=$d->prepare('DELETE FROM jobs_productos WHERE id=?'); $q->execute([$id]);
        $d->commit();
        message('Producto eliminado definitivamente.');
    } catch (Throwable $e) {
        if ($d->inTransaction()) $d->rollBack();
        error_log('Eliminar producto: '.$e->getMessage());
        message('No se pudo eliminar el producto.');
    }
    go('/admin/productos');
}
if($path==='/admin/pedido/estado'&&$method==='POST'){
    require_admin();verify_csrf();
    $estado=(string)($_POST['estado']??'');$id=(int)($_POST['id']??0);
    if(!in_array($estado,['Pendiente','Confirmado','Enviado','Entregado','Cancelado'],true)||$id<1){message('Estado inválido.');go('/admin/pedidos');}
    $d=db();if(!$d){message('Railway no está disponible.');go('/admin/pedidos');}
    try{
        initdb($d);$d->beginTransaction();
        $q=$d->prepare('SELECT * FROM jobs_pedidos WHERE id=? FOR UPDATE');$q->execute([$id]);$pedido=$q->fetch();
        if(!$pedido)throw new RuntimeException('Pedido no encontrado.');
        if($pedido['estado']==='Cancelado' && $estado!=='Cancelado')throw new RuntimeException('No se puede reabrir un pedido cancelado: crea un pedido nuevo para reservar inventario.');
        if($estado==='Cancelado' && $pedido['estado']!=='Cancelado'){
            $lineas=json_decode((string)$pedido['items_json'],true);
            if(!is_array($lineas))throw new RuntimeException('No se pudieron leer los productos del pedido.');
            foreach($lineas as $linea){
                $pid=(string)($linea['id']??'');$n=(int)($linea['cantidad']??0);$color=(string)($linea['color']??'');
                if($n<1)throw new RuntimeException('Cantidad de pedido inválida.');
                $q=$d->prepare('SELECT nombre,stock FROM jobs_productos WHERE id=? FOR UPDATE');$q->execute([$pid]);$p=$q->fetch();
                // Historial preservado incluso cuando un artículo fue eliminado.
                if(!$p)continue;
                $before=(int)$p['stock'];
                if($color!==''){
                    $q=$d->prepare('SELECT stock FROM jobs_colores WHERE producto_id=? AND color=? FOR UPDATE');$q->execute([$pid,$color]);$prev=$q->fetchColumn();
                    if($prev===false)continue;
                    $before=(int)$prev;
                    $q=$d->prepare('UPDATE jobs_colores SET stock=stock+? WHERE producto_id=? AND color=?');$q->execute([$n,$pid,$color]);
                }
                $q=$d->prepare('UPDATE jobs_productos SET stock=stock+? WHERE id=?');$q->execute([$n,$pid]);
                jobs_registrar_movimiento($d,$pid,$p['nombre'],$color?:null,'devolucion',$n,$before,$before+$n,null,null,$pedido['referencia'],'Inventario devuelto por cancelación del pedido');
            }
        }
        $q=$d->prepare('UPDATE jobs_pedidos SET estado=? WHERE id=?');$q->execute([$estado,$id]);
        $d->commit();message('Estado del pedido actualizado.');
    }catch(Throwable $e){if($d->inTransaction())$d->rollBack();error_log('Pedido estado: '.$e->getMessage());message($e instanceof RuntimeException?$e->getMessage():'No se pudo actualizar el pedido.');}
    go('/admin/pedidos');
}
if($path==='/admin/resena/aprobar'&&$method==='POST'){require_admin();verify_csrf();if(db()){$q=db()->prepare('UPDATE jobs_resenas SET aprobada=? WHERE id=?');$q->execute([(int)($_POST['aprobar']??0),(int)($_POST['id']??0)]);}go('/admin/resenas');}

// Entradas y salidas manuales, siempre con bloqueo de filas y auditoría.
if ($path === '/admin/inventario/movimiento' && $method === 'POST') {
    require_admin(); verify_csrf();
    $d=db();
    if (!$d) { message('No hay conexión a Railway.'); go('/admin/mercancia/'.((($_POST['tipo']??'')==='salida')?'salida':'entrada')); }
    try {
        initdb($d);
        $id=(string)($_POST['producto_id']??'');
        $color=(string)($_POST['color']??'');
        $tipo=(string)($_POST['tipo']??'');
        $rawQty=(string)($_POST['cantidad']??'');
        $motivo=trim((string)($_POST['motivo']??''));
        $proveedor=trim((string)($_POST['proveedor']??''));
        $rawCost=trim((string)($_POST['costo_unitario']??''));
        if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/D',$id) || !in_array($tipo,['entrada','salida'],true) || !preg_match('/^[1-9][0-9]{0,4}$/D',$rawQty)) throw new RuntimeException('Datos del movimiento inválidos.');
        if (mb_strlen($motivo)>255 || mb_strlen($proveedor)>160 || ($tipo==='salida' && $motivo==='')) throw new RuntimeException('Especifica un motivo válido para la salida.');
        $cantidad=(int)$rawQty;
        $costo=null;
        if($rawCost!==''){
            if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$rawCost))throw new RuntimeException('Costo unitario inválido.');
            $costo=(float)$rawCost;
        }
        $d->beginTransaction();
        $q=$d->prepare('SELECT id,nombre,stock FROM jobs_productos WHERE id=? FOR UPDATE');$q->execute([$id]);$producto=$q->fetch();
        if(!$producto) throw new RuntimeException('No existe ese producto.');
        $v=$d->prepare('SELECT color,stock FROM jobs_colores WHERE producto_id=? ORDER BY color FOR UPDATE');$v->execute([$id]);$variants=$v->fetchAll();
        $previous=(int)$producto['stock'];
        if($variants){
            $row=null;foreach($variants as $r){if($r['color']===$color){$row=$r;break;}}
            if(!$row)throw new RuntimeException('Selecciona un color válido del producto.');
            $previous=(int)$row['stock'];
        } elseif ($color!=='') throw new RuntimeException('Este producto no utiliza colores.');
        $next=$previous+($tipo==='entrada'?$cantidad:-$cantidad);
        $nextTotal=(int)$producto['stock']+($tipo==='entrada'?$cantidad:-$cantidad);
        if($next<0||$nextTotal<0)throw new RuntimeException('No hay existencias suficientes para esa salida.');
        if($next>99999||$nextTotal>999999)throw new RuntimeException('La cantidad supera el inventario máximo permitido.');
        if($variants){$u=$d->prepare('UPDATE jobs_colores SET stock=? WHERE producto_id=? AND color=?');$u->execute([$next,$id,$color]);}
        $u=$d->prepare('UPDATE jobs_productos SET stock=? WHERE id=?');$u->execute([$nextTotal,$id]);
        jobs_registrar_movimiento($d,$id,$producto['nombre'],$color?:null,$tipo,$cantidad,$previous,$next,$costo,$proveedor?:null,null,$motivo?:($tipo==='entrada'?'Ingreso de mercancía':'Salida de mercancía'));
        $d->commit();message('Movimiento registrado e inventario actualizado.');
    } catch(Throwable $e) {
        if($d->inTransaction())$d->rollBack();
        error_log('Movimiento de inventario: '.$e->getMessage());
        message($e instanceof RuntimeException?$e->getMessage():'No se pudo registrar el movimiento.');
    }
    go('/admin/mercancia/'.((($_POST['tipo']??'')==='salida')?'salida':'entrada'));
}
