<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');

// Verificar permissão de admin
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';
MenuPermissaoService::exigirAcesso('gerenciar_mensagens_whatsapp');

require_once __DIR__ . '/../api/conexao.php';

// Buscar tipos de mensagens disponíveis
$tipos = [];
$result = $conn->query("SELECT DISTINCT tipo FROM mensagens_padrao ORDER BY tipo");
while ($row = $result->fetch_assoc()) {
    $tipos[] = $row['tipo'];
}

// Buscar todas as mensagens
$mensagens = [];
$tipo_filtro = $_GET['tipo'] ?? '';
$where = $tipo_filtro ? "WHERE tipo = '" . $conn->real_escape_string($tipo_filtro) . "'" : '';
$result = $conn->query("SELECT * FROM mensagens_padrao $where ORDER BY tipo, id");
while ($row = $result->fetch_assoc()) {
    $mensagens[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Mensagens WhatsApp - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .header-page {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .card-mensagem { 
            transition: all 0.2s; 
            border-left: 4px solid #25D366;
        }
        .card-mensagem:hover { 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
            transform: translateY(-2px);
        }
        .badge-tipo { font-size: 0.75rem; }
        .mensagem-texto {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .placeholder-example {
            color: #6c757d;
            font-size: 0.85rem;
            font-style: italic;
        }
        .btn-whatsapp {
            background: #25D366;
            border-color: #25D366;
            color: white;
        }
        .btn-whatsapp:hover {
            background: #128C7E;
            border-color: #128C7E;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-page">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-whatsapp me-2"></i>Gerenciar Mensagens WhatsApp</h3>
                    <small class="opacity-75">Configure as mensagens variadas enviadas via WhatsApp</small>
                </div>
                <div>
                    <button class="btn btn-light btn-sm" onclick="abrirModalCriar()">
                        <i class="bi bi-plus-circle me-1"></i>Nova Mensagem
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm ms-2">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 pb-5">
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Filtrar por Tipo</label>
                        <select class="form-select" id="filtroTipo" onchange="filtrarPorTipo()">
                            <option value="">Todos os tipos</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?= htmlspecialchars($tipo) ?>" <?= $tipo_filtro === $tipo ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Placeholders disponíveis:</strong> <code>{nome}</code>, <code>{horario_limite}</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Mensagens -->
        <div class="row">
            <?php if (empty($mensagens)): ?>
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Nenhuma mensagem encontrada. 
                        <a href="#" onclick="abrirModalCriar(); return false;" class="alert-link">Criar primeira mensagem</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($mensagens as $msg): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card card-mensagem h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="badge bg-whatsapp badge-tipo"><?= htmlspecialchars($msg['tipo']) ?></span>
                                <div>
                                    <?php if ($msg['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mensagem-texto mb-3"><?= htmlspecialchars($msg['mensagem']) ?></div>
                                <div class="placeholder-example mb-2">
                                    <small>Exemplo: <?= str_replace(['{nome}', '{horario_limite}'], ['João Silva', '09:01'], htmlspecialchars($msg['mensagem'])) ?></small>
                                </div>
                                <div class="text-muted small mb-3">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    Criado: <?= date('d/m/Y H:i', strtotime($msg['criado_em'])) ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <div class="btn-group w-100" role="group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editarMensagem(<?= $msg['id'] ?>)">
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="excluirMensagem(<?= $msg['id'] ?>, '<?= htmlspecialchars(addslashes($msg['tipo'])) ?>')">
                                        <i class="bi bi-trash"></i> Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Criar/Editar -->
    <div class="modal fade" id="modalMensagem" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nova Mensagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formMensagem">
                        <input type="hidden" id="mensagemId" name="id">
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo da Mensagem <span class="text-danger">*</span></label>
                            <select class="form-select" id="mensagemTipo" name="tipo" required>
                                <option value="">Selecione...</option>
                                <option value="lembrete_reserva">Lembrete de Reserva</option>
                                <option value="confirmacao_reserva">Confirmação de Reserva</option>
                                <option value="cancelamento_reserva">Cancelamento de Reserva</option>
                            </select>
                            <small class="text-muted">Ou digite um novo tipo</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mensagem <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="mensagemTexto" name="mensagem" rows="5" required 
                                      placeholder="Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}"></textarea>
                            <small class="text-muted">
                                Use <code>{nome}</code> e <code>{horario_limite}</code> como placeholders
                            </small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="mensagemAtivo" name="ativo" checked>
                                <label class="form-check-label" for="mensagemAtivo">
                                    Mensagem ativa (será usada no sorteio)
                                </label>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <strong>Preview:</strong>
                            <div id="previewMensagem" class="mt-2 p-2 bg-light border rounded">
                                <em class="text-muted">Digite a mensagem para ver o preview</em>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-whatsapp" onclick="salvarMensagem()">
                        <i class="bi bi-check-circle me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('modalMensagem'));
        
        // Atualizar preview em tempo real
        document.getElementById('mensagemTexto').addEventListener('input', atualizarPreview);
        document.getElementById('mensagemTipo').addEventListener('change', function() {
            const tipo = this.value;
            if (tipo && !document.getElementById('mensagemId').value) {
                // Sugerir mensagem padrão baseada no tipo
                if (tipo === 'lembrete_reserva') {
                    document.getElementById('mensagemTexto').value = 'Olá {nome}, você ainda não fez sua reserva de almoço para hoje. Horário limite: {horario_limite}';
                    atualizarPreview();
                }
            }
        });

        function atualizarPreview() {
            const texto = document.getElementById('mensagemTexto').value;
            const preview = document.getElementById('previewMensagem');
            
            if (!texto.trim()) {
                preview.innerHTML = '<em class="text-muted">Digite a mensagem para ver o preview</em>';
                return;
            }
            
            const exemplo = texto
                .replace(/{nome}/g, '<strong>João Silva</strong>')
                .replace(/{horario_limite}/g, '<strong>09:01</strong>');
            
            preview.innerHTML = exemplo;
        }

        function filtrarPorTipo() {
            const tipo = document.getElementById('filtroTipo').value;
            window.location.href = '?tipo=' + encodeURIComponent(tipo);
        }

        function abrirModalCriar() {
            document.getElementById('formMensagem').reset();
            document.getElementById('mensagemId').value = '';
            document.getElementById('modalTitulo').textContent = 'Nova Mensagem';
            document.getElementById('mensagemAtivo').checked = true;
            document.getElementById('previewMensagem').innerHTML = '<em class="text-muted">Digite a mensagem para ver o preview</em>';
            modal.show();
        }

        function editarMensagem(id) {
            fetch(`../api/mensagens_whatsapp/buscar.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        const msg = data.mensagem;
                        document.getElementById('mensagemId').value = msg.id;
                        document.getElementById('mensagemTipo').value = msg.tipo;
                        document.getElementById('mensagemTexto').value = msg.mensagem;
                        document.getElementById('mensagemAtivo').checked = msg.ativo == 1;
                        document.getElementById('modalTitulo').textContent = 'Editar Mensagem';
                        atualizarPreview();
                        modal.show();
                    } else {
                        alert('Erro ao carregar mensagem: ' + data.mensagem);
                    }
                })
                .catch(err => {
                    alert('Erro ao carregar mensagem: ' + err);
                });
        }

        function salvarMensagem() {
            const form = document.getElementById('formMensagem');
            const formData = new FormData(form);
            formData.append('ativo', document.getElementById('mensagemAtivo').checked ? '1' : '0');
            
            const id = document.getElementById('mensagemId').value;
            const url = id ? '../api/mensagens_whatsapp/editar.php' : '../api/mensagens_whatsapp/criar.php';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    alert('Mensagem salva com sucesso!');
                    modal.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + data.mensagem);
                }
            })
            .catch(err => {
                alert('Erro ao salvar: ' + err);
            });
        }

        function excluirMensagem(id, tipo) {
            if (!confirm(`Tem certeza que deseja excluir esta mensagem do tipo "${tipo}"?`)) {
                return;
            }
            
            fetch('../api/mensagens_whatsapp/excluir.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    alert('Mensagem excluída com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.mensagem);
                }
            })
            .catch(err => {
                alert('Erro ao excluir: ' + err);
            });
        }
    </script>
</body>
</html>

