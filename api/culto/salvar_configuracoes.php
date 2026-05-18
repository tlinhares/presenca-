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
    // Validar dados recebidos
    $horario_inicio = $_POST['horario_inicio'] ?? '';
    $horario_fim = $_POST['horario_fim'] ?? '';
    $tolerancia_atraso = intval($_POST['tolerancia_atraso'] ?? 0);
    $gerar_faltas = isset($_POST['gerar_faltas_automaticas']) ? 1 : 0;
    $notificar_presencas = isset($_POST['notificar_presencas']) ? 1 : 0;
    
    // Validações básicas
    if (empty($horario_inicio) || empty($horario_fim)) {
        throw new Exception('Horários de início e fim são obrigatórios.');
    }
    
    if ($tolerancia_atraso < 0 || $tolerancia_atraso > 120) {
        throw new Exception('Tolerância deve estar entre 0 e 120 minutos.');
    }
    
    // Preparar configurações para salvar
    $configuracoes = [
        'horario_inicio' => $horario_inicio,
        'horario_fim' => $horario_fim,
        'tolerancia_atraso' => $tolerancia_atraso,
        'gerar_faltas_automaticas' => $gerar_faltas,
        'notificar_presencas' => $notificar_presencas
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
