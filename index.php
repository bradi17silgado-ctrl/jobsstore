<?php
declare(strict_types=1);
define('JOBS_APP', true);
require_once __DIR__.'/security.php';
jobs_start_secure_session();
jobs_security_headers();
function envv(string $k,string $fallback=''):string { $v=getenv($k); if($v!==false)return $v; static $cfg=null; if($cfg===null){$cfg=[]; if(is_file(__DIR__.'/.env'))foreach(file(__DIR__.'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l){$l=trim($l);if($l===''||str_starts_with($l,'#')||!str_contains($l,'='))continue;[$a,$b]=explode('=',$l,2);$cfg[trim($a)]=trim($b," \"'");}}return $cfg[$k]??$fallback; }
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function money($v):string{return '$'.number_format((float)$v,0,',','.');}
function db():?PDO {static $pdo=null,$attempt=false;if($attempt)return $pdo;$attempt=true;try{$host=envv('DB_HOST');if(!$host)return null;$pdo=new PDO('mysql:host='.$host.';port='.envv('DB_PORT','3306').';dbname='.envv('DB_NAME').';charset=utf8mb4',envv('DB_USER'),envv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $pdo;}catch(Throwable $e){error_log('Database unavailable: '.$e->getMessage());return null;}}
function initdb(PDO $db):void{
$db->exec('CREATE TABLE IF NOT EXISTS jobs_productos (id VARCHAR(32) PRIMARY KEY,nombre VARCHAR(160) NOT NULL,categoria VARCHAR(60) NOT NULL,descripcion TEXT,precio DECIMAL(12,2) NOT NULL,imagen VARCHAR(255) NOT NULL,stock INT NOT NULL DEFAULT 0,destacado TINYINT NOT NULL DEFAULT 0,visible TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_colores (producto_id VARCHAR(32) NOT NULL, color VARCHAR(32) NOT NULL, stock INT NOT NULL DEFAULT 0, PRIMARY KEY(producto_id,color), INDEX idx_color_producto (producto_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_color_imagenes (producto_id VARCHAR(32) NOT NULL, color VARCHAR(32) NOT NULL, mime VARCHAR(32) NOT NULL, contenido MEDIUMBLOB NOT NULL, PRIMARY KEY(producto_id,color)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_imagenes (producto_id VARCHAR(32) PRIMARY KEY, mime VARCHAR(32) NOT NULL, contenido MEDIUMBLOB NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_pedidos (id BIGINT AUTO_INCREMENT PRIMARY KEY,referencia VARCHAR(32) NOT NULL UNIQUE,cliente VARCHAR(120) NOT NULL,telefono VARCHAR(35) NOT NULL,ciudad VARCHAR(180),total DECIMAL(12,2) NOT NULL,estado VARCHAR(35) NOT NULL DEFAULT \'Pendiente\',items_json JSON NOT NULL,creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
// Migración compatible con pedidos anteriores: conservar ciudad y añadir dirección opcional.
$col=$db->query("SHOW COLUMNS FROM jobs_pedidos LIKE 'direccion'")->fetch();
if (!$col) { $db->exec("ALTER TABLE jobs_pedidos ADD COLUMN direccion VARCHAR(255) NULL AFTER ciudad"); }
$db->exec('CREATE TABLE IF NOT EXISTS jobs_movimientos (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, producto_id VARCHAR(32) NULL, producto_nombre VARCHAR(160) NOT NULL, color VARCHAR(32) NULL, tipo VARCHAR(20) NOT NULL, cantidad INT NOT NULL, stock_anterior INT NOT NULL, stock_nuevo INT NOT NULL, costo_unitario DECIMAL(12,2) NULL, proveedor VARCHAR(160) NULL, referencia VARCHAR(40) NULL, motivo VARCHAR(255) NULL, usuario VARCHAR(60) NOT NULL DEFAULT \'administrador\', creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_mov_fecha (creado), INDEX idx_mov_producto (producto_id,creado), INDEX idx_mov_tipo (tipo,creado)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_ingresos_anulados (movimiento_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, motivo VARCHAR(255) NOT NULL, usuario VARCHAR(60) NOT NULL DEFAULT \'administrador\', creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_anul_creado (creado)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_resenas (id BIGINT AUTO_INCREMENT PRIMARY KEY,producto_id VARCHAR(32) NOT NULL,cliente VARCHAR(100) NOT NULL,calificacion TINYINT NOT NULL,comentario TEXT NOT NULL,aprobada TINYINT NOT NULL DEFAULT 0,creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_config (clave VARCHAR(60) PRIMARY KEY, valor VARCHAR(60) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$seeded=$db->query("SELECT valor FROM jobs_config WHERE clave='productos_inicializados'")->fetchColumn();
if ($seeded===false) {
    $cnt=(int)$db->query('SELECT COUNT(*) FROM jobs_productos')->fetchColumn();
    if (!$cnt) {
        $stmt=$db->prepare('INSERT INTO jobs_productos (id,nombre,categoria,descripcion,precio,imagen,stock,destacado) VALUES (?,?,?,?,?,?,?,?)');
        foreach (json_decode(file_get_contents(__DIR__.'/data/productos.json'),true) ?: [] as $p) {
            $stmt->execute([$p['id'],$p['nombre'],$p['categoria'],$p['descripcion'],$p['precio'],$p['imagen'],$p['stock'],(int)$p['destacado']]);
        }
    }
    $db->exec("INSERT INTO jobs_config(clave,valor) VALUES('productos_inicializados','1')");
}}
function products(bool $all=false):array{$d=db();if($d){try{initdb($d);$q=$d->query('SELECT * FROM jobs_productos '.($all?'':'WHERE visible=1 ').'ORDER BY categoria,nombre');$rows=$q->fetchAll();$colors=$d->query('SELECT c.producto_id,c.color,c.stock,IF(i.producto_id IS NULL,0,1) AS imagen_disponible FROM jobs_colores c LEFT JOIN jobs_color_imagenes i ON i.producto_id=c.producto_id AND i.color=c.color ORDER BY c.color')->fetchAll();$colorsByProduct=[];foreach($colors as $c){$colorsByProduct[$c['producto_id']][]=['color'=>$c['color'],'stock'=>(int)$c['stock'],'imagen_url'=>!empty($c['imagen_disponible'])?'/media/color/'.rawurlencode($c['producto_id']).'/'.rawurlencode($c['color']):''];}foreach($rows as &$p){$p['precio']=(float)$p['precio'];$p['stock']=(int)$p['stock'];$p['colores']=$colorsByProduct[$p['id']]??[];$p['imagen_url']=str_starts_with((string)$p['imagen'],'db:')?'/media/producto/'.rawurlencode($p['id']):'/static/img/'.ltrim($p['imagen'],'/');}return $rows;}catch(Throwable $e){error_log($e->getMessage());}}$rows=json_decode(file_get_contents(__DIR__.'/data/productos.json'),true)?:[];foreach($rows as &$p){$p['imagen_url']='/static/img/'.$p['imagen'];$p['colores']=[];}return $all?$rows:array_values(array_filter($rows,static fn($p)=>!isset($p['visible'])||$p['visible']));}
function csrf():string {return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function verify_csrf():void{if(!hash_equals(csrf(),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf']??''))){http_response_code(403);exit('Solicitud inválida (CSRF).');}}
function admin():bool{
 if(empty($_SESSION['admin']))return false;
 $now=time();
 if(($now-(int)($_SESSION['admin_last_seen']??0))>1800){
   unset($_SESSION['admin'],$_SESSION['admin_last_seen']);return false;
 }
 $_SESSION['admin_last_seen']=$now;
 return true;
}
function go(string $path):never{header('Location: '.$path, true,303);exit;}
function require_admin():void{if(!admin())go('/admin/login');}
function message(string $s):void{$_SESSION['message']=$s;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?: '/'; if($path!=='/')$path=rtrim($path,'/');$method=$_SERVER['REQUEST_METHOD'];
// Fotografías reales asignadas a cada variante de color.
if ($method==='GET' && preg_match('~^/media/color/([a-zA-Z0-9_-]{1,32})/(negro|blanco|gris|azul-marino|azul-rey|rojo|verde-militar|beige|cafe|rosado|dorado|plateado|morado|vinotinto)$~D',$path,$m)) {
    $d=db();if(!$d){http_response_code(503);exit;}
    try { initdb($d);$q=$d->prepare('SELECT i.mime,i.contenido FROM jobs_color_imagenes i JOIN jobs_productos p ON p.id=i.producto_id WHERE i.producto_id=? AND i.color=? AND (p.visible=1 OR ?=1)');$q->execute([$m[1],$m[2],admin()?1:0]);$img=$q->fetch();if(!$img){http_response_code(404);exit;}header('Content-Type: '.$img['mime']);header('Cache-Control: public, max-age=300');header('X-Content-Type-Options: nosniff');echo $img['contenido'];exit; }
    catch(Throwable $e){error_log('Imagen color: '.$e->getMessage());http_response_code(500);exit;}
}
// Imágenes persistentes almacenadas en MySQL (sin depender del disco efímero de Render).
if ($method === 'GET' && preg_match('~^/media/producto/([a-zA-Z0-9_-]{1,32})$~', $path, $mediaMatch)) {
    $d=db();
    if (!$d) { http_response_code(503); exit; }
    try {
        initdb($d);
        $q=$d->prepare('SELECT i.mime,i.contenido FROM jobs_imagenes i INNER JOIN jobs_productos p ON p.id=i.producto_id WHERE i.producto_id=? AND (p.visible=1 OR ?=1)');
        $q->execute([$mediaMatch[1], admin()?1:0]);
        $img=$q->fetch();
        if (!$img) { http_response_code(404); exit; }
        header('Content-Type: '.$img['mime']);
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        echo $img['contenido']; exit;
    } catch (Throwable $e) { error_log('Image delivery: '.$e->getMessage()); http_response_code(500); exit; }
}
if ($path==='/admin/informes/pdf' && $method==='GET') {
    require_admin();
    require_once __DIR__.'/app_admin/informes_lib.php';
    jobs_descargar_informe_pdf();
}
$all=products();$byId=[];foreach($all as $p)$byId[$p['id']]=$p;
require_once __DIR__.'/app_admin/informes_lib.php';
require_once __DIR__.'/app_admin/actions.php';
// Las redirecciones deben ejecutarse antes de generar cualquier HTML.
if (in_array($path, ['/admin','/admin/productos','/admin/producto/nuevo','/admin/pedidos','/admin/resenas','/admin/ventas','/admin/mercancia/entrada','/admin/mercancia/salida','/admin/movimientos'], true) && !admin()) {
    go('/admin/login');
}
// Responder con 404 antes de enviar la plantilla HTML.
$isPublicPage = in_array($path, ['/', '/inicio', '/catalogo', '/seguimiento', '/informacion', '/pedido/confirmado', '/admin', '/admin/login', '/admin/informes', '/admin/informes/pdf', '/admin/productos', '/admin/producto/nuevo', '/admin/pedidos', '/admin/resenas', '/admin/ventas', '/admin/mercancia/entrada', '/admin/mercancia/salida', '/admin/movimientos'], true);
$isAction = $method === 'POST' && in_array($path, ['/pedido/crear','/resenas/crear','/admin/login','/admin/salir','/admin/producto/guardar','/admin/producto/eliminar','/admin/producto/visibilidad','/admin/pedido/estado','/admin/resena/aprobar','/admin/inventario/movimiento','/admin/inventario/anular-entrada'], true);
$isProductPage = preg_match('~^/producto/([a-zA-Z0-9_-]+)$~', $path, $productMatch) && isset($byId[$productMatch[1]]);
if (!$isPublicPage && !$isProductPage && !$isAction) {
    http_response_code(404);
}
if($path==='/pedido/crear'&&$method==='POST'){verify_csrf();jobs_rate_limit('order',12,900);header('Content-Type: application/json; charset=utf-8');$d=db();if(!$d){http_response_code(503);echo json_encode(['error'=>'La base de datos no está configurada. Puedes pedir por WhatsApp.']);exit;}$payload=json_decode(file_get_contents('php://input'),true);if(!is_array($payload))$payload=$_POST;$items=$payload['items']??[];$cliente=trim((string)($payload['nombre']??''));$telefono=trim((string)($payload['telefono']??''));$ciudad=substr(trim((string)($payload['ciudad']??'')),0,180);$direccion=substr(trim((string)($payload['direccion']??'')),0,255);if(!is_array($items)||!$items||count($items)>30||$cliente===''||$telefono===''||strlen($cliente)>480||strlen($telefono)>35){http_response_code(422);echo json_encode(['error'=>'Indica nombre, teléfono y productos.']);exit;}try{initdb($d);$d->beginTransaction();$total=0;$saved=[];$q=$d->prepare('SELECT id,nombre,precio,stock FROM jobs_productos WHERE id=? AND visible=1 FOR UPDATE');
$colorQuery=$d->prepare('SELECT color,stock FROM jobs_colores WHERE producto_id=? AND color=? FOR UPDATE');
$colorCount=$d->prepare('SELECT COUNT(*) FROM jobs_colores WHERE producto_id=?');
$updateColor=$d->prepare('UPDATE jobs_colores SET stock=stock-? WHERE producto_id=? AND color=? AND stock>=?');
$updateProduct=$d->prepare('UPDATE jobs_productos SET stock=stock-? WHERE id=? AND stock>=?');
foreach($items as $cartKey=>$qty){
 if(!is_string($cartKey) || !preg_match('/^([a-zA-Z0-9_-]{1,32})(?:::(negro|blanco|gris|azul-marino|azul-rey|rojo|verde-militar|beige|cafe|rosado|dorado|plateado|morado|vinotinto))?$/D',$cartKey,$match) || !is_numeric($qty) || (int)$qty<1 || (int)$qty>99 || (string)(int)$qty!==(string)$qty) throw new RuntimeException('Cantidad, color o producto inválido');
 $id=$match[1];$color=$match[2]??null;$n=(int)$qty;
 $q->execute([$id]);$p=$q->fetch();if(!$p || (int)$p['stock']<$n)throw new RuntimeException('No hay existencias suficientes de '.$id);
 $colorCount->execute([$id]);$hasVariants=(int)$colorCount->fetchColumn()>0;
 if($hasVariants){
  if(!$color)throw new RuntimeException('Selecciona el color del producto '.$p['nombre']);
  $colorQuery->execute([$id,$color]);$variant=$colorQuery->fetch();
  if(!$variant||(int)$variant['stock']<$n)throw new RuntimeException('No hay suficientes unidades en color '.str_replace('-',' ',$color));
  $updateColor->execute([$n,$id,$color,$n]);if($updateColor->rowCount()!==1)throw new RuntimeException('El stock del color cambió. Inténtalo nuevamente.');
 }elseif($color!==null)throw new RuntimeException('Color no disponible para '.$p['nombre']);
 $updateProduct->execute([$n,$id,$n]);if($updateProduct->rowCount()!==1)throw new RuntimeException('Stock insuficiente.');
 $saved[]=['id'=>$id,'nombre'=>$p['nombre'],'color'=>$color,'cantidad'=>$n,'precio'=>(float)$p['precio']];$total+=$n*(float)$p['precio'];
}
$ref='JOB-'.strtoupper(bin2hex(random_bytes(5)));$stmt=$d->prepare('INSERT INTO jobs_pedidos(referencia,cliente,telefono,ciudad,direccion,total,items_json) VALUES(?,?,?,?,?,?,?)');$stmt->execute([$ref,$cliente,$telefono,$ciudad,$direccion,$total,json_encode($saved,JSON_UNESCAPED_UNICODE)]);
foreach ($saved as $line) {
    $oldVariant = null;
    if (!empty($line['color'])) {
        $c=$d->prepare('SELECT stock FROM jobs_colores WHERE producto_id=? AND color=?');
        $c->execute([$line['id'],$line['color']]);
        $oldVariant=(int)$c->fetchColumn()+(int)$line['cantidad'];
    } else {
        $c=$d->prepare('SELECT stock FROM jobs_productos WHERE id=?');
        $c->execute([$line['id']]);
        $oldVariant=(int)$c->fetchColumn()+(int)$line['cantidad'];
    }
    jobs_registrar_movimiento($d, $line['id'], $line['nombre'], $line['color'], 'pedido', (int)$line['cantidad'], $oldVariant, $oldVariant-(int)$line['cantidad'], null, null, $ref, 'Salida por pedido registrado', 'tienda');
}
$d->commit();
// La confirmación se consulta mediante un token temporal de sesión, sin exponer datos privados.
$token=bin2hex(random_bytes(24));
if (!isset($_SESSION['pedidos_confirmados']) || !is_array($_SESSION['pedidos_confirmados'])) $_SESSION['pedidos_confirmados']=[];
$_SESSION['pedidos_confirmados'][$token]=['referencia'=>$ref,'total'=>$total,'items'=>$saved,'creado'=>time()];
foreach ($_SESSION['pedidos_confirmados'] as $k=>$registro) {
    if (!isset($registro['creado']) || (int)$registro['creado']<time()-86400) unset($_SESSION['pedidos_confirmados'][$k]);
}
// Limitar el número de comprobantes de la sesión.
$_SESSION['pedidos_confirmados']=array_slice($_SESSION['pedidos_confirmados'],-10,null,true);
echo json_encode(['referencia'=>$ref,'total'=>$total,'confirmacion_url'=>'/pedido/confirmado?token='.$token]);}catch(Throwable $e){if($d->inTransaction())$d->rollBack();http_response_code(422);$msg=$e instanceof RuntimeException?$e->getMessage():'No se pudo procesar el pedido.';error_log('Pedido rechazado: '.$e->getMessage());echo json_encode(['error'=>$msg]);}exit;}
if($path==='/resenas/crear'&&$method==='POST'){verify_csrf();jobs_rate_limit('review',8,900);$id=(string)($_POST['id']??'');$d=db();if($d&&isset($byId[$id])){initdb($d);$q=$d->prepare('INSERT INTO jobs_resenas(producto_id,cliente,calificacion,comentario) VALUES (?,?,?,?)');$q->execute([$id,substr(trim((string)($_POST['cliente']??'')),0,100),max(1,min(5,(int)($_POST['calificacion']??5))),substr(trim((string)($_POST['comentario']??'')),0,2000)]);message('Reseña enviada para revisión.');}go('/producto/'.rawurlencode($id));}
$jobsPalette=['negro'=>'#171717','blanco'=>'#ffffff','gris'=>'#969ba3','azul-marino'=>'#172a4b','azul-rey'=>'#2458d3','rojo'=>'#c62828','verde-militar'=>'#606c38','beige'=>'#d5be9a','cafe'=>'#754c35','rosado'=>'#eba5b9','dorado'=>'#d2ae45','plateado'=>'#c1c5cc','morado'=>'#8557ad','vinotinto'=>'#76283e'];
$wa=preg_replace('/\D/','',envv('WHATSAPP_NUMBER','573227879835'));$page='Inicio';$desc='Gorras y relojes con estilo';
function product_card(array $p):void{ $stock=(int)$p['stock']; ?>
<article class="product-card premium-product-card">
  <a class="product-card-media" href="/producto/<?=h(rawurlencode($p['id']))?>" aria-label="Ver <?=h($p['nombre'])?>">
    <img loading="lazy" decoding="async" data-product-image src="<?=h($p['imagen_url'])?>" alt="<?=h($p['nombre'])?>">
    <span class="product-card-chip"><?=h(ucfirst($p['categoria']))?></span>
  </a>
  <div class="product-card-content">
    <p class="product-card-category">JOBS · <?=h(ucfirst($p['categoria']))?></p>
    <h3><a href="/producto/<?=h(rawurlencode($p['id']))?>"><?=h($p['nombre'])?></a></h3>
    <p class="product-card-price"><?=money($p['precio'])?></p>
    <p class="product-card-stock <?= $stock>0?'is-available':'is-soldout' ?>"><?= $stock>0?'● Disponible':'● Agotado' ?></p>
<?php if (!empty($p['colores'])): ?>
  <fieldset class="jobs-swatch-fieldset" data-color-group="<?=h($p['id'])?>" data-default-image="<?=h($p['imagen_url'])?>">
    <legend>Selecciona el color: <strong data-selected-color>Elige un color</strong></legend>
    <div class="jobs-swatch-list" role="radiogroup" aria-label="Colores disponibles de <?=h($p['nombre'])?>">
    <?php foreach ($p['colores'] as $variant):
      $slug=$variant['color'];$name=ucwords(str_replace('-',' ',$slug));$qty=(int)$variant['stock'];$hex=($GLOBALS['jobsPalette'][$slug]??'#aaaaaa'); ?>
      <label class="jobs-swatch-option" title="<?=h($name)?>">
        <input type="radio" name="color-<?=h($p['id'])?>" value="<?=h($slug)?>" data-color-for="<?=h($p['id'])?>" data-color-image="<?=h($variant['imagen_url']??'')?>" <?=$qty<1?'disabled':''?> required>
        <span class="jobs-swatch" style="--swatch:<?=h($hex)?>"></span>
        <span class="jobs-swatch-name"><?=h($name)?></span>
      </label>
    <?php endforeach; ?>
    </div><small class="jobs-swatch-stock" data-color-stock>Elige el color que prefieras</small>
  </fieldset>
<?php endif; ?>
    <button type="button" class="btn btn-primary product-card-buy" data-add="<?=h($p['id'])?>" <?= $stock<1?'disabled':'' ?>><?= $stock>0?'Agregar al carrito':'Sin existencias' ?></button>
  </div>
</article><?php }

?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>JOBS Store | <?=h($page)?></title><meta name="description" content="<?=h($desc)?>"><link rel="icon" href="/static/img/favicon.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/static/style.css?v=admin-buttons-20261113"><style>.jobs-wrap{max-width:1160px;margin:auto;padding:28px 20px}.jobs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:24px}.jobs-form{display:grid;gap:12px;max-width:580px}.jobs-form input,.jobs-form textarea,.jobs-form select{padding:12px;border:1px solid #ccd4df;border-radius:8px;width:100%}.jobs-table{width:100%;border-collapse:collapse;background:white}.jobs-table td,.jobs-table th{padding:10px;border-bottom:1px solid #ddd;text-align:left}.jobs-table img{width:50px}.jobs-panel{padding:22px;background:#fff;border-radius:16px;margin:20px 0}.jobs-msg{background:#fef3c7;padding:12px;border-radius:8px}.jobs-drawer{position:fixed;right:0;top:0;bottom:0;z-index:999;background:white;width:min(440px,100%);padding:24px;overflow:auto;box-shadow:0 0 0 100vmax #0008;display:none}.jobs-drawer.open{display:block}.jobs-drawer input{padding:10px;margin:5px 0;width:100%;border:1px solid #ccc;border-radius:8px}.jobs-drawer button{margin:5px}.jobs-item{padding:10px 0;border-bottom:1px solid #ddd}.jobs-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center}</style></head><body><header class="topbar"><div class="wrap topbar-in"><a href="/" class="brand"><img src="/static/img/logo-emblema.png" alt="" width="44"><span class="brand-txt"><b>JOBS</b> <i>Store</i></span></a><nav class="nav" id="nav" aria-label="Menú principal"><div class="menu-side-title">Menú <button type="button" id="closeMenu" aria-label="Cerrar menú">✕</button></div><a href="/">Inicio</a><a href="/catalogo">Catálogo</a><a href="/catalogo?c=gorra">Gorras</a><a href="/catalogo?c=reloj">Relojes</a><a href="/seguimiento">Seguimiento</a><a href="/informacion">Ayuda</a><a href="/admin">Administración</a></nav><div class="menu-overlay" id="menuOverlay" aria-hidden="true"></div><div class="topbar-actions"><button class="cart-btn" id="openCart" aria-label="Abrir carrito">🛍️ <span id="cartCount">0</span></button><button class="burger" id="burger" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav">☰</button></div></div></header><main id="contenido">
<?php if($m=$_SESSION['message']??null){unset($_SESSION['message']);echo '<div class="jobs-wrap jobs-msg">'.h($m).'</div>';} ?>
<?php if($path==='/'||$path==='/inicio'){?><?php include __DIR__ . "/views/inicio.php"; }
elseif($path==='/catalogo'){ $cat=(string)($_GET['c']??'');?><?php include __DIR__ . "/views/catalogo.php"; }
elseif(preg_match('~^/producto/([a-zA-Z0-9_-]+)$~',$path,$m)&&isset($byId[$m[1]])){$p=$byId[$m[1]];?><?php include __DIR__ . "/views/producto.php"; }
elseif($path==='/pedido/confirmado'){include __DIR__.'/views/pedido_confirmado.php';}
elseif($path==='/seguimiento'){?><?php include __DIR__ . "/views/seguimiento.php"; }
elseif($path==='/informacion'){?><?php include __DIR__ . "/views/informacion.php"; }
elseif($path==='/admin/login'){header('Cache-Control: no-store, private');include __DIR__.'/app_admin/login.php';}
elseif(in_array($path,['/admin','/admin/productos','/admin/producto/nuevo','/admin/pedidos','/admin/resenas'],true)){require_admin();header('Cache-Control: no-store, private');include __DIR__.'/app_admin/dashboard.php';}
elseif(in_array($path,['/admin/informes','/admin/ventas','/admin/mercancia/entrada','/admin/mercancia/salida','/admin/movimientos'],true)){require_admin();header('Cache-Control: no-store, private');include __DIR__.'/app_admin/informes.php';}
else{http_response_code(404);?><section class="jobs-wrap"><h1>Página no encontrada</h1><a href="/">Volver al inicio</a></section><?php }?></main><footer class="footer"><div class="wrap footer-in"><div><a class="footer-brand" href="/">JOBS <span>STORE</span></a><p>Accesorios con carácter. Tu estilo, tus reglas.</p></div><div><h4>Explora</h4><a href="/catalogo">Colección completa</a><a href="/catalogo?c=gorra">Gorras</a><a href="/catalogo?c=reloj">Relojes</a></div><div><h4>Te ayudamos</h4><a href="/seguimiento">Rastrea tu pedido</a><a href="/informacion">Preguntas frecuentes</a><a href="https://wa.me/<?=h($wa)?>" target="_blank" rel="noopener">Atención por WhatsApp</a></div></div><div class="foot-bar"><div class="wrap">© <?=date('Y')?> JOBS Store</div></div></footer><aside class="jobs-drawer" id="cartDrawer" aria-label="Mi pedido"><div class="cart-drawer-heading"><div><span class="premium-kicker">TU SELECCIÓN</span><h2>Mi carrito</h2></div><button id="closeCart" class="btn cart-close" aria-label="Cerrar carrito">✕</button></div><p class="cart-drawer-caption">Revisa tus artículos y completa tus datos para continuar.</p><div id="cartItems"></div><h3 id="cartTotal">$0</h3><label class="cart-field">Nombre completo<input id="clientName" placeholder="¿Cómo te llamas?" autocomplete="name"></label><label class="cart-field">Teléfono<input id="clientPhone" placeholder="Número de contacto" autocomplete="tel" inputmode="tel"></label><label class="cart-field">Ciudad<input id="clientCity" placeholder="Ciudad de entrega" autocomplete="address-level2" maxlength="180"></label><label class="cart-field">Dirección de entrega<input id="clientAddress" placeholder="Calle, número y detalles" autocomplete="street-address" maxlength="255"></label><button id="saveOrder" class="btn btn-primary">Registrar pedido</button><button id="sendWa" class="btn btn-wa">Enviar por WhatsApp</button><button id="clearCart" class="btn">Vaciar carrito</button><p>Los pagos en línea no están habilitados hasta configurar y verificar una pasarela.</p></aside><script>window.JOBS={productos:<?=json_encode($all,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,whatsapp:<?=json_encode($wa)?>,csrf:<?=json_encode(csrf())?>};</script><script src="/static/store-php.js?v=stock-privacy-20261113"></script></body></html>
