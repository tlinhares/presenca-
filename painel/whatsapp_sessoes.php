<?php
session_start();
require_once __DIR__ . '/../auth/verifica_sessao.php';
require_once __DIR__ . '/../core/services/MenuPermissaoService.php';

MenuPermissaoService::exigirAcesso('gerenciar_whatsapp_sessoes');

require_once __DIR__ . '/../api/conexao.php';

$result = $conn->query(
    "SELECT id, nome, base_url, session_name, numero_whatsapp, ativo
       FROM whatsapp_apis
       WHERE base_url IS NOT NULL AND session_name IS NOT NULL
       ORDER BY ativo DESC, prioridade ASC, nome ASC"
);
$apis = [];
while ($row = $result->fetch_assoc()) {
    $apis[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sessões WhatsApp - Sistema de Presença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= MenuPermissaoService::ajustarUrl('/css/feedback-system.css') ?>" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .header-page {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(37, 211, 102, 0.25);
        }
        .session-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 6px solid #cbd5e0;
            transition: border-color 0.3s;
        }
        .session-card.state-connected   { border-left-color: #38a169; }
        .session-card.state-qrcode      { border-left-color: #ecc94b; }
        .session-card.state-initializing,
        .session-card.state-starting,
        .session-card.state-opening,
        .session-card.state-pairing     { border-left-color: #3182ce; }
        .session-card.state-conflict    { border-left-color: #e53e3e; }
        .session-card.state-closed,
        .session-card.state-not_logged,
        .session-card.state-unpaired,
        .session-card.state-unpaired_idle { border-left-color: #a0aec0; }
        .state-badge {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            font-weight: 600;
        }
        .qr-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            border: 2px dashed #cbd5e0;
        }
        .qr-img { max-width: 280px; width: 100%; height: auto; border-radius: 8px; }
        .meta { color: #718096; font-size: 0.85rem; }
        .api-name { font-weight: 700; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="header-page">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1"><i class="bi bi-whatsapp me-2"></i>Sessões WhatsApp</h3>
                    <small class="opacity-75">Status, reconexão e QR code</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="whatsapp_apis.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-gear me-1"></i>Gerenciar APIs
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 pb-5">
        <?php if (empty($apis)): ?>
            <div class="alert alert-warning">
                Nenhuma sessão configurada. Cadastre uma API em
                <a href="whatsapp_apis.php">Gerenciar APIs</a>.
            </div>
        <?php else: ?>
        <div class="row">
        <?php foreach ($apis as $api): ?>
            <div class="col-lg-6">
                <div class="session-card" data-api-id="<?= (int)$api['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="api-name"><?= htmlspecialchars($api['nome']) ?></span>
                            <?php if (!$api['ativo']): ?>
                                <span class="badge bg-secondary ms-2">inativa</span>
                            <?php endif; ?>
                            <div class="meta mt-1">
                                <code><?= htmlspecialchars($api['session_name']) ?></code>
                                <span class="ms-2"><?= htmlspecialchars($api['base_url']) ?></span>
                            </div>
                            <?php if ($api['numero_whatsapp']): ?>
                                <div class="meta mt-1"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($api['numero_whatsapp']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span data-role="badge" class="state-badge bg-secondary text-white">verificando…</span>
                    </div>

                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <button class="btn btn-sm btn-success" data-role="btn-start">
                            <i class="bi bi-play-fill me-1"></i>Iniciar / Reconectar
                        </button>
                        <button class="btn btn-sm btn-outline-warning" data-role="btn-logout">
                            <i class="bi bi-box-arrow-right me-1"></i>Desconectar
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" data-role="btn-refresh" title="Atualizar agora">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>

                    <div data-role="qr-wrap" class="qr-box" style="display:none">
                        <div class="mb-2 small text-muted">Abra o WhatsApp &gt; <b>Aparelhos conectados</b> &gt; <b>Conectar aparelho</b></div>
                        <img data-role="qr-img" class="qr-img" alt="QR code">
                        <div class="meta mt-2">O QR expira em ~30s. Atualiza automaticamente.</div>
                    </div>

                    <div data-role="error" class="alert alert-danger small mt-2" style="display:none"></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?= MenuPermissaoService::ajustarUrl('/js/feedback-system.js') ?>"></script>
    <script>
    (function () {
        const baseUrl = '<?= MenuPermissaoService::getBaseUrl() ?>';
        const POLL_MS = 3000;
        const QR_STATES = ['QRCODE', 'NOT_LOGGED', 'UNPAIRED', 'UNPAIRED_IDLE'];
        const LABELS = {
            CONNECTED:      { txt: 'Conectado',         cls: 'bg-success', state: 'connected'   },
            QRCODE:         { txt: 'Aguardando QR',     cls: 'bg-warning text-dark', state: 'qrcode' },
            INITIALIZING:   { txt: 'Inicializando…',    cls: 'bg-info text-dark', state: 'initializing' },
            STARTING:       { txt: 'Iniciando…',        cls: 'bg-info text-dark', state: 'starting' },
            OPENING:        { txt: 'Abrindo…',          cls: 'bg-info text-dark', state: 'opening' },
            PAIRING:        { txt: 'Pareando…',         cls: 'bg-info text-dark', state: 'pairing' },
            NOT_LOGGED:     { txt: 'Não conectado',     cls: 'bg-secondary', state: 'not_logged' },
            CLOSED:         { txt: 'Fechada',           cls: 'bg-secondary', state: 'closed' },
            DESTROYED:      { txt: 'Destruída',         cls: 'bg-dark', state: 'destroyed' },
            CONFLICT:       { txt: 'Conflito',          cls: 'bg-danger', state: 'conflict' },
            UNPAIRED:       { txt: 'Desemparelhado',    cls: 'bg-warning text-dark', state: 'unpaired' },
            UNPAIRED_IDLE:  { txt: 'Aguardando',        cls: 'bg-warning text-dark', state: 'unpaired_idle' },
            UNKNOWN:        { txt: 'Desconhecido',      cls: 'bg-secondary', state: 'unknown' },
        };

        document.querySelectorAll('.session-card').forEach((card) => {
            const id     = card.dataset.apiId;
            const badge  = card.querySelector('[data-role=badge]');
            const qrWrap = card.querySelector('[data-role=qr-wrap]');
            const qrImg  = card.querySelector('[data-role=qr-img]');
            const errBox = card.querySelector('[data-role=error]');
            const btnStart   = card.querySelector('[data-role=btn-start]');
            const btnLogout  = card.querySelector('[data-role=btn-logout]');
            const btnRefresh = card.querySelector('[data-role=btn-refresh]');

            function setBadge(state) {
                const m = LABELS[state] || LABELS.UNKNOWN;
                badge.className = 'state-badge text-white ' + m.cls;
                badge.textContent = m.txt;
                // limpa todas as classes de estado antes
                card.classList.forEach(c => { if (c.indexOf('state-') === 0) card.classList.remove(c); });
                card.classList.add('state-' + m.state);
            }

            function showErr(msg) {
                if (!msg) { errBox.style.display = 'none'; return; }
                errBox.textContent = msg;
                errBox.style.display = 'block';
            }

            async function tick() {
                try {
                    const r = await fetch(baseUrl + '/api/whatsapp_apis/sessao/status.php?id=' + id, {
                        credentials: 'same-origin'
                    });
                    const data = await r.json();
                    if (!data.ok && !data.state) {
                        setBadge('UNKNOWN');
                        showErr(data.error || 'erro ao consultar status');
                        return;
                    }
                    showErr(null);
                    const state = (data.state || 'UNKNOWN').toUpperCase();
                    setBadge(state);
                    if (QR_STATES.includes(state)) {
                        qrWrap.style.display = 'block';
                        qrImg.src = baseUrl + '/api/whatsapp_apis/sessao/qr.php?id=' + id + '&ts=' + Date.now();
                    } else {
                        qrWrap.style.display = 'none';
                    }
                } catch (e) {
                    setBadge('UNKNOWN');
                    showErr('rede: ' + e.message);
                } finally {
                    setTimeout(tick, POLL_MS);
                }
            }

            async function callJson(url, payload) {
                const r = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload || {})
                });
                return r.json();
            }

            btnStart.addEventListener('click', async () => {
                btnStart.disabled = true;
                const old = btnStart.innerHTML;
                btnStart.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Iniciando…';
                try {
                    const r = await callJson(baseUrl + '/api/whatsapp_apis/sessao/start.php', { id: id });
                    if (!r.ok) showErr(r.error || 'falha ao iniciar');
                    else showErr(null);
                } catch (e) {
                    showErr('rede: ' + e.message);
                } finally {
                    btnStart.disabled = false;
                    btnStart.innerHTML = old;
                }
            });

            btnLogout.addEventListener('click', async () => {
                if (!confirm('Desconectar esta sessão? (Vai precisar de QR para reconectar)')) return;
                btnLogout.disabled = true;
                const old = btnLogout.innerHTML;
                btnLogout.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Desconectando…';
                try {
                    const r = await callJson(baseUrl + '/api/whatsapp_apis/sessao/logout.php', { id: id });
                    if (!r.ok) showErr(r.error || 'falha ao desconectar');
                    else showErr(null);
                } catch (e) {
                    showErr('rede: ' + e.message);
                } finally {
                    btnLogout.disabled = false;
                    btnLogout.innerHTML = old;
                }
            });

            btnRefresh.addEventListener('click', tick);

            tick();
        });
    })();
    </script>
</body>
</html>
