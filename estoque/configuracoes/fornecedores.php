<?php
session_start();
require_once __DIR__ . '/../../auth/verifica_sessao.php';
require_once __DIR__ . '/../../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('estoque_config_fornecedores');

$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Fornecedores - Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(17, 153, 142, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        .table td { vertical-align: middle; }
        .badge-ativo { background: #38a169; }
        .badge-inativo { background: #e53e3e; }
        .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .empty-state { padding: 3rem; text-align: center; color: #718096; }
        .empty-state i { font-size: 4rem; opacity: 0.3; margin-bottom: 1rem; }
        @media (max-width: 768px) { .hide-mobile { display: none !important; } .table td, .table th { font-size: 0.85rem; padding: 0.5rem; } }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-truck me-2"></i>Fornecedores</h5>
                        <small class="opacity-75">Gerenciamento de parceiros</small>
                    </div>
                </div>
                <button class="btn btn-light btn-sm" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-lg me-1"></i><span class="hide-mobile">Novo Fornecedor</span>
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4">
        <div class="card-main">
            <div class="card-header bg-white border-0 py-3 px-4">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="busca" placeholder="Buscar fornecedor...">
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="mostrar-inativos">
                            <label class="form-check-label small" for="mostrar-inativos">Mostrar inativos</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th class="hide-mobile">CNPJ</th>
                            <th class="hide-mobile">Contato</th>
                            <th class="hide-mobile">Telefone</th>
                            <th class="text-center">Status</th>
                            <th style="width: 100px;" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela-fornecedores">
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Cadastro/Edição -->
    <div class="modal fade" id="modalFornecedor" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title" id="modalTitulo"><i class="bi bi-truck me-2"></i>Novo Fornecedor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formFornecedor">
                        <input type="hidden" id="forn_id" name="id">
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Nome/Razão Social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="forn_nome" name="nome" required maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">CNPJ</label>
                                <input type="text" class="form-control" id="forn_cnpj" name="cnpj" maxlength="18" placeholder="00.000.000/0000-00">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Contato</label>
                                <input type="text" class="form-control" id="forn_contato" name="contato" maxlength="100" placeholder="Nome do contato">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="forn_telefone" name="telefone" maxlength="20" placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="forn_email" name="email" maxlength="100">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control" id="forn_endereco" name="endereco" maxlength="255">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="forn_cidade" name="cidade" maxlength="100">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">UF</label>
                                <select class="form-select" id="forn_uf" name="uf">
                                    <option value="">Selecione</option>
                                    <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option>
                                    <option value="AM">AM</option><option value="BA">BA</option><option value="CE">CE</option>
                                    <option value="DF">DF</option><option value="ES">ES</option><option value="GO">GO</option>
                                    <option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                                    <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option>
                                    <option value="PR">PR</option><option value="PE">PE</option><option value="PI">PI</option>
                                    <option value="RJ">RJ</option><option value="RN">RN</option><option value="RS">RS</option>
                                    <option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                                    <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="forn_observacoes" name="observacoes" rows="2"></textarea>
                        </div>
                        
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="forn_ativo" name="ativo" value="1" checked>
                            <label class="form-check-label" for="forn_ativo">Fornecedor ativo</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarFornecedor()">
                        <i class="bi bi-check-lg me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmação Exclusão -->
    <div class="modal fade" id="modalExcluir" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title"><i class="bi bi-trash me-2"></i>Excluir Fornecedor</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p>Deseja realmente excluir o fornecedor <strong id="excluir-nome"></strong>?</p>
                    <small class="text-muted">Se houver dados vinculados, o fornecedor será apenas desativado.</small>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmarExclusao()">
                        <i class="bi bi-trash me-1"></i>Excluir
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
        let fornecedores = [];
        let excluirId = null;
        
        $(document).ready(function() {
            carregarFornecedores();
            $('#busca').on('input', filtrarTabela);
            $('#mostrar-inativos').change(carregarFornecedores);
            
            // Máscara CNPJ
            $('#forn_cnpj').on('input', function() {
                let v = $(this).val().replace(/\D/g, '');
                v = v.replace(/^(\d{2})(\d)/, '$1.$2');
                v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
                v = v.replace(/(\d{4})(\d)/, '$1-$2');
                $(this).val(v.substring(0, 18));
            });
            
            // Máscara Telefone
            $('#forn_telefone').on('input', function() {
                let v = $(this).val().replace(/\D/g, '');
                if (v.length > 10) {
                    v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                } else if (v.length > 6) {
                    v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
                } else if (v.length > 2) {
                    v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
                } else if (v.length > 0) {
                    v = v.replace(/^(\d*)/, '($1');
                }
                $(this).val(v);
            });
        });
        
        function carregarFornecedores() {
            const mostrarInativos = $('#mostrar-inativos').is(':checked');
            
            $.ajax({
                url: baseUrl + '/api/estoque/fornecedores/listar.php',
                data: { todos: mostrarInativos ? 'true' : 'false' },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        fornecedores = data.fornecedores;
                        renderizarTabela();
                    } else {
                        exibirToast(data.mensagem || 'Erro ao carregar fornecedores', 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao carregar fornecedores', 'danger');
                }
            });
        }
        
        function renderizarTabela() {
            const tbody = $('#tabela-fornecedores');
            const busca = $('#busca').val().toLowerCase();
            
            const filtrados = fornecedores.filter(f => 
                f.nome.toLowerCase().includes(busca) || 
                (f.cnpj && f.cnpj.includes(busca)) ||
                (f.contato && f.contato.toLowerCase().includes(busca))
            );
            
            if (filtrados.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-truck"></i>
                                <h5>Nenhum fornecedor encontrado</h5>
                                <p>Clique em "Novo Fornecedor" para criar</p>
                            </div>
                        </td>
                    </tr>
                `);
                return;
            }
            
            let html = '';
            filtrados.forEach(f => {
                html += `
                    <tr class="${!f.ativo ? 'table-secondary' : ''}">
                        <td>
                            <strong>${f.nome}</strong>
                            ${f.email ? `<br><small class="text-muted">${f.email}</small>` : ''}
                        </td>
                        <td class="hide-mobile">${f.cnpj || '-'}</td>
                        <td class="hide-mobile">${f.contato || '-'}</td>
                        <td class="hide-mobile">${f.telefone || '-'}</td>
                        <td class="text-center">
                            <span class="badge ${f.ativo ? 'badge-ativo' : 'badge-inativo'}">
                                ${f.ativo ? 'Ativo' : 'Inativo'}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-outline-primary btn-action me-1" onclick="editarFornecedor(${f.id})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-action" onclick="excluirFornecedor(${f.id}, '${f.nome.replace(/'/g, "\\'")}')" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.html(html);
        }
        
        function filtrarTabela() {
            renderizarTabela();
        }
        
        function abrirModalNovo() {
            $('#formFornecedor')[0].reset();
            $('#forn_id').val('');
            $('#forn_ativo').prop('checked', true);
            $('#modalTitulo').html('<i class="bi bi-truck me-2"></i>Novo Fornecedor');
            new bootstrap.Modal('#modalFornecedor').show();
        }
        
        function editarFornecedor(id) {
            const forn = fornecedores.find(f => f.id === id);
            if (!forn) return;
            
            $('#forn_id').val(forn.id);
            $('#forn_nome').val(forn.nome);
            $('#forn_cnpj').val(forn.cnpj);
            $('#forn_contato').val(forn.contato);
            $('#forn_telefone').val(forn.telefone);
            $('#forn_email').val(forn.email);
            $('#forn_endereco').val(forn.endereco);
            $('#forn_cidade').val(forn.cidade);
            $('#forn_uf').val(forn.uf);
            $('#forn_observacoes').val(forn.observacoes);
            $('#forn_ativo').prop('checked', forn.ativo);
            
            $('#modalTitulo').html('<i class="bi bi-pencil me-2"></i>Editar Fornecedor');
            new bootstrap.Modal('#modalFornecedor').show();
        }
        
        function salvarFornecedor() {
            const nome = $('#forn_nome').val().trim();
            
            if (!nome) {
                exibirToast('Nome é obrigatório', 'warning');
                return;
            }
            
            const dados = {
                id: $('#forn_id').val(),
                nome: nome,
                cnpj: $('#forn_cnpj').val().trim(),
                contato: $('#forn_contato').val().trim(),
                telefone: $('#forn_telefone').val().trim(),
                email: $('#forn_email').val().trim(),
                endereco: $('#forn_endereco').val().trim(),
                cidade: $('#forn_cidade').val().trim(),
                uf: $('#forn_uf').val(),
                observacoes: $('#forn_observacoes').val().trim(),
                ativo: $('#forn_ativo').is(':checked') ? 1 : 0
            };
            
            $.ajax({
                url: baseUrl + '/api/estoque/fornecedores/salvar.php',
                method: 'POST',
                data: dados,
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'ok') {
                        bootstrap.Modal.getInstance('#modalFornecedor').hide();
                        carregarFornecedores();
                        exibirToast(data.mensagem, 'success');
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao salvar fornecedor', 'danger');
                }
            });
        }
        
        function excluirFornecedor(id, nome) {
            excluirId = id;
            $('#excluir-nome').text(nome);
            new bootstrap.Modal('#modalExcluir').show();
        }
        
        function confirmarExclusao() {
            if (!excluirId) return;
            
            $.ajax({
                url: baseUrl + '/api/estoque/fornecedores/excluir.php',
                method: 'POST',
                data: { id: excluirId },
                dataType: 'json',
                success: function(data) {
                    bootstrap.Modal.getInstance('#modalExcluir').hide();
                    if (data.status === 'ok') {
                        carregarFornecedores();
                        exibirToast(data.mensagem, 'success');
                    } else {
                        exibirToast(data.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao excluir fornecedor', 'danger');
                }
            });
        }
    </script>
</body>
</html>



