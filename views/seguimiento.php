<?php
// Vista PHP: seguimiento. Variables definidas por index.php.
if (!defined("JOBS_APP")) { http_response_code(403); exit; }
?>
<section class="jobs-wrap"><h1>Seguimiento de pedidos</h1><form method="get" class="jobs-form"><input name="ref" placeholder="Referencia JOB-..." value="<?=h($_GET['ref']??'')?>" required><input name="telefono" placeholder="Teléfono del pedido" value="<?=h($_GET['telefono']??'')?>" required><button class="btn btn-primary">Consultar</button></form><?php if(isset($_GET['ref'],$_GET['telefono'])&&db()){$q=db()->prepare('SELECT referencia,estado,total,creado FROM jobs_pedidos WHERE referencia=? AND telefono=?');$q->execute([$_GET['ref'],$_GET['telefono']]);$r=$q->fetch();echo '<div class="jobs-panel">'.($r?'Pedido '.h($r['referencia']).' — '.h($r['estado']).' — '.money($r['total']):'No encontramos un pedido con esos datos.').'</div>';}?></section>
