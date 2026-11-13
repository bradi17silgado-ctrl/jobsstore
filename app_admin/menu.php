<?php
if(!defined('JOBS_APP')){http_response_code(403);exit;}
$adminMenu=[
 ['/admin','⌂','Inicio'],
 ['/admin/productos','▦','Productos'],
 ['/admin/producto/nuevo','＋','Agregar producto'],
 ['/admin/pedidos','▤','Pedidos'],
 ['/admin/ventas','▥','Informe de ventas'],
 ['/admin/mercancia/entrada','↧','Ingreso de mercancía'],
 ['/admin/mercancia/salida','↥','Salida de mercancía'],
 ['/admin/movimientos','≡','Historial'],
 ['/admin/resenas','☆','Reseñas']
];
?>
<nav class="jobs-admin-nav" aria-label="Secciones del panel administrativo">
<?php foreach($adminMenu as [$url,$simbolo,$texto]): ?>
<a href="<?=h($url)?>" class="<?=($path===$url || ($url==='/admin/ventas' && $path==='/admin/informes'))?'active':''?>" <?=($path===$url)?'aria-current="page"':''?>><span aria-hidden="true"><?=h($simbolo)?></span><?=h($texto)?></a>
<?php endforeach; ?>
</nav>
