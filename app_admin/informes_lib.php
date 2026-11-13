<?php
if (!defined('JOBS_APP')) { http_response_code(403); exit('Acceso denegado'); }

/** Audit log. Invoke inside the same transaction as the stock update. */
function jobs_registrar_movimiento(PDO $d, string $id, string $nombre, ?string $color, string $tipo, int $cantidad, int $antes, int $despues, ?float $costo=null, ?string $proveedor=null, ?string $ref=null, ?string $motivo=null, string $usuario='administrador'):void {
    $q=$d->prepare('INSERT INTO jobs_movimientos (producto_id,producto_nombre,color,tipo,cantidad,stock_anterior,stock_nuevo,costo_unitario,proveedor,referencia,motivo,usuario) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute([$id,$nombre,$color,$tipo,$cantidad,$antes,$despues,$costo,$proveedor,$ref,$motivo,$usuario]);
}

function jobs_filtros_informe():array {
    $tipo=(string)($_GET['tipo']??'general');
    if(!in_array($tipo,['general','ventas','entradas','salidas'],true))$tipo='general';
    $desde=(string)($_GET['desde']??date('Y-m-01'));
    $hasta=(string)($_GET['hasta']??date('Y-m-d'));
    foreach([$desde,$hasta] as $v){
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$v);
        if(!$dt || $dt->format('Y-m-d')!==$v)throw new RuntimeException('Fecha inválida.');
    }
    if($hasta<$desde)throw new RuntimeException('La fecha final no puede ser anterior a la inicial.');
    $start=new DateTimeImmutable($desde);
    $end=new DateTimeImmutable($hasta);
    if($start->diff($end)->days>366)throw new RuntimeException('Selecciona máximo 367 días por informe.');
    return [$tipo,$desde,$hasta,$end->modify('+1 day')->format('Y-m-d')];
}

/** Ventas confirmadas como entregadas; pedidos pendientes do not count as revenue. */
function jobs_datos_informe(PDO $db,string $tipo,string $desde,string $hastaExclusiva):array {
    $q=$db->prepare("SELECT referencia,cliente,total,estado,creado,items_json FROM jobs_pedidos WHERE estado='Entregado' AND creado>=? AND creado<? ORDER BY creado DESC,id DESC");
    $q->execute([$desde,$hastaExclusiva]);$ventas=$q->fetchAll();
    $resumen=['cantidad_ventas'=>count($ventas),'total_ventas'=>0.0,'unidades_vendidas'=>0,'entradas'=>0,'salidas'=>0,'costo_entradas'=>0.0];
    $masVendidos=[];
    foreach($ventas as $v){
        $resumen['total_ventas']+=(float)$v['total'];
        $lineas=json_decode((string)$v['items_json'],true);
        if(!is_array($lineas))continue;
        foreach($lineas as $linea){if(!is_array($linea))continue;
            $n=(int)($linea['cantidad']??0);$resumen['unidades_vendidas']+=$n;
            $nombre=(string)($linea['nombre']??'Producto');
            $color=(string)($linea['color']??'');
            $key=$nombre.'|'.$color;
            if(!isset($masVendidos[$key]))$masVendidos[$key]=['producto'=>$nombre,'color'=>$color,'cantidad'=>0];
            $masVendidos[$key]['cantidad']+=$n;
        }
    }
    usort($masVendidos,static fn($a,$b)=>$b['cantidad']<=>$a['cantidad']);
    $q=$db->prepare('SELECT m.id,m.producto_id,m.producto_nombre,m.color,m.tipo,m.cantidad,m.stock_anterior,m.stock_nuevo,m.costo_unitario,m.proveedor,m.referencia,m.motivo,m.usuario,m.creado, a.movimiento_id IS NOT NULL AS anulado FROM jobs_movimientos m LEFT JOIN jobs_ingresos_anulados a ON a.movimiento_id=m.id WHERE m.creado>=? AND m.creado<? ORDER BY m.creado DESC,m.id DESC LIMIT 10000');
    $q->execute([$desde,$hastaExclusiva]);$movimientos=$q->fetchAll();
    foreach($movimientos as $m){
        if($m['tipo']==='entrada' && !$m['anulado']){
            $resumen['entradas']+=(int)$m['cantidad'];
            $resumen['costo_entradas']+=(float)($m['costo_unitario']??0)*(int)$m['cantidad'];
        }
        if(in_array($m['tipo'],['salida','pedido'],true))$resumen['salidas']+=(int)$m['cantidad'];
    }
    if($tipo==='entradas')$movimientos=array_values(array_filter($movimientos,static fn($m)=>$m['tipo']==='entrada' && !$m['anulado']));
    if($tipo==='salidas')$movimientos=array_values(array_filter($movimientos,static fn($m)=>in_array($m['tipo'],['salida','pedido'],true)));
    $q=$db->query('SELECT nombre,stock FROM jobs_productos ORDER BY nombre');
    $existencias=$q->fetchAll();
    return compact('ventas','movimientos','resumen','masVendidos','existencias');
}

function jobs_titulo_informe(string $tipo):string {
    return ['general'=>'Informe general','ventas'=>'Informe de ventas','entradas'=>'Ingreso de mercancía','salidas'=>'Salida de mercancía'][$tipo]??'Informe general';
}

function jobs_descargar_informe_pdf():never {
    header('Cache-Control: no-store, private');
    try {
        [$tipo,$desde,$hasta,$hastaExclusiva]=jobs_filtros_informe();
        $db=db();if(!$db)throw new RuntimeException('Railway no está disponible.');
        initdb($db);
        $datos=jobs_datos_informe($db,$tipo,$desde,$hastaExclusiva);
        $autoload=__DIR__.'/../vendor/autoload.php';
        if(!is_file($autoload))throw new RuntimeException('El motor de PDF no está instalado. Ejecuta composer install o despliega usando Dockerfile.');
        require_once $autoload;
        $opciones=new \Dompdf\Options();
        $opciones->set('isRemoteEnabled',false);
        $opciones->set('isPhpEnabled',false);
        $opciones->set('defaultFont','DejaVu Sans');
        $pdf=new \Dompdf\Dompdf($opciones);
        ob_start();
        require __DIR__.'/informe_pdf_plantilla.php';
        $html=ob_get_clean();
        $pdf->loadHtml($html,'UTF-8');
        $pdf->setPaper('letter','portrait');
        $pdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="jobs-store-'.$tipo.'-'.$desde.'-'.$hasta.'.pdf"');
        echo $pdf->output();
    }catch(Throwable $e){
        while(ob_get_level()>0)ob_end_clean();
        error_log('Exportar PDF: '.$e->getMessage());
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo generar el PDF. Consulta los logs del servidor.';
    }
    exit;
}
