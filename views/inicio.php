<?php
if (!defined('JOBS_APP')) { http_response_code(403); exit; }
$destacados = array_values(array_filter($all, fn($p)=>!empty($p['destacado'])));
if (!$destacados) $destacados = array_slice($all, 0, 8);
?>
<section class="hero premium-hero"><div class="wrap hero-in">
 <div class="hero-copy"><span class="eyebrow">JOBS STORE / COLECCIÓN 2026</span><h1>Tu estilo.<br><span class="grad">Sin pedir permiso.</span></h1><p class="lead">Gorras que hablan por ti. Relojes que marcan tu ritmo. Encuentra ese accesorio que hace toda la diferencia.</p>
 <div class="hero-cta"><a class="btn btn-primary btn-lg" href="/catalogo">Descubrir la colección <span aria-hidden="true"></span></a><a class="btn btn-ghost btn-lg" href="/catalogo?c=gorra">Ver gorras</a></div>
 <div class="hero-benefits"><span>✦ Selección con estilo</span><span>✦ Atención personalizada</span></div></div>
 <div class="hero-visual"><div class="hero-art-label"><span>THE EVERYDAY EDIT</span><strong>JOBS / 26</strong></div><div class="hv hv-1"><img src="/static/img/productos/gorra-azul-royal.webp" alt="Gorra de la colección JOBS Store"></div><div class="hv hv-2"><img src="/static/img/productos/reloj-esqueleto-oro-rosa.webp" alt="Reloj de la colección JOBS Store"></div><div class="hero-art-foot">ACCESORIOS QUE DEJAN HUELLA <span></span></div></div>
</div></section>
<section class="premium-value-strip"><div class="wrap value-strip-inner"><span>01 / Descubre tu estilo</span><span>02 / Elige tus favoritos</span><span>03 / Haz tu pedido</span></div></section>
<section class="jobs-wrap premium-home-section"><div class="premium-section-heading"><div><span class="premium-kicker">SELECCIONADOS PARA TI</span><h2>Los más buscados<span class="gold-dot">.</span></h2><p>Detalles que elevan cualquier look.</p></div><a href="/catalogo" class="premium-text-link">Explorar todo <span aria-hidden="true"></span></a></div>
<div class="jobs-grid"><?php foreach($destacados as $p) product_card($p); ?></div></section>
<section class="jobs-wrap premium-collections"><a href="/catalogo?c=gorra" class="premium-collection premium-collection-cap"><div><span class="premium-kicker">COLECCIÓN 01</span><h2>Gorras</h2><span>Descubre la colección </span></div><img loading="lazy" src="/static/img/productos/gorra-negra-clasica.webp" alt="Gorras de JOBS Store"></a><a href="/catalogo?c=reloj" class="premium-collection premium-collection-watch"><div><span class="premium-kicker">COLECCIÓN 02</span><h2>Relojes</h2><span>Descubre la colección </span></div><img loading="lazy" src="/static/img/productos/reloj-cronografo-titanio.webp" alt="Relojes de JOBS Store"></a></section>
<section class="premium-final-cta"><div class="wrap"><span class="premium-kicker">TU PRÓXIMO FAVORITO TE ESPERA</span><h2>Los detalles hacen la diferencia.</h2><p>Encuentra el accesorio que va contigo.</p><a href="/catalogo" class="btn btn-primary btn-lg">Comprar ahora </a></div></section>
