<?php
// Comprobante ligado a la sesión del cliente; no se muestran datos de otra persona.
$token=(string)($_GET['token']??'');
$registro=null;
$jobsCheckout=null;
$jobsPaymentStatus='PENDING';
if (preg_match('/^[a-f0-9]{48}$/D',$token)) {
    $registro=$_SESSION['pedidos_confirmados'][$token]??null;
    if ($registro && (!isset($registro['creado']) || $registro['creado']<time()-86400)) {
        unset($_SESSION['pedidos_confirmados'][$token]);
        $registro=null;
    }
}
if ($registro) {
    try {
        $d=db();
        if ($d) {
            initdb($d);
            $status=$d->prepare('SELECT estado FROM jobs_pagos WHERE referencia=?');
            $status->execute([$registro['referencia']]);
            $jobsPaymentStatus=(string)($status->fetchColumn()?:'PENDING');
        }
        $jobsCheckout=jobs_wompi_checkout((string)$registro['referencia']);
    } catch (Throwable $e) { error_log('Checkout Wompi: '.$e->getMessage()); }
}
if (!$registro) http_response_code(404);
?>
<section class="jobs-wrap confirm-page" aria-labelledby="confirm-title">
<?php if (!$registro): ?>
  <div class="confirm-card">
    <div class="confirm-icon confirm-icon-muted" aria-hidden="true">!</div>
    <h1 id="confirm-title">Comprobante no disponible</h1>
    <p class="confirm-subtitle">Este enlace de confirmación no existe o ha caducado. Si ya registraste tu pedido, puedes consultar su estado con tu referencia.</p>
    <div class="confirm-actions"><a class="btn btn-primary" href="/seguimiento">Consultar seguimiento</a><a class="btn confirm-secondary" href="/">Volver a la tienda</a></div>
  </div>
<?php else: ?>
  <div class="confirm-card">
    <div class="confirm-icon" aria-hidden="true">✓</div>
    <div class="confirm-eyebrow">JOBS STORE · CONFIRMACIÓN</div>
    <h1 id="confirm-title">¡Pedido registrado!</h1>
    <p class="confirm-subtitle">Recibimos tu pedido correctamente. Conserva la siguiente referencia para consultar su estado.</p>
    <div class="confirm-reference">
      <span>NÚMERO DE PEDIDO</span>
      <strong id="confirmRef"><?=h($registro['referencia'])?></strong>
      <button type="button" class="confirm-copy" id="copyOrderRef">Copiar referencia</button>
      <small id="copyOrderFeedback" role="status" aria-live="polite"></small>
    </div>
    <div class="confirm-summary">
      <h2>Resumen de tu pedido</h2>
      <?php foreach (($registro['items']??[]) as $item): ?>
      <div class="confirm-line"><span><?=h($item['nombre'])?><?php if(!empty($item['color'])): ?> <small>· <?=h(ucwords(str_replace('-', ' ', $item['color'])))?></small><?php endif; ?> <small>× <?= (int)$item['cantidad'] ?></small></span><strong><?=money((float)$item['precio']*(int)$item['cantidad'])?></strong></div>
      <?php endforeach; ?>
      <div class="confirm-total"><span>Total</span><strong><?=money($registro['total'])?></strong></div>
    </div>
    <?php if ($jobsPaymentStatus==='APPROVED'): ?>
    <p class="confirm-note">Pago aprobado y registrado. Gracias por comprar en JOBS Store.</p>
    <?php elseif ($jobsCheckout): ?>
    <p class="confirm-note">Tu pedido está registrado y pendiente de pago. Paga de forma segura con Wompi.</p>
    <form action="https://checkout.wompi.co/p/" method="GET" class="confirm-actions">
      <input type="hidden" name="public-key" value="<?=h($jobsCheckout['publicKey'])?>">
      <input type="hidden" name="currency" value="COP">
      <input type="hidden" name="amount-in-cents" value="<?=h($jobsCheckout['amount'])?>">
      <input type="hidden" name="reference" value="<?=h($jobsCheckout['reference'])?>">
      <input type="hidden" name="signature:integrity" value="<?=h($jobsCheckout['integrity'])?>">
      <input type="hidden" name="redirect-url" value="<?=h($jobsCheckout['redirect'])?>">
      <button type="submit" class="btn btn-primary">Pagar en línea con Wompi</button>
    </form>
    <?php else: ?>
    <p class="confirm-note">El pedido está registrado, pero los pagos en línea todavía no están configurados. Puedes consultar su estado en Seguimiento.</p>
    <?php endif; ?>
    <div class="confirm-actions">
      <a href="/seguimiento" class="btn btn-primary">Consultar seguimiento</a>
      <a href="/" class="btn confirm-secondary">Volver a la tienda</a>
    </div>
  </div>
  <script>
  (()=>{const b=document.getElementById('copyOrderRef');if(!b)return;
  b.addEventListener('click',async()=>{const value=document.getElementById('confirmRef').textContent;
    try{await navigator.clipboard.writeText(value);document.getElementById('copyOrderFeedback').textContent='Referencia copiada';}
    catch{document.getElementById('copyOrderFeedback').textContent='Selecciona la referencia para copiarla';}
  });})();
  </script>
<?php endif; ?>
</section>
