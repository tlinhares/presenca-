<?php
/**
 * Simula o que o dispositivo facial faz
 * Envia dados como o dispositivo real enviaria
 */

echo "<h1>📱 Simulação de Dispositivo Facial</h1>";

// Dados que o dispositivo enviaria
$dados_dispositivo = [
    'nome_usuario' => 'João Silva',
    'ip_dispositivo' => '192.168.1.100',
    'timestamp' => time(),
    'foto_base64' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ...'
];

echo "<h2>1. Dados que o dispositivo enviaria:</h2>";
echo "<pre>" . htmlspecialchars(json_encode($dados_dispositivo, JSON_PRETTY_PRINT)) . "</pre>";

echo "<h2>2. Simulando requisição POST:</h2>";

// Simular exatamente o que o dispositivo faria
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_USER_AGENT'] = 'Dispositivo Facial SS3530';
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
$_SERVER['REQUEST_URI'] = '/presenca/api/culto/receber_leitura_facial.php';

// Simular php://input
$input_data = json_encode($dados_dispositivo);

// Capturar output
ob_start();

// Incluir a API
include __DIR__ . '/api/culto/receber_leitura_facial.php';

$output = ob_get_contents();
ob_end_clean();

echo "<h3>Resposta da API:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

echo "<h2>3. Verificar se foi registrado no banco:</h2>";

// Verificar se a presença foi registrada
try {
    require_once __DIR__ . '/api/conexao.php';
    
    $stmt = $conn->prepare("
        SELECT pc.*, u.nome, df.nome as dispositivo
        FROM presencas_culto pc
        JOIN usuarios u ON pc.id_usuario = u.id
        LEFT JOIN dispositivos_faciais df ON pc.dispositivo_ip = df.ip
        WHERE DATE(pc.data) = CURDATE()
        ORDER BY pc.horario_confirmacao DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "✅ Presenças encontradas no banco:<br>";
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['nome']} - {$row['status']} - {$row['horario_confirmacao']}<br>";
        }
    } else {
        echo "❌ Nenhuma presença encontrada no banco<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar banco: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Verificar logs:</h2>";
$logFile = __DIR__ . '/logs/leitura_facial_culto_' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    $logs = file($logFile, FILE_IGNORE_NEW_LINES);
    echo "<h3>Últimas 10 linhas:</h3>";
    $ultimas = array_slice($logs, -10);
    echo "<pre>" . htmlspecialchars(implode("\n", $ultimas)) . "</pre>";
} else {
    echo "❌ Arquivo de log não encontrado<br>";
}

echo "<hr>";
echo "<p><strong>Simulação concluída em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

