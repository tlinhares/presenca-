<?php
/**
 * Teste específico para parsing _DoorFace_
 */

echo "<h1>🔍 Teste Parsing _DoorFace_</h1>";

// Simular o formato exato que está sendo recebido
$block = 'Code=_DoorFace_;action=Pulse;index=0;data={
   "Alive" : 100,
   "CurrentTime" : 285452474,
   "Door" : 0,
   "FeatureId" : 1,
   "HatColor" : [ 0, 0, 0, 255 ],
   "HatType" : 0,
   "OpenDoorMethod" : 3,
   "PartInfo" : {
      "FeatureId" : 1,
      "ObjectID" : 54,
      "Region" : [ 287, 206, 758, 625 ],
      "Sequence" : 1711706
   },
   "RealUTC" : 1761311858,
   "Similarity" : 92,
   "SnapPath" : "",
   "UserID" : "22222",
   "readID" : 1
}';

echo "<h3>Block de teste:</h3>";
echo "<pre>" . htmlspecialchars($block) . "</pre>";

// Testar o parsing corrigido
$lines = explode("\n", $block);
$event = [];
$data_json = null;
$in_data = false;
$data_content = '';

echo "<h3>Processando linhas:</h3>";

foreach ($lines as $i => $line) {
    $line = trim($line);
    if (empty($line)) continue;
    
    echo "<p><strong>Linha $i:</strong> " . htmlspecialchars($line) . "</p>";
    
    if (strpos($line, '=') !== false && !$in_data) {
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        echo "<p><strong>Key:</strong> " . htmlspecialchars($key) . "</p>";
        echo "<p><strong>Value:</strong> " . htmlspecialchars(substr($value, 0, 100)) . "...</p>";
        
        if ($key === 'data' && strpos($value, '{') === 0) {
            echo "<p style='color: blue;'>🔍 <strong>Campo data encontrado - começando captura JSON</strong></p>";
            $in_data = true;
            $data_content = $value;
        } else {
            $event[$key] = $value;
            echo "<p><strong>Campo adicionado:</strong> $key = " . htmlspecialchars(substr($value, 0, 50)) . "...</p>";
        }
    } elseif ($in_data) {
        echo "<p style='color: blue;'>📝 <strong>Capturando JSON:</strong> " . htmlspecialchars($line) . "</p>";
        $data_content .= "\n" . $line;
        
        if (strpos($line, '}') !== false) {
            echo "<p style='color: green;'>🏁 <strong>Fim do JSON detectado</strong></p>";
            $in_data = false;
            
            $data_json = json_decode($data_content, true);
            if (is_array($data_json)) {
                echo "<p style='color: green;'>✅ <strong>JSON decodificado com sucesso!</strong></p>";
                echo "<pre>" . htmlspecialchars(json_encode($data_json, JSON_PRETTY_PRINT)) . "</pre>";
                
                $event = array_merge($event, $data_json);
                
                if (isset($data_json['UserID'])) {
                    echo "<p style='color: red; font-weight: bold;'>🎯 <strong>UserID ENCONTRADO: " . $data_json['UserID'] . "</strong></p>";
                }
            } else {
                echo "<p style='color: red;'>❌ <strong>Erro ao decodificar JSON:</strong> " . json_last_error_msg() . "</p>";
            }
        }
    }
}

echo "<h3>Evento final:</h3>";
echo "<pre>" . htmlspecialchars(json_encode($event, JSON_PRETTY_PRINT)) . "</pre>";

// Verificar se é _DoorFace_
if (isset($event['Code']) && strpos($event['Code'], '_DoorFace_') !== false) {
    echo "<p style='color: orange; font-weight: bold;'>🎯 <strong>EVENTO _DoorFace_ DETECTADO!</strong></p>";
    
    if (isset($event['UserID'])) {
        echo "<p style='color: red; font-weight: bold;'>🎯 <strong>UserID: " . $event['UserID'] . "</strong></p>";
        echo "<p style='color: green;'>✅ <strong>Pronto para processar presença!</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>UserID não encontrado</strong></p>";
    }
} else {
    echo "<p style='color: gray;'>⚪ <strong>Evento não é _DoorFace_</strong></p>";
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
