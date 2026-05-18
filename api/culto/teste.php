<?php
// ===== CONFIG =====
$ip   = '10.144.129.78';      // IP do SS3530
$user = 'admin';
$pass = 'Arcs2901';

// Tabelas possíveis (ordem de tentativa)
$recordNames = ['AccessControlFaceRec', 'AccessControlEvent', 'AccessControlCardRec'];

// Janela de tempo: últimos 5 minutos (use hora LOCAL do device)
$endTs   = time();
$startTs = $endTs - 5 * 60;

// Formato de data aceito pelo device
$fmt   = 'Y-m-d H:i:s';
$start = urlencode(date($fmt, $startTs));  // hora local
$end   = urlencode(date($fmt, $endTs));

// Parâmetros extras exigidos por alguns firmwares
$position = 0;
$count    = 50;

// ===== FUNÇÃO REQUISIÇÃO =====
function dahuaFind($ip, $user, $pass, $name, $start, $end, $pos, $count) {
    $url = "http://{$ip}/cgi-bin/recordFinder.cgi?action=find"
         . "&name={$name}&StartTime={$start}&EndTime={$end}"
         . "&Position={$pos}&Count={$count}";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH       => CURLAUTH_DIGEST,
        CURLOPT_USERPWD        => "{$user}:{$pass}",
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, $err, $body, $url];
}

// ===== TENTA EM ORDEM =====
$result = null;
foreach ($recordNames as $rn) {
    [$http, $err, $body, $url] = dahuaFind($ip, $user, $pass, $rn, $start, $end, $position, $count);
    if ($http === 200 && $body) { $result = [$rn, $http, $err, $body, $url]; break; }
    // Se 400/404/500, mantém última resposta para exibir
    $lastErr = [$rn, $http, $err, $body, $url];
}

if (!$result && isset($lastErr)) $result = $lastErr;
list($recordName, $http, $err, $resp, $reqUrl) = $result;

// ===== PARSE "table.*=valor" =====
function parseDahuaTable($text, $recordName) {
    $out = ['Total' => 0, 'Rows' => []];
    if (preg_match('/table\.Total=(\d+)/', $text, $m)) $out['Total'] = (int)$m[1];

    $lines = preg_split('/\r\n|\r|\n/', $text);
    foreach ($lines as $line) {
        if (strpos($line, "table.{$recordName}[") !== 0) continue;
        if (preg_match('/^table\.'.preg_quote($recordName,'/').'\[(\d+)\]\.([^=]+)=(.*)$/', $line, $m)) {
            $idx   = (int)$m[1];
            $field = trim($m[2]);
            $value = trim($m[3]);
            if (!isset($out['Rows'][$idx])) $out['Rows'][$idx] = [];
            $out['Rows'][$idx][$field] = $value;
        }
    }
    foreach ($out['Rows'] as &$row) {
        $row['_ts'] = isset($row['Time']) ? strtotime($row['Time']) : 0; // Time já vem local
    }
    unset($row);
    usort($out['Rows'], fn($a,$b) => $a['_ts'] <=> $b['_ts']);
    return $out;
}

$parsed = ($http === 200 && $resp) ? parseDahuaTable($resp, $recordName) : ['Total'=>0,'Rows'=>[]];
$last   = !empty($parsed['Rows']) ? end($parsed['Rows']) : null;

// ===== SAÍDA =====
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Última leitura SS3530</title>
<meta http-equiv="refresh" content="3">
<style>
 body{font-family:system-ui,Arial,sans-serif;margin:20px}
 .box{border:1px solid #ddd;border-radius:10px;padding:16px;max-width:920px}
 .key{color:#555;width:220px;display:inline-block}
 h1{margin-top:0;font-size:18px}
 pre{background:#f6f8fa;padding:12px;overflow:auto;max-height:360px}
</style>
</head>
<body>
<div class="box">
  <h1>Última leitura do SS3530 (<?=htmlspecialchars($recordName)?>)</h1>
  <div><span class="key">Request URL:</span> <?=htmlspecialchars($reqUrl)?></div>
  <div><span class="key">Janela:</span> <?=date('Y-m-d H:i:s', $startTs)?> → <?=date('Y-m-d H:i:s', $endTs)?></div>
  <div><span class="key">HTTP:</span> <?=$http?> <?=$err ? ' - '.$err : ''?></div>
  <div><span class="key">Total eventos:</span> <?=$parsed['Total']?></div>

<?php if ($http===200 && $last): ?>
  <h2>Evento mais recente</h2>
  <?php foreach ($last as $k=>$v): if ($k==='_ts') continue; ?>
    <div><span class="key"><?=htmlspecialchars($k)?>:</span> <?=htmlspecialchars($v)?></div>
  <?php endforeach; ?>
<?php elseif ($http===200): ?>
  <p><em>Nenhum evento nesse intervalo.</em></p>
<?php else: ?>
  <p><strong>O device respondeu HTTP <?=$http?>.</strong> Abaixo o corpo devolvido (útil para entender o 400):</p>
  <pre><?=htmlspecialchars($resp ?? '')?></pre>
<?php endif; ?>

  <details>
    <summary>Resposta bruta</summary>
    <pre><?=htmlspecialchars($resp ?? '')?></pre>
  </details>
</div>
</body>
</html>
