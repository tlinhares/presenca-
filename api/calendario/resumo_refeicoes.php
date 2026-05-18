<?php
/**
 * API de Resumo de Refeições do Mês
 * Retorna total de reservas confirmadas e valor estimado
 */
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../auth/verifica_sessao.php';
require_once '../../api/conexao.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
    exit;
}

try {
    if (!$conn) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    // Buscar reservas próprias do mês
    $sql_reservas = "
        SELECT COUNT(*) as total_reservas,
               COALESCE(SUM(valor_refeicao), 0) as valor_total
        FROM reservas_almoco 
        WHERE id_usuario = ?
        AND YEAR(data) = ? AND MONTH(data) = ?
    ";
    
    $stmt_reservas = $conn->prepare($sql_reservas);
    $stmt_reservas->bind_param("iii", $usuario_id, $ano, $mes);
    $stmt_reservas->execute();
    $result = $stmt_reservas->get_result();
    $dados_reservas = $result->fetch_assoc();
    
    $total_reservas_proprias = intval($dados_reservas['total_reservas']);
    $valor_reservas_proprias = floatval($dados_reservas['valor_total']);
    
    // Buscar reservas de dependentes do mês
    $sql_dependentes = "
        SELECT COUNT(*) as total_reservas,
               COALESCE(SUM(valor_refeicao), 0) as valor_refeicao,
               COALESCE(SUM(valor_marmitex), 0) as valor_marmitex
        FROM reservas_adicionais ra
        JOIN dependentes d ON ra.id_dependente = d.id
        WHERE d.id_usuario = ?
        AND YEAR(ra.data) = ? AND MONTH(ra.data) = ?
    ";
    
    $stmt_dependentes = $conn->prepare($sql_dependentes);
    $stmt_dependentes->bind_param("iii", $usuario_id, $ano, $mes);
    $stmt_dependentes->execute();
    $result_dep = $stmt_dependentes->get_result();
    $dados_dependentes = $result_dep->fetch_assoc();
    
    $total_reservas_dependentes = intval($dados_dependentes['total_reservas']);
    $valor_dependentes = floatval($dados_dependentes['valor_refeicao']) + floatval($dados_dependentes['valor_marmitex']);
    
    // Total geral
    $total_confirmadas = $total_reservas_proprias + $total_reservas_dependentes;
    $valor_total = $valor_reservas_proprias + $valor_dependentes;
    
    // Buscar configurações: hora_limite e permitir_reserva_atraso
    $horario_limite = '09:00'; // Padrão
    $permitir_reserva_atraso = 0; // Padrão: não permite
    
    $sql_config = "SELECT chave, valor FROM configuracoes WHERE chave IN ('hora_limite', 'permitir_reserva_atraso')";
    $stmt_config = $conn->prepare($sql_config);
    $stmt_config->execute();
    $result_config = $stmt_config->get_result();
    
    while ($config = $result_config->fetch_assoc()) {
        if ($config['chave'] === 'hora_limite') {
            $horario_limite = $config['valor'];
        }
        if ($config['chave'] === 'permitir_reserva_atraso') {
            $permitir_reserva_atraso = intval($config['valor']);
        }
    }
    
    // Verificar se passou do horário limite usando DateTime
    $agora = new DateTime();
    $limite_hoje = new DateTime();
    $partes_horario = explode(':', $horario_limite);
    $limite_hoje->setTime(intval($partes_horario[0]), intval($partes_horario[1] ?? 0));
    
    $fora_do_horario = ($agora > $limite_hoje);
    
    // Lógica:
    // - Se permitir_reserva_atraso = 1 → sempre permite (horario_limite_passado = false)
    // - Se permitir_reserva_atraso = 0 → verifica horário
    $horario_limite_passado = false;
    if ($permitir_reserva_atraso == 0 && $fora_do_horario) {
        $horario_limite_passado = true;
    }
    
    // Nomes dos meses
    $nomes_meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    
    echo json_encode([
        'status' => 'ok',
        'resumo' => [
            'total_confirmadas' => $total_confirmadas,
            'reservas_proprias' => $total_reservas_proprias,
            'reservas_dependentes' => $total_reservas_dependentes,
            'valor_total' => $valor_total,
            'valor_formatado' => 'R$ ' . number_format($valor_total, 2, ',', '.'),
            'mes_nome' => $nomes_meses[$mes] ?? '',
            'ano' => $ano,
            'horario_limite_passado' => $horario_limite_passado,
            'permitir_reserva_atraso' => $permitir_reserva_atraso,
            'fora_do_horario' => $fora_do_horario,
            'horario_limite' => $horario_limite,
            'horario_atual' => $agora->format('H:i')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar resumo: ' . $e->getMessage()
    ]);
}
?>

