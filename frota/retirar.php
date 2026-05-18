<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('frota_retirar');

$usuarioId = $_SESSION['usuario_id'] ?? 0;
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$veiculoId = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retirar Veículo - Frota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .header-retirar {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
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
        .step-header h6 {
            margin: 0;
            font-weight: 600;
        }
        .step-body { padding: 1.5rem; }
        
        /* Captura de foto */
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
        .foto-container:hover { border-color: #28a745; background-color: #f8fff9; }
        .foto-container.has-photo { border-style: solid; border-color: #28a745; }
        .foto-container img {
            max-width: 100%;
            max-height: 180px;
            border-radius: 8px;
        }
        .foto-container i { font-size: 3rem; color: #6c757d; }
        .foto-container .btn-remover {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        /* Camera modal */
        .camera-preview {
            width: 100%;
            max-height: 400px;
            background: #000;
            border-radius: 8px;
        }
        
        /* Veiculo info */
        .veiculo-info-card {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border: 2px solid #4caf50;
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
        
        /* Checklist */
        .checklist-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .checklist-item:last-child { border-bottom: none; }
        .checklist-buttons .btn { min-width: 60px; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-retirar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1"><i class="bi bi-box-arrow-right me-2"></i>Retirar Veículo</h3>
                    <small class="opacity-75">Registre a saída do veículo</small>
                </div>
                <a href="<?= MenuPermissaoService::ajustarUrl('/frota/dashboard.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Informações do Veículo -->
        <div class="veiculo-info-card mb-4" id="veiculoInfo" style="display: none;">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-truck" style="font-size: 3rem; color: #4caf50;"></i>
                </div>
                <div class="col">
                    <h4 class="mb-1" id="veiculoModelo">-</h4>
                    <p class="mb-0 text-muted" id="veiculoDetalhes">-</p>
                </div>
                <div class="col-auto">
                    <span class="placa-grande" id="veiculoPlaca">-</span>
                </div>
            </div>
        </div>

        <form id="formRetirada">
            <input type="hidden" id="id_veiculo" name="id_veiculo" value="<?= $veiculoId ?>">
            
            <!-- Step 1: Dados da Saída -->
            <div class="card-step">
                <div class="step-header">
                    <h6><i class="bi bi-1-circle-fill text-success me-2"></i>Dados da Saída</h6>
                </div>
                <div class="step-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">KM Atual <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-lg" id="km_saida" name="km_saida" required min="0">
                            <small class="text-muted">Quilometragem atual</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Entidade <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_entidade" name="id_entidade" required>
                                <option value="">Selecione...</option>
                            </select>
                            <small class="text-muted">Área responsável</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Departamento <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_departamento" name="id_departamento" required>
                                <option value="">Selecione...</option>
                            </select>
                            <small class="text-muted">Departamento solicitante</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Motivo <span class="text-danger">*</span></label>
                            <select class="form-select" id="motivo" name="motivo" required>
                                <option value="">Selecione...</option>
                                <option value="Eventos">Eventos</option>
                                <option value="Entregas">Entregas</option>
                                <option value="Busca de materiais">Busca de materiais</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Destino <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="destino" name="destino" required placeholder="Ex: Local, Endereço">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes_saida" name="observacoes_saida" rows="2" placeholder="Informações adicionais..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Fotos Obrigatórias -->
            <div class="card-step">
                <div class="step-header">
                    <h6><i class="bi bi-2-circle-fill text-success me-2"></i>Fotos Obrigatórias</h6>
                </div>
                <div class="step-body">
                    <div class="row g-4">
                        <!-- Selfie -->
                        <div class="col-md-6">
                            <label class="form-label">Sua Selfie <span class="text-danger">*</span></label>
                            <div class="foto-container" id="container-selfie" onclick="abrirCamera('selfie')">
                                <i class="bi bi-person-bounding-box"></i>
                                <p class="text-muted mt-2 mb-0">Clique para tirar sua foto</p>
                            </div>
                            <input type="hidden" id="foto_selfie" name="foto_selfie">
                        </div>
                        
                        <!-- Foto do KM -->
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

            <!-- Step 3: Fotos do Veículo (Opcionais) -->
            <div class="card-step">
                <div class="step-header">
                    <h6><i class="bi bi-3-circle-fill text-success me-2"></i>Fotos do Veículo (Opcional)</h6>
                </div>
                <div class="step-body">
                    <p class="text-muted mb-3">Registre o estado atual do veículo (avarias, arranhões, etc.)</p>
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
                    <h6><i class="bi bi-4-circle-fill text-success me-2"></i>Checklist de Inspeção</h6>
                </div>
                <div class="step-body p-0">
                    <div class="checklist-item">
                        <span><i class="bi bi-circle-fill text-secondary me-2" style="font-size: 0.5rem;"></i>Pneus em bom estado</span>
                        <div class="checklist-buttons btn-group">
                            <input type="radio" class="btn-check" name="pneus_ok" id="pneus_ok_sim" value="1">
                            <label class="btn btn-outline-success btn-sm" for="pneus_ok_sim">OK</label>
                            <input type="radio" class="btn-check" name="pneus_ok" id="pneus_ok_nao" value="0">
                            <label class="btn btn-outline-danger btn-sm" for="pneus_ok_nao">Não</label>
                        </div>
                    </div>
                    <div class="checklist-item">
                        <span><i class="bi bi-circle-fill text-secondary me-2" style="font-size: 0.5rem;"></i>Faróis funcionando</span>
                        <div class="checklist-buttons btn-group">
                            <input type="radio" class="btn-check" name="farois_ok" id="farois_ok_sim" value="1">
                            <label class="btn btn-outline-success btn-sm" for="farois_ok_sim">OK</label>
                            <input type="radio" class="btn-check" name="farois_ok" id="farois_ok_nao" value="0">
                            <label class="btn btn-outline-danger btn-sm" for="farois_ok_nao">Não</label>
                        </div>
                    </div>
                    <div class="checklist-item">
                        <span><i class="bi bi-circle-fill text-secondary me-2" style="font-size: 0.5rem;"></i>Documentos no veículo</span>
                        <div class="checklist-buttons btn-group">
                            <input type="radio" class="btn-check" name="documentos_ok" id="documentos_ok_sim" value="1">
                            <label class="btn btn-outline-success btn-sm" for="documentos_ok_sim">OK</label>
                            <input type="radio" class="btn-check" name="documentos_ok" id="documentos_ok_nao" value="0">
                            <label class="btn btn-outline-danger btn-sm" for="documentos_ok_nao">Não</label>
                        </div>
                    </div>
                    <div class="checklist-item">
                        <span><i class="bi bi-circle-fill text-secondary me-2" style="font-size: 0.5rem;"></i>Limpeza OK</span>
                        <div class="checklist-buttons btn-group">
                            <input type="radio" class="btn-check" name="limpeza_ok" id="limpeza_ok_sim" value="1">
                            <label class="btn btn-outline-success btn-sm" for="limpeza_ok_sim">OK</label>
                            <input type="radio" class="btn-check" name="limpeza_ok" id="limpeza_ok_nao" value="0">
                            <label class="btn btn-outline-danger btn-sm" for="limpeza_ok_nao">Não</label>
                        </div>
                    </div>
                    <div class="checklist-item">
                        <span><i class="bi bi-circle-fill text-secondary me-2" style="font-size: 0.5rem;"></i>Nível de Combustível</span>
                        <div>
                            <select class="form-select form-select-sm" name="nivel_combustivel" style="width: auto;">
                                <option value="">-</option>
                                <option value="cheio">Cheio</option>
                                <option value="3/4">3/4</option>
                                <option value="1/2">1/2</option>
                                <option value="1/4">1/4</option>
                                <option value="reserva">Reserva</option>
                            </select>
                        </div>
                    </div>
                    <div class="p-3">
                        <label class="form-label">Detalhes da retirada</label>
                        <textarea class="form-control" name="avarias_encontradas" rows="2" placeholder="Informe detalhes relevantes sobre a retirada do veículo..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Botão Confirmar -->
            <div class="d-grid gap-2 mb-5">
                <button type="submit" class="btn btn-success btn-lg" id="btnConfirmar">
                    <i class="bi bi-check-circle me-2"></i>Confirmar Retirada do Veículo
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
                    <button type="button" class="btn btn-success" onclick="capturarFoto()">
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
        const veiculoId = <?= $veiculoId ?>;
        let currentPhotoType = '';
        let cameraStream = null;
        const modalCamera = new bootstrap.Modal(document.getElementById('modalCamera'));
        
        $(document).ready(function() {
            if (veiculoId) {
                carregarVeiculo(veiculoId);
                carregarEntidades();
                carregarDepartamentos();
            } else {
                exibirToast('Veículo não especificado!', 'danger');
                setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 1500);
            }
            
            // Verificar se usuário já tem veículo
            verificarUtilizacaoAtiva();
        });
        
        function carregarEntidades() {
            $.ajax({
                url: baseUrl + '/api/frota/listar_entidades.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok' && data.entidades) {
                        const select = $('#id_entidade');
                        select.find('option:not(:first)').remove();
                        data.entidades.forEach(function(e) {
                            select.append(`<option value="${e.id}">${e.nome}</option>`);
                        });
                    }
                }
            });
        }
        
        function carregarDepartamentos() {
            $.ajax({
                url: baseUrl + '/api/frota/departamentos.php',
                method: 'GET',
                data: { apenas_ativos: 1 },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok' && data.departamentos) {
                        const select = $('#id_departamento');
                        select.find('option:not(:first)').remove();
                        data.departamentos.forEach(function(d) {
                            select.append(`<option value="${d.id}">${d.nome}</option>`);
                        });
                    }
                }
            });
        }
        
        function verificarUtilizacaoAtiva() {
            $.ajax({
                url: baseUrl + '/api/frota/minha_utilizacao.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.tem_veiculo) {
                        exibirToast('Você já possui um veículo em uso! Devolva primeiro antes de retirar outro.', 'warning');
                        setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 2000);
                    }
                }
            });
        }
        
        function carregarVeiculo(id) {
            $.ajax({
                url: baseUrl + '/api/frota/buscar_veiculo.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok' && data.veiculo) {
                        const v = data.veiculo;
                        $('#veiculoModelo').text(v.modelo);
                        $('#veiculoDetalhes').text(`${v.marca} • ${v.ano || ''} • ${v.cor || ''} • KM: ${v.km_atual.toLocaleString()}`);
                        $('#veiculoPlaca').text(v.placa);
                        $('#km_saida').val(v.km_atual).attr('min', v.km_atual);
                        $('#veiculoInfo').show();
                        
                        if (v.status !== 'disponivel') {
                            exibirToast('Este veículo não está disponível!', 'danger');
                            setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 1500);
                        }
                    } else {
                        exibirToast('Veículo não encontrado!', 'danger');
                        setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 1500);
                    }
                }
            });
        }
        
        function abrirCamera(tipo) {
            currentPhotoType = tipo;
            
            // Verificar se é selfie (usar câmera frontal)
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
                    console.error('Erro ao acessar câmera:', err);
                    exibirToast('Não foi possível acessar a câmera. Verifique as permissões.', 'danger');
                });
        }
        
        function capturarFoto() {
            const video = document.getElementById('cameraPreview');
            const canvas = document.getElementById('cameraCanvas');
            const ctx = canvas.getContext('2d');
            
            // Redimensionar se muito grande (max 1024px)
            let width = video.videoWidth;
            let height = video.videoHeight;
            const maxSize = 1024;
            
            if (width > maxSize || height > maxSize) {
                if (width > height) {
                    height = Math.round(height * maxSize / width);
                    width = maxSize;
                } else {
                    width = Math.round(width * maxSize / height);
                    height = maxSize;
                }
            }
            
            canvas.width = width;
            canvas.height = height;
            ctx.drawImage(video, 0, 0, width, height);
            
            // Comprimir imagem (qualidade 0.5 para reduzir tamanho)
            const dataUrl = canvas.toDataURL('image/jpeg', 0.5);
            
            console.log('[FROTA] Foto capturada:', currentPhotoType, 'tamanho:', dataUrl.length);
            
            // Parar câmera
            pararCamera();
            modalCamera.hide();
            
            // Atualizar container
            const container = document.getElementById('container-' + currentPhotoType);
            container.innerHTML = `
                <img src="${dataUrl}" alt="Foto">
                <button type="button" class="btn btn-danger btn-sm btn-remover" onclick="removerFoto('${currentPhotoType}', event)">
                    <i class="bi bi-x"></i>
                </button>
            `;
            container.classList.add('has-photo');
            
            // Salvar no input hidden
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
        
        // Parar câmera ao fechar modal
        document.getElementById('modalCamera').addEventListener('hidden.bs.modal', function() {
            pararCamera();
        });
        
        // Enviar formulário
        $('#formRetirada').submit(function(e) {
            e.preventDefault();
            
            // Validar fotos obrigatórias
            if (!$('#foto_selfie').val()) {
                exibirToast('Por favor, tire sua selfie!', 'warning');
                return;
            }
            if (!$('#foto_km').val()) {
                exibirToast('Por favor, tire a foto do painel (KM)!', 'warning');
                return;
            }
            
            // Validar checklist obrigatório
            const checklistItems = ['pneus_ok', 'farois_ok', 'documentos_ok', 'limpeza_ok'];
            for (const item of checklistItems) {
                if (!$(`input[name="${item}"]:checked`).val()) {
                    exibirToast('Por favor, preencha todos os itens do checklist de inspeção!', 'warning');
                    return;
                }
            }
            if (!$('select[name="nivel_combustivel"]').val()) {
                exibirToast('Por favor, informe o nível de combustível!', 'warning');
                return;
            }
            
            const btn = $('#btnConfirmar');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processando...');
            
            // Debug: verificar tamanho das fotos
            console.log('[FROTA] Enviando form:');
            console.log('[FROTA] foto_selfie:', $('#foto_selfie').val() ? $('#foto_selfie').val().length + ' chars' : 'VAZIO');
            console.log('[FROTA] foto_km:', $('#foto_km').val() ? $('#foto_km').val().length + ' chars' : 'VAZIO');
            
            const formData = {
                id_veiculo: $('#id_veiculo').val(),
                id_entidade: $('#id_entidade').val(),
                id_departamento: $('#id_departamento').val(),
                km_saida: $('#km_saida').val(),
                destino: $('#destino').val(),
                motivo: $('#motivo').val(),
                observacoes_saida: $('#observacoes_saida').val(),
                foto_selfie: $('#foto_selfie').val(),
                foto_km: $('#foto_km').val(),
                foto_veiculo1: $('#foto_veiculo1').val(),
                foto_veiculo2: $('#foto_veiculo2').val(),
                foto_veiculo3: $('#foto_veiculo3').val(),
                checklist: {
                    pneus_ok: $('input[name="pneus_ok"]:checked').val(),
                    farois_ok: $('input[name="farois_ok"]:checked').val(),
                    documentos_ok: $('input[name="documentos_ok"]:checked').val(),
                    limpeza_ok: $('input[name="limpeza_ok"]:checked').val(),
                    nivel_combustivel: $('select[name="nivel_combustivel"]').val(),
                    avarias_encontradas: $('textarea[name="avarias_encontradas"]').val()
                }
            };
            
            $.ajax({
                url: baseUrl + '/api/frota/registrar_saida.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast('Veículo retirado com sucesso!', 'success');
                        setTimeout(() => window.location.href = baseUrl + '/frota/dashboard.php', 1500);
                    } else {
                        exibirToast('Erro: ' + (data.mensagem || 'Erro desconhecido'), 'danger');
                        btn.prop('disabled', false).html('<i class="bi bi-check-circle me-2"></i>Confirmar Retirada do Veículo');
                    }
                },
                error: function() {
                    exibirToast('Erro ao processar requisição', 'danger');
                    btn.prop('disabled', false).html('<i class="bi bi-check-circle me-2"></i>Confirmar Retirada do Veículo');
                }
            });
        });
    </script>
</body>
</html>


