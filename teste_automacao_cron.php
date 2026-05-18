<?php
/**
 * Script de Teste para Automação de Relatórios
 * Acesse: https://presenca.aom.org.br/teste_automacao_cron.php
 */

include_once(__DIR__ . '/api/conexao.php');

echo "<h2>Teste de Automação de Relatórios</h2>";
echo "<p><strong>Data/Hora atual:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Buscar automações ativas
$sql = "SELECT * FROM automacoes_relatorios WHERE ativo = 1 ORDER BY id";
$result = $conn->query($sql);

if (!$result) {
    echo "<p style='color: red;'><strong>Erro:</strong> " . $conn->error . "</p>";
    exit;
}

$automacoes = $result->fetch_all(MYSQLI_ASSOC);

if (empty($automacoes)) {
    echo "<p style='color: orange;'><strong>Nenhuma automação ativa encontrada.</strong></p>";
    exit;
}

echo "<h3>Automações Ativas (" . count($automacoes) . ")</h3>";

$hoje = date('Y-m-d');
$agora = date('H:i');

foreach ($automacoes as $automacao) {
    echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>ID {$automacao['id']}: {$automacao['nome']}</h4>";
    
    // Verificar dias da semana
    $dias_semana = json_decode($automacao['dias_semana'], true);
    $dia_atual = date('N', strtotime($hoje));
    $deve_executar_hoje = in_array($dia_atual, $dias_semana);
    
    echo "<p><strong>Dias configurados:</strong> " . implode(', ', $dias_semana) . " (hoje é dia $dia_atual)</p>";
    echo "<p><strong>Deve executar hoje:</strong> " . ($deve_executar_hoje ? '✅ SIM' : '❌ NÃO') . "</p>";
    
    // Verificar se já foi enviada hoje
    $ja_enviada = false;
    if (!empty($automacao['ultimo_envio'])) {
        $ultimo_envio = date('Y-m-d', strtotime($automacao['ultimo_envio']));
        $ja_enviada = ($ultimo_envio === $hoje);
    }
    
    echo "<p><strong>Último envio:</strong> " . ($automacao['ultimo_envio'] ?: 'Nunca') . "</p>";
    echo "<p><strong>Já enviada hoje:</strong> " . ($ja_enviada ? '✅ SIM' : '❌ NÃO') . "</p>";
    
    // Verificar horário
    $horario_configurado = $automacao['horario_envio'];
    $tolerancia = 5; // minutos
    
    $agora_minutos = (int)date('H') * 60 + (int)date('i');
    $configurado_minutos = (int)substr($horario_configurado, 0, 2) * 60 + (int)substr($horario_configurado, 3, 2);
    $diferenca = abs($agora_minutos - $configurado_minutos);
    
    $esta_no_horario = $diferenca <= $tolerancia;
    
    echo "<p><strong>Horário configurado:</strong> $horario_configurado</p>";
    echo "<p><strong>Horário atual:</strong> $agora</p>";
    echo "<p><strong>Diferença:</strong> $diferenca minutos (tolerância: $tolerancia min)</p>";
    echo "<p><strong>Está no horário:</strong> " . ($esta_no_horario ? '✅ SIM' : '❌ NÃO') . "</p>";
    
    // Resultado final
    $deve_executar = $deve_executar_hoje && !$ja_enviada && $esta_no_horario;
    
    echo "<p><strong>RESULTADO:</strong> ";
    if ($deve_executar) {
        echo "<span style='color: green; font-weight: bold;'>🚀 DEVE EXECUTAR AGORA!</span>";
    } else {
        echo "<span style='color: red; font-weight: bold;'>⏸️ NÃO DEVE EXECUTAR</span>";
    }
    echo "</p>";
    
    echo "</div>";
}

echo "<hr>";
echo "<h3>Configuração do Cron</h3>";
echo "<p>Para executar de hora em hora, adicione esta linha ao crontab:</p>";
echo "<code>0 * * * * /usr/bin/php /var/www/html/presenca/executar_automacoes_cron.php</code>";
echo "<p><strong>Nota:</strong> O script tem tolerância de ±5 minutos para o horário configurado.</p>";

echo "<hr>";
echo "<h3>Logs</h3>";
echo "<p>Os logs são salvos em: <code>/var/www/html/presenca/logs/automacao_cron_" . date('Y-m-d') . ".log</code></p>";
echo "<p><a href='painel/automacao_relatorios.php'>← Voltar para Automações</a></p>";
?>
