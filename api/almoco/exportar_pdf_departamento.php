<?php
header('Content-Type: application/pdf');
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    die('Usuário não autenticado');
}

$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
if (!$isAdmin) {
    die('Acesso negado');
}

$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$entidade_id = $_GET['entidade_id'] ?? '';

require_once __DIR__ . '/../../vendor/autoload.php';

try {
    $where = [];
    $params = [];
    $types = '';

    if (!empty($data_inicio)) {
        $where[] = "rd.data >= ?";
        $params[] = $data_inicio;
        $types .= 's';
    }
    if (!empty($data_fim)) {
        $where[] = "rd.data <= ?";
        $params[] = $data_fim;
        $types .= 's';
    }
    if (!empty($entidade_id)) {
        $where[] = "rd.entidade_id = ?";
        $params[] = intval($entidade_id);
        $types .= 'i';
    }

    $sql = "SELECT rd.id, rd.data, rd.quantidade, rd.evento_motivo, rd.valor_total, 
                   rd.data_cadastro, e.entidade_nome, u.nome as criado_por
            FROM reservas_departamento rd
            LEFT JOIN entidade e ON rd.entidade_id = e.entidade_id
            LEFT JOIN usuarios u ON rd.criado_por = u.id";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY rd.data DESC, rd.data_cadastro DESC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $reservas = [];
    $total_quantidade = 0;
    $total_valor = 0;

    while ($row = $result->fetch_assoc()) {
        $reservas[] = $row;
        $total_quantidade += intval($row['quantidade']);
        $total_valor += floatval($row['valor_total']);
    }

    if (isset($stmt)) {
        $stmt->close();
    }

    $periodo = '';
    if (!empty($data_inicio) && !empty($data_fim)) {
        $periodo = date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
    } elseif (!empty($data_inicio)) {
        $periodo = 'A partir de ' . date('d/m/Y', strtotime($data_inicio));
    } elseif (!empty($data_fim)) {
        $periodo = 'Até ' . date('d/m/Y', strtotime($data_fim));
    } else {
        $periodo = 'Todas as datas';
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);

    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .header { 
            text-align: center; 
            padding: 15px; 
            background: linear-gradient(135deg, #ffc107, #e0a800); 
            color: #212529; 
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .header h1 { margin: 0 0 5px 0; font-size: 20px; font-weight: bold; }
        .header p { margin: 0; font-size: 12px; }
        .resumo {
            margin-bottom: 20px;
        }
        .resumo table { width: 100%; }
        .resumo td {
            width: 33.33%;
            text-align: center;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
        }
        .resumo .numero { font-size: 24px; font-weight: bold; }
        .resumo .label { font-size: 10px; color: #6c757d; text-transform: uppercase; }
        table.dados { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.dados th {
            background: #343a40;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
        }
        table.dados td {
            padding: 8px;
            border-bottom: 1px solid #e9ecef;
            font-size: 11px;
        }
        table.dados tr:nth-child(even) { background: #f8f9fa; }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #dee2e6;
            color: #6c757d;
            font-size: 9px;
        }
    </style>
    
    <div class="header">
        <h1>RELATÓRIO DE RESERVAS POR DEPARTAMENTO</h1>
        <p>Período: ' . htmlspecialchars($periodo) . '</p>
    </div>
    
    <div class="resumo">
        <table>
            <tr>
                <td>
                    <div class="numero" style="color: #ffc107;">' . count($reservas) . '</div>
                    <div class="label">Total de Registros</div>
                </td>
                <td>
                    <div class="numero" style="color: #28a745;">' . number_format($total_quantidade, 0, ',', '.') . '</div>
                    <div class="label">Total de Refeições</div>
                </td>
                <td>
                    <div class="numero" style="color: #17a2b8;">R$ ' . number_format($total_valor, 2, ',', '.') . '</div>
                    <div class="label">Valor Total</div>
                </td>
            </tr>
        </table>
    </div>
    
    <table class="dados">
        <thead>
            <tr>
                <th>Data</th>
                <th>Departamento</th>
                <th>Evento/Motivo</th>
                <th style="text-align: center;">Quantidade</th>
                <th style="text-align: right;">Valor Total</th>
                <th>Criado por</th>
                <th>Data Cadastro</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($reservas)) {
        $html .= '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #999;">Nenhuma reserva encontrada para o período selecionado</td></tr>';
    } else {
        foreach ($reservas as $r) {
            $data_fmt = '';
            if ($r['data']) {
                $dt = DateTime::createFromFormat('Y-m-d', $r['data']);
                if ($dt) $data_fmt = $dt->format('d/m/Y');
            }

            $cadastro_fmt = '';
            if ($r['data_cadastro']) {
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $r['data_cadastro']);
                if ($dt) $cadastro_fmt = $dt->format('d/m/Y H:i');
            }

            $html .= '
            <tr>
                <td>' . $data_fmt . '</td>
                <td>' . htmlspecialchars($r['entidade_nome'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($r['evento_motivo'] ?? '-') . '</td>
                <td style="text-align: center;">' . intval($r['quantidade']) . '</td>
                <td style="text-align: right;">R$ ' . number_format(floatval($r['valor_total']), 2, ',', '.') . '</td>
                <td>' . htmlspecialchars($r['criado_por'] ?? 'N/A') . '</td>
                <td>' . $cadastro_fmt . '</td>
            </tr>';
        }
    }

    $html .= '
        </tbody>
    </table>
    
    <div class="footer">
        <p><strong>Sistema de Refeições - Reservas de Departamento</strong></p>
        <p>Documento gerado em: ' . date('d/m/Y H:i:s') . '</p>
    </div>';

    $mpdf->SetTitle('Relatório Reservas Departamento');
    $mpdf->SetAuthor('Sistema de Refeições');
    $mpdf->WriteHTML($html);

    $filename = 'reservas_departamento_' . date('Ymd_His') . '.pdf';
    $mpdf->Output($filename, 'I');

} catch (Exception $e) {
    die('Erro ao gerar PDF: ' . $e->getMessage());
}

$conn->close();
?>
