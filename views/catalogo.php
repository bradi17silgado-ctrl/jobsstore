<?php
// Vista PHP: catalogo. Variables definidas por index.php.
if (!defined("JOBS_APP")) { http_response_code(403); exit; }
?>
<section class="jobs-wrap"><h1>Catálogo JOBS Store</h1><div class="jobs-actions"><a class="btn" href="/catalogo">Todos</a><a class="btn" href="/catalogo?c=gorra">Gorras</a><a class="btn" href="/catalogo?c=reloj">Relojes</a></div><div class="jobs-grid" style="margin-top:24px"><?php foreach($all as $p)if(!$cat||$p['categoria']===$cat)product_card($p);?></div></section>
