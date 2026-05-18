<?php
// Script de teste para verificar a API de listagem de reservas adicionais
session_start();

// Simular sessão de usuário (substitua pelo ID real do Francisco)
$_SESSION['usuario_id'] = 1; // Substitua pelo ID real do Francisco

echo "<h2>Teste da API de Listagem de Reservas Adicionais</h2>";

// Simular parâmetros GET
$_GET['data_inicio'] = '2025-10-01';
$_GET['data_fim'] = '2025-10-31';

echo "<p><strong>Parâmetros:</strong></p>";
echo "<ul>";
echo "<li>ID Usuário: " . $_SESSION['usuario_id'] . "</li>";
echo "<li>Data início: " . $_GET['data_inicio'] . "</li>";
echo "<li>Data fim: " . $_GET['data_fim'] . "</li>";
echo "</ul>";

echo "<h3>Executando API...</h3>";

// Capturar output da API
ob_start();
include 'api/almoco/listar_reservas_adicionais_usuario.php';
$output = ob_get_clean();

echo "<h3>Resposta da API:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Tentar decodificar JSON
$json = json_decode($output, true);
if ($json) {
    echo "<h3>Dados decodificados:</h3>";
    echo "<pre>" . print_r($json, true) . "</pre>";
    
    if (isset($json['reservas']) && count($json['reservas']) > 0) {
        echo "<h3>Reservas encontradas:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Data</th><th>Dependente</th><th>Tipo</th><th>Valor</th><th>Status</th></tr>";
        
        foreach ($json['reservas'] as $reserva) {
            echo "<tr>";
            echo "<td>" . $reserva['id'] . "</td>";
            echo "<td>" . $reserva['data'] . "</td>";
            echo "<td>" . $reserva['dependente_nome'] . "</td>";
            echo "<td>" . $reserva['tipo'] . "</td>";
            echo "<td>R$ " . number_format($reserva['valor_total'], 2, ',', '.') . "</td>";
            echo "<td>" . $reserva['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><strong>Nenhuma reserva encontrada!</strong></p>";
    }
} else {
    echo "<p><strong>Erro ao decodificar JSON!</strong></p>";
}
?>

