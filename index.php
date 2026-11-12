<?php
declare(strict_types=1);
define('JOBS_APP', true);
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','use_strict_mode'=>true]);
header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
function envv(string $k,string $fallback=''):string { $v=getenv($k); if($v!==false)return $v; static $cfg=null; if($cfg===null){$cfg=[]; if(is_file(__DIR__.'/.env'))foreach(file(__DIR__.'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l){$l=trim($l);if($l===''||str_starts_with($l,'#')||!str_contains($l,'='))continue;[$a,$b]=explode('=',$l,2);$cfg[trim($a)]=trim($b," \"'");}}return $cfg[$k]??$fallback; }
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function money($v):string{return '$'.number_format((float)$v,0,',','.');}
function db():?PDO {static $pdo=null,$attempt=false;if($attempt)return $pdo;$attempt=true;try{$host=envv('DB_HOST');if(!$host)return null;$pdo=new PDO('mysql:host='.$host.';port='.envv('DB_PORT','3306').';dbname='.envv('DB_NAME').';charset=utf8mb4',envv('DB_USER'),envv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $pdo;}catch(Throwable $e){error_log('Database unavailable: '.$e->getMessage());return null;}}
function initdb(PDO $db):void{
$db->exec('CREATE TABLE IF NOT EXISTS jobs_productos (id VARCHAR(32) PRIMARY KEY,nombre VARCHAR(160) NOT NULL,categoria VARCHAR(60) NOT NULL,descripcion TEXT,precio DECIMAL(12,2) NOT NULL,imagen VARCHAR(255) NOT NULL,stock INT NOT NULL DEFAULT 0,destacado TINYINT NOT NULL DEFAULT 0,visible TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_imagenes (producto_id VARCHAR(32) PRIMARY KEY, mime VARCHAR(32) NOT NULL, contenido MEDIUMBLOB NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS jobs_pedidos (id BIGINT AUTO_INCREMENT PRIMARY KEY,referencia VARCHAR(32) NOT NULL UNIQUE,cliente VARCHAR(120) NOT NULL,telefono VARCHAR(35) NOT NULL,ciudad VARCHAR(180),total DECIMAL(12,2) NOT NULL,estado VARCHAR(35) NOT NULL DEFAULT \'Pendiente\',items_json JSON NOT NULL,creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
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
function products(bool $all=false):array{$d=db();if($d){try{initdb($d);$q=$d->query('SELECT * FROM jobs_productos '.($all?'':'WHERE visible=1 ').'ORDER BY categoria,nombre');$rows=$q->fetchAll();foreach($rows as &$p){$p['precio']=(float)$p['precio'];$p['stock']=(int)$p['stock'];$p['imagen_url']=str_starts_with((string)$p['imagen'],'db:')?'/media/producto/'.rawurlencode($p['id']):'/static/img/'.ltrim($p['imagen'],'/');}return $rows;}catch(Throwable $e){error_log($e->getMessage());}}$rows=json_decode(file_get_contents(__DIR__.'/data/productos.json'),true)?:[];foreach($rows as &$p)$p['imagen_url']='/static/img/'.$p['imagen'];return $all?$rows:array_values(array_filter($rows,static fn($p)=>!isset($p['visible'])||$p['visible']));}
function csrf():string {return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function verify_csrf():void{if(!hash_equals(csrf(),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf']??''))){http_response_code(403);exit('Solicitud inválida (CSRF).');}}
function admin():bool{return !empty($_SESSION['admin']);}
function go(string $path):never{header('Location: '.$path, true,303);exit;}
function require_admin():void{if(!admin())go('/admin/login');}
function message(string $s):void{$_SESSION['message']=$s;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?: '/'; if($path!=='/')$path=rtrim($path,'/');$method=$_SERVER['REQUEST_METHOD'];
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
$all=products();$byId=[];foreach($all as $p)$byId[$p['id']]=$p;
require_once __DIR__.'/app_admin/actions.php';
// Las redirecciones deben ejecutarse antes de generar cualquier HTML.
if ($path === '/admin' && !admin()) {
    go('/admin/login');
}
// Responder con 404 antes de enviar la plantilla HTML.
$isPublicPage = in_array($path, ['/', '/inicio', '/catalogo', '/seguimiento', '/informacion', '/admin', '/admin/login'], true);
$isAction = $method === 'POST' && in_array($path, ['/pedido/crear','/resenas/crear','/admin/login','/admin/salir','/admin/producto/guardar','/admin/producto/eliminar','/admin/producto/visibilidad','/admin/pedido/estado','/admin/resena/aprobar'], true);
$isProductPage = preg_match('~^/producto/([a-zA-Z0-9_-]+)$~', $path, $productMatch) && isset($byId[$productMatch[1]]);
if (!$isPublicPage && !$isProductPage && !$isAction) {
    http_response_code(404);
}
if($path==='/pedido/crear'&&$method==='POST'){verify_csrf();header('Content-Type: application/json; charset=utf-8');$d=db();if(!$d){http_response_code(503);echo json_encode(['error'=>'La base de datos no está configurada. Puedes pedir por WhatsApp.']);exit;}$payload=json_decode(file_get_contents('php://input'),true);if(!is_array($payload))$payload=$_POST;$items=$payload['items']??[];$cliente=trim((string)($payload['nombre']??''));$telefono=trim((string)($payload['telefono']??''));$ciudad=trim((string)($payload['ciudad']??''));if(!is_array($items)||!$items||$cliente===''||$telefono===''){http_response_code(422);echo json_encode(['error'=>'Indica nombre, teléfono y productos.']);exit;}try{initdb($d);$d->beginTransaction();$total=0;$saved=[];$q=$d->prepare('SELECT id,nombre,precio,stock FROM jobs_productos WHERE id=? AND visible=1 FOR UPDATE');foreach($items as $id=>$qty){if(!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{1,32}$/',$id)||!is_numeric($qty)|| (int)$qty<1||(int)$qty>99)throw new RuntimeException('Cantidad o producto inválido');$q->execute([$id]);$p=$q->fetch();if(!$p||$p['stock']<(int)$qty)throw new RuntimeException('No hay existencias suficientes para '.$id);$saved[]=['id'=>$id,'nombre'=>$p['nombre'],'cantidad'=>(int)$qty,'precio'=>(float)$p['precio']];$total+=(int)$qty*(float)$p['precio'];}$ref='JOB-'.strtoupper(bin2hex(random_bytes(5)));$stmt=$d->prepare('INSERT INTO jobs_pedidos(referencia,cliente,telefono,ciudad,total,items_json) VALUES(?,?,?,?,?,?)');$stmt->execute([$ref,$cliente,$telefono,$ciudad,$total,json_encode($saved,JSON_UNESCAPED_UNICODE)]);$u=$d->prepare('UPDATE jobs_productos SET stock=stock-? WHERE id=?');foreach($saved as $p)$u->execute([$p['cantidad'],$p['id']]);$d->commit();echo json_encode(['referencia'=>$ref,'total'=>$total]);}catch(Throwable $e){if($d->inTransaction())$d->rollBack();http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}exit;}
if($path==='/resenas/crear'&&$method==='POST'){verify_csrf();$id=(string)($_POST['id']??'');$d=db();if($d&&isset($byId[$id])){initdb($d);$q=$d->prepare('INSERT INTO jobs_resenas(producto_id,cliente,calificacion,comentario) VALUES (?,?,?,?)');$q->execute([$id,substr(trim((string)($_POST['cliente']??'')),0,100),max(1,min(5,(int)($_POST['calificacion']??5))),substr(trim((string)($_POST['comentario']??'')),0,2000)]);message('Reseña enviada para revisión.');}go('/producto/'.rawurlencode($id));}
$wa=preg_replace('/\D/','',envv('WHATSAPP_NUMBER','573227879835'));$page='Inicio';$desc='Gorras y relojes con estilo';
function product_card(array $p):void{?> <article class="product-card" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px #001b3b16"><a href="/producto/<?=h(rawurlencode($p['id']))?>"><img style="width:100%;aspect-ratio:1;object-fit:cover" src="<?=h($p['imagen_url'])?>" alt="<?=h($p['nombre'])?>"></a><div style="padding:18px"><small><?=h(ucfirst($p['categoria']))?></small><h3><?=h($p['nombre'])?></h3><p><?=money($p['precio'])?></p><p><?=((int)$p['stock']>0)?'Disponibles: '.(int)$p['stock']:'Agotado'?></p><button class="btn btn-primary" data-add="<?=h($p['id'])?>" <?=((int)$p['stock']<1)?'disabled':''?>>Agregar al carrito</button></div></article><?php }
?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>JOBS Store | <?=h($page)?></title><meta name="description" content="<?=h($desc)?>"><link rel="icon" href="/static/img/favicon.png"><link rel="stylesheet" href="/static/style.css"><style>.jobs-wrap{max-width:1160px;margin:auto;padding:28px 20px}.jobs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:24px}.jobs-form{display:grid;gap:12px;max-width:580px}.jobs-form input,.jobs-form textarea,.jobs-form select{padding:12px;border:1px solid #ccd4df;border-radius:8px;width:100%}.jobs-table{width:100%;border-collapse:collapse;background:white}.jobs-table td,.jobs-table th{padding:10px;border-bottom:1px solid #ddd;text-align:left}.jobs-table img{width:50px}.jobs-panel{padding:22px;background:#fff;border-radius:16px;margin:20px 0}.jobs-msg{background:#fef3c7;padding:12px;border-radius:8px}.jobs-drawer{position:fixed;right:0;top:0;bottom:0;z-index:999;background:white;width:min(440px,100%);padding:24px;overflow:auto;box-shadow:0 0 0 100vmax #0008;display:none}.jobs-drawer.open{display:block}.jobs-drawer input{padding:10px;margin:5px 0;width:100%;border:1px solid #ccc;border-radius:8px}.jobs-drawer button{margin:5px}.jobs-item{padding:10px 0;border-bottom:1px solid #ddd}.jobs-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center}</style></head><body><header class="topbar"><div class="wrap topbar-in"><a href="/" class="brand"><img src="/static/img/logo-emblema.png" alt="" width="44"><span class="brand-txt"><b>JOBS</b> <i>Store</i></span></a><nav class="nav" id="nav"><a href="/">Inicio</a><a href="/catalogo">Catálogo</a><a href="/catalogo?c=gorra">Gorras</a><a href="/catalogo?c=reloj">Relojes</a><a href="/seguimiento">Seguimiento</a><a href="/informacion">Ayuda</a><a href="/admin">Administración</a></nav><div class="topbar-actions"><button class="cart-btn" id="openCart" aria-label="Abrir carrito">🛍️ <span id="cartCount">0</span></button><button class="burger" id="burger" aria-label="Menú">☰</button></div></div></header><main id="contenido">
<?php if($m=$_SESSION['message']??null){unset($_SESSION['message']);echo '<div class="jobs-wrap jobs-msg">'.h($m).'</div>';} ?>
<?php if($path==='/'||$path==='/inicio'){?><?php include __DIR__ . "/views/inicio.php"; }
elseif($path==='/catalogo'){ $cat=(string)($_GET['c']??'');?><?php include __DIR__ . "/views/catalogo.php"; }
elseif(preg_match('~^/producto/([a-zA-Z0-9_-]+)$~',$path,$m)&&isset($byId[$m[1]])){$p=$byId[$m[1]];?><?php include __DIR__ . "/views/producto.php"; }
elseif($path==='/seguimiento'){?><?php include __DIR__ . "/views/seguimiento.php"; }
elseif($path==='/informacion'){?><?php include __DIR__ . "/views/informacion.php"; }
elseif($path==='/admin/login'){include __DIR__.'/app_admin/login.php';}
elseif($path==='/admin'){include __DIR__.'/app_admin/dashboard.php';}
else{http_response_code(404);?><section class="jobs-wrap"><h1>Página no encontrada</h1><a href="/">Volver al inicio</a></section><?php }?></main><footer class="footer"><div class="wrap footer-in"><div><b>JOBS Store</b><p>Creatividad · Innovación · Estilo</p></div><div><h4>Tienda</h4><a href="/catalogo">Catálogo completo</a></div><div><h4>Atención</h4><a href="https://wa.me/<?=h($wa)?>" target="_blank" rel="noopener">WhatsApp</a></div></div><div class="foot-bar"><div class="wrap">© <?=date('Y')?> JOBS Store</div></div></footer><aside class="jobs-drawer" id="cartDrawer" aria-label="Mi pedido"><button id="closeCart" class="btn">Cerrar ✕</button><h2>Mi pedido</h2><div id="cartItems"></div><h3 id="cartTotal">$0</h3><input id="clientName" placeholder="Nombre completo"><input id="clientPhone" placeholder="Teléfono"><input id="clientCity" placeholder="Ciudad / dirección"><button id="saveOrder" class="btn btn-primary">Registrar pedido</button><button id="sendWa" class="btn btn-wa">Enviar por WhatsApp</button><button id="clearCart" class="btn">Vaciar carrito</button><p>Los pagos en línea no están habilitados hasta configurar y verificar una pasarela.</p></aside><script>window.JOBS={productos:<?=json_encode($all,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,whatsapp:<?=json_encode($wa)?>,csrf:<?=json_encode(csrf())?>};</script><script src="/static/store-php.js"></script></body></html>
