<?php
/**
 * API para Registrar Entrada (Devolução) de Veículo
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

$usuario_id = $_SESSION['usuario_id'];

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$id_utilizacao = intval($input['id_utilizacao'] ?? 0);
$km_entrada = intval($input['km_entrada'] ?? 0);
$observacoes_entrada = trim($input['observacoes_entrada'] ?? '');
$foto_selfie = $input['foto_selfie'] ?? '';
$foto_km = $input['foto_km'] ?? '';
$foto_veiculo1 = $input['foto_veiculo1'] ?? '';
$foto_veiculo2 = $input['foto_veiculo2'] ?? '';
$foto_veiculo3 = $input['foto_veiculo3'] ?? '';
$checklist = $input['checklist'] ?? [];

// Validações
if (!$id_utilizacao) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Utilização não informada']);
    exit;
}
if (!$km_entrada) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'KM de entrada não informado']);
    exit;
}
if (!$foto_selfie || !$foto_km) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Fotos obrigatórias não enviadas']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Buscar utilização
    $sql_check = "SELECT fu.*, v.id as veiculo_id 
                  FROM frota_utilizacoes fu 
                  JOIN frota_veiculos v ON fu.id_veiculo = v.id
                  WHERE fu.id = ? AND fu.id_usuario = ? AND fu.status = 'em_andamento' 
                  FOR UPDATE";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("is", $id_utilizacao, $usuario_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        throw new Exception('Utilização não encontrada ou não pertence a você');
    }
    
    $utilizacao = $result_check->fetch_assoc();
    $km_saida = intval($utilizacao['km_saida']);
    $id_veiculo = intval($utilizacao['veiculo_id']);
    
    if ($km_entrada < $km_saida) {
        throw new Exception('KM de entrada não pode ser menor que o KM de saída');
    }
    
    // Criar pasta de uploads
    $upload_dir = __DIR__ . '/../../uploads/frota/' . date('Y/m');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Função para salvar imagem base64
    function salvarImagem($base64, $prefix, $upload_dir) {
        if (empty($base64)) return null;
        
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
            $type = strtolower($type[1]);
            
            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                $type = 'jpg';
            }
            
            $base64 = base64_decode($base64);
            if ($base64 === false) return null;
            
            $filename = $prefix . '_' . uniqid() . '.' . $type;
            $filepath = $upload_dir . '/' . $filename;
            
            if (file_put_contents($filepath, $base64)) {
                return date('Y/m') . '/' . $filename;
            }
        }
        return null;
    }
    
    // Salvar fotos
    $foto_selfie_path = salvarImagem($foto_selfie, 'selfie_entrada', $upload_dir);
    $foto_km_path = salvarImagem($foto_km, 'km_entrada', $upload_dir);
    $foto_veiculo1_path = salvarImagem($foto_veiculo1, 'veiculo_entrada1', $upload_dir);
    $foto_veiculo2_path = salvarImagem($foto_veiculo2, 'veiculo_entrada2', $upload_dir);
    $foto_veiculo3_path = salvarImagem($foto_veiculo3, 'veiculo_entrada3', $upload_dir);
    
    // Calcular KM percorrido e tempo
    $km_percorrido = $km_entrada - $km_saida;
    $data_entrada = date('Y-m-d H:i:s');
    
    // Calcular tempo em minutos
    $data_saida_obj = new DateTime($utilizacao['data_saida']);
    $data_entrada_obj = new DateTime($data_entrada);
    $diff = $data_entrada_obj->diff($data_saida_obj);
    $tempo_utilizacao = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    
    // Atualizar utilização
    $sql_update = "UPDATE frota_utilizacoes SET 
                   data_entrada = ?,
                   km_entrada = ?,
                   km_percorrido = ?,
                   tempo_utilizacao = ?,
                   foto_km_entrada = ?,
                   foto_selfie_entrada = ?,
                   foto_veiculo_entrada_1 = ?,
                   foto_veiculo_entrada_2 = ?,
                   foto_veiculo_entrada_3 = ?,
                   observacoes_entrada = ?,
                   status = 'finalizado'
                   WHERE id = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("siiissssssi",
        $data_entrada,
        $km_entrada,
        $km_percorrido,
        $tempo_utilizacao,
        $foto_km_path,
        $foto_selfie_path,
        $foto_veiculo1_path,
        $foto_veiculo2_path,
        $foto_veiculo3_path,
        $observacoes_entrada,
        $id_utilizacao
    );
    
    if (!$stmt_update->execute()) {
        throw new Exception('Erro ao atualizar utilização: ' . $stmt_update->error);
    }
    
    // Inserir checklist de entrada
    if (!empty($checklist)) {
        $sql_checklist = "INSERT INTO frota_checklist 
                          (id_utilizacao, tipo, limpeza_ok, nivel_combustivel, avarias_encontradas)
                          VALUES (?, 'entrada', ?, ?, ?)";
        
        $stmt_checklist = $conn->prepare($sql_checklist);
        $limpeza = isset($checklist['limpeza_ok']) ? intval($checklist['limpeza_ok']) : null;
        $nivel = $checklist['nivel_combustivel'] ?? null;
        $avarias = $checklist['avarias_encontradas'] ?? null;
        
        $stmt_checklist->bind_param("iiss", $id_utilizacao, $limpeza, $nivel, $avarias);
        $stmt_checklist->execute();
    }
    
    // Atualizar veículo para disponível
    $sql_veiculo = "UPDATE frota_veiculos SET status = 'disponivel', km_atual = ? WHERE id = ?";
    $stmt_veiculo = $conn->prepare($sql_veiculo);
    $stmt_veiculo->bind_param("ii", $km_entrada, $id_veiculo);
    $stmt_veiculo->execute();
    
    $conn->commit();
    
    // Formatar tempo de uso para resposta
    $horas = floor($tempo_utilizacao / 60);
    $mins = $tempo_utilizacao % 60;
    $tempo_uso_formatado = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins}min";
    
    // Enviar comprovante via WhatsApp (mensagem + PDF)
    $whatsapp_enviado = false;
    try {
        require_once __DIR__ . '/../../core/services/WhatsAppService.php';
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $sql_user = "SELECT nome, telefone FROM usuarios WHERE id = ?";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("s", $usuario_id);
        $stmt_user->execute();
        $user_data = $stmt_user->get_result()->fetch_assoc();
        
        if ($user_data && !empty($user_data['telefone']) && $user_data['telefone'] !== '0') {
            $sql_dados = "SELECT fu.*, v.placa, v.modelo, v.marca, v.cor, v.ano,
                                 u.nome as usuario_nome,
                                 e.entidade_nome,
                                 fd.nome as departamento_nome,
                                 DATE_FORMAT(fu.data_saida, '%d/%m/%Y %H:%i') as data_saida_fmt,
                                 DATE_FORMAT(fu.data_entrada, '%d/%m/%Y %H:%i') as data_entrada_fmt
                          FROM frota_utilizacoes fu
                          JOIN frota_veiculos v ON fu.id_veiculo = v.id
                          JOIN usuarios u ON fu.id_usuario = u.id
                          LEFT JOIN entidade e ON fu.id_entidade = e.entidade_id
                          LEFT JOIN frota_departamentos fd ON fu.id_departamento = fd.id
                          WHERE fu.id = ?";
            $stmt_dados = $conn->prepare($sql_dados);
            $stmt_dados->bind_param("i", $id_utilizacao);
            $stmt_dados->execute();
            $dados = $stmt_dados->get_result()->fetch_assoc();
            
            $valor_km_cfg = 0;
            $sql_vkm = "SELECT valor FROM frota_configuracoes WHERE chave = 'valor_km' LIMIT 1";
            $r_vkm = $conn->query($sql_vkm);
            if ($r_vkm && $row_vkm = $r_vkm->fetch_assoc()) {
                $valor_km_cfg = floatval($row_vkm['valor']);
            }
            $valor_locacao = $km_percorrido * $valor_km_cfg;
            
            $mensagem = "🚗 *COMPROVANTE DE DEVOLUÇÃO*\n\n"
                . "Olá *" . $user_data['nome'] . "*,\n\n"
                . "Sua utilização de veículo foi finalizada com sucesso!\n\n"
                . "🚛 *Veículo:* " . $dados['modelo'] . " - " . $dados['marca'] . "\n"
                . "🔢 *Placa:* " . $dados['placa'] . "\n"
                . (!empty($dados['departamento_nome']) ? "🏢 *Departamento:* " . $dados['departamento_nome'] . "\n" : "")
                . "📅 *Saída:* " . $dados['data_saida_fmt'] . "\n"
                . "📅 *Entrada:* " . $dados['data_entrada_fmt'] . "\n"
                . "⏱ *Tempo de uso:* " . $tempo_uso_formatado . "\n"
                . "📏 *KM Saída:* " . number_format($dados['km_saida'], 0, ',', '.') . " km\n"
                . "📏 *KM Entrada:* " . number_format($km_entrada, 0, ',', '.') . " km\n"
                . "🛣 *KM Percorrido:* " . number_format($km_percorrido, 0, ',', '.') . " km\n";
            
            if ($valor_km_cfg > 0) {
                $mensagem .= "💰 *Valor/KM:* R$ " . number_format($valor_km_cfg, 2, ',', '.') . "\n"
                    . "💵 *Valor Total:* R$ " . number_format($valor_locacao, 2, ',', '.') . "\n";
            }
            
            $mensagem .= "\n📎 _Comprovante PDF em anexo_\n\n🤖 *Sistema de Frota AOM*";
            
            // Gerar PDF do comprovante para envio
            $caminho_pdf = null;
            try {
                $statusText = 'FINALIZADO';
                $statusColor = '#28a745';
                
                $html_pdf = '
                <style>
                    body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
                    .header { text-align: center; padding: 20px; background: linear-gradient(135deg, #17a2b8, #138496); color: white; border-radius: 10px; margin-bottom: 20px; }
                    .header h1 { margin: 0 0 5px 0; font-size: 22px; }
                    .header p { margin: 0; opacity: 0.9; }
                    .placa { display: inline-block; background: #ffc107; color: #212529; padding: 8px 20px; border-radius: 5px; font-weight: bold; font-family: monospace; font-size: 20px; margin: 10px 0; }
                    .section { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 15px; overflow: hidden; }
                    .section-header { background: #343a40; color: white; padding: 10px 15px; font-weight: bold; }
                    .section-header.saida { background: #28a745; }
                    .section-header.entrada { background: #ffc107; color: #212529; }
                    .section-body { padding: 15px; }
                    table { width: 100%; border-collapse: collapse; }
                    table td { padding: 8px 0; border-bottom: 1px solid #e9ecef; }
                    table tr:last-child td { border-bottom: none; }
                    .label { color: #6c757d; width: 40%; }
                    .value { font-weight: bold; }
                    .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; color: white; font-weight: bold; background: ' . $statusColor . '; }
                    .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #dee2e6; color: #6c757d; font-size: 9px; }
                    .assinatura { margin-top: 40px; text-align: center; }
                    .linha-assinatura { border-top: 1px solid #333; width: 250px; margin: 0 auto; padding-top: 5px; }
                </style>
                
                <div class="header">
                    <h1>COMPROVANTE DE UTILIZAÇÃO DE VEÍCULO</h1>
                    <p>Registro #' . $id_utilizacao . '</p>
                </div>
                
                <table style="margin-bottom: 20px;">
                    <tr>
                        <td style="width: 60%; vertical-align: top; border: none;">
                            <div class="placa">' . $dados['placa'] . '</div>
                            <h3 style="margin: 5px 0;">' . htmlspecialchars($dados['modelo']) . '</h3>
                            <p style="color: #6c757d; margin: 0 0 5px 0;">'
                                . htmlspecialchars($dados['marca'])
                                . ($dados['ano'] ? ' • ' . $dados['ano'] : '')
                                . ($dados['cor'] ? ' • ' . $dados['cor'] : '') . '</p>
                            <span style="background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: bold;">
                                Valor KM: R$ ' . number_format($valor_km_cfg, 2, ',', '.') . '
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
                            <div style="font-size: 22px; font-weight: bold; color: #28a745;">' . number_format($km_percorrido, 0, ',', '.') . '</div>
                            <div style="font-size: 10px; color: #6c757d;">KM PERCORRIDO</div>
                        </td>
                        <td style="width: 2%;"></td>
                        <td style="width: 24%; text-align: center; border: 2px solid #17a2b8; border-radius: 8px; padding: 10px;">
                            <div style="font-size: 22px; font-weight: bold; color: #17a2b8;">' . $tempo_uso_formatado . '</div>
                            <div style="font-size: 10px; color: #6c757d;">TEMPO DE USO</div>
                        </td>
                        <td style="width: 2%;"></td>
                        <td style="width: 24%; text-align: center; border: 2px solid #28a745; border-radius: 8px; padding: 10px; background: #f0fff4;">
                            <div style="font-size: 20px; font-weight: bold; color: #28a745;">R$ ' . number_format($valor_locacao, 2, ',', '.') . '</div>
                            <div style="font-size: 10px; color: #6c757d;">VALOR LOCAÇÃO</div>
                            <div style="font-size: 8px; color: #999; margin-top: 2px;">' . number_format($km_percorrido, 0, ',', '.') . ' km × R$ ' . number_format($valor_km_cfg, 2, ',', '.') . '</div>
                        </td>
                        <td style="width: 2%;"></td>
                        <td style="width: 24%; text-align: center; border: 2px solid #6c757d; border-radius: 8px; padding: 10px;">
                            <div style="font-size: 14px; font-weight: bold; color: #333;">' . htmlspecialchars($dados['usuario_nome']) . '</div>
                            <div style="font-size: 10px; color: #6c757d;">USUÁRIO</div>
                        </td>
                    </tr>
                </table>
                
                <div class="section">
                    <div class="section-header saida">SAÍDA DO VEÍCULO</div>
                    <div class="section-body">
                        <table>
                            <tr><td class="label">Data/Hora</td><td class="value">' . $dados['data_saida_fmt'] . '</td></tr>
                            <tr><td class="label">Quilometragem</td><td class="value">' . number_format($dados['km_saida'], 0, ',', '.') . ' km</td></tr>
                            <tr><td class="label">Entidade</td><td class="value">' . htmlspecialchars($dados['entidade_nome'] ?? '-') . '</td></tr>
                            <tr><td class="label">Departamento</td><td class="value">' . htmlspecialchars($dados['departamento_nome'] ?? '-') . '</td></tr>
                            <tr><td class="label">Destino</td><td class="value">' . htmlspecialchars($dados['destino'] ?? '-') . '</td></tr>
                            <tr><td class="label">Motivo</td><td class="value">' . htmlspecialchars($dados['motivo'] ?? '-') . '</td></tr>'
                            . ($dados['observacoes_saida'] ? '<tr><td class="label">Observações</td><td class="value">' . nl2br(htmlspecialchars($dados['observacoes_saida'])) . '</td></tr>' : '') . '
                        </table>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-header entrada">DEVOLUÇÃO DO VEÍCULO</div>
                    <div class="section-body">
                        <table>
                            <tr><td class="label">Data/Hora</td><td class="value">' . $dados['data_entrada_fmt'] . '</td></tr>
                            <tr><td class="label">Quilometragem</td><td class="value">' . number_format($km_entrada, 0, ',', '.') . ' km</td></tr>'
                            . ($observacoes_entrada ? '<tr><td class="label">Observações</td><td class="value">' . nl2br(htmlspecialchars($observacoes_entrada)) . '</td></tr>' : '') . '
                        </table>
                    </div>
                </div>
                
                <div class="assinatura">
                    <div class="linha-assinatura">' . htmlspecialchars($dados['usuario_nome']) . '</div>
                    <p style="font-size: 10px; color: #6c757d; margin-top: 5px;">Assinatura do Usuário</p>
                </div>
                
                <div class="footer">
                    <p><strong>Sistema de Controle de Frota</strong></p>
                    <p>Este documento é um comprovante da utilização do veículo e deve ser mantido para fins de controle.</p>
                    <p>Documento gerado em: ' . date('d/m/Y H:i:s') . '</p>
                </div>';
                
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_left' => 15,
                    'margin_right' => 15,
                    'margin_top' => 15,
                    'margin_bottom' => 15
                ]);
                
                $mpdf->SetTitle('Comprovante de Utilização #' . $id_utilizacao);
                $mpdf->SetAuthor('Sistema de Frota');
                $mpdf->WriteHTML($html_pdf);
                
                $tmp_dir = sys_get_temp_dir();
                $caminho_pdf = $tmp_dir . '/comprovante_frota_' . $id_utilizacao . '_' . date('Ymd_His') . '.pdf';
                $mpdf->Output($caminho_pdf, 'F');
            } catch (Exception $e_pdf) {
                error_log("[FROTA] Erro ao gerar PDF do comprovante: " . $e_pdf->getMessage());
            }
            
            $resultado = WhatsAppService::enviarMensagemEArquivo(
                $user_data['telefone'],
                $mensagem,
                $caminho_pdf,
                [
                    'usuario_id' => $usuario_id,
                    'nome_destinatario' => $user_data['nome'],
                    'tipo_notificacao' => 'frota_devolucao'
                ]
            );
            
            $whatsapp_enviado = $resultado['sucesso'] ?? false;
            
            if ($caminho_pdf && file_exists($caminho_pdf)) {
                @unlink($caminho_pdf);
            }
        }
    } catch (Exception $e) {
        error_log("[FROTA] Erro ao enviar WhatsApp de devolução: " . $e->getMessage());
    }
    
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Veículo devolvido com sucesso',
        'km_percorrido' => $km_percorrido,
        'tempo_uso' => $tempo_uso_formatado,
        'whatsapp_enviado' => $whatsapp_enviado
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}
?>

