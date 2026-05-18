<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../auth/verifica_sessao.php';
require_once '../../api/conexao.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Validar formato da data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data inválida']);
    exit;
}

try {
    // Buscar nome do usuário
    $stmt_usuario = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
    if (!$stmt_usuario) {
        throw new Exception('Erro ao preparar consulta de usuário: ' . $conn->error);
    }
    $stmt_usuario->bind_param("s", $usuario_id);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();
    $usuario = $result_usuario->fetch_assoc();
    $nome_usuario = $usuario['nome'] ?? 'Usuário';
    $stmt_usuario->close();
    
    // Buscar reserva própria do dia
    $sql_propria = "
        SELECT id, data, horario_confirmacao
        FROM reservas_almoco
        WHERE id_usuario = ? 
        AND data = ?
    ";
    
    $stmt_propria = $conn->prepare($sql_propria);
    if (!$stmt_propria) {
        throw new Exception('Erro ao preparar consulta de reserva própria: ' . $conn->error);
    }
    $stmt_propria->bind_param("ss", $usuario_id, $data);
    $stmt_propria->execute();
    $result_propria = $stmt_propria->get_result();
    
    $reserva_propria = null;
    if ($row = $result_propria->fetch_assoc()) {
        $reserva_propria = [
            'id' => $row['id'],
            'nome' => $nome_usuario,
            'criado_em' => $row['horario_confirmacao'] ? date('d/m/Y H:i', strtotime($row['horario_confirmacao'])) : '-'
        ];
    }
    $stmt_propria->close();
    
    // Buscar reservas adicionais (dependentes) do dia
    $sql_adicionais = "
        SELECT ra.id, ra.data, ra.data_cadastro, ra.valor_refeicao,
               d.id as id_dependente, d.nome as nome_dependente
        FROM reservas_adicionais ra
        JOIN dependentes d ON ra.id_dependente = d.id
        WHERE ra.id_usuario = ?
        AND ra.data = ?
        ORDER BY d.nome
    ";
    
    $stmt_adicionais = $conn->prepare($sql_adicionais);
    if (!$stmt_adicionais) {
        throw new Exception('Erro ao preparar consulta de reservas adicionais: ' . $conn->error);
    }
    $stmt_adicionais->bind_param("ss", $usuario_id, $data);
    $stmt_adicionais->execute();
    $result_adicionais = $stmt_adicionais->get_result();
    
    $reservas_adicionais = [];
    while ($row = $result_adicionais->fetch_assoc()) {
        // O valor está diretamente em valor_refeicao da reserva adicional
        $valor = floatval($row['valor_refeicao']);
        
        $reservas_adicionais[] = [
            'id' => $row['id'],
            'id_dependente' => $row['id_dependente'],
            'nome' => $row['nome_dependente'],
            'valor' => $valor,
            'valor_formatado' => 'R$ ' . number_format($valor, 2, ',', '.'),
            'criado_em' => $row['data_cadastro'] ? date('d/m/Y H:i', strtotime($row['data_cadastro'])) : '-'
        ];
    }
    $stmt_adicionais->close();
    
    // Formatar data para exibição
    $data_formatada = date('d/m/Y', strtotime($data));
    $dia_semana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $dia_semana_nome = $dia_semana[date('w', strtotime($data))];
    
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'data_formatada' => $data_formatada,
        'dia_semana' => $dia_semana_nome,
        'reserva_propria' => $reserva_propria,
        'reservas_adicionais' => $reservas_adicionais,
        'total_adicionais' => count($reservas_adicionais),
        'tem_reservas' => ($reserva_propria !== null || count($reservas_adicionais) > 0)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar detalhes: ' . $e->getMessage()
    ]);
}
?>
