<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
    exit;
}

$tipo = $_GET['tipo'] ?? 'diario';
$data = $_GET['data'] ?? date('Y-m-d');

try {
    $dados = [];
    
    if ($tipo === 'diario' || $tipo === 'diario_completo') {
        // Buscar reservas próprias
        $sql_proprias = "SELECT u.nome, 1 as quantidade, 'Própria' as origem, ra.data, ra.valor_refeicao as valor
                        FROM reservas_almoco ra
                        JOIN usuarios u ON ra.id_usuario = u.id
                        WHERE ra.data = ?
                        ORDER BY u.nome ASC";
        
        $stmt = $conn->prepare($sql_proprias);
        $stmt->bind_param("s", $data);
        $stmt->execute();
        $result_proprias = $stmt->get_result();
        
        while ($row = $result_proprias->fetch_assoc()) {
            $dados[] = $row;
        }
        $stmt->close();
        
        // Buscar reservas adicionais
        $sql_adicionais = "SELECT u.nome, ra.quantidade, 'Adicional' as origem, ra.data, 
                          (ra.valor_refeicao + ra.valor_marmitex) as valor
                          FROM reservas_adicionais ra
                          JOIN usuarios u ON ra.id_usuario = u.id
                          WHERE ra.data = ?
                          ORDER BY u.nome ASC";
        
        $stmt = $conn->prepare($sql_adicionais);
        $stmt->bind_param("s", $data);
        $stmt->execute();
        $result_adicionais = $stmt->get_result();
        
        while ($row = $result_adicionais->fetch_assoc()) {
            $dados[] = $row;
        }
        $stmt->close();
        
        // Buscar reservas de departamento
        $sql_departamentos = "SELECT rd.evento_motivo as nome, rd.quantidade, 'Departamento' as origem, rd.data, 
                             (rd.quantidade * rd.valor_unitario) as valor
                             FROM reservas_departamento rd
                             WHERE rd.data = ?
                             ORDER BY rd.evento_motivo ASC";
        
        $stmt = $conn->prepare($sql_departamentos);
        $stmt->bind_param("s", $data);
        $stmt->execute();
        $result_departamentos = $stmt->get_result();
        
        while ($row = $result_departamentos->fetch_assoc()) {
            $dados[] = $row;
        }
        $stmt->close();
        
        // Calcular totais
        $total_proprias = 0;
        $total_adicionais = 0;
        $total_departamentos = 0;
        
        foreach ($dados as $item) {
            if ($item['origem'] === 'Própria') {
                $total_proprias += $item['quantidade'];
            } elseif ($item['origem'] === 'Adicional') {
                $total_adicionais += $item['quantidade'];
            } elseif ($item['origem'] === 'Departamento') {
                $total_departamentos += $item['quantidade'];
            }
        }
        
        $total_geral = $total_proprias + $total_adicionais + $total_departamentos;
        
        echo json_encode([
            'status' => 'sucesso',
            'dados' => $dados,
            'totais' => [
                'proprias' => $total_proprias,
                'adicionais' => $total_adicionais,
                'departamentos' => $total_departamentos,
                'geral' => $total_geral
            ],
            'data' => $data,
            'tipo' => $tipo
        ]);
        
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo de relatório não suportado']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao gerar relatório: ' . $e->getMessage()
    ]);
}
?>
