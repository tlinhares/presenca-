<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Verifica permissão de admin
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAdmin();


include_once(__DIR__ . '/../conexao.php');

// Verificar se a conexão foi estabelecida
if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Verificar se a sessão está ativa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é admin


// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

try {
    // Obter dados do formulário
    $nome = trim($_POST['nome_automacao'] ?? '');
    $tipo_relatorio = $_POST['tipo_relatorio'] ?? '';
    $numero_whatsapp = trim($_POST['numero_whatsapp'] ?? '');
    $horario_envio = $_POST['horario_envio'] ?? '';
    $dias_semana = $_POST['dias_semana'] ?? [];
    $mensagem_personalizada = trim($_POST['mensagem_personalizada'] ?? '');
    
    // Validações
    if (empty($nome) || empty($tipo_relatorio) || empty($numero_whatsapp) || empty($horario_envio)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Campos obrigatórios não preenchidos']);
        exit;
    }
    
    if (empty($dias_semana)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Selecione pelo menos um dia da semana']);
        exit;
    }
    
    // Validar número do WhatsApp (formato básico)
    if (!preg_match('/^\d{10,15}$/', $numero_whatsapp)) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Número do WhatsApp inválido. Use apenas números (ex: 5565999793296)']);
        exit;
    }
    
    // Converter dias da semana para JSON
    $dias_json = json_encode($dias_semana);
    
    // Verificar se é edição (ID presente)
    $id = intval($_POST['id'] ?? 0);
    
    if ($id > 0) {
        // Atualizar automação existente
        $stmt = $conn->prepare("
            UPDATE automacoes_relatorios 
            SET nome = ?, tipo_relatorio = ?, numero_whatsapp = ?, horario_envio = ?, 
                dias_semana = ?, mensagem_personalizada = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssssssi", $nome, $tipo_relatorio, $numero_whatsapp, $horario_envio, $dias_json, $mensagem_personalizada, $id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => 'Automação atualizada com sucesso!'
            ]);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar automação: ' . $conn->error]);
        }
    } else {
        // Inserir nova automação
        $stmt = $conn->prepare("
            INSERT INTO automacoes_relatorios 
            (nome, tipo_relatorio, numero_whatsapp, horario_envio, dias_semana, mensagem_personalizada) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ssssss", $nome, $tipo_relatorio, $numero_whatsapp, $horario_envio, $dias_json, $mensagem_personalizada);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => 'Automação criada com sucesso!'
            ]);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar automação: ' . $conn->error]);
        }
    }
    
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
?>
