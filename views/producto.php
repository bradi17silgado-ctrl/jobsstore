<?php
if (!defined('JOBS_APP')) { http_response_code(403); exit; }
?>
<section class="jobs-wrap premium-detail-page"><div class="premium-breadcrumb"><a href="/">Inicio</a><span>/</span><a href="/catalogo">Catálogo</a><span>/</span><span><?=h($p['nombre'])?></span></div>
<div class="premium-detail-layout"><div class="premium-detail-media"><img data-product-image src="<?=h($p['imagen_url'])?>" alt="<?=h($p['nombre'])?>"></div><div class="premium-detail-content"><span class="premium-kicker">JOBS STORE / <?=h(strtoupper($p['categoria']))?></span><h1><?=h($p['nombre'])?></h1><p class="premium-detail-price"><?=money($p['precio'])?></p><p class="premium-detail-stock"><?= (int)$p['stock']>0?'● Disponible':'● Agotado' ?></p><div class="premium-detail-divider"></div><h2>Sobre este accesorio</h2><p class="premium-detail-description"><?=nl2br(h($p['descripcion']))?></p><?php if (!empty($p['colores'])): ?>
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
<button class="btn btn-primary btn-lg premium-detail-buy" data-add="<?=h($p['id'])?>" <?=$p['stock']<1?'disabled':''?>><?= $p['stock']<1?'Agotado':'Agregar al carrito ' ?></button><p class="premium-detail-help">¿Tienes dudas? <a href="https://wa.me/<?=h($wa)?>" target="_blank" rel="noopener">Habla con nosotros por WhatsApp</a></p></div></div>
<div class="jobs-panel premium-reviews"><div class="premium-section-heading"><div><span class="premium-kicker">COMUNIDAD JOBS</span><h2>Opiniones sobre este producto</h2></div></div><div class="premium-reviews-list"><?php if(db()){ $q=db()->prepare('SELECT cliente,calificacion,comentario FROM jobs_resenas WHERE producto_id=? AND aprobada=1 ORDER BY id DESC LIMIT 20');$q->execute([$p['id']]);foreach($q as $r)echo '<p><b>'.h($r['cliente']).' · '.(int)$r['calificacion'].'/5 ★</b><span>'.h($r['comentario']).'</span></p>';}?></div><h3>Comparte tu experiencia</h3><form method="post" action="/resenas/crear" class="jobs-form premium-review-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=h($p['id'])?>"><label>Tu nombre<input name="cliente" placeholder="Nombre completo" required maxlength="100"></label><label>Tu calificación<select name="calificacion"><option value="5">★★★★★ · Excelente</option><option value="4">★★★★ · Muy bueno</option><option value="3">★★★ · Bueno</option><option value="2">★★ · Regular</option><option value="1">★ · Malo</option></select></label><label>Tu opinión<textarea name="comentario" rows="4" placeholder="Cuéntanos qué te pareció..." required></textarea></label><button class="btn btn-primary">Enviar reseña</button></form></div></section>
