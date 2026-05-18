<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verifica permissão de admin
require_once __DIR__ . '/../../../core/services/MenuPermissaoService.php';
if (!MenuPermissaoService::isAdmin()) {
    die('Acesso não autorizado. Por favor, faça login novamente.');
}

include_once(__DIR__ . '/../../conexao.php');

header('Content-Type: text/csv; charset=UTF-8');
$nome_arquivo = 'relatorio_culto_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');

// Adiciona BOM UTF-8
echo "\xEF\xBB\xBF";

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'presencas';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$usuario_id = isset($_GET['usuario_id']) && !empty($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;

// Buscar dados
$dados = buscarDadosRelatorioCSV($tipo, $data_inicio, $data_fim, $usuario_id, $conn);

// Usar ponto e vírgula como delimitador (padrão brasileiro)
$delimitador = ';';
$output = fopen('php://output', 'w');

// Imprimir cabeçalhos
switch($tipo) {
    case 'presencas':
        fputcsv($output, ['Data', 'Usuário', 'Horário', 'Tipo', 'Status'], $delimitador);
        foreach ($dados as $row) {
            fputcsv($output, [
                date('d/m/Y', strtotime($row['data'])),
                $row['nome_usuario'],
                $row['horario_confirmacao'],
                $row['tipo_confirmacao'],
                $row['status']
            ], $delimitador);
        }
        break;
    case 'faltas':
        fputcsv($output, ['Data', 'Usuário', 'Justificada', 'Motivo'], $delimitador);
        foreach ($dados as $row) {
            fputcsv($output, [
                date('d/m/Y', strtotime($row['data'])),
                $row['nome_usuario'],
                $row['justificada'] ? 'Sim' : 'Não',
                $row['motivo'] ?? '-'
            ], $delimitador);
        }
        break;
    case 'justificativas':
        fputcsv($output, ['Data Falta', 'Usuário', 'Motivo', 'Status'], $delimitador);
        foreach ($dados as $row) {
            fputcsv($output, [
                date('d/m/Y', strtotime($row['data_falta'])),
                $row['nome_usuario'],
                $row['motivo'],
                $row['status']
            ], $delimitador);
        }
        break;
}

fclose($output);

function buscarDadosRelatorioCSV($tipo, $data_inicio, $data_fim, $usuario_id, $conn) {
    $dados = [];
    
    switch($tipo) {
        case 'presencas':
            $sql = "SELECT 
                        pc.data,
                        pc.horario_confirmacao,
                        pc.tipo_confirmacao,
                        pc.status,
                        u.nome as nome_usuario
                    FROM presencas_culto pc
                    INNER JOIN usuarios u ON pc.id_usuario = u.id
                    WHERE pc.data BETWEEN ? AND ?";
            
            if ($usuario_id) {
                $sql .= " AND pc.id_usuario = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $data_inicio, $data_fim, $usuario_id);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $data_inicio, $data_fim);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            break;
            
        case 'faltas':
            // Buscar faltas
            $sql_datas = "SELECT DISTINCT data FROM presencas_culto WHERE data BETWEEN ? AND ? ORDER BY data";
            $stmt_datas = $conn->prepare($sql_datas);
            $stmt_datas->bind_param("ss", $data_inicio, $data_fim);
            $stmt_datas->execute();
            $result_datas = $stmt_datas->get_result();
            $datas_culto = [];
            while ($row = $result_datas->fetch_assoc()) {
                $datas_culto[] = $row['data'];
            }
            
            $sql_usuarios = "SELECT id, nome FROM usuarios WHERE culto = 1 AND ativo = 1";
            if ($usuario_id) {
                $sql_usuarios .= " AND id = ?";
                $stmt_usuarios = $conn->prepare($sql_usuarios);
                $stmt_usuarios->bind_param("i", $usuario_id);
            } else {
                $stmt_usuarios = $conn->prepare($sql_usuarios);
            }
            $stmt_usuarios->execute();
            $result_usuarios = $stmt_usuarios->get_result();
            
            while ($usuario = $result_usuarios->fetch_assoc()) {
                foreach ($datas_culto as $data) {
                    $sql_presenca = "SELECT id FROM presencas_culto WHERE id_usuario = ? AND data = ?";
                    $stmt_presenca = $conn->prepare($sql_presenca);
                    $stmt_presenca->bind_param("is", $usuario['id'], $data);
                    $stmt_presenca->execute();
                    $result_presenca = $stmt_presenca->get_result();
                    
                    if ($result_presenca->num_rows == 0) {
                        $sql_just = "SELECT motivo, status FROM justificativas_culto WHERE id_usuario = ? AND data_falta = ?";
                        $stmt_just = $conn->prepare($sql_just);
                        $stmt_just->bind_param("is", $usuario['id'], $data);
                        $stmt_just->execute();
                        $result_just = $stmt_just->get_result();
                        $justificativa = $result_just->fetch_assoc();
                        
                        $dados[] = [
                            'data' => $data,
                            'nome_usuario' => $usuario['nome'],
                            'justificada' => $justificativa !== null,
                            'motivo' => $justificativa['motivo'] ?? null
                        ];
                    }
                }
            }
            break;
            
        case 'justificativas':
            $sql = "SELECT 
                        j.data_falta,
                        j.motivo,
                        j.status,
                        u.nome as nome_usuario
                    FROM justificativas_culto j
                    INNER JOIN usuarios u ON j.id_usuario = u.id
                    WHERE j.data_falta BETWEEN ? AND ?";
            
            if ($usuario_id) {
                $sql .= " AND j.id_usuario = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $data_inicio, $data_fim, $usuario_id);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $data_inicio, $data_fim);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $dados[] = $row;
            }
            break;
    }
    
    return $dados;
}
?>

