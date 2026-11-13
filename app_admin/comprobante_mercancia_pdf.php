<?php if(!defined('JOBS_APP')){http_response_code(403);exit;} ?>
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><style>
@page{margin:38px 42px 45px}body{font-family:DejaVu Sans,sans-serif;color:#172c43;font-size:11px;line-height:1.5}
.brand{font-size:18px;font-weight:700;letter-spacing:1px;color:#152e49}.bar{border-bottom:3px solid #1d4765;padding-bottom:16px;margin-bottom:20px}
h1{font-size:21px;margin:10px 0 5px}h2{font-size:13px;margin:22px 0 8px;color:#1d4765}
.muted{color:#68798b}.number{font-size:12px;color:#274c69}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #dce4ea;vertical-align:top}th{width:37%;background:#f3f6f9;color:#29435d}td{overflow-wrap:break-word}
.flag{padding:10px;background:#fff3e6;border-left:4px solid #bc742c;margin:15px 0}.summary{margin-top:24px;padding:12px;background:#edf3f8;font-weight:bold}
.footer{margin-top:50px;padding-top:12px;border-top:1px solid #d8e2ea;color:#68798b;font-size:9px}
</style></head><body>
<div class="bar"><div class="brand">JOBS STORE</div><h1><?= $clase==='ingreso'?'Comprobante de ingreso de mercancía':'Comprobante de anulación de ingreso' ?></h1>
<div class="number">Registro original #<?=h($ingreso['id'])?> · Generado: <?=h(date('Y-m-d H:i:s'))?></div></div>
<?php if($clase==='ingreso' && $ingreso['fecha_anulacion']): ?><div class="flag"><strong>INGRESO ANULADO</strong> el <?=h($ingreso['fecha_anulacion'])?>. Este documento refleja el registro original, no un ingreso vigente.</div><?php endif; ?>
<?php if($clase==='anulacion'): ?><div class="flag"><strong>ANULACIÓN DE INVENTARIO</strong> — Este documento acredita la reversión del ingreso #<?=h($ingreso['id'])?>. No representa una salida por venta.</div><?php endif; ?>
<h2>Datos del movimiento</h2>
<table>
<tr><th>Número de ingreso</th><td>#<?=h($ingreso['id'])?></td></tr>
<tr><th>Producto</th><td><?=h($ingreso['producto_nombre'])?> (<?=h($ingreso['producto_id'])?>)</td></tr>
<tr><th>Color</th><td><?=h($ingreso['color']?:'Sin variante')?></td></tr>
<tr><th>Unidades <?= $clase==='ingreso'?'ingresadas':'anuladas' ?></th><td><?= (int)$ingreso['cantidad'] ?></td></tr>
<?php if($clase==='ingreso'): ?>
<tr><th>Fecha de ingreso</th><td><?=h($ingreso['creado'])?></td></tr>
<tr><th>Existencia anterior</th><td><?= (int)$ingreso['stock_anterior'] ?></td></tr>
<tr><th>Existencia tras el ingreso</th><td><?= (int)$ingreso['stock_nuevo'] ?></td></tr>
<tr><th>Proveedor</th><td><?=h($ingreso['proveedor']?:'No especificado')?></td></tr>
<tr><th>Costo unitario</th><td><?= $ingreso['costo_unitario']===null?'No registrado':money($ingreso['costo_unitario']) ?></td></tr>
<tr><th>Costo total registrado</th><td><?= $ingreso['costo_unitario']===null?'No registrado':money((float)$ingreso['costo_unitario']*(int)$ingreso['cantidad']) ?></td></tr>
<tr><th>Motivo / observaciones</th><td><?=h($ingreso['motivo']?:'Sin observaciones')?></td></tr>
<tr><th>Registrado por</th><td><?=h($ingreso['usuario'])?></td></tr>
<?php else: ?>
<tr><th>Fecha del ingreso original</th><td><?=h($ingreso['creado'])?></td></tr>
<tr><th>Fecha de anulación</th><td><?=h($ingreso['fecha_anulacion'])?></td></tr>
<tr><th>Motivo de anulación</th><td><?=h($ingreso['motivo_anulacion'])?></td></tr>
<tr><th>Anulado por</th><td><?=h($ingreso['usuario_anulacion'])?></td></tr>
<?php if($anulacion): ?>
<tr><th>Movimiento de reversión</th><td>#<?=h($anulacion['id'])?></td></tr>
<tr><th>Existencias antes de anular</th><td><?= (int)$anulacion['stock_anterior'] ?></td></tr>
<tr><th>Existencias tras anular</th><td><?= (int)$anulacion['stock_nuevo'] ?></td></tr>
<?php endif; ?>
<?php endif; ?>
</table>
<div class="summary"><?= $clase==='ingreso'?'Cantidad registrada: +':'Cantidad revertida: −' ?><?= (int)$ingreso['cantidad'] ?> unidades</div>
<p class="footer">JOBS Store · Comprobante interno generado desde el panel administrativo. Documento de control de inventario; no sustituye una factura electrónica ni un soporte tributario.</p>
</body></html>
