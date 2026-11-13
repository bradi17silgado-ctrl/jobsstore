<?php
if (!defined('JOBS_APP')) { http_response_code(403); exit; }
$filtered = array_values(array_filter($all, fn($p)=>!$cat || $p['categoria']===$cat));
$catTitle = $cat==='gorra'?'Gorras':($cat==='reloj'?'Relojes':'Toda la colección');
?>
<section class="premium-page-intro"><div class="jobs-wrap"><span class="premium-kicker">JOBS STORE / CATÁLOGO</span><h1><?=h($catTitle)?><span class="gold-dot">.</span></h1><p>Explora nuestra selección de accesorios para todos los días.</p></div></section>
<section class="jobs-wrap premium-catalog"><div class="premium-catalog-toolbar"><div class="premium-filters" aria-label="Filtrar por categoría"><a class="premium-filter <?=!$cat?'selected':''?>" href="/catalogo" <?=!$cat?'aria-current="page"':''?>>Todos</a><a class="premium-filter <?=$cat==='gorra'?'selected':''?>" href="/catalogo?c=gorra" <?=$cat==='gorra'?'aria-current="page"':''?>>Gorras</a><a class="premium-filter <?=$cat==='reloj'?'selected':''?>" href="/catalogo?c=reloj" <?=$cat==='reloj'?'aria-current="page"':''?>>Relojes</a></div><span class="catalog-count"><?=count($filtered)?> productos</span></div>
<?php if ($filtered): ?><div class="jobs-grid"><?php foreach($filtered as $p)product_card($p);?></div><?php else: ?><div class="premium-empty"><h2>Pronto habrá novedades</h2><p>No hay productos disponibles en esta categoría.</p><a class="btn btn-primary" href="/catalogo">Ver toda la colección</a></div><?php endif; ?></section>
