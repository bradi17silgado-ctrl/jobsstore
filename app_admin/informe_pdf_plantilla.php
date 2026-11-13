<?php if(!defined('JOBS_APP')){http_response_code(403);exit;} ?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@page{margin:30px 34px 40px}body{font-family:DejaVu Sans,sans-serif;color:#172a40;font-size:9px;line-height:1.45}
h1{font-size:20px;margin:0;color:#13273f}h2{font-size:12px;color:#13273f;margin:18px 0 7px}p{margin:4px 0}.muted{color:#617184}.top{padding-bottom:16px;border-bottom:2px solid #13273f}.brand{font-size:14px;font-weight:bold;letter-spacing:1px;color:#13273f}
.stats{width:100%;margin:14px 0}.stats td{border:1px solid #dce4ed;padding:10px 8px;width:25%;vertical-align:top}.stats strong{display:block;font-size:12px;margin-top:4px}
table.details{width:100%;border-collapse:collapse;table-layout:fixed}.details th{background:#13273f;color:#fff;text-align:left;padding:7px 6px}.details td{padding:6px;border-bottom:1px solid #e4e9f0;overflow-wrap:break-word}.details tr:nth-child(even){background:#f3f6fa}.footer{margin-top:20px;color:#748397;font-size:8px;border-top:1px solid #dce4ed;padding-top:9px}
</style></head><body>
<div class="top"><div class="brand">JOBS STORE</div><h1><?=h(jobs_titulo_informe($tipo))?></h1><p class="muted">Periodo: <?=h($desde)?> a <?=h($hasta)?> · Generado: <?=h(date('Y-m-d H:i'))?></p></div>
<table class="stats"><tr>
<td>Ventas entregadas<strong><?=count($datos['ventas'])?></strong></td>
<td>Ingresos por ventas<strong><?=money($datos['resumen']['total_ventas'])?></strong></td>
<td>Unidades vendidas<strong><?=$datos['resumen']['unidades_vendidas']?></strong></td>
<td>Entradas / salidas<strong><?=$datos['resumen']['entradas']?> / <?=$datos['resumen']['salidas']?></strong></td>
</tr></table>
<?php if($tipo==='general'||$tipo==='ventas'): ?>
<h2>Ventas (pedidos entregados)</h2><table class="details"><thead><tr><th>Fecha</th><th>Referencia</th><th>Cliente</th><th style="text-align:right">Total</th></tr></thead><tbody>
<?php foreach($datos['ventas'] as $v):?><tr><td><?=h($v['creado'])?></td><td><?=h($v['referencia'])?></td><td><?=h($v['cliente'])?></td><td style="text-align:right"><?=money($v['total'])?></td></tr><?php endforeach; ?>
<?php if(!$datos['ventas']):?><tr><td colspan="4">No hay pedidos entregados en este periodo.</td></tr><?php endif; ?></tbody></table>
<h2>Productos más vendidos</h2><table class="details"><thead><tr><th>Producto</th><th>Color</th><th>Unidades</th></tr></thead><tbody>
<?php foreach(array_slice($datos['masVendidos'],0,30) as $r):?><tr><td><?=h($r['producto'])?></td><td><?=h($r['color']?:'Sin variante')?></td><td><?=$r['cantidad']?></td></tr><?php endforeach; ?>
<?php if(!$datos['masVendidos']):?><tr><td colspan="3">Sin ventas en el periodo.</td></tr><?php endif; ?></tbody></table>
<?php endif; ?>
<?php if($tipo!=='ventas'): ?>
<h2>Movimientos de inventario</h2><table class="details"><thead><tr><th>Fecha</th><th>Producto / color</th><th>Tipo</th><th>Cant.</th><th>Antes → Después</th><th>Detalle</th></tr></thead><tbody>
<?php foreach($datos['movimientos'] as $m):?><tr><td><?=h($m['creado'])?></td><td><?=h($m['producto_nombre'])?><br><?=h($m['color']??'')?></td><td><?=h(!empty($m['anulado'])?'Entrada anulada':($m['tipo']==='anulacion_entrada'?'Anulación de entrada':ucfirst($m['tipo'])))?></td><td><?=$m['cantidad']?></td><td><?=$m['stock_anterior']?> → <?=$m['stock_nuevo']?></td><td><?=h($m['motivo']??'')?><?php if($m['proveedor']): ?><br><?=h($m['proveedor'])?><?php endif; ?><?php if($m['costo_unitario']!==null):?><br>Costo/u <?=money($m['costo_unitario'])?><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$datos['movimientos']):?><tr><td colspan="6">Sin movimientos registrados.</td></tr><?php endif; ?></tbody></table>
<?php endif; ?>
<?php if($tipo==='general'): ?>
<h2>Existencias actuales (al generar el informe)</h2><table class="details"><thead><tr><th>Producto</th><th>Unidades</th></tr></thead><tbody>
<?php foreach($datos['existencias'] as $r):?><tr><td><?=h($r['nombre'])?></td><td><?=$r['stock']?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
<p class="footer">JOBS Store · Documento interno del panel administrativo. Los ingresos consideran únicamente pedidos en estado Entregado. Los pedidos nuevos descuentan inventario al registrarse; las entradas y salidas reflejan movimientos registrados desde que se habilitó este módulo.</p>
</body></html>
