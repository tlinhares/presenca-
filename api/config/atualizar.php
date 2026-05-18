<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../conexao.php';
include_once(__DIR__ . '/../../auth/verifica_sessao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

try {
    $configuracoes = [
        // Configurações Gerais
        'hora_limite' => $_POST['hora_limite'] ?? '',
        'permitir_reserva_atraso' => $_POST['permitir_reserva_atraso'] ?? '0',
        'limite_reservas_dia' => $_POST['limite_reservas_dia'] ?? '20',
        'fuso_horario' => $_POST['fuso_horario'] ?? 'America/Cuiaba',
        'mensagem_inicio' => $_POST['mensagem_inicio'] ?? '',
        
        // Valores
        'valor_refeicao' => $_POST['valor_refeicao'] ?? '0.00',
        'valor_marmitex' => $_POST['valor_marmitex'] ?? '0.00',
        'valor_fora_horario' => $_POST['valor_fora_horario'] ?? '0.00',
        'marmitex_habilitado' => $_POST['marmitex_habilitado'] ?? '0',
        
        // Departamentos
        'valor_departamento' => $_POST['valor_departamento'] ?? '0.00',
        'valor_departamento_fora_horario' => $_POST['valor_departamento_fora_horario'] ?? '0.00',
        'horario_departamento' => $_POST['horario_departamento'] ?? '',
        'permitir_reserva_departamento_atraso' => $_POST['permitir_reserva_departamento_atraso'] ?? '0',
        
        // Notificações
        'email_notificacoes' => $_POST['email_notificacoes'] ?? '',
        'notificacao_diaria_habilitada' => $_POST['notificacao_diaria_habilitada'] ?? '0',
        'horario_notificacao_diaria' => $_POST['horario_notificacao_diaria'] ?? '',
        'assunto_email_notificacao' => $_POST['assunto_email_notificacao'] ?? '',
        'template_email_notificacao' => $_POST['template_email_notificacao'] ?? '',
        
        // Email
        'smtp_email' => $_POST['smtp_email'] ?? '',
        'imap_email' => $_POST['imap_email'] ?? '',
        'port_email' => $_POST['port_email'] ?? '587',
        'senha_email' => $_POST['senha_email'] ?? '',
        'nome_remetente_email' => $_POST['nome_remetente_email'] ?? 'Sistema de Presença AOM',
        
        // Acesso Especial
        'emails_acesso_especial' => $_POST['emails_acesso_especial'] ?? ''
    ];
    
    $conn->begin_transaction();
    
    foreach ($configuracoes as $chave => $valor) {
        $stmt = $conn->prepare("
            INSERT INTO configuracoes (chave, valor) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");
        $stmt->bind_param("ss", $chave, $valor);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Configurações atualizadas com sucesso'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar configurações: ' . $e->getMessage()
    ]);
}
?>