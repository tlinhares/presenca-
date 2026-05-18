<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_importar_xml');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$isAdmin = isset($_SESSION['usuario_categoria']) && $_SESSION['usuario_categoria'] === 'admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Importar XML NF - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(79, 172, 254, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .upload-zone { border: 3px dashed #dee2e6; border-radius: 16px; padding: 3rem; text-align: center; transition: all 0.3s; cursor: pointer; }
        .upload-zone:hover, .upload-zone.dragover { border-color: #4facfe; background: rgba(79, 172, 254, 0.05); }
        .upload-zone i { font-size: 4rem; color: #4facfe; }
        .produto-item { background: #f8f9fa; border-radius: 10px; padding: 1rem; margin-bottom: 0.75rem; }
        .produto-item.novo { border-left: 4px solid #48bb78; }
        .produto-item.existente { border-left: 4px solid #4299e1; }
        @media (max-width: 768px) { .hide-mobile { display: none !important; } }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-file-earmark-code me-2"></i>Importar XML</h5>
                        <small class="opacity-75">Entrada via Nota Fiscal</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Upload Zone -->
                <div class="card-main p-4 mb-4">
                    <div class="upload-zone" id="uploadZone" onclick="$('#inputXml').click()">
                        <i class="bi bi-file-earmark-code d-block mb-3"></i>
                        <h5>Arraste o arquivo XML aqui</h5>
                        <p class="text-muted mb-0">ou clique para selecionar</p>
                        <input type="file" id="inputXml" accept=".xml" class="d-none">
                    </div>
                </div>

                <!-- Informações da NF -->
                <div class="card-main p-4 mb-4" id="infoNf" style="display: none;">
                    <h5 class="mb-3"><i class="bi bi-receipt me-2"></i>Dados da Nota Fiscal</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Número</label>
                            <div class="fw-bold" id="nf-numero">-</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Série</label>
                            <div class="fw-bold" id="nf-serie">-</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Data Emissão</label>
                            <div class="fw-bold" id="nf-data">-</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Valor Total</label>
                            <div class="fw-bold text-success" id="nf-valor">-</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted">Fornecedor</label>
                            <div class="fw-bold" id="nf-fornecedor">-</div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label class="form-label">Departamento de Destino <span class="text-danger">*</span></label>
                        <select class="form-select" id="departamento-destino" required>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>

                <!-- Produtos -->
                <div class="card-main p-4 mb-4" id="listaProdutos" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Produtos</h5>
                        <span class="badge bg-primary" id="total-produtos">0 produtos</span>
                    </div>
                    <div id="produtos-container"></div>
                </div>

                <!-- Botão de Confirmar -->
                <div class="text-center" id="btnContainer" style="display: none;">
                    <button class="btn btn-success btn-lg px-5" onclick="confirmarImportacao()">
                        <i class="bi bi-check-lg me-2"></i>Confirmar Importação
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        let dadosNf = null;
        
        $(document).ready(function() {
            carregarDepartamentos();
            
            const inputXml = document.getElementById('inputXml');
            const zone = document.getElementById('uploadZone');
            
            // Clique na zona de upload
            zone.addEventListener('click', function(e) {
                // Evitar que o clique no input dispare novamente
                if (e.target !== inputXml) {
                    inputXml.click();
                }
            });
            
            // Drag and drop
            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('dragover');
            });
            
            zone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
            });
            
            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
                
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    const file = e.dataTransfer.files[0];
                    if (file.name.toLowerCase().endsWith('.xml')) {
                        processarXml(file);
                    } else {
                        exibirToast('Selecione um arquivo XML válido', 'warning');
                    }
                }
            });
            
            // Evento change do input file
            $(inputXml).on('change', function(e) {
                const file = this.files[0];
                if (file) {
                    if (file.name.toLowerCase().endsWith('.xml')) {
                        processarXml(file);
                        // Limpar o input para permitir selecionar o mesmo arquivo novamente
                        $(this).val('');
                    } else {
                        exibirToast('Selecione um arquivo XML válido', 'warning');
                        $(this).val('');
                    }
                }
            });
        });
        
        function carregarDepartamentos() {
            $.getJSON(baseUrl + '/api/estoque/departamentos/listar.php', function(data) {
                if (data.status === 'ok') {
                    let html = '<option value="">Selecione...</option>';
                    data.departamentos.forEach(d => html += `<option value="${d.id}">${d.nome}</option>`);
                    $('#departamento-destino').html(html);
                }
            });
        }
        
        function processarXml(file) {
            if (!file) {
                exibirToast('Nenhum arquivo selecionado', 'warning');
                return;
            }
            
            if (!file.name.toLowerCase().endsWith('.xml')) {
                exibirToast('Selecione um arquivo XML válido', 'warning');
                return;
            }
            
            // Mostrar loading
            exibirToast('Processando XML...', 'info');
            
            const formData = new FormData();
            formData.append('xml', file);
            
            $.ajax({
                url: baseUrl + '/api/estoque/nf/processar_xml.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        dadosNf = data;
                        exibirDadosNf(data);
                        exibirToast('XML processado com sucesso!', 'success');
                    } else {
                        exibirToast(data.mensagem || 'Erro ao processar XML', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao processar XML:', error);
                    let mensagem = 'Erro ao enviar arquivo';
                    if (xhr.responseJSON && xhr.responseJSON.mensagem) {
                        mensagem = xhr.responseJSON.mensagem;
                    }
                    exibirToast(mensagem, 'danger');
                }
            });
        }
        
        function exibirDadosNf(data) {
            $('#nf-numero').text(data.nf.numero);
            $('#nf-serie').text(data.nf.serie);
            $('#nf-data').text(data.nf.data_emissao);
            $('#nf-valor').text('R$ ' + parseFloat(data.nf.valor_total).toFixed(2).replace('.', ','));
            $('#nf-fornecedor').text(data.fornecedor.nome + (data.fornecedor.cnpj ? ' - ' + data.fornecedor.cnpj : ''));
            
            let html = '';
            data.produtos.forEach((p, i) => {
                html += `
                    <div class="produto-item ${p.existente ? 'existente' : 'novo'}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${p.nome}</strong>
                                <span class="badge ${p.existente ? 'bg-info' : 'bg-success'} ms-2">
                                    ${p.existente ? 'Existente' : 'Novo'}
                                </span>
                                <div class="small text-muted">${p.codigo || 'Sem código'}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">${parseFloat(p.quantidade).toFixed(2)} ${p.unidade}</div>
                                <div class="small text-muted">R$ ${parseFloat(p.valor_unitario).toFixed(2)}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#produtos-container').html(html);
            $('#total-produtos').text(data.produtos.length + ' produtos');
            
            $('#infoNf, #listaProdutos, #btnContainer').show();
        }
        
        function confirmarImportacao() {
            const departamento = $('#departamento-destino').val();
            if (!departamento) {
                exibirToast('Selecione o departamento de destino', 'warning');
                return;
            }
            
            $.ajax({
                url: baseUrl + '/api/estoque/nf/importar.php',
                type: 'POST',
                data: {
                    dados: JSON.stringify(dadosNf),
                    departamento: departamento
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        exibirToast(data.mensagem, 'success');
                        setTimeout(() => window.location.href = 'index.php', 2000);
                    } else {
                        exibirToast(data.mensagem || 'Erro ao importar', 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao importar NF', 'danger');
                }
            });
        }
    </script>
</body>
</html>

