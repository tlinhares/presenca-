<?php
/**
 * Teste do formato JSON duplo que o dispositivo está retornando
 */

echo "<h1>🧪 Teste do Formato JSON Duplo</h1>";

// Simular exatamente o formato que o dispositivo está retornando
$dados_dispositivo = '{"data":"{\n   \"Alive\" : 100,\n   \"CurrentTime\" : 278733333,\n   \"Door\" : 0,\n   \"FeatureId\" : 1,\n   \"HatColor\" : [ 0, 0, 0, 255 ],\n   \"HatType\" : 0,\n   \"OpenDoorMethod\" : 3,\n   \"PartInfo\" : {\n      \"FeatureId\" : 1,\n      \"ObjectID\" : 26,\n      \"Region\" : [ 341, 469, 709, 764 ],\n      \"Sequence\" : 1671865\n   },\n   \"RealUTC\" : 1761305139,\n   \"Similarity\" : 95,\n   \"SnapPath\" : \"\",\n   \"UserID\" : \"22222\",\n   \"readID\" : 1\n}","Code":"_DoorFace_","action":"Pulse","index":"0","Alive":100,"CurrentTime":278733333,"Door":0,"FeatureId":1,"HatColor":[0,0,0,255],"HatType":0,"OpenDoorMethod":3,"PartInfo":{"FeatureId":1,"ObjectID":26,"Region":[341,469,709,764],"Sequence":1671865},"RealUTC":1761305139,"Similarity":95,"SnapPath":"","UserID":"22222","readID":1,"ISOTime":"2025-10-24 07:25:39","Event":"_DoorFace_"}';

echo "<h2>📋 Dados Brutos do Dispositivo:</h2>";
echo "<pre>" . htmlspecialchars($dados_dispositivo) . "</pre>";

// Testar a função processarEventosDispositivo
function processarEventosDispositivo($body) {
    $eventos = [];
    
    if (empty($body)) {
        return $eventos;
    }
    
    echo "<h3>🔍 Processando dados:</h3>";
    echo "<p><strong>Tamanho:</strong> " . strlen($body) . " bytes</p>";
    
    // Tentar JSON direto com campo "data"
    $json = json_decode($body, true);
    if (is_array($json)) {
        echo "<p style='color: green;'>✅ <strong>JSON válido detectado</strong></p>";
        
        // Se tem campo "data" com JSON string, decodificar
        if (isset($json['data']) && is_string($json['data'])) {
            echo "<p><strong>Campo 'data' encontrado:</strong> " . htmlspecialchars(substr($json['data'], 0, 100)) . "...</p>";
            
            $data_json = json_decode($json['data'], true);
            if (is_array($data_json)) {
                echo "<p style='color: green;'>✅ <strong>JSON do campo 'data' decodificado com sucesso</strong></p>";
                echo "<h4>Dados do campo 'data':</h4>";
                echo "<pre>" . htmlspecialchars(json_encode($data_json, JSON_PRETTY_PRINT)) . "</pre>";
                
                // Usar os dados do campo "data" como evento principal
                $eventos[] = normalizarEvento($data_json);
            } else {
                echo "<p style='color: red;'>❌ <strong>Erro ao decodificar JSON do campo 'data'</strong> - " . json_last_error_msg() . "</p>";
            }
        } else {
            echo "<p><strong>Campo 'data' não encontrado, usando JSON direto</strong></p>";
            $eventos[] = normalizarEvento($json);
        }
    } else {
        echo "<p style='color: red;'>❌ <strong>JSON inválido</strong> - " . json_last_error_msg() . "</p>";
    }
    
    return $eventos;
}

function normalizarEvento($event) {
    echo "<h4>🔧 Normalizando evento:</h4>";
    echo "<pre>" . htmlspecialchars(json_encode($event, JSON_PRETTY_PRINT)) . "</pre>";
    
    // CORREÇÃO: Usar UserID (que é string) em vez de readID (que é interno do dispositivo)
    $user_id = $event['UserID'] ?? $event['PersonID'] ?? $event['EmployeeNo'] ?? $event['Employee'] ?? $event['ID'] ?? null;
    
    // Converter para inteiro se necessário
    if ($user_id && is_string($user_id)) {
        $user_id = (int) $user_id;
    }
    
    echo "<p><strong>UserID extraído:</strong> " . ($user_id ?? 'NULL') . " (tipo: " . gettype($user_id) . ")</p>";
    
    return [
        'user_id' => $user_id,
        'card' => $event['CardNo'] ?? $event['CardID'] ?? $event['Card'] ?? null,
        'face' => $event['FaceID'] ?? $event['Face'] ?? null,
        'event_type' => $event['EventType'] ?? $event['Code'] ?? $event['Event'] ?? 'FaceRecognition',
        'result' => $event['Pass'] ?? $event['Result'] ?? $event['Status'] ?? 'Pass',
        'time' => $event['Time'] ?? $event['DateTime'] ?? $event['ISOTime'] ?? $event['Date'] ?? date('Y-m-d H:i:s'),
        'raw' => $event
    ];
}

$eventos = processarEventosDispositivo($dados_dispositivo);

echo "<h2>✅ Resultado Final:</h2>";
echo "<p><strong>Eventos processados:</strong> " . count($eventos) . "</p>";

foreach ($eventos as $i => $evento) {
    echo "<h3>Evento " . ($i + 1) . ":</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($evento, JSON_PRETTY_PRINT)) . "</pre>";
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
