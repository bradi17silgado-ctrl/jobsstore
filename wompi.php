<?php
declare(strict_types=1);
// Wompi Checkout Web: https://docs.wompi.co/docs/colombia/widget-checkout-web/
function jobs_wompi_active(): bool {
    $key=envv('WOMPI_PUBLIC_KEY'); $secret=envv('WOMPI_INTEGRITY_SECRET');
    return (bool)preg_match('/^pub_(test|prod)_[A-Za-z0-9]+$/D',$key) &&
        (bool)preg_match('/^(test|prod)_integrity_[A-Za-z0-9]+$/D',$secret) &&
        explode('_',$key)[1]===explode('_',$secret)[0] &&
        str_starts_with(envv('APP_URL'),'https://');
}
function jobs_wompi_amount_cents(string $amount): int {
    // Precio guardado por MySQL como DECIMAL, no usar totales del navegador.
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$amount)) throw new RuntimeException('Importe inválido');
    [$pesos,$centavos]=array_pad(explode('.',$amount,2),2,'');
    return (int)$pesos*100+(int)str_pad($centavos,2,'0');
}
function jobs_wompi_checkout(string $reference): ?array {
    if (!jobs_wompi_active()) return null;
    $d=db(); if (!$d) return null;
    initdb($d);
    $q=$d->prepare('SELECT p.referencia, p.total, COALESCE(g.estado,\'PENDING\') AS pago_estado FROM jobs_pedidos p LEFT JOIN jobs_pagos g ON g.referencia=p.referencia WHERE p.referencia=?');
    $q->execute([$reference]); $order=$q->fetch();
    if (!$order || $order['pago_estado']==='APPROVED') return null;
    $cents=jobs_wompi_amount_cents((string)$order['total']);
    if ($cents<=0) return null;
    $integrity=hash('sha256',$reference.$cents.'COP'.envv('WOMPI_INTEGRITY_SECRET'));
    return ['reference'=>$reference,'amount'=>$cents,'publicKey'=>envv('WOMPI_PUBLIC_KEY'),'integrity'=>$integrity,'redirect'=>rtrim(envv('APP_URL'),'/').'/pago/resultado'];
}
function jobs_wompi_event_handler(string $method): void {
    header('Content-Type: application/json; charset=utf-8');
    if ($method!=='POST') {http_response_code(405);echo '{"error":"Metodo no permitido"}';return;}
    $secret=envv('WOMPI_EVENTS_SECRET');
    if (!$secret) {http_response_code(503);echo '{"error":"Webhook no configurado"}';return;}
    $raw=file_get_contents('php://input',false,null,0,65537);
    if (!$raw || strlen($raw)>65536) {http_response_code(400);return;}
    $event=json_decode($raw,true);
    if (!is_array($event) || ($event['event']??'')!=='transaction.updated') {http_response_code(400);return;}
    $props=$event['signature']['properties']??null;
    $timestamp=$event['timestamp']??null;
    $checksum=(string)($event['signature']['checksum']??'');
    if (!is_array($props)||!$props||!is_int($timestamp)||!preg_match('/^[a-f0-9]{64}$/iD',$checksum)) {http_response_code(400);return;}
    $signed='';
    foreach ($props as $property) {
        if (!is_string($property)||!preg_match('/^[a-zA-Z0-9_.]+$/D',$property)) {http_response_code(400);return;}
        $v=$event['data']??[];
        foreach (explode('.',$property) as $segment) {
            if (!is_array($v)||!array_key_exists($segment,$v)) {http_response_code(400);return;}
            $v=$v[$segment];
        }
        if (!is_scalar($v) || is_bool($v)) {http_response_code(400);return;}
        $signed.=(string)$v;
    }
    $expected=hash('sha256',$signed.$timestamp.$secret);
    if (!hash_equals($expected,strtolower($checksum))) {http_response_code(403);echo '{"error":"Firma invalida"}';return;}
    $tx=$event['data']['transaction']??[];
    $reference=(string)($tx['reference']??'');$id=(string)($tx['id']??'');
    $status=(string)($tx['status']??'');$currency=(string)($tx['currency']??'');
    $amount=$tx['amount_in_cents']??null;
    $expectedEnv=str_contains(envv('WOMPI_PUBLIC_KEY'),'pub_prod_')?'prod':'test';
    if (($event['environment']??'')!==$expectedEnv || !preg_match('/^JOB-[A-F0-9]{10}$/D',$reference) || $id==='' || strlen($id)>100 || !in_array($status,['PENDING','APPROVED','DECLINED','VOIDED','ERROR'],true)||$currency!=='COP'||!is_int($amount)) {http_response_code(400);return;}
    try {
        $d=db();if (!$d) throw new RuntimeException('Sin base de datos');
        initdb($d);
        $d->beginTransaction();
        $q=$d->prepare('SELECT total, estado FROM jobs_pedidos WHERE referencia=? FOR UPDATE');$q->execute([$reference]);$order=$q->fetch();
        if (!$order || jobs_wompi_amount_cents((string)$order['total'])!==$amount) { $d->rollBack();http_response_code(422);return; }
        $q=$d->prepare('SELECT estado,transaccion_id FROM jobs_pagos WHERE referencia=? FOR UPDATE');$q->execute([$reference]);$previous=$q->fetch();
        // No permitir que notificaciones tardías deshagan un pago confirmado.
        if ($previous && $previous['estado']==='APPROVED') { $d->commit();echo '{"ok":true}';return; }
        if ($previous && $previous['transaccion_id'] && $previous['transaccion_id']!==$id && !in_array($previous['estado'],['DECLINED','VOIDED','ERROR'],true)) { $d->rollBack();http_response_code(409);return; }
        $q=$d->prepare('INSERT INTO jobs_pagos(referencia,transaccion_id,estado,metodo) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE transaccion_id=VALUES(transaccion_id),estado=VALUES(estado),metodo=VALUES(metodo)');
        $q->execute([$reference,$id,$status,substr((string)($tx['payment_method_type']??''),0,40)]);
        if ($status==='APPROVED') {
            $q=$d->prepare("UPDATE jobs_pedidos SET estado='Pagado' WHERE referencia=? AND estado='Pendiente'");$q->execute([$reference]);
        }
        $d->commit();echo '{"ok":true}';
    } catch (Throwable $e) {
        if (isset($d) && $d instanceof PDO && $d->inTransaction()) $d->rollBack();
        error_log('Wompi webhook: '.$e->getMessage());http_response_code(503);echo '{"error":"No procesado"}';
    }
}
