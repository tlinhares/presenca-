<?php
// API para salvar configurações do culto via AJAX
header('Content-Type: application/json; charset=utf-8');

require_once '../conexao.php';

// ╔════════════════════════════════════════════════════════════════╗
// ║  Acesso: Mesma permissão de culto_configuracoes               ║
// ╚════════════════════════════════════════════════════════════════╝
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('culto_configuracoes');

require_once '../../auth/verifica_sessao.php';



// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método não permitido.'
    ]);
    exit;
}

try {
    // Validar dados recebidos - apenas campos reais da tabela configuracoes_culto
    $dias_semana = $_POST['dias_semana'] ?? '1,2,3,4,5';
    $gerar_faltas_automaticas = ($_POST['gerar_faltas_automaticas'] ?? '0') === '1' ? '1' : '0';
    $horario_fim = $_POST['horario_fim'] ?? '';
    $horario_inicio = $_POST['horario_inicio'] ?? '';
    $notificacao_ausencia = ($_POST['notificacao_ausencia'] ?? '0') === '1' ? '1' : '0';
    $notificar_presencas = ($_POST['notificar_presencas'] ?? '0') === '1' ? '1' : '0';
    $permitir_atraso = ($_POST['permitir_atraso'] ?? '0') === '1' ? '1' : '0';
    $tolerancia_atraso = intval($_POST['tolerancia_atraso'] ?? 0);
    
    // Validações básicas
    if (empty($horario_inicio) || empty($horario_fim)) {
        throw new Exception('Horários de início e fim são obrigatórios.');
    }
    
    if ($tolerancia_atraso < 0 || $tolerancia_atraso > 120) {
        throw new Exception('Tolerância deve estar entre 0 e 120 minutos.');
    }
    
    // Validar dias da semana
    if (!preg_match('/^[1-7,]+$/', $dias_semana)) {
        throw new Exception('Formato de dias da semana inválido. Use: 1,2,3,4,5 (1=segunda, 7=domingo)');
    }
    
    // Preparar configurações para salvar - apenas campos reais da tabela
    $configuracoes = [
        'dias_semana' => $dias_semana,
        'gerar_faltas_automaticas' => $gerar_faltas_automaticas,
        'horario_fim' => $horario_fim,
        'horario_inicio' => $horario_inicio,
        'notificacao_ausencia' => $notificacao_ausencia,
        'notificar_presencas' => $notificar_presencas,
        'permitir_atraso' => $permitir_atraso,
        'tolerancia_atraso' => $tolerancia_atraso
    ];
    
    // Salvar cada configuração
    foreach ($configuracoes as $chave => $valor) {
        $stmt = $conn->prepare("
            INSERT INTO configuracoes_culto (chave, valor) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE valor = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Erro ao preparar consulta para '$chave': " . $conn->error);
        }
        
        $stmt->bind_param("sss", $chave, $valor, $valor);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao salvar configuração '$chave': " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    // Log da operação
    error_log("Configurações do culto atualizadas por: " . ($_SESSION['usuario_nome'] ?? 'Desconhecido'));
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Configurações salvas com sucesso!'
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao salvar configurações do culto: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

$conn->close();
?>
