<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once(__DIR__ . '/../../utils/acesso_especial.php');
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

// Verificar acesso especial
if (!pode_acessar_especial()) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso negado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

// Obter dados do formulário
$usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
$data = isset($_POST['data']) ? $_POST['data'] : '';
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$dependente_id = isset($_POST['dependente_id']) ? (int)$_POST['dependente_id'] : 0;
$quantidade = isset($_POST['quantidade']) ? (int)$_POST['quantidade'] : 1;
$valor_especial = isset($_POST['valor_especial']) ? (float)$_POST['valor_especial'] : 0;
$observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : 'Reserva especial';

// Validações
if (!$usuario_id || !$data || !$tipo) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados obrigatórios não fornecidos']);
    exit;
}

// Validar observação obrigatória
if (empty($observacao)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O campo Observação é obrigatório para reservas especiais']);
    exit;
}

// Verificar se o usuário existe e está ativo
$stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE id = ? AND ativo = 1");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não encontrado ou inativo']);
    exit;
}

$usuario = $result->fetch_assoc();
$stmt->close();

// Verificar se já existe reserva para esta data e usuário (apenas para reservas próprias)
if ($tipo === 'propria') {
    $stmt = $conn->prepare("SELECT id FROM reservas_almoco WHERE id_usuario = ? AND data = ?");
    $stmt->bind_param("is", $usuario_id, $data);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário já possui reserva para esta data']);
        exit;
    }
    $stmt->close();
}

// Obter valores padrão do sistema
$stmt = $conn->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('valor_refeicao', 'valor_marmitex')");
if (!$stmt) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao preparar consulta de configurações: ' . $conn->error]);
    exit;
}
$stmt->execute();
$result = $stmt->get_result();
$config = [];
while ($row = $result->fetch_assoc()) {
    $config[$row['chave']] = $row['valor'];
}
$stmt->close();

// Calcular valor da reserva (sempre usar valores padrão para reservas especiais)
$valor_final = 0;
if ($valor_especial > 0) {
    $valor_final = $valor_especial;
} else {
    switch ($tipo) {
        case 'propria':
            $valor_final = (float)$config['valor_refeicao'];
            break;
        case 'adicional':
        case 'marmitex':
            $valor_final = (float)$config['valor_refeicao'] + (float)$config['valor_marmitex'];
            break;
    }
}

// Inserir reserva baseada no tipo
$reserva_id = 0;
$tabela_reserva = '';
$detalhes_log = '';

try {
    $conn->begin_transaction();

    switch ($tipo) {
        case 'propria':
            $stmt = $conn->prepare("INSERT INTO reservas_almoco (id_usuario, data, valor_refeicao, observacao_especial) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Erro ao preparar inserção de reserva própria: ' . $conn->error);
            }
            $stmt->bind_param("isds", $usuario_id, $data, $valor_final, $observacao);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao executar inserção: ' . $stmt->error);
            }
            
            $reserva_id = $conn->insert_id;
            $tabela_reserva = 'reservas_almoco';
            $detalhes_log = "Reserva própria criada para {$usuario['nome']} na data {$data}";
            break;

        case 'adicional':
        case 'marmitex':
            // Verificar se dependente foi selecionado
            if (!$dependente_id) {
                throw new Exception('Dependente é obrigatório para reservas adicionais');
            }

            // Verificar se o dependente existe e obter dados para cálculo de idade
            $stmt = $conn->prepare("SELECT id, nome, nascimento, cobrar FROM dependentes WHERE id = ? AND id_usuario = ?");
            $stmt->bind_param("ii", $dependente_id, $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Dependente não encontrado');
            }
            
            $dependente = $result->fetch_assoc();
            $stmt->close();
            
            // Calcular idade e definir cobrança
            $nascimento = new DateTime($dependente['nascimento']);
            $hoje = new DateTime();
            $idade = $hoje->diff($nascimento)->y;
            
            // Se idade <= 12 anos, não cobra (cobrar = 1 significa não cobrar)
            // Se idade > 12 anos, cobra (cobrar = 0 significa cobrar)
            $cobrar = $idade <= 12 ? 1 : 0;

            // Converter tipo para o formato esperado pela tabela
            $tipo_banco = ($tipo === 'adicional') ? 'presencial' : 'marmitex';

            $stmt = $conn->prepare("INSERT INTO reservas_adicionais (id_usuario, id_dependente, data, quantidade, tipo, valor_refeicao, valor_marmitex, observacao_especial) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Erro ao preparar inserção de reserva adicional: ' . $conn->error);
            }
            
            // Calcular valores baseado na idade do dependente
            if ($cobrar == 1) {
                // Dependente <= 12 anos: não cobra (valores zerados)
                $valor_refeicao = 0.00;
                $valor_marmitex = 0.00;
            } else {
                // Dependente > 12 anos: cobra valores normais
                $valor_refeicao = (float)$config['valor_refeicao'];
                $valor_marmitex = (float)$config['valor_marmitex'];
            }
            
            $stmt->bind_param("iisisdds", $usuario_id, $dependente_id, $data, $quantidade, $tipo_banco, $valor_refeicao, $valor_marmitex, $observacao);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao executar inserção: ' . $stmt->error);
            }
            
            $reserva_id = $conn->insert_id;
            $tabela_reserva = 'reservas_adicionais';
            $status_cobranca = $cobrar == 1 ? "GRATUITA (idade: {$idade} anos)" : "PAGA (idade: {$idade} anos)";
            $detalhes_log = "Reserva {$tipo} criada para {$usuario['nome']} - {$dependente['nome']} na data {$data} - {$status_cobranca}";
            break;

        default:
            throw new Exception('Tipo de reserva inválido');
    }

    // Registrar log da ação especial
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $stmt = $conn->prepare("INSERT INTO logs_acesso_especial (usuario_id, acao, detalhes, ip, data_hora) VALUES (?, ?, ?, ?, NOW())");
    $acao = 'reserva_especial';
    $stmt->bind_param("isss", $_SESSION['usuario_id'], $acao, $detalhes_log, $ip);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    
    // Enviar notificação se habilitada (respeitando configurações do usuário)
    require_once __DIR__ . '/../notificacao/enviar_notificacao_reserva.php';
    $horario_atual = date('H:i');
    
    try {
        switch ($tipo) {
            case 'propria':
                $dados_notificacao = [
                    'data' => date('d/m/Y', strtotime($data)),
                    'horario' => $horario_atual,
                    'valor' => $valor_final,
                    'fora_horario' => false // Reservas especiais não são consideradas fora do horário
                ];
                enviarNotificacaoReserva($usuario_id, 'propria', $dados_notificacao, $conn);
                break;
                
            case 'adicional':
            case 'marmitex':
                // Buscar nome do dependente para notificação
                $stmt_dep = $conn->prepare("SELECT nome FROM dependentes WHERE id = ?");
                $stmt_dep->bind_param("i", $dependente_id);
                $stmt_dep->execute();
                $result_dep = $stmt_dep->get_result();
                $dependente_nome = 'Dependente';
                if ($result_dep->num_rows > 0) {
                    $dep_row = $result_dep->fetch_assoc();
                    $dependente_nome = $dep_row['nome'];
                }
                $stmt_dep->close();
                
                $tipo_notificacao = ($tipo === 'adicional') ? 'presencial' : 'marmitex';
                
                // Usar o valor correto que foi calculado considerando a idade do dependente
                // $valor_refeicao e $valor_marmitex já foram calculados corretamente nas linhas 172-181
                $valor_para_notificacao = ($tipo_notificacao === 'marmitex') ? $valor_marmitex : $valor_refeicao;
                $valor_total = $valor_para_notificacao * $quantidade;
                
                $dados_notificacao = [
                    'data' => date('d/m/Y', strtotime($data)),
                    'horario' => $horario_atual,
                    'dependente_nome' => $dependente_nome,
                    'tipo' => $tipo_notificacao,
                    'quantidade' => $quantidade,
                    'valor_total' => $valor_total,
                    'fora_horario' => false // Reservas especiais não são consideradas fora do horário
                ];
                enviarNotificacaoReserva($usuario_id, 'adicional', $dados_notificacao, $conn);
                break;
        }
    } catch (Exception $e) {
        // Log do erro mas não interrompe o processo (notificação é opcional)
        error_log("Erro ao enviar notificação de reserva especial: " . $e->getMessage());
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Reserva especial criada com sucesso',
        'reserva_id' => $reserva_id,
        'tabela' => $tabela_reserva
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
?>
