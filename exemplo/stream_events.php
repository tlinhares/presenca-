<?php
// stream_events.php — v4 (SSE)
// ✔ Conserta horário: usa offset do dispositivo (getCurrentTime) ou tz_offset_minutes
@ini_set('output_buffering','off');
@ini_set('zlib.output_compression',0);
@ini_set('implicit_flush',1);
@ini_set('max_execution_time',0);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

function flush_sse(){ echo str_repeat(' ',2048)."\n"; @ob_flush(); @flush(); }

$ip    = $_GET['ip']    ?? '';
$user  = $_GET['user']  ?? '';
$pass  = $_GET['pass']  ?? '';
$codes = $_GET['codes'] ?? '[All]';
$hb    = isset($_GET['heartbeat']) ? (int)$_GET['heartbeat'] : 5;

// override manual opcional: minutos (ex.: -180 = -3h)
$manualOffsetMin = isset($_GET['tz_offset_minutes']) ? (int)$_GET['tz_offset_minutes'] : null;

if ($ip===''||$user===''||$pass==='') {
  echo "event: error\ndata: ".json_encode(['error'=>'Parâmetros obrigatórios: ip, user, pass'])."\n\n"; flush_sse(); exit;
}

// 1) Calcular offset de fuso do DISPOSITIVO
$tzOffsetSec = 0;
if ($manualOffsetMin !== null) {
  $tzOffsetSec = $manualOffsetMin * 60;
} else {
  // lê hora local do device
  $devTimeUrl = "http://{$ip}/cgi-bin/global.cgi?action=getCurrentTime";
  [$code,$body] = http_digest_get($devTimeUrl, $user, $pass);
  // formato típico: result=YYYY-MM-DD HH:MM:SS
  if ($code>=200 && $code<300 && preg_match('/result\s*=\s*([0-9:\- ]{19})/i', $body, $m)) {
    $deviceLocalStr = $m[1];
    // interpreta a string no fuso do SERVIDOR apenas para obter um epoch comparável
    // depois calcula offset relativo ao epoch UTC atual
    $deviceLocalEpoch_assumingServerTZ = strtotime($deviceLocalStr);
    if ($deviceLocalEpoch_assumingServerTZ !== false) {
      $tzOffsetSec = $deviceLocalEpoch_assumingServerTZ - time();
    }
  }
}

$endpoint = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach"
          . "&codes=" . urlencode($codes)
          . "&heartbeat=" . max(1,$hb);

// hello com debug do offset
echo "event: hello\n";
echo "data: ".json_encode([
  'endpoint'=>$endpoint,
  'tz_offset_seconds'=>$tzOffsetSec,
  'tz_offset_human'=>sprintf('%+d:%02d', intdiv($tzOffsetSec,3600), abs(($tzOffsetSec/60)%60))
])."\n\n";
flush_sse();

// 2) Abre stream e processa
$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL            => $endpoint,
  CURLOPT_HTTPAUTH       => CURLAUTH_DIGEST,
  CURLOPT_USERPWD        => $user.":".$pass,
  CURLOPT_HEADER         => 0,
  CURLOPT_RETURNTRANSFER => false,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT        => 0,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_SSL_VERIFYHOST => 0,
  CURLOPT_WRITEFUNCTION  => function($ch,$chunk) use ($tzOffsetSec) {
    static $buf='';
    $buf .= $chunk;

    while (true) {
      $p = strpos($buf, "\r\n\r\n");
      if ($p===false) break;

      $headers = substr($buf,0,$p);
      $rest    = substr($buf,$p+4);

      $len = null;
      if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $m)) $len=(int)$m[1];

      if ($len!==null) {
        if (strlen($rest) < $len) { $buf = $headers."\r\n\r\n".$rest; break; }
        $body = substr($rest,0,$len);
        $buf  = substr($rest,$len);
      } else {
        $next = strpos($rest, "\r\n--");
        if ($next===false) { $buf = $headers."\r\n\r\n".$rest; break; }
        $body = substr($rest,0,$next);
        $buf  = substr($rest,$next+2);
      }

      $block = trim($body);
      if ($block==='') { echo "event: ping\ndata: ".json_encode(['t'=>time()])."\n\n"; flush_sse(); continue; }

      // JSON direto?
      $j = json_decode($block, true);
      if (is_array($j)) {
        $j = enrichTime($j, $tzOffsetSec);
        echo "event: message\n";
        echo "data: ".json_encode($j,JSON_UNESCAPED_UNICODE)."\n\n";
        flush_sse();
        continue;
      }

      // key=value com data={...}
      $kv  = parse_kv_block($block);
      $out = $kv;

      if (!empty($kv['data']) && is_string($kv['data'])) {
        $dataJson = json_decode($kv['data'], true);
        if (is_array($dataJson)) foreach ($dataJson as $k=>$v) $out[$k]=$v;
      }

      $out = enrichTime($out, $tzOffsetSec);

      echo "event: message\n";
      echo "data: ".json_encode($out,JSON_UNESCAPED_UNICODE)."\n\n";
      flush_sse();
    }

    echo "event: ping\ndata: ".json_encode(['t'=>time()])."\n\n"; flush_sse();
    return strlen($chunk);
  }
]);

curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
if ($err) {
  echo "event: error\ndata: ".json_encode(['curl_error'=>$err])."\n\n"; flush_sse();
}

/* ------------ helpers ------------- */

function http_digest_get($url,$user,$pass){
  $ch=curl_init();
  curl_setopt_array($ch,[
    CURLOPT_URL=>$url,
    CURLOPT_HTTPAUTH=>CURLAUTH_DIGEST,
    CURLOPT_USERPWD=>$user.":".$pass,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_SSL_VERIFYHOST=>0,
    CURLOPT_TIMEOUT=>10,
  ]);
  $body=curl_exec($ch);
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code,(string)$body];
}

function parse_kv_block($block){
  $out = [];
  if (($pos = strpos($block, 'data=')) !== false) {
    $after = substr($block, $pos+5);
    $after = ltrim($after);
    if (strlen($after) && $after[0]==='{') {
      $json = capture_json($after);
      if ($json !== null) {
        $out['data'] = $json;
        $block = substr($block, 0, $pos);
      }
    }
  }
  $pairs = preg_split('/[;\r\n]+/', $block);
  foreach ($pairs as $pair) {
    $pair = trim($pair);
    if ($pair==='' || strpos($pair,'=')===false) continue;
    [$k,$v] = array_map('trim', explode('=',$pair,2));
    if ($k!=='') $out[$k] = $v;
  }
  return $out;
}

function capture_json($s){
  $depth=0; $inStr=false; $esc=false;
  for ($i=0;$i<strlen($s);$i++){
    $ch=$s[$i];
    if ($inStr){ if($esc){$esc=false;continue;} if($ch==='\\'){$esc=true;continue;} if($ch==='"'){$inStr=false;continue;} }
    else { if($ch==='"'){$inStr=true;continue;} if($ch==='{'){$depth++;} if($ch==='}'){ $depth--; if($depth===0){ return substr($s,0,$i+1);} } }
  }
  return null;
}

function enrichTime(array $o, int $offsetSec){
  // Converte epoch (RealUTC/UTC/UTCTime/Timestamp) para ISOTime aplicando offset do DISPOSITIVO
  $epoch = null;
  foreach (['RealUTC','UTC','UTCTime','Timestamp'] as $k){
    if (isset($o[$k]) && is_numeric($o[$k])) { $epoch = (int)$o[$k]; break; }
  }
  if ($epoch !== null && $epoch>0 && $epoch< 99999999999) {
    $o['ISOTime'] = date('Y-m-d H:i:s', $epoch + $offsetSec);
  }
  if (!isset($o['Event']) && isset($o['Code'])) $o['Event']=$o['Code'];
  if (!isset($o['UserID']) && isset($o['PersonID'])) $o['UserID']=$o['PersonID'];
  return $o;
}
