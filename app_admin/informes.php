<?php
if(!defined('JOBS_APP')){http_response_code(403);exit;}
require_admin();
$d=db();$error=null;$datos=null;
$reportSection = [
 '/admin/informes'=>'general', '/admin/ventas'=>'ventas',
 '/admin/mercancia/entrada'=>'entrada', '/admin/mercancia/salida'=>'salida',
 '/admin/movimientos'=>'movimientos'
][$path] ?? 'general';
$reportTitle=['general'=>'Informes generales','ventas'=>'Informe de ventas','entrada'=>'Ingreso de mercancía','salida'=>'Salida de mercancía','movimientos'=>'Historial de movimientos'][$reportSection];
// La sección de navegación determina el tipo; GET no puede mezclar el contenido de otras páginas.
if($reportSection!=='general') $_GET['tipo'] = ['ventas'=>'ventas','entrada'=>'entradas','salida'=>'salidas','movimientos'=>'general'][$reportSection];
try{
    if(!$d)throw new RuntimeException('No hay conexión con Railway.');
    initdb($d);
    [$tipo,$desde,$hasta,$hastaExclusiva]=jobs_filtros_informe();
    $datos=jobs_datos_informe($d,$tipo,$desde,$hastaExclusiva);
    $productos=$d->query('SELECT id,nombre,stock FROM jobs_productos ORDER BY nombre')->fetchAll();
    $colores=$d->query('SELECT producto_id,color,stock FROM jobs_colores ORDER BY color')->fetchAll();
    $entradasAnulables=[];
    if($reportSection==='entrada') {
        $entradasAnulables=$d->query("SELECT m.id,m.creado,m.producto_nombre,m.color,m.cantidad,m.costo_unitario,m.proveedor,m.motivo, a.movimiento_id IS NOT NULL AS anulado FROM jobs_movimientos m LEFT JOIN jobs_ingresos_anulados a ON a.movimiento_id=m.id WHERE m.tipo='entrada' ORDER BY m.id DESC LIMIT 150")->fetchAll();
    }

}catch(Throwable $e){$error=$e->getMessage();$tipo='general';$desde=date('Y-m-01');$hasta=date('Y-m-d');$productos=[];$colores=[];}
$pdfQuery=http_build_query(['tipo'=>$tipo,'desde'=>$desde,'hasta'=>$hasta]);
$comprobanteId=(string)($_GET['comprobante']??'');
$comprobanteClase=(string)($_GET['clase']??'');
$comprobanteDisponible=preg_match('/^[1-9][0-9]{0,18}$/D',$comprobanteId) && in_array($comprobanteClase,['ingreso','anulacion'],true);

?>
<section class="jobs-wrap jobs-admin jobs-report-page">
 <div class="jobs-admin-header"><div><span class="jobs-eyebrow">JOBS STORE · ADMINISTRACIÓN</span><h1><?=h($reportTitle)?></h1><p>Consulta o registra información desde esta sección independiente.</p></div><a href="/admin" class="btn jobs-secondary">← Volver al panel</a></div>

 <?php if($reportSection==='entrada' && $comprobanteDisponible): ?>
 <div class="jobs-panel jobs-admin-panel"><strong>Comprobante disponible.</strong>
 <a class="btn btn-primary" href="/admin/mercancia/comprobante/pdf?id=<?=h($comprobanteId)?>&amp;clase=<?=h($comprobanteClase)?>">⬇ Descargar comprobante PDF #<?=h($comprobanteId)?></a>
 </div>
 <?php endif; ?>
 <?php if($error):?><p class="jobs-msg"><?=h($error)?></p><?php endif; ?>
 <?php if(in_array($reportSection,['general','ventas','movimientos'],true)): ?>
 <div class="jobs-panel jobs-admin-panel">
  <div class="jobs-section-heading"><div><h2>Generar informe</h2><p>Selecciona las fechas y el tipo de reporte.</p></div></div>
  <form method="get" action="<?=h($path)?>" class="jobs-report-filter">
    <?php if($reportSection==='general'): ?><label>Informe<select name="tipo"><option value="general" <?=$tipo==='general'?'selected':''?>>Informe general</option><option value="ventas" <?=$tipo==='ventas'?'selected':''?>>Ventas</option><option value="entradas" <?=$tipo==='entradas'?'selected':''?>>Ingreso de mercancía</option><option value="salidas" <?=$tipo==='salidas'?'selected':''?>>Salida de mercancía</option></select></label><?php endif; ?>
    <label class="jobs-date-field"><span class="jobs-date-label">Fecha inicial</span><span class="jobs-date-control"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg><input type="date" name="desde" value="<?=h($desde)?>" max="<?=h($hasta)?>" aria-label="Fecha inicial" required></span></label>
    <label class="jobs-date-field"><span class="jobs-date-label">Fecha final</span><span class="jobs-date-control"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg><input type="date" name="hasta" value="<?=h($hasta)?>" min="<?=h($desde)?>" aria-label="Fecha final" required></span></label>
    <button class="btn jobs-secondary" type="submit">Consultar</button>
    <?php if($datos):?><a class="btn btn-primary" href="/admin/informes/pdf?<?=h($pdfQuery)?>">⬇ Descargar PDF</a><?php endif; ?>
  </form>
 </div>
 <?php endif; ?>
 <?php if($datos && in_array($reportSection,['general','ventas','movimientos'],true)): ?>
 <div class="jobs-stats jobs-report-stats">
  <div class="jobs-stat"><span>Ingresos · pedidos entregados</span><strong><?=money($datos['resumen']['total_ventas'])?></strong></div>
  <div class="jobs-stat"><span>Pedidos entregados</span><strong><?=$datos['resumen']['cantidad_ventas']?></strong></div>
  <div class="jobs-stat"><span>Unidades vendidas</span><strong><?=$datos['resumen']['unidades_vendidas']?></strong></div>
  <div class="jobs-stat"><span>Entradas / salidas</span><strong><?=$datos['resumen']['entradas']?> / <?=$datos['resumen']['salidas']?></strong></div>
 </div>
 <div class="jobs-panel jobs-admin-panel"><div class="jobs-section-heading"><div><h2>Detalle del informe: <?=h(jobs_titulo_informe($tipo))?></h2><p>Periodo <?=h($desde)?> — <?=h($hasta)?>. Las ventas corresponden exclusivamente a pedidos entregados.</p></div></div>
 <?php if($reportSection!=='movimientos' && in_array($tipo,['general','ventas'],true)): ?>
 <div class="jobs-table-scroll"><table class="jobs-table"><thead><tr><th>Fecha</th><th>Referencia</th><th>Cliente</th><th>Total</th></tr></thead><tbody>
 <?php foreach($datos['ventas'] as $r): ?><tr><td><?=h($r['creado'])?></td><td><?=h($r['referencia'])?></td><td><?=h($r['cliente'])?></td><td><?=money($r['total'])?></td></tr><?php endforeach; ?>
 <?php if(!$datos['ventas']):?><tr><td colspan="4">Sin pedidos entregados en este periodo.</td></tr><?php endif; ?>
 </tbody></table></div>
 <?php endif; ?>
 <?php if($tipo!=='ventas'): ?>
 <h3 class="jobs-report-subtitle">Movimientos registrados</h3>
 <div class="jobs-table-scroll"><table class="jobs-table"><thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Antes → Después</th><th>Detalle</th></tr></thead><tbody>
 <?php foreach(array_slice($datos['movimientos'],0,200) as $r):?><tr><td><?=h($r['creado'])?></td><td><?=h($r['producto_nombre'])?><?= $r['color']?' · '.h($r['color']):'' ?></td><td><?=h(!empty($r['anulado'])?'Entrada anulada':($r['tipo']==='anulacion_entrada'?'Anulación de entrada':ucfirst($r['tipo'])))?></td><td><?=(int)$r['cantidad']?></td><td><?=(int)$r['stock_anterior']?> → <?=(int)$r['stock_nuevo']?></td><td><?=h($r['motivo']??'')?><?= $r['proveedor']?' · '.h($r['proveedor']):''?></td></tr><?php endforeach; ?>
 <?php if(!$datos['movimientos']):?><tr><td colspan="6">Todavía no hay movimientos registrados en este periodo.</td></tr><?php endif; ?>
 </tbody></table></div><p class="jobs-report-note">Se muestran hasta 200 movimientos en pantalla y hasta 10.000 en el PDF por periodo.</p>
 <?php endif; ?>
 </div>
 <?php endif; ?>
 <?php if(in_array($reportSection,['entrada','salida'],true)): ?>
 <div class="jobs-panel jobs-admin-panel"><div class="jobs-section-heading"><div><h2>Descargar informe de <?= $reportSection==='entrada'?'entradas':'salidas' ?></h2><p>Exporta los movimientos de mercancía del periodo seleccionado.</p></div></div>
 <form class="jobs-report-filter" method="get" action="/admin/informes/pdf">
 <input type="hidden" name="tipo" value="<?= $reportSection==='entrada'?'entradas':'salidas' ?>">
 <label class="jobs-date-field"><span class="jobs-date-label">Fecha inicial</span><span class="jobs-date-control"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg><input type="date" name="desde" value="<?=h($desde)?>" max="<?=h($hasta)?>" aria-label="Fecha inicial" required></span></label>
 <label class="jobs-date-field"><span class="jobs-date-label">Fecha final</span><span class="jobs-date-control"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg><input type="date" name="hasta" value="<?=h($hasta)?>" min="<?=h($desde)?>" aria-label="Fecha final" required></span></label>
 <button class="btn btn-primary" type="submit" <?= $error?'disabled':'' ?>>⬇ Descargar PDF</button>
 </form></div>
 <div class="jobs-panel jobs-admin-panel" id="registrar-movimiento"><div class="jobs-section-heading"><div><h2><?=h($reportTitle)?></h2><p>Actualiza las existencias con registro de fecha, producto, color, costo y motivo. Solo el administrador puede hacerlo.</p></div></div>
 <form class="jobs-form jobs-report-form" method="post" action="/admin/inventario/movimiento">
  <input type="hidden" name="csrf" value="<?=h(csrf())?>">
  <div class="jobs-form-grid">
   <input type="hidden" name="tipo" value="<?=h($reportSection)?>">
   <label>Producto<select name="producto_id" id="jobsMoveProduct" required><option value="">Selecciona un producto</option><?php foreach($productos as $p):?><option value="<?=h($p['id'])?>"><?=h($p['nombre'])?> · <?= (int)$p['stock'] ?> unidades</option><?php endforeach; ?></select></label>
   <label>Color<select name="color" id="jobsMoveColor" required><option value="">Selecciona primero un producto</option></select></label>
   <label>Cantidad<input name="cantidad" type="number" min="1" max="99999" step="1" required placeholder="Ej. 12"></label>
   <label>Costo unitario COP (opcional)<input name="costo_unitario" type="number" min="0" max="9999999999" step="0.01" placeholder="Ej. 25000"></label>
   <label>Proveedor (opcional)<input name="proveedor" maxlength="160" placeholder="Nombre del proveedor"></label>
  </div>
  <label>Motivo / observación<textarea name="motivo" rows="2" maxlength="255" placeholder="Ej. Compra de inventario, pérdida, devolución a proveedor..."></textarea></label>
  <button type="submit" class="btn btn-primary" <?=$error?'disabled':''?>>Registrar movimiento</button>
 </form>
 <p class="jobs-report-note">Los pedidos descontarán inventario automáticamente. No registres nuevamente como salida una venta que ya entró como pedido.</p>
 </div>
 <?php endif; ?>
<?php if($reportSection==='entrada'): ?>
 <div class="jobs-panel jobs-admin-panel">
  <div class="jobs-section-heading"><div><h2>Eliminar / anular ingresos registrados</h2><p>Selecciona un ingreso registrado por error. Se conserva el historial y se resta del inventario su cantidad si queda stock suficiente.</p></div></div>
  <div class="jobs-table-scroll"><table class="jobs-table"><thead><tr><th>Ingreso</th><th>Fecha</th><th>Producto / color</th><th>Cantidad</th><th>Proveedor</th><th>Comprobantes PDF</th><th>Estado / acción</th></tr></thead><tbody>
  <?php foreach(($entradasAnulables??[]) as $r): ?>
   <tr><td>#<?=(int)$r['id']?></td><td><?=h($r['creado'])?></td><td><?=h($r['producto_nombre'])?><?=!empty($r['color'])?' · '.h($r['color']):''?></td><td><?=(int)$r['cantidad']?></td><td><?=h($r['proveedor']??'—')?></td><td>
    <div class="jobs-receipt-actions"><a class="btn jobs-secondary" href="/admin/mercancia/comprobante/pdf?id=<?=(int)$r['id']?>&amp;clase=ingreso">⬇ PDF ingreso</a>
    <?php if($r['anulado']): ?><a class="btn jobs-secondary" href="/admin/mercancia/comprobante/pdf?id=<?=(int)$r['id']?>&amp;clase=anulacion">⬇ PDF anulación</a><?php endif; ?></div>
   </td><td>
    <?php if($r['anulado']): ?><span>Ingreso anulado</span>
    <?php else: ?>
     <form method="post" action="/admin/inventario/anular-entrada" onsubmit="return confirm('¿Confirmas anular este ingreso? Se restarán sus unidades del inventario y se guardará el motivo.');">
      <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="movimiento_id" value="<?=(int)$r['id']?>">
      <label>Motivo de anulación<input name="motivo_anulacion" required minlength="5" maxlength="255" placeholder="Ej. ingreso duplicado"></label>
      <button type="submit" class="btn jobs-secondary">Eliminar ingreso</button>
     </form>
    <?php endif; ?>
   </td></tr>
  <?php endforeach; ?>
  <?php if(empty($entradasAnulables)): ?><tr><td colspan="7">Todavía no hay ingresos de mercancía registrados.</td></tr><?php endif; ?>
  </tbody></table></div>
 </div>
 <?php endif; ?>
</section>
<?php if(in_array($reportSection,['entrada','salida'],true)): ?><script>const jobsMoveColors=<?=json_encode($colores,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;const jobsMoveProducts=document.getElementById('jobsMoveProduct');const jobsMoveColor=document.getElementById('jobsMoveColor');function jobsUpdateMoveColors(){const list=jobsMoveColors.filter(c=>c.producto_id===jobsMoveProducts.value);jobsMoveColor.replaceChildren();if(!list.length){jobsMoveColor.add(new Option('Sin variante de color',''));jobsMoveColor.required=false;}else{jobsMoveColor.add(new Option('Selecciona un color',''));for(const c of list)jobsMoveColor.add(new Option(c.color.replaceAll('-',' ')+' · '+c.stock+' disponibles',c.color));jobsMoveColor.required=true;}}jobsMoveProducts.addEventListener('change',jobsUpdateMoveColors);jobsUpdateMoveColors();</script><?php endif; ?>
