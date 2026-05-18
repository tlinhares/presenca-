<?php
/**
 * Teste com evento simulado baseado no exemplo que funcionou
 */

require_once 'api/conexao.php';

echo "<h1>🧪 Teste com Evento Simulado</h1>";

// Simular o evento que funcionou no exemplo
$evento_simulado = [
    "data" => "{\n   \"Alive\" : 100,\n   \"CurrentTime\" : 278733333,\n   \"Door\" : 0,\n   \"FeatureId\" : 1,\n   \"HatColor\" : [ 0, 0, 0, 255 ],\n   \"HatType\" : 0,\n   \"OpenDoorMethod\" : 3,\n   \"PartInfo\" : {\n      \"FeatureId\" : 1,\n      \"ObjectID\" : 26,\n      \"Region\" : [ 341, 469, 709, 764 ],\n      \"Sequence\" : 1671865\n   },\n   \"RealUTC\" : 1761305139,\n   \"Similarity\" : 95,\n   \"SnapPath\" : \"\",\n   \"UserID\" : \"22222\",\n   \"readID\" : 1\n}",
    "Code" => "_DoorFace_",
    "action" => "Pulse",
    "index" => "0",
    "Alive" => 100,
    "CurrentTime" => 278733333,
    "Door" => 0,
    "FeatureId" => 1,
    "HatColor" => [0,0,0,255],
    "HatType" => 0,
    "OpenDoorMethod" => 3,
    "PartInfo" => [
        "FeatureId" => 1,
        "ObjectID" => 26,
        "Region" => [341,469,709,764],
        "Sequence" => 1671865
    ],
    "RealUTC" => 1761305139,
    "Similarity" => 95,
    "SnapPath" => "",
    "UserID" => "22222",
    "readID" => 1,
    "ISOTime" => "2025-10-24 07:25:39",
    "Event" => "_DoorFace_"
];

echo "<h2>📋 Evento Simulado:</h2>";
echo "<pre>" . htmlspecialchars(json_encode($evento_simulado, JSON_PRETTY_PRINT)) . "</pre>";

// Testar a função normalizarEvento
function normalizarEvento($event) {
    echo "<h3>🔍 Processando evento:</h3>";
    
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

$evento_normalizado = normalizarEvento($evento_simulado);

echo "<h2>✅ Evento Normalizado:</h2>";
echo "<pre>" . htmlspecialchars(json_encode($evento_normalizado, JSON_PRETTY_PRINT)) . "</pre>";

// Verificar se o usuário existe
echo "<h2>👤 Verificação do Usuário:</h2>";
try {
    $stmt = $conn->prepare("SELECT id, nome, email, ativo FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $evento_normalizado['user_id']);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();
        echo "<p style='color: green;'>✅ <strong>Usuário encontrado:</strong> " . htmlspecialchars($usuario['nome']) . " (ID: " . htmlspecialchars($usuario['id']) . ")</p>";
        echo "<pre>" . htmlspecialchars(json_encode($usuario, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ <strong>Usuário não encontrado:</strong> ID " . htmlspecialchars($evento_normalizado['user_id']) . "</p>";
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro ao verificar usuário: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
