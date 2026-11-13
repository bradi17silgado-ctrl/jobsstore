<?php
// Comprobante ligado a la sesión del cliente; no se muestran datos de otra persona.
$token=(string)($_GET['token']??'');
$registro=null;
if (preg_match('/^[a-f0-9]{48}$/D',$token)) {
    $registro=$_SESSION['pedidos_confirmados'][$token]??null;
    if ($registro && (!isset($registro['creado']) || $registro['creado']<time()-86400)) {
        unset($_SESSION['pedidos_confirmados'][$token]);
        $registro=null;
    }
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
    <p class="confirm-note">El pedido quedó registrado, pero aún no está pagado. Los pagos en línea no están habilitados hasta configurar la pasarela.</p>
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
