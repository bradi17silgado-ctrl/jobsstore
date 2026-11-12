<?php
if (!defined('JOBS_APP')) { http_response_code(403); exit('Acceso denegado'); }
require_admin();
$d=db();
if ($d) { try { initdb($d); } catch (Throwable $e) { error_log($e->getMessage()); $d=null; } }
$items=products(true);
$edit=null;
foreach ($items as $item) { if ($item['id']===($_GET['editar']??'')) { $edit=$item; break; } }
$total=count($items); $agotados=count(array_filter($items,static fn($p)=>(int)$p['stock']===0));
?>
<section class="jobs-wrap jobs-admin">
  <div class="jobs-admin-header">
    <div><span class="jobs-eyebrow">JOBS STORE · ADMINISTRACIÓN</span><h1>Panel administrativo</h1><p>Gestiona productos, inventario y pedidos desde un solo lugar.</p></div>
    <form method="post" action="/admin/salir"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><button class="btn jobs-secondary" type="submit">Cerrar sesión</button></form>
  </div>
  <?php if (!$d): ?><p class="jobs-msg">La conexión a Railway no está disponible. Configura las variables DB_HOST, DB_NAME, DB_USER y DB_PASSWORD para guardar cambios.</p><?php endif; ?>
  <div class="jobs-stats">
    <div class="jobs-stat"><span>Productos registrados</span><strong><?=$total?></strong></div>
    <div class="jobs-stat"><span>Sin existencias</span><strong><?=$agotados?></strong></div>
    <div class="jobs-stat"><span>Disponibles</span><strong><?=$total-$agotados?></strong></div>
  </div>
  <div class="jobs-panel jobs-admin-panel">
    <div class="jobs-section-heading"><div><h2>Inventario</h2><p>Controla los precios, las existencias y las fotografías.</p></div><a class="btn btn-primary" href="/admin#producto-form">+ Agregar producto</a></div>
    <div class="jobs-table-scroll"><table class="jobs-table jobs-inventory"><thead><tr><th>Producto</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
    <?php foreach ($items as $item): ?>
    <tr><td><div class="jobs-product-cell"><img src="<?=h($item['imagen_url'])?>" alt="" loading="lazy"><div><strong><?=h($item['nombre'])?></strong><small><?=h(ucfirst($item['categoria']))?></small></div></div></td><td><strong><?=money($item['precio'])?></strong></td><td><?= (int)$item['stock'] ?></td><td><span class="jobs-status <?=(empty($item['visible'])?'jobs-hidden':((int)$item['stock']===0?'jobs-out':'jobs-in'))?>"><?=(empty($item['visible'])?'Oculto':((int)$item['stock']===0?'Agotado':'Disponible'))?></span></td><td><div class="jobs-row-actions">
      <a class="jobs-edit-link jobs-action-button jobs-edit-button" href="/admin?editar=<?=rawurlencode($item['id'])?>#producto-form">✎ Editar</a>
      <form method="post" action="/admin/producto/visibilidad">
        <input type="hidden" name="csrf" value="<?=h(csrf())?>">
        <input type="hidden" name="id" value="<?=h($item['id'])?>">
        <input type="hidden" name="visible" value="<?=!empty($item['visible'])?'0':'1'?>">
        <button type="submit" class="jobs-action-button jobs-hide-button" <?=!$d?'disabled':''?>><?=!empty($item['visible'])?'◉ Ocultar':'◉ Publicar'?></button>
      </form>
      <form method="post" action="/admin/producto/eliminar" onsubmit="return confirm('¿Eliminar definitivamente este producto y su imagen? Esta acción no se puede deshacer.');">
        <input type="hidden" name="csrf" value="<?=h(csrf())?>">
        <input type="hidden" name="id" value="<?=h($item['id'])?>">
        <input type="hidden" name="confirmar" value="ELIMINAR">
        <button type="submit" class="jobs-action-button jobs-delete-button" <?=!$d?'disabled':''?> aria-label="Eliminar <?=h($item['nombre'])?>">✕ Eliminar</button>
      </form>
    </div></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="jobs-panel jobs-admin-panel" id="producto-form">
    <div class="jobs-section-heading"><div><span class="jobs-eyebrow">CATÁLOGO</span><h2><?= $edit?'Editar producto':'Agregar producto' ?></h2><p><?= $edit?'Actualiza los datos o cambia la fotografía.':'Completa los datos para publicar un nuevo artículo.' ?></p></div><?php if ($edit): ?><a href="/admin#producto-form" class="btn jobs-secondary">Cancelar edición</a><?php endif; ?></div>
    <form class="jobs-form jobs-product-form" action="/admin/producto/guardar" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>">
      <div class="jobs-form-grid">
        <label>Nombre del producto<input name="nombre" required maxlength="160" placeholder="Ej. Gorra Premium Negra" value="<?=h($edit['nombre']??'')?>"></label>
        <label>Categoría<select name="categoria"><option value="gorra" <?=($edit['categoria']??'')==='gorra'?'selected':''?>>Gorras</option><option value="reloj" <?=($edit['categoria']??'')==='reloj'?'selected':''?>>Relojes</option></select></label>
        <label>Precio (COP)<input type="number" min="0" step="1" name="precio" required placeholder="55000" value="<?=h($edit['precio']??'')?>"></label>
        <label>Unidades disponibles<input type="number" min="0" step="1" name="stock" required placeholder="10" value="<?=h($edit['stock']??'')?>"></label>
      </div>
      <label>Descripción del producto<textarea name="descripcion" rows="4" placeholder="Describe el material, color y características..."><?=h($edit['descripcion']??'')?></textarea></label>
      <div class="jobs-upload" id="jobsUploadArea">
        <div class="jobs-upload-icon">▧</div>
        <strong>Fotografía del producto</strong>
        <p>Selecciona una imagen de Descargas o cualquier carpeta de tu computador.</p>
        <label class="btn jobs-secondary jobs-file-button" for="jobsImageInput">Seleccionar imagen</label>
        <input id="jobsImageInput" name="imagen_archivo" type="file" accept="image/jpeg,image/png,image/webp" <?= $edit?'':'required' ?> >
        <small>JPG, PNG o WEBP · Máximo 3 MB · 5000 × 5000 px</small>
        <div id="jobsImagePreview" class="jobs-preview" <?= $edit?'':'hidden' ?> ><?php if ($edit): ?><img src="<?=h($edit['imagen_url'])?>" alt="Imagen actual del producto"><span>Imagen actual</span><?php endif; ?></div>
      </div>
      <div class="jobs-options"><label><input type="checkbox" name="visible" <?=!$edit||!empty($edit['visible'])?'checked':''?>> Visible en la tienda</label><label><input type="checkbox" name="destacado" <?=!empty($edit['destacado'])?'checked':''?>> Destacar en inicio</label></div>
      <button type="submit" class="btn btn-primary jobs-save" <?=!$d?'disabled':''?>>Guardar producto</button>
    </form>
  </div>
  <div class="jobs-panel jobs-admin-panel"><h2>Pedidos recibidos</h2><div class="jobs-table-scroll"><table class="jobs-table"><thead><tr><th>Referencia</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Actualizar</th></tr></thead><tbody><?php if($d) foreach($d->query('SELECT * FROM jobs_pedidos ORDER BY id DESC LIMIT 100') as $r): ?><tr><td><?=h($r['referencia'])?></td><td><?=h($r['cliente'])?><br><?=h($r['telefono'])?><br><small><?=h($r['items_json'])?></small></td><td><?=money($r['total'])?></td><td><?=h($r['estado'])?></td><td><form method="post" action="/admin/pedido/estado"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=h($r['id'])?>"><select name="estado"><?php foreach(['Pendiente','Confirmado','Enviado','Entregado','Cancelado'] as $estado): ?><option <?=($r['estado']===$estado?'selected':'')?>><?=h($estado)?></option><?php endforeach; ?></select><button class="btn jobs-secondary">Guardar</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
  <div class="jobs-panel jobs-admin-panel"><h2>Reseñas pendientes</h2><?php if($d) foreach($d->query('SELECT * FROM jobs_resenas WHERE aprobada=0 ORDER BY id DESC LIMIT 30') as $r): ?><div class="jobs-review"><p><strong><?=h($r['cliente'])?></strong> — <?=h($r['comentario'])?></p><form method="post" action="/admin/resena/aprobar"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input type="hidden" name="aprobar" value="1"><button class="btn jobs-secondary">Aprobar</button></form></div><?php endforeach; ?></div>
</section>
<script src="/static/admin-productos.js" defer></script>
