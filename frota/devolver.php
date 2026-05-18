<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_devolver');

$usuarioId = $_SESSION['usuario_id'] ?? 0;
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$utilizacaoId = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devolver Veículo - Frota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .header-devolver {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .card-step {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        .step-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-radius: 12px 12px 0 0;
            border-bottom: 1px solid #e9ecef;
        }
        .step-header h6 { margin: 0; font-weight: 600; }
        .step-body { padding: 1.5rem; }
        
        .foto-container {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .foto-container:hover { border-color: #ffc107; background-color: #fffef8; }
        .foto-container.has-photo { border-style: solid; border-color: #ffc107; }
        .foto-container img { max-width: 100%; max-height: 180px; border-radius: 8px; }
        .foto-container i { font-size: 3rem; color: #6c757d; }
        .foto-container .btn-remover { position: absolute; top: 10px; right: 10px; }
        
        .camera-preview { width: 100%; max-height: 400px; background: #000; border-radius: 8px; }
        
        .veiculo-info-card {
            background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 1.5rem;
        }
        .placa-grande {
            background-color: #ffc107;
            color: #212529;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: bold;
            font-family: monospace;
            font-size: 1.5rem;
            display: inline-block;
        }
        
        .info-saida {
            background: #e9ecef;
            border-radius: 8px;
            padding: 1rem;
        }
        .info-saida .label { color: #6c757d; font-size: 0.8rem; }
        .info-saida .valor { font-weight: 600; }
        
        .checklist-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .checklist-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-devolver">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1"><i class="bi bi-box-arrow-in-left me-2"></i>Devolver Veículo</h3>
                    <small class="opacity-75">Registre a entrada do veículo</small>
                </div>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="btn btn-outline-dark btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Informações do Veículo e Saída -->
        <div class="veiculo-info-card mb-4" id="veiculoInfo" style="display: none;">
            <div class="row align-items-center mb-3">
                <div class="col-auto">
                    <i class="bi bi-truck" style="font-size: 3rem; color: #e0a800;"></i>
                </div>
                <div class="col">
                    <h4 class="mb-1" id="veiculoModelo">-</h4>
                    <p class="mb-0 text-muted" id="veiculoDetalhes">-</p>
                </div>
                <div class="col-auto">
                    <span class="placa-grande" id="veiculoPlaca">-</span>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="info-saida">
                        <div class="label">Data Saída</div>
                        <div class="valor" id="dataSaida">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-saida">
                        <div class="label">KM Saída</div>
                        <div class="valor" id="kmSaida">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-saida">
                        <div class="label">Destino</div>
                        <div class="valor" id="destinoSaida">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-saida">
                        <div class="label">Tempo em Uso</div>
                        <div class="valor" id="tempoUso">-</div>
                    </div>
                </div>
            </div>
        </div>

        <form id="formDevolucao">
            <input type="hidden" id="id_utilizacao" name="id_utilizacao" value="">
            
            <!-- Step 1: Dados da Entrada -->
            <div class="card-step">
                <div class="step-header">
                    <h6><i class="bi bi-1-circle-fill text-warning me-2"></i>Dados da Entrada</h6>
                </div>
                <div class="step-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">KM Atual <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-lg" id="km_entrada" name="km_entrada" required min="0">
                            <small class="text-muted">KM percorrido: <strong id="kmPercorrido">0</strong> km</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes_entrada" name="observacoes_entrada" rows="3" placeholder="Informações sobre a viagem, ocorrências, etc..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Fotos Obrigatórias -->
            <div class="card-step">
                <div class="step-header">
                    <h6><i class="bi bi-2-circle-fill text-warning me-2"></i>Fotos Obrigatórias</h6>
                </div>
                <div class="step-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Sua Selfie <span class="text-danger">*</span></label>
                            <div class="foto-container" id="container-selfie" onclick="abrirCamera('selfie')">
                                <i class="bi bi-person-bounding-box"></i>
                                <p class="text-muted mt-2 mb-0">Clique para tirar sua foto</p>
                            </div>
                            <input type="hidden" id="foto_selfie" name="foto_selfie">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Foto do Painel (KM) <span class="text-danger">*</span></label>
                            <div class="foto-container" id="container-km" onclick="abrirCamera('km')">
                                <i class="bi bi-speedometer2"></i>
                                <p class="text-muted mt-2 mb-0">Clique para fotografar o painel</p>
                            </div>
                            <input type="hidden" id="foto_km" name="foto_km">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Fotos do Veículo -->
            <div class="card-step">
                <div class="step-header">
                    <h6><i class="bi bi-3-circle-fill text-warning me-2"></i>Fotos do Veículo (Opcional)</h6>
                </div>
                <div class="step-body">
                    <p class="text-muted mb-3">Registre o estado atual do veículo na devolução</p>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="foto-container" id="container-veiculo1" onclick="abrirCamera('veiculo1')">
                                <i class="bi bi-camera"></i>
                                <p class="text-muted mt-2 mb-0">Foto 1</p>
                            </div>
                            <input type="hidden" id="foto_veiculo1" name="foto_veiculo1">
                        </div>
                        <div class="col-md-4">
                            <div class="foto-container" id="container-veiculo2" onclick="abrirCamera('veiculo2')">
                                <i class="bi bi-camera"></i>
                                <p class="text-muted mt-2 mb-0">Foto 2</p>
                            </div>
                            <input type="hidden" id="foto_veiculo2" name="foto_veiculo2">
                        </div>
                        <div class="col-md-4">
                            <div class="foto-container" id="container-veiculo3" onclick="abrirCamera('veiculo3')">
                                <i class="bi bi-camera"></i>
                                <p class="text-muted mt-2 mb-0">Foto 3</p>
                            </div>
                            <input type="hidden" id="foto_veiculo3" name="foto_veiculo3">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Checklist -->
            <div class="card-step">
                <div class="step-header">
                    <h6><i class="bi bi-4-circle-fill text-warning me-2"></i>Checklist de Devolução</h6>
                </div>
                <div class="step-body p-0">
                    <div class="checklist-item">
                        <span><i class="bi bi-circle-fill text-secondary me-2" style="font-size: 0.5rem;"></i>Veículo limpo</span>
                        <div class="btn-group">
                            <input type="radio" class="btn-check" name="limpeza_ok" id="limpeza_ok_sim" value="1">
                            <label class="btn btn-outline-success btn-sm" for="limpeza_ok_sim">OK</label>
                            <input type="radio" class="btn-check" name="limpeza_ok" id="limpeza_ok_nao" value="0">
                            <label class="btn btn-outline-danger btn-sm" for="limpeza_ok_nao">Não</label>
                        </div>
                    </div>
                    <div class="checklist-item">
                        <span><i class="bi bi-circle-fill text-secondary me-2" style="font-size: 0.5rem;"></i>Nível de Combustível</span>
                        <select class="form-select form-select-sm" name="nivel_combustivel" style="width: auto;">
                            <option value="">-</option>
                            <option value="cheio">Cheio</option>
                            <option value="3/4">3/4</option>
                            <option value="1/2">1/2</option>
                            <option value="1/4">1/4</option>
                            <option value="reserva">Reserva</option>
                        </select>
                    </div>
                    <div class="p-3">
                        <label class="form-label">Avarias ou problemas encontrados</label>
                        <textarea class="form-control" name="avarias_encontradas" rows="2" placeholder="Descreva qualquer problema ou avaria..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Botão Confirmar -->
            <div class="d-grid gap-2 mb-5">
                <button type="submit" class="btn btn-warning btn-lg" id="btnConfirmar">
                    <i class="bi bi-check-circle me-2"></i>Confirmar Devolução do Veículo
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Câmera -->
    <div class="modal fade" id="modalCamera" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-camera me-2"></i>Capturar Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="cameraPreview" class="camera-preview" autoplay playsinline></video>
                    <canvas id="cameraCanvas" style="display: none;"></canvas>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="capturarFoto()">
                        <i class="bi bi-camera me-1"></i>Capturar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        let utilizacaoId = <?= $utilizacaoId ?>;
        let kmSaida = 0;
        let currentPhotoType = '';
        let cameraStream = null;
        const modalCamera = new bootstrap.Modal(document.getElementById('modalCamera'));
        
        $(document).ready(function() {
            if (utilizacaoId) {
                carregarUtilizacao(utilizacaoId);
            } else {
                // Buscar utilização ativa do usuário
                buscarMinhaUtilizacao();
            }
            
            $('#km_entrada').on('input', function() {
                const kmEntrada = parseInt($(this).val()) || 0;
                const percorrido = kmEntrada - kmSaida;
                $('#kmPercorrido').text(percorrido >= 0 ? percorrido.toLocaleString() : 0);
                window._kmIgualConfirmado = false;
            });
        });
        
        function buscarMinhaUtilizacao() {
            $.ajax({
                url: baseUrl + '/api/frota/minha_utilizacao.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.tem_veiculo && data.utilizacao) {
                        utilizacaoId = data.utilizacao.id;
                        $('#id_utilizacao').val(utilizacaoId);
                        exibirDadosUtilizacao(data.utilizacao);
                    } else {
                        exibirToast('Você não possui veículo para devolver!', 'warning');
                        setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 1500);
                    }
                }
            });
        }
        
        function carregarUtilizacao(id) {
            $.ajax({
                url: baseUrl + '/api/frota/buscar_utilizacao.php',
                method: 'GET',
                data: { id: id, para_devolucao: true },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok' && data.utilizacao) {
                        $('#id_utilizacao').val(id);
                        exibirDadosUtilizacao(data.utilizacao);
                    } else {
                        // Verificar se já foi finalizada
                        if (data.ja_finalizada) {
                            exibirToast('Esta utilização já foi finalizada e não pode ser devolvida novamente.', 'warning');
                        } else {
                            exibirToast(data.mensagem || 'Utilização não encontrada!', 'danger');
                        }
                        setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 2000);
                    }
                },
                error: function() {
                    exibirToast('Erro ao carregar utilização!', 'danger');
                    setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 1500);
                }
            });
        }
        
        function exibirDadosUtilizacao(u) {
            kmSaida = parseInt(u.km_saida);
            
            $('#veiculoModelo').text(u.modelo);
            $('#veiculoDetalhes').text(`${u.marca} • ${u.cor || ''}`);
            $('#veiculoPlaca').text(u.placa);
            $('#dataSaida').text(u.data_saida_formatada);
            $('#kmSaida').text(kmSaida.toLocaleString() + ' km');
            $('#destinoSaida').text(u.destino || '-');
            $('#tempoUso').text(u.tempo_uso || '-');
            
            $('#km_entrada').attr('min', kmSaida);
            $('#km_entrada').val(kmSaida);
            
            $('#veiculoInfo').show();
        }
        
        function abrirCamera(tipo) {
            currentPhotoType = tipo;
            const constraints = {
                video: {
                    facingMode: tipo === 'selfie' ? 'user' : 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            };
            
            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    cameraStream = stream;
                    document.getElementById('cameraPreview').srcObject = stream;
                    modalCamera.show();
                })
                .catch(function(err) {
                    exibirToast('Não foi possível acessar a câmera. Verifique as permissões.', 'danger');
                });
        }
        
        function capturarFoto() {
            const video = document.getElementById('cameraPreview');
            const canvas = document.getElementById('cameraCanvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0);
            
            const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
            pararCamera();
            modalCamera.hide();
            
            const container = document.getElementById('container-' + currentPhotoType);
            container.innerHTML = `
                <img src="${dataUrl}" alt="Foto">
                <button type="button" class="btn btn-danger btn-sm btn-remover" onclick="removerFoto('${currentPhotoType}', event)">
                    <i class="bi bi-x"></i>
                </button>
            `;
            container.classList.add('has-photo');
            
            const inputMap = {
                'selfie': 'foto_selfie',
                'km': 'foto_km',
                'veiculo1': 'foto_veiculo1',
                'veiculo2': 'foto_veiculo2',
                'veiculo3': 'foto_veiculo3'
            };
            document.getElementById(inputMap[currentPhotoType]).value = dataUrl;
        }
        
        function removerFoto(tipo, event) {
            event.stopPropagation();
            
            const iconMap = {
                'selfie': 'bi-person-bounding-box',
                'km': 'bi-speedometer2',
                'veiculo1': 'bi-camera',
                'veiculo2': 'bi-camera',
                'veiculo3': 'bi-camera'
            };
            
            const textMap = {
                'selfie': 'Clique para tirar sua foto',
                'km': 'Clique para fotografar o painel',
                'veiculo1': 'Foto 1',
                'veiculo2': 'Foto 2',
                'veiculo3': 'Foto 3'
            };
            
            const container = document.getElementById('container-' + tipo);
            container.innerHTML = `
                <i class="bi ${iconMap[tipo]}"></i>
                <p class="text-muted mt-2 mb-0">${textMap[tipo]}</p>
            `;
            container.classList.remove('has-photo');
            
            const inputMap = {
                'selfie': 'foto_selfie',
                'km': 'foto_km',
                'veiculo1': 'foto_veiculo1',
                'veiculo2': 'foto_veiculo2',
                'veiculo3': 'foto_veiculo3'
            };
            document.getElementById(inputMap[tipo]).value = '';
        }
        
        function pararCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
        }
        
        document.getElementById('modalCamera').addEventListener('hidden.bs.modal', pararCamera);
        
        $('#formDevolucao').submit(function(e) {
            e.preventDefault();
            
            if (!$('#foto_selfie').val()) {
                exibirToast('Por favor, tire sua selfie!', 'warning');
                return;
            }
            if (!$('#foto_km').val()) {
                exibirToast('Por favor, tire a foto do painel (KM)!', 'warning');
                return;
            }
            
            const kmEntrada = parseInt($('#km_entrada').val()) || 0;
            if (kmEntrada < kmSaida) {
                exibirToast('O KM de entrada não pode ser menor que o KM de saída!', 'warning');
                return;
            }
            
            if (kmEntrada === kmSaida && !window._kmIgualConfirmado) {
                if (!confirm('O KM de entrada é igual ao KM de saída (' + kmSaida.toLocaleString() + ' km). Não houve alteração no quilômetro.\n\nDeseja prosseguir com a devolução mesmo assim?')) {
                    return;
                }
                window._kmIgualConfirmado = true;
            }
            
            const btn = $('#btnConfirmar');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processando...');
            
            const formData = {
                id_utilizacao: $('#id_utilizacao').val(),
                km_entrada: kmEntrada,
                observacoes_entrada: $('#observacoes_entrada').val(),
                foto_selfie: $('#foto_selfie').val(),
                foto_km: $('#foto_km').val(),
                foto_veiculo1: $('#foto_veiculo1').val(),
                foto_veiculo2: $('#foto_veiculo2').val(),
                foto_veiculo3: $('#foto_veiculo3').val(),
                checklist: {
                    limpeza_ok: $('input[name="limpeza_ok"]:checked').val(),
                    nivel_combustivel: $('select[name="nivel_combustivel"]').val(),
                    avarias_encontradas: $('textarea[name="avarias_encontradas"]').val()
                }
            };
            
            $.ajax({
                url: baseUrl + '/api/frota/registrar_entrada.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        let msg = 'Veículo devolvido com sucesso! KM percorrido: ' + data.km_percorrido + ' km | Tempo de uso: ' + data.tempo_uso;
                        if (data.whatsapp_enviado) {
                            msg += ' | Comprovante enviado via WhatsApp';
                        }
                        exibirToast(msg, 'success');
                        setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 2500);
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                        btn.prop('disabled', false).html('<i class="bi bi-check-circle me-2"></i>Confirmar Devolução do Veículo');
                    }
                },
                error: function() {
                    exibirToast('Erro ao processar requisição', 'danger');
                    btn.prop('disabled', false).html('<i class="bi bi-check-circle me-2"></i>Confirmar Devolução do Veículo');
                }
            });
        });
    </script>
</body>
</html>

