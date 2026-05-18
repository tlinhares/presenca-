<?php
/**
 * API para Gerar Comprovante de Utilização em PDF
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

// Verificar permissão básica
if (!MenuPermissaoService::podeAcessar('frota_dashboard')) {
    die('Acesso não autorizado');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../conexao.php';

$utilizacaoId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$usuarioId = $_SESSION['usuario_id'] ?? 0;
$isAdmin = MenuPermissaoService::podeAcessar('frota_admin_veiculos');

if (!$utilizacaoId) {
    die('ID não informado');
}

// Buscar dados
$sql = "SELECT fu.*, 
               v.placa, v.modelo, v.marca, v.cor, v.ano,
               u.nome as usuario_nome, u.email as usuario_email,
               e.entidade_nome,
               fd.nome as departamento_nome,
               DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_fmt,
               DATE_FORMAT(fu.data_entrada, '%d/%m/%Y %H:%i') as data_entrada_fmt,
               DATE_FORMAT(fu.criado_em, '%d/%m/%Y %H:%i') as criado_em_fmt
        FROM frota_utilizacoes fu
        JOIN frota_veiculos v ON fu.id_veiculo = v.id
        JOIN usuarios u ON fu.id_usuario = u.id
        LEFT JOIN entidade e ON fu.id_entidade = e.entidade_id
        LEFT JOIN frota_departamentos fd ON fu.id_departamento = fd.id
        WHERE fu.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $utilizacaoId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Utilização não encontrada');
}

$u = $result->fetch_assoc();

// Verificar permissão
if ($u['id_usuario'] != $usuarioId && !$isAdmin) {
    die('Acesso não autorizado');
}

// Calcular tempo
$tempo_formatado = '-';
if ($u['tempo_utilizacao']) {
    $min = intval($u['tempo_utilizacao']);
    $h = floor($min / 60);
    $m = $min % 60;
    $tempo_formatado = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

// Buscar valor do KM
$valor_km = 0;
$sql_config = "SELECT valor FROM frota_configuracoes WHERE chave = 'valor_km' LIMIT 1";
$result_config = $conn->query($sql_config);
if ($result_config && $row_config = $result_config->fetch_assoc()) {
    $valor_km = floatval($row_config['valor']);
}
$valor_locacao = $u['km_percorrido'] ? intval($u['km_percorrido']) * $valor_km : 0;

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);
    
    $statusText = $u['status'] === 'finalizado' ? 'FINALIZADO' : 
                 ($u['status'] === 'em_andamento' ? 'EM ANDAMENTO' : 'CANCELADO');
    $statusColor = $u['status'] === 'finalizado' ? '#28a745' : 
                  ($u['status'] === 'em_andamento' ? '#ffc107' : '#dc3545');
    
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .header { 
            text-align: center; 
            padding: 20px; 
            background: linear-gradient(135deg, #17a2b8, #138496); 
            color: white; 
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .header h1 { margin: 0 0 5px 0; font-size: 22px; }
        .header p { margin: 0; opacity: 0.9; }
        .placa {
            display: inline-block;
            background: #ffc107;
            color: #212529;
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: bold;
            font-family: monospace;
            font-size: 20px;
            margin: 10px 0;
        }
        .section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .section-header {
            background: #343a40;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
        }
        .section-header.saida { background: #28a745; }
        .section-header.entrada { background: #ffc107; color: #212529; }
        .section-body { padding: 15px; }
        table { width: 100%; border-collapse: collapse; }
        table td { padding: 8px 0; border-bottom: 1px solid #e9ecef; }
        table tr:last-child td { border-bottom: none; }
        .label { color: #6c757d; width: 40%; }
        .value { font-weight: bold; }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            color: white;
            font-weight: bold;
            background: ' . $statusColor . ';
        }
        .resumo-box {
            background: white;
            border: 2px solid #17a2b8;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin: 10px 0;
        }
        .resumo-box .numero {
            font-size: 28px;
            font-weight: bold;
            color: #17a2b8;
        }
        .resumo-box .label { color: #6c757d; font-size: 10px; }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
            color: #6c757d;
            font-size: 9px;
        }
        .assinatura {
            margin-top: 40px;
            text-align: center;
        }
        .linha-assinatura {
            border-top: 1px solid #333;
            width: 250px;
            margin: 0 auto;
            padding-top: 5px;
        }
    </style>
    
    <div class="header">
        <h1>COMPROVANTE DE UTILIZAÇÃO DE VEÍCULO</h1>
        <p>Registro #' . $utilizacaoId . '</p>
    </div>
    
    <table style="margin-bottom: 20px;">
        <tr>
            <td style="width: 60%; vertical-align: top; border: none;">
                <div class="placa">' . $u['placa'] . '</div>
                <h3 style="margin: 5px 0;">' . htmlspecialchars($u['modelo']) . '</h3>
                <p style="color: #6c757d; margin: 0 0 5px 0;">
                    ' . htmlspecialchars($u['marca']) . 
                    ($u['ano'] ? ' • ' . $u['ano'] : '') . 
                    ($u['cor'] ? ' • ' . $u['cor'] : '') . '
                </p>
                <span style="background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: bold;">
                    Valor KM: R$ ' . number_format($valor_km, 2, ',', '.') . '
                </span>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top; border: none;">
                <span class="status-badge">' . $statusText . '</span>
            </td>
        </tr>
    </table>
    
    <table style="margin-bottom: 20px;">
        <tr>
            <td style="width: 24%; text-align: center; border: 2px solid #28a745; border-radius: 8px; padding: 10px;">
                <div style="font-size: 22px; font-weight: bold; color: #28a745;">' . 
                    ($u['km_percorrido'] ? number_format($u['km_percorrido'], 0, ',', '.') : '0') . '</div>
                <div style="font-size: 10px; color: #6c757d;">KM PERCORRIDO</div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 24%; text-align: center; border: 2px solid #17a2b8; border-radius: 8px; padding: 10px;">
                <div style="font-size: 22px; font-weight: bold; color: #17a2b8;">' . $tempo_formatado . '</div>
                <div style="font-size: 10px; color: #6c757d;">TEMPO DE USO</div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 24%; text-align: center; border: 2px solid #28a745; border-radius: 8px; padding: 10px; background: #f0fff4;">
                <div style="font-size: 20px; font-weight: bold; color: #28a745;">R$ ' . number_format($valor_locacao, 2, ',', '.') . '</div>
                <div style="font-size: 10px; color: #6c757d;">VALOR LOCAÇÃO</div>
                <div style="font-size: 8px; color: #999; margin-top: 2px;">' . ($u['km_percorrido'] ? number_format($u['km_percorrido'], 0, ',', '.') : '0') . ' km × R$ ' . number_format($valor_km, 2, ',', '.') . '</div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 24%; text-align: center; border: 2px solid #6c757d; border-radius: 8px; padding: 10px;">
                <div style="font-size: 14px; font-weight: bold; color: #333;">' . htmlspecialchars($u['usuario_nome']) . '</div>
                <div style="font-size: 10px; color: #6c757d;">USUÁRIO</div>
            </td>
        </tr>
    </table>
    
    <div class="section">
        <div class="section-header saida">SAÍDA DO VEÍCULO</div>
        <div class="section-body">
            <table>
                <tr>
                    <td class="label">Data/Hora</td>
                    <td class="value">' . $u['data_saida_fmt'] . '</td>
                </tr>
                <tr>
                    <td class="label">Quilometragem</td>
                    <td class="value">' . number_format($u['km_saida'], 0, ',', '.') . ' km</td>
                </tr>
                <tr>
                    <td class="label">Entidade</td>
                    <td class="value">' . htmlspecialchars($u['entidade_nome'] ?? '-') . '</td>
                </tr>
                <tr>
                    <td class="label">Departamento</td>
                    <td class="value">' . htmlspecialchars($u['departamento_nome'] ?? '-') . '</td>
                </tr>
                <tr>
                    <td class="label">Destino</td>
                    <td class="value">' . htmlspecialchars($u['destino'] ?? '-') . '</td>
                </tr>
                <tr>
                    <td class="label">Motivo</td>
                    <td class="value">' . htmlspecialchars($u['motivo'] ?? '-') . '</td>
                </tr>
                ' . ($u['observacoes_saida'] ? '
                <tr>
                    <td class="label">Observações</td>
                    <td class="value">' . nl2br(htmlspecialchars($u['observacoes_saida'])) . '</td>
                </tr>' : '') . '
            </table>
        </div>
    </div>';
    
    if ($u['status'] === 'finalizado') {
        $html .= '
    <div class="section">
        <div class="section-header entrada">DEVOLUÇÃO DO VEÍCULO</div>
        <div class="section-body">
            <table>
                <tr>
                    <td class="label">Data/Hora</td>
                    <td class="value">' . ($u['data_entrada_fmt'] ?? '-') . '</td>
                </tr>
                <tr>
                    <td class="label">Quilometragem</td>
                    <td class="value">' . ($u['km_entrada'] ? number_format($u['km_entrada'], 0, ',', '.') . ' km' : '-') . '</td>
                </tr>
                ' . ($u['observacoes_entrada'] ? '
                <tr>
                    <td class="label">Observações</td>
                    <td class="value">' . nl2br(htmlspecialchars($u['observacoes_entrada'])) . '</td>
                </tr>' : '') . '
            </table>
        </div>
    </div>';
    }
    
    $html .= '
    <div class="assinatura">
        <div class="linha-assinatura">
            ' . htmlspecialchars($u['usuario_nome']) . '
        </div>
        <p style="font-size: 10px; color: #6c757d; margin-top: 5px;">Assinatura do Usuário</p>
    </div>
    
    <div class="footer">
        <p><strong>Sistema de Controle de Frota</strong></p>
        <p>Este documento é um comprovante da utilização do veículo e deve ser mantido para fins de controle.</p>
        <p>Documento gerado em: ' . date('d/m/Y H:i:s') . '</p>
    </div>';
    
    $mpdf->SetTitle('Comprovante de Utilização #' . $utilizacaoId);
    $mpdf->SetAuthor('Sistema de Frota');
    $mpdf->WriteHTML($html);
    
    $filename = 'comprovante_frota_' . $utilizacaoId . '_' . date('Ymd') . '.pdf';
    $mpdf->Output($filename, 'I');
    
} catch (Exception $e) {
    die('Erro ao gerar PDF: ' . $e->getMessage());
}
?>



