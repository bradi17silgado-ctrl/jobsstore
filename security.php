<?php
/** Seguridad compartida de JOBS Store. No debe exponerse directamente por HTTP. */
if (!defined('JOBS_APP')) { http_response_code(403); exit; }
function jobs_start_secure_session():void {
    // APP_ENV=production en Render activa cookies seguras detrás del proxy HTTPS.
    $secure=(getenv('APP_ENV')==='production') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off');
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    ini_set('session.cookie_httponly','1');
    session_name('JOBSSESSID');
    session_start(['cookie_httponly'=>true,'cookie_secure'=>$secure,'cookie_samesite'=>'Lax','use_strict_mode'=>true]);
}
function jobs_security_headers():void {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    // Política compatible con los scripts y estilos inline actuales. Futuro: pasar a nonces.
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: https:; connect-src 'self'");
    if (getenv('APP_ENV')==='production') header('Strict-Transport-Security: max-age=31536000');
}
/** Límite por origen: temporal, no incluye datos personales. Uso de flock para concurrencia. */
function jobs_rate_limit(string $action,int $max,int $window):void {
    $identifier=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    $key=hash('sha256',$action.'|'.$identifier);
    $dir=sys_get_temp_dir().'/jobs_rate_limits';
    if(!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)){
        error_log('No se pudo inicializar rate limit');
        http_response_code(503);exit('Servicio temporalmente no disponible.');
    }
    $file=@fopen($dir.'/'.$key.'.json','c+');
    if(!$file){http_response_code(503);exit('Servicio temporalmente no disponible.');}
    try {
        if(!flock($file,LOCK_EX)){http_response_code(503);exit('Servicio temporalmente no disponible.');}
        $saved=json_decode(stream_get_contents($file),true);
        $now=time();$started=(int)($saved['started']??0);$count=(int)($saved['count']??0);
        if($started<=0 || $now-$started >= $window){$started=$now;$count=0;}
        if($count >= $max){
            header('Retry-After: '.max(1,$window-($now-$started)));
            http_response_code(429);exit('Demasiados intentos. Intenta más tarde.');
        }
        rewind($file);ftruncate($file,0);
        fwrite($file,json_encode(['started'=>$started,'count'=>$count+1]));fflush($file);
    }finally{flock($file,LOCK_UN);fclose($file);}
}
function jobs_rate_limit_reset(string $action):void {
    $key=hash('sha256',$action.'|'.(string)($_SERVER['REMOTE_ADDR']??'unknown'));
    $file=sys_get_temp_dir().'/jobs_rate_limits/'.$key.'.json';
    // Purgar al iniciar sesión exitosamente.
    if(is_file($file))@unlink($file);
}
