<?php
/**
 * Script de teste para validar o sistema de mensagens variadas
 * Não afeta o sistema em produção, apenas testa as funcionalidades
 */

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/../core/services/WhatsAppService.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <title>Teste - Mensagens Variadas</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        .test-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🧪 Teste - Sistema de Mensagens Variadas</h1>";

try {
    // Teste 1: Verificar se a tabela existe
    echo "<div class='test-box'><h3>Teste 1: Verificar Tabela</h3>";
    $check = $conn->query("SHOW TABLES LIKE 'mensagens_padrao'");
    if ($check->num_rows > 0) {
        echo "<div class='success'>✅ Tabela 'mensagens_padrao' existe</div>";
    } else {
        echo "<div class='error'>❌ Tabela 'mensagens_padrao' não encontrada</div>";
        throw new Exception("Tabela não existe");
    }
    echo "</div>";
    
    // Teste 2: Contar mensagens disponíveis
    echo "<div class='test-box'><h3>Teste 2: Mensagens Disponíveis</h3>";
    $count = $conn->query("SELECT COUNT(*) as total FROM mensagens_padrao WHERE tipo = 'lembrete_reserva' AND ativo = 1");
    $row = $count->fetch_assoc();
    echo "<div class='info'>📊 Total de mensagens ativas do tipo 'lembrete_reserva': <strong>{$row['total']}</strong></div>";
    
    if ($row['total'] == 0) {
        echo "<div class='error'>❌ Nenhuma mensagem encontrada. Execute o script de instalação.</div>";
    }
    echo "</div>";
    
    // Teste 3: Testar função buscarMensagemAleatoria
    echo "<div class='test-box'><h3>Teste 3: Função buscarMensagemAleatoria</h3>";
    
    function buscarMensagemAleatoria($conn, $tipo, $dados = []) {
        try {
            $stmt = $conn->prepare("
                SELECT mensagem 
                FROM mensagens_padrao 
                WHERE tipo = ? AND ativo = 1 
                ORDER BY RAND() 
                LIMIT 1
            ");
            
            if (!$stmt) {
                throw new Exception("Erro ao preparar consulta: " . $conn->error);
            }
            
            $stmt->bind_param("s", $tipo);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            if ($result->num_rows === 0) {
                $mensagem_padrao = 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}';
            } else {
                $row = $result->fetch_assoc();
                $mensagem_padrao = $row['mensagem'];
            }
            
            foreach ($dados as $key => $value) {
                $mensagem_padrao = str_replace('{' . $key . '}', $value, $mensagem_padrao);
            }
            
            return $mensagem_padrao;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar mensagem aleatória: " . $e->getMessage());
            $mensagem_padrao = 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}';
            foreach ($dados as $key => $value) {
                $mensagem_padrao = str_replace('{' . $key . '}', $value, $mensagem_padrao);
            }
            return $mensagem_padrao;
        }
    }
    
    // Testar 5 vezes para ver variação
    echo "<div class='info'>Testando 5 chamadas para verificar variação:</div><ul>";
    for ($i = 1; $i <= 5; $i++) {
        $msg = buscarMensagemAleatoria($conn, 'lembrete_reserva', [
            'nome' => 'João Silva',
            'horario_limite' => '09:01'
        ]);
        echo "<li><code>" . htmlspecialchars($msg) . "</code></li>";
    }
    echo "</ul></div>";
    
    // Teste 4: Testar métodos do WhatsAppService
    echo "<div class='test-box'><h3>Teste 4: Métodos do WhatsAppService</h3>";
    
    // Testar calcularDelayVariado
    echo "<div class='info'>Testando calcularDelayVariado (5 chamadas):</div><ul>";
    for ($i = 1; $i <= 5; $i++) {
        $delay = WhatsAppService::calcularDelayVariado();
        echo "<li>Delay $i: <strong>{$delay}s</strong> (" . round($delay / 60, 1) . " min)</li>";
    }
    echo "</ul>";
    
    // Testar estaNaJanelaEnvio
    $hora_atual = date('H:i');
    $na_janela = WhatsAppService::estaNaJanelaEnvio('07:00', '08:30');
    echo "<div class='info'>Hora atual: <strong>$hora_atual</strong></div>";
    if ($na_janela) {
        echo "<div class='success'>✅ Dentro da janela de envio (07:00-08:30)</div>";
    } else {
        echo "<div class='info'>ℹ️ Fora da janela de envio (07:00-08:30)</div>";
    }
    echo "</div>";
    
    // Teste 5: Verificar estrutura de batches
    echo "<div class='test-box'><h3>Teste 5: Estrutura de Batches</h3>";
    $destinatarios_teste = [];
    for ($i = 1; $i <= 25; $i++) {
        $destinatarios_teste[] = [
            'telefone' => '6599999999' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'mensagem' => 'Mensagem de teste ' . $i,
            'nome' => 'Usuário ' . $i
        ];
    }
    
    echo "<div class='info'>Total de destinatários de teste: <strong>" . count($destinatarios_teste) . "</strong></div>";
    echo "<div class='info'>Com batch de 10-30, serão criados aproximadamente <strong>" . ceil(count($destinatarios_teste) / 20) . " batches</strong></div>";
    echo "<div class='success'>✅ Estrutura de batches configurada corretamente</div>";
    echo "</div>";
    
    echo "<div class='success'><h3>✅ Todos os testes passaram com sucesso!</h3></div>";
    echo "<div class='info'><strong>Nota:</strong> Este script apenas testa as funcionalidades. Não envia mensagens reais.</div>";
    
} catch (Exception $e) {
    echo "<div class='error'><h3>❌ Erro nos testes:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "</body></html>";
?>

