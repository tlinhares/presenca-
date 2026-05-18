<?php
header('Content-Type: application/json; charset=utf-8');

function respond($code, $payload){
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
}
function http_digest($method, $url, $user, $pass, $postFields=null) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postFields) ? http_build_query($postFields) : $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
  }
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
  curl_setopt($ch, CURLOPT_USERPWD, $user . ":" . $pass);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, $resp, $err];
}

$ip    = $_GET['ip']   ?? '10.144.129.64';
$user  = $_GET['user'] ?? 'admin';
$pass  = $_GET['pass'] ?? 'Arcs2901';
$nameParam = $_GET['name'] ?? null;

$now = time();
$end   = isset($_GET['end'])   ? (int)$_GET['end']   : $now;
$start = isset($_GET['start']) ? (int)$_GET['start'] : ($now - 5*60);
if ($start > $end) { $t=$start; $start=$end; $end=$t; }

$fmt = 'Y-m-d H:i:s';
$times = [
  ['label'=>'local','start'=>date($fmt,$start),'end'=>date($fmt,$end)],
  ['label'=>'gmt',  'start'=>gmdate($fmt,$start),'end'=>gmdate($fmt,$end)],
];

$namesToTry = $nameParam ? [$nameParam] : [
  'AccessControlEvent','AccessControlFaceRec','AccessControlCardRec',
  'AccessControlLog','Event','Record','FaceRecord','CardRecord'
];

$attempts = [];
$success  = null;
$dataOut  = null;
$methods  = ['GET','POST'];

foreach ($namesToTry as $recordName) {
  foreach ($times as $t) {
    foreach ($methods as $method) {
      $base = "http://{$ip}/cgi-bin/recordFinder.cgi?action=find";
      if ($method === 'GET') {
        $url = $base . "&name={$recordName}&StartTime=" . urlencode($t['start']) . "&EndTime=" . urlencode($t['end']);
        [$code, $resp, $err] = http_digest('GET', $url, $user, $pass);
        $attempts[] = ['method'=>$method,'tz'=>$t['label'],'name'=>$recordName,'url'=>$url,'code'=>$code,'err'=>$err,'sample'=>substr(trim((string)$resp),0,160)];
      } else {
        $url = $base;
        $post = [
          'name'=>$recordName,
          'StartTime'=>$t['start'],
          'EndTime'=>$t['end'],
          // paginação comum em alguns firmwares:
          'Page'=>1, 'PageSize'=>50, 'Rows'=>50
        ];
        [$code, $resp, $err] = http_digest('POST', $url, $user, $pass, $post);
        $attempts[] = ['method'=>$method,'tz'=>$t['label'],'name'=>$recordName,'url'=>$url,'post'=>$post,'code'=>$code,'err'=>$err,'sample'=>substr(trim((string)$resp),0,160)];
      }

      if ($resp !== false && $code >= 200 && $code < 300) {
        $trim = trim((string)$resp);
        if ($trim === '') { $success = end($attempts); $dataOut=['ok'=>true,'format'=>'empty','data'=>[]]; break 3; }
        if ($trim[0]==='{' || $trim[0]==='[') { $json=json_decode($trim,true); if(json_last_error()===JSON_ERROR_NONE){ $success=end($attempts); $dataOut=['ok'=>true,'format'=>'json','data'=>$json]; break 3; } }
        if ($trim[0]==='<') { libxml_use_internal_errors(true); $xml=simplexml_load_string($trim); if($xml!==false){ $json=json_decode(json_encode($xml),true); $success=end($attempts); $dataOut=['ok'=>true,'format'=>'xml','data'=>$json]; break 3; } }
        $success=end($attempts); $dataOut=['ok'=>true,'format'=>'text','raw'=>$trim]; break 3;
      }
    }
  }
}
if ($success) {
  respond(200, ['ok'=>true,'used'=>['method'=>$success['method'],'timezone_format'=>$success['tz'],'name'=>$success['name'],'endpoint'=>$success['url'],'post'=>$success['post']??null],'result'=>$dataOut]);
}
respond(400, ['ok'=>false,'message'=>'O dispositivo retornou erro em todas as tentativas (name/timezone/metodo).','tried'=>$attempts]);
