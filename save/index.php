<?php
$baseDir = __DIR__;
$logFile = $baseDir . '/events.log';
$imgDir  = $baseDir . '/images';

// Le as últimas N linhas do log
function tail($filepath, $lines = 30) {
    if (!file_exists($filepath)) return [];
    $f = fopen($filepath, "rb");
    if (!$f) return [];
    $pos = -1;
    $buffer = '';
    $count = 0;
    while ($count < $lines) {
        if (fseek($f, $pos, SEEK_END) === -1) break;
        $char = fgetc($f);
        if ($char === "\n" && $buffer !== '') $count++;
        if (ftell($f) === 0) {
            rewind($f);
            $buffer = stream_get_contents($f) . $buffer;
            break;
        }
        $pos--;
        $buffer = $char . $buffer;
    }
    fclose($f);
    $linesArr = array_filter(explode("\n", trim($buffer)));
    return array_slice($linesArr, -$lines);
}

$rows = tail($logFile, 50);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Visualizador de Eventos Faciais</title>
  <style>
    body { font-family: Arial, sans-serif; margin:20px; }
    .event { border:1px solid #ddd; padding:10px; margin-bottom:10px; border-radius:6px; }
    .event pre { white-space:pre-wrap; word-wrap:break-word; }
    img { max-width:240px; display:block; margin-top:8px; }
  </style>
</head>
<body>
  <h1>Eventos Recebidos (últimas linhas)</h1>
  <?php if (empty($rows)): ?>
    <p>Nenhum evento registrado ainda.</p>
  <?php else: ?>
    <?php foreach (array_reverse($rows) as $line): ?>
      <div class="event">
        <pre><?php echo htmlspecialchars($line, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
        <?php
           // Tenta localizar imagens mencionadas no log (nome de arquivo)
           if (preg_match_all('/"IMAGE_SAVED ([^"]+)"/', $line, $m)) {
               foreach ($m[1] as $img) {
                   $p = $imgDir . '/' . $img;
                   if (file_exists($p)) {
                       $url = 'images/' . rawurlencode($img);
                       echo "<img src=\"{$url}\" alt=\"{$img}\">";
                   }
               }
           }
           // Também procura por file paths no JSON (campo FilePath)
           if (preg_match('/"FilePath"\s*:\s*"([^"]+)"/', $line, $m2)) {
               echo "<small>FilePath do dispositivo: " . htmlspecialchars($m2[1]) . "</small>";
           }
        ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
