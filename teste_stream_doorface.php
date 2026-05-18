<?php
/**
 * Teste específico para capturar eventos _DoorFace_
 */

echo "<h1>🔍 Teste Stream _DoorFace_</h1>";

// Configurações do dispositivo
$ip = "10.144.129.64";
$user = "admin";
$pass = "Arcs2901";
$codes = "[_DoorFace_]";

echo "<h3>Configurações:</h3>";
echo "<p><strong>IP:</strong> $ip</p>";
echo "<p><strong>Usuário:</strong> $user</p>";
echo "<p><strong>Codes:</strong> $codes</p>";

echo "<h3>Iniciando captura de eventos...</h3>";
echo "<p><strong>Faça sua leitura facial no dispositivo agora!</strong></p>";

// URL do stream_events.php
$url = "https://presenca.aom.org.br/exemplo/stream_events.php?ip=" . urlencode($ip) . "&user=" . urlencode($user) . "&pass=" . urlencode($pass) . "&codes=" . urlencode($codes) . "&heartbeat=5";

echo "<p><strong>URL:</strong> " . htmlspecialchars($url) . "</p>";

// Capturar eventos por 30 segundos
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<p style='color: red;'><strong>Erro cURL:</strong> " . htmlspecialchars($error) . "</p>";
} else {
    echo "<h3>Resposta recebida:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Processar eventos
    $lines = explode("\n", $response);
    $eventos = [];
    
    foreach ($lines as $line) {
        if (strpos($line, 'event: message') !== false) {
            $next_line = next($lines);
            if ($next_line && strpos($next_line, 'data:') === 0) {
                $data = substr($next_line, 5);
                $json = json_decode($data, true);
                if (is_array($json) && !empty($json)) {
                    $eventos[] = $json;
                }
            }
        }
    }
    
    echo "<h3>Eventos capturados: " . count($eventos) . "</h3>";
    
    foreach ($eventos as $i => $evento) {
        echo "<h4>Evento " . ($i + 1) . ":</h4>";
        echo "<pre>" . htmlspecialchars(json_encode($evento, JSON_PRETTY_PRINT)) . "</pre>";
        
        // Verificar se é _DoorFace_
        if (isset($evento['Code']) && $evento['Code'] === '_DoorFace_') {
            echo "<p style='color: orange; font-weight: bold;'>🎯 <strong>EVENTO _DoorFace_ ENCONTRADO!</strong></p>";
            
            if (isset($evento['UserID'])) {
                echo "<p style='color: red; font-weight: bold;'>🎯 <strong>UserID: " . $evento['UserID'] . "</strong></p>";
            }
            if (isset($evento['ID'])) {
                echo "<p style='color: red; font-weight: bold;'>🎯 <strong>ID: " . $evento['ID'] . "</strong></p>";
            }
            if (isset($evento['EmployeeNo'])) {
                echo "<p style='color: red; font-weight: bold;'>🎯 <strong>EmployeeNo: " . $evento['EmployeeNo'] . "</strong></p>";
            }
            if (isset($evento['PersonID'])) {
                echo "<p style='color: red; font-weight: bold;'>🎯 <strong>PersonID: " . $evento['PersonID'] . "</strong></p>";
            }
        }
    }
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
