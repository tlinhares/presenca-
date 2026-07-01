<?php
session_start();
require_once __DIR__ . '/../auth/verifica_sessao.php';
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';

if (empty($_SESSION['usuario_categoria']) || $_SESSION['usuario_categoria'] !== 'admin') {
    header('Location: ../resumo.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Notificação Push - Intranet AOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .header-page { background: var(--primary-gradient); color: white; padding: 1rem 0; box-shadow: 0 4px 20px rgba(255, 107, 107, 0.4); position: sticky; top: 0; z-index: 1000; }
        .card-main { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .form-label { font-weight: 600; color: #2d3748; }
        .help { font-size: .82rem; color: #718096; }
        textarea.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; }

        .tipo-btn {
            display: flex; align-items: center; gap: .5rem;
            padding: .7rem 1rem;
            border: 2px solid #e2e8f0;
            background: #fff; color: #4a5568;
            border-radius: 10px;
            cursor: pointer;
            transition: all .15s;
            font-weight: 600;
        }
        .tipo-btn:hover { border-color: #ee5a6f; color: #ee5a6f; }
        .tipo-btn.active { background: #ee5a6f; color: #fff; border-color: #ee5a6f; }
        .tipo-btn i { font-size: 1.1rem; }

        .destinatarios-chip {
            display: inline-flex; align-items: center; gap: .35rem;
            background: #ebf4ff; color: #2c5282;
            padding: 4px 10px 4px 12px;
            border-radius: 20px;
            font-size: .85rem;
            margin: 3px;
        }
        .destinatarios-chip button {
            background: transparent; border: none; color: #2c5282;
            padding: 0 4px; margin-left: 4px; font-size: 1rem; line-height: 1;
            cursor: pointer;
        }

        .sug-item {
            padding: .5rem .75rem;
            cursor: pointer;
            border-bottom: 1px solid #edf2f7;
        }
        .sug-item:hover { background: #f7fafc; }
        .sug-item .nome { font-weight: 600; color: #2d3748; }
        .sug-item .email { font-size: .8rem; color: #718096; }
        .sug-item .disp { font-size: .75rem; color: #38a169; }

        .agend-row { border-left: 3px solid #cbd5e0; padding: .6rem .85rem; background: #f7fafc; border-radius: 6px; margin-bottom: .5rem; }
        .agend-row.pendente { border-left-color: #ed8936; background: #fffaf0; }
        .agend-row.concluido { border-left-color: #48bb78; }
        .agend-row.falha { border-left-color: #e53e3e; background: #fff5f5; }
        .agend-row.cancelado { border-left-color: #a0aec0; }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <a href="../resumo.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="mb-0"><i class="bi bi-send-fill me-2"></i>Enviar Notificação Push</h5>
                        <small class="opacity-75">Manual — Intranet AOM (iOS + Android)</small>
                    </div>
                </div>
                <a href="notificacoes_push.php" class="btn btn-light btn-sm">
                    <i class="bi bi-gear me-1"></i>Configuração Firebase
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 py-4" style="max-width: 960px;">

        <div class="card-main p-4 mb-4">
            <h5 class="mb-3"><i class="bi bi-1-square me-2"></i>Destinatário</h5>

            <div class="d-flex gap-2 mb-3 flex-wrap">
                <div class="tipo-btn active" data-tipo="usuario" onclick="setTipo('usuario')">
                    <i class="bi bi-person"></i>Um usuário
                </div>
                <div class="tipo-btn" data-tipo="varios" onclick="setTipo('varios')">
                    <i class="bi bi-people"></i>Vários usuários
                </div>
                <div class="tipo-btn" data-tipo="todos" onclick="setTipo('todos')">
                    <i class="bi bi-broadcast"></i>Todos com o app
                </div>
            </div>

            <div id="bloco-usuarios">
                <label class="form-label">Buscar usuário (nome, e-mail ou CPF)</label>
                <div class="position-relative">
                    <input type="text" class="form-control" id="busca-usuario" placeholder="Digite ao menos 2 caracteres…" autocomplete="off">
                    <div class="border rounded mt-1 bg-white shadow-sm" id="sugestoes" style="max-height: 260px; overflow: auto; display:none; position:absolute; left:0; right:0; z-index:100;"></div>
                </div>
                <div class="mt-2" id="chips-destinatarios"></div>
                <small class="help d-block mt-2">Só usuários com o app instalado e autenticado aparecem na busca.</small>
            </div>

            <div id="bloco-todos" class="alert alert-info mb-0" style="display:none;">
                <i class="bi bi-broadcast me-1"></i>
                A notificação será enviada para <strong><span id="total-alcancaveis">–</span> usuário(s)</strong> que têm o aplicativo instalado e ativo agora.
            </div>
        </div>

        <div class="card-main p-4 mb-4">
            <h5 class="mb-3"><i class="bi bi-2-square me-2"></i>Conteúdo</h5>

            <div class="mb-3">
                <label class="form-label" for="titulo">Título</label>
                <input type="text" class="form-control" id="titulo" maxlength="120" placeholder="Ex.: Aviso importante">
                <small class="help">Aparece em destaque na notificação (máx. 120).</small>
            </div>

            <div class="mb-3">
                <label class="form-label" for="corpo">Mensagem</label>
                <textarea class="form-control" id="corpo" rows="3" maxlength="500" placeholder="Escreva a mensagem que o usuário vai receber…"></textarea>
                <small class="help">Máx. 500 caracteres.</small>
            </div>

            <details class="mb-1">
                <summary class="form-label mb-2" style="cursor:pointer;">
                    <i class="bi bi-code-slash me-1"></i>Dados customizados (opcional)
                </summary>
                <textarea class="form-control mono" id="dados_json" rows="3" placeholder='{"rota":"almoco","destaque":"true"}'>{"tipo":"manual"}</textarea>
                <small class="help">JSON puro com string em todos os valores. Usado pelo app para roteamento (ex.: abrir tela específica). Padrão: <code>{"tipo":"manual"}</code>.</small>
            </details>
        </div>

        <div class="card-main p-4 mb-4">
            <h5 class="mb-3"><i class="bi bi-3-square me-2"></i>Quando enviar</h5>

            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <button type="button" class="btn btn-danger w-100 py-3" id="btnEnviarAgora" style="background:#ee5a6f; border:none;">
                        <i class="bi bi-lightning-charge-fill me-1"></i>Enviar agora
                    </button>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="agendado_para">
                        <i class="bi bi-clock me-1"></i>OU agendar para:
                    </label>
                    <div class="d-flex gap-2">
                        <input type="datetime-local" class="form-control" id="agendado_para">
                        <button type="button" class="btn btn-outline-primary" id="btnAgendar">
                            <i class="bi bi-calendar-check me-1"></i>Agendar
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-3" id="resultado"></div>
        </div>

        <div class="card-main p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Agendamentos</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" data-filtro="pendente" onclick="filtroAgend('pendente')">Pendentes</button>
                    <button type="button" class="btn btn-outline-secondary" data-filtro="concluido" onclick="filtroAgend('concluido')">Histórico</button>
                </div>
            </div>
            <div id="lista-agendamentos"><em class="text-muted">Carregando…</em></div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        let tipoAtual = 'usuario';
        let destinatarios = [];   // array de {id, nome, email}
        let filtroAgendamento = 'pendente';
        let buscaTimeout = null;

        function setTipo(t) {
            tipoAtual = t;
            $('.tipo-btn').removeClass('active');
            $(`.tipo-btn[data-tipo="${t}"]`).addClass('active');
            if (t === 'todos') {
                $('#bloco-usuarios').hide();
                $('#bloco-todos').show();
                $.getJSON(baseUrl + '/api/painel/push_envios/buscar_usuarios.php', function(data) {
                    if (data.status === 'ok') $('#total-alcancaveis').text(data.total_alcancaveis);
                });
            } else {
                $('#bloco-usuarios').show();
                $('#bloco-todos').hide();
                // "usuario" mantém apenas 1 na lista, "varios" permite múltiplos
                if (t === 'usuario' && destinatarios.length > 1) {
                    destinatarios = destinatarios.slice(0, 1);
                    renderChips();
                }
            }
        }

        function renderChips() {
            const box = $('#chips-destinatarios').empty();
            destinatarios.forEach(function(u) {
                box.append(`<span class="destinatarios-chip">
                    ${$('<div/>').text(u.nome).html()}
                    <button type="button" onclick="removerDestinatario(${u.id})">×</button>
                </span>`);
            });
        }
        window.removerDestinatario = function(id) {
            destinatarios = destinatarios.filter(u => u.id !== id);
            renderChips();
        };

        $('#busca-usuario').on('input', function() {
            clearTimeout(buscaTimeout);
            const q = $(this).val().trim();
            if (q.length < 2) { $('#sugestoes').hide().empty(); return; }
            buscaTimeout = setTimeout(function() {
                $.getJSON(baseUrl + '/api/painel/push_envios/buscar_usuarios.php', { q: q }, function(data) {
                    if (data.status !== 'ok') return;
                    const box = $('#sugestoes').empty();
                    if (data.usuarios.length === 0) {
                        box.append('<div class="sug-item text-muted"><em>Nenhum usuário com dispositivo push encontrado</em></div>');
                    } else {
                        data.usuarios.forEach(function(u) {
                            let dispHtml;
                            if (u.dispositivos_ativos > 0) {
                                dispHtml = `<div class="disp"><i class="bi bi-phone"></i> ${u.dispositivos_ativos} dispositivo(s) ativo(s)</div>`;
                            } else if (u.dispositivos_historicos > 0) {
                                dispHtml = `<div class="disp" style="color:#dd6b20;"><i class="bi bi-exclamation-triangle"></i> App instalado mas token expirou — peça pra abrir o app</div>`;
                            } else {
                                dispHtml = `<div class="disp" style="color:#a0aec0;"><i class="bi bi-x-circle"></i> Sem app instalado</div>`;
                            }
                            const item = $(`<div class="sug-item">
                                <div class="nome">${$('<div/>').text(u.nome).html()}</div>
                                <div class="email">${$('<div/>').text(u.email || '').html()}</div>
                                ${dispHtml}
                            </div>`);
                            item.on('click', function() {
                                if (destinatarios.find(x => x.id === u.id)) return;
                                if (tipoAtual === 'usuario') destinatarios = [];
                                destinatarios.push({ id: u.id, nome: u.nome, email: u.email });
                                renderChips();
                                $('#busca-usuario').val('');
                                $('#sugestoes').hide().empty();
                            });
                            box.append(item);
                        });
                    }
                    box.show();
                });
            }, 250);
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#busca-usuario, #sugestoes').length) $('#sugestoes').hide();
        });

        function coletarPayload() {
            const titulo = $('#titulo').val().trim();
            const corpo  = $('#corpo').val().trim();
            let dados = {};
            const raw = $('#dados_json').val().trim();
            if (raw) {
                try {
                    dados = JSON.parse(raw);
                    if (typeof dados !== 'object' || Array.isArray(dados) || dados === null) throw new Error('deve ser objeto');
                } catch (e) {
                    exibirToast('Dados customizados: JSON inválido — ' + e.message, 'warning');
                    return null;
                }
            }
            if (!titulo || !corpo) { exibirToast('Preencha título e mensagem', 'warning'); return null; }
            if (tipoAtual !== 'todos' && destinatarios.length === 0) {
                exibirToast('Selecione ao menos um usuário destinatário', 'warning'); return null;
            }
            return {
                titulo: titulo,
                corpo: corpo,
                dados: dados,
                destinatarios_tipo: tipoAtual,
                ids: destinatarios.map(u => u.id),
            };
        }

        $('#btnEnviarAgora').on('click', function() {
            const p = coletarPayload();
            if (!p) return;
            const alvo = (p.destinatarios_tipo === 'todos') ? 'TODOS os usuários com o app' : (p.ids.length + ' usuário(s)');
            if (!confirm(`Enviar push AGORA para ${alvo}?`)) return;
            $('#btnEnviarAgora').prop('disabled', true);
            $('#resultado').html('<div class="text-muted"><i class="bi bi-hourglass-split"></i> Enviando…</div>');
            $.ajax({
                url: baseUrl + '/api/painel/push_envios/enviar_agora.php',
                type: 'POST', contentType: 'application/json',
                data: JSON.stringify(p), dataType: 'json',
                success: function(res) {
                    if (res.status === 'ok') {
                        const r = res.resumo;
                        const cls = r.falhas > 0 ? 'alert-warning' : 'alert-success';
                        $('#resultado').html(`<div class="alert ${cls} mb-0">
                            <strong>${res.mensagem}</strong><br>
                            Alvo: ${r.usuarios_alvo} · Entregues: ${r.enviados} · Falhas: ${r.falhas} · Sem dispositivo: ${r.sem_dispositivo}
                        </div>`);
                        exibirToast('Push enviado', 'success');
                    } else {
                        $('#resultado').html(`<div class="alert alert-danger mb-0">${res.mensagem}</div>`);
                    }
                },
                error: function() { $('#resultado').html('<div class="alert alert-danger mb-0">Erro de comunicação</div>'); },
                complete: function() { $('#btnEnviarAgora').prop('disabled', false); }
            });
        });

        $('#btnAgendar').on('click', function() {
            const p = coletarPayload();
            if (!p) return;
            const dt = $('#agendado_para').val();
            if (!dt) { exibirToast('Informe a data/hora do agendamento', 'warning'); return; }
            p.agendado_para = dt;
            $('#btnAgendar').prop('disabled', true);
            $.ajax({
                url: baseUrl + '/api/painel/push_envios/agendar.php',
                type: 'POST', contentType: 'application/json',
                data: JSON.stringify(p), dataType: 'json',
                success: function(res) {
                    if (res.status === 'ok') {
                        exibirToast(res.mensagem, 'success');
                        $('#resultado').html(`<div class="alert alert-info mb-0"><i class="bi bi-calendar-check me-1"></i>${res.mensagem}</div>`);
                        carregarAgendamentos();
                    } else {
                        exibirToast(res.mensagem || 'Erro ao agendar', 'danger');
                    }
                },
                error: function() { exibirToast('Erro de comunicação ao agendar', 'danger'); },
                complete: function() { $('#btnAgendar').prop('disabled', false); }
            });
        });

        function filtroAgend(f) {
            filtroAgendamento = f;
            $('[data-filtro]').removeClass('active');
            $(`[data-filtro="${f}"]`).addClass('active');
            carregarAgendamentos();
        }

        function carregarAgendamentos() {
            $.getJSON(baseUrl + '/api/painel/push_envios/listar_agendamentos.php', { status: filtroAgendamento }, function(data) {
                if (data.status !== 'ok') { $('#lista-agendamentos').html('<em class="text-muted">Erro ao carregar</em>'); return; }
                if (data.agendamentos.length === 0) {
                    $('#lista-agendamentos').html('<em class="text-muted">Nenhum agendamento ' + (filtroAgendamento === 'pendente' ? 'pendente' : 'no histórico') + '.</em>');
                    return;
                }
                const box = $('#lista-agendamentos').empty();
                data.agendamentos.forEach(function(a) {
                    const dt = new Date(a.agendado_para.replace(' ', 'T'));
                    const dtFmt = dt.toLocaleString('pt-BR');
                    const acao = (a.status === 'pendente') ?
                        `<button class="btn btn-sm btn-outline-danger" onclick="cancelarAgend(${a.id})"><i class="bi bi-x-lg"></i> Cancelar</button>` :
                        `<span class="badge bg-secondary">${a.status.toUpperCase()}</span>`;
                    let resultado = '';
                    if (a.resultado) {
                        resultado = `<div class="small text-muted mt-1">Executado ${a.executado_em || ''} — enviados ${a.resultado.enviados || 0}, falhas ${a.resultado.falhas || 0}</div>`;
                    }
                    box.append(`
                        <div class="agend-row ${a.status}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <div class="fw-bold">${$('<div/>').text(a.titulo).html()}</div>
                                    <div class="text-muted small">${$('<div/>').text(a.corpo).html()}</div>
                                    <div class="small mt-1">
                                        <i class="bi bi-calendar-event me-1"></i>${dtFmt} ·
                                        <i class="bi bi-people me-1"></i>${a.destinatarios_resumo} ·
                                        criado por ${$('<div/>').text(a.criado_por_nome || '—').html()}
                                    </div>
                                    ${resultado}
                                </div>
                                <div>${acao}</div>
                            </div>
                        </div>
                    `);
                });
            });
        }

        window.cancelarAgend = function(id) {
            if (!confirm('Cancelar este agendamento?')) return;
            $.ajax({
                url: baseUrl + '/api/painel/push_envios/cancelar_agendamento.php',
                type: 'POST', contentType: 'application/json',
                data: JSON.stringify({ id: id }), dataType: 'json',
                success: function(res) {
                    if (res.status === 'ok') { exibirToast(res.mensagem, 'success'); carregarAgendamentos(); }
                    else exibirToast(res.mensagem, 'danger');
                },
                error: function() { exibirToast('Erro de comunicação', 'danger'); }
            });
        };

        // pré-preenche datetime com "agora + 15 min" para conveniência
        const dt = new Date(Date.now() + 15 * 60000);
        const pad = n => String(n).padStart(2, '0');
        $('#agendado_para').val(
            `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`
        );

        carregarAgendamentos();
        setInterval(carregarAgendamentos, 30000);   // refresh a cada 30s
    </script>
</body>
</html>
