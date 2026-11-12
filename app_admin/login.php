<?php if (!defined('JOBS_APP')) { http_response_code(403); exit('Acceso denegado'); } ?>
<?php ?><section class="jobs-wrap"><h1>Acceso administrativo</h1><form class="jobs-form" method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="password" name="password" placeholder="Contraseña" required><button class="btn btn-primary">Ingresar</button></form></section>
