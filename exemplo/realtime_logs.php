<?php
// realtime_logs.php — visualizador organizado (Eventos/Diagnóstico)
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Logs do Dispositivo — Tempo Real</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 1rem; }
    .sticky { position: sticky; top: 0; background: #fff; z-index: 10; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    #evtTable, #diagTable { font-size: .95rem; }
    .smallnote { font-size:.85rem; color:#6c757d; }
  </style>
</head>
<body class="container">
  <div class="sticky py-2">
    <h4 class="mb-3">Logs do Dispositivo (pull)</h4>
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">IP do dispositivo</label>
        <input id="ip" class="form-control" value="192.168.3.87">
      </div>
      <div class="col-md-2">
        <label class="form-label">Usuário</label>
        <input id="user" class="form-control" value="admin">
      </div>
      <div class="col-md-3">
        <label class="form-label">Senha</label>
        <input id="pass" class="form-control" value="acesso1234" type="password">
      </div>
      <div class="col-md-2">
        <label class="form-label">Atualizar</label>
        <select id="interval" class="form-select">
          <option value="5000">a cada 5s</option>
          <option value="10000">a cada 10s</option>
          <option value="30000">a cada 30s</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button id="btnStart" class="btn btn-primary">Iniciar</button>
      </div>
    </div>
    <div id="status" class="smallnote mt-2"></div>
    <ul class="nav nav-tabs mt-3" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-events" data-bs-toggle="tab" data-bs-target="#pane-events" type="button" role="tab">Eventos</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-diagnostics" data-bs-toggle="tab" data-bs-target="#pane-diagnostics" type="button" role="tab">Diagnóstico</button>
      </li>
    </ul>
  </div>

  <div class="tab-content mt-3">
    <!-- Eventos -->
    <div class="tab-pane fade show active" id="pane-events" role="tabpanel" aria-labelledby="tab-events">
      <div class="table-responsive">
        <table id="evtTable" class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>ID/Usuário</th>
              <th>Cartão/Face</th>
              <th>Tipo</th>
              <th>Resultado</th>
              <th>Data/Hora</th>
              <th class="mono">Bruto</th>
            </tr>
          </thead>
          <tbody id="evtBody">
            <tr><td colspan="7" class="text-muted">Sem dados ainda…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Diagnóstico -->
    <div class="tab-pane fade" id="pane-diagnostics" role="tabpanel" aria-labelledby="tab-diagnostics">
      <div class="alert alert-info py-2">
        Quando não houver eventos ou houver erro (ex.: <span class="mono">400 Bad Request</span>), as tentativas aparecem aqui.
      </div>
      <div class="table-responsive">
        <table id="diagTable" class="table table-bordered table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Método</th>
              <th>Timezone</th>
              <th>name</th>
              <th>URL</th>
              <th>HTTP</th>
              <th>Erro cURL</th>
              <th>Sample</th>
            </tr>
          </thead>
          <tbody id="diagBody">
            <tr><td colspan="8" class="text-muted">Sem diagnósticos…</td></tr>
          </tbody>
        </table>
      </div>
      <pre id="rawDiag" class="mono small text-secondary"></pre>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let timer = null;

    const $ = (id)=>document.getElementById(id);
    const statusBox = $('status');
    const evtBody   = $('evtBody');
    const diagBody  = $('diagBody');
    const rawDiag   = $('rawDiag');

    $('btnStart').addEventListener('click', () => {
      if (timer) {
        clearInterval(timer); timer=null;
        $('btnStart').textContent = 'Iniciar';
        statusBox.textContent = 'Parado.';
        return;
      }
      $('btnStart').textContent = 'Parar';
      const interval = parseInt($('interval').value,10);
      // primeira chamada imediata
      fetchOnce();
      // e continua
      timer = setInterval(fetchOnce, interval);
    });

    async function fetchOnce(){
      const ip   = $('ip').value.trim();
      const user = $('user').value.trim();
      const pass = $('pass').value.trim();

      const url = `fetch_logs.php?ip=${encodeURIComponent(ip)}&user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}`;

      const startedAt = new Date();
      try {
        const resp = await fetch(url);
        const text = await resp.text(); // para depurar respostas inválidas
        let data;
        try { data = JSON.parse(text) } catch(e){ data = { parse_error: e.message, raw: text } }

        const dt = startedAt.toLocaleTimeString();
        statusBox.textContent = `[${dt}] HTTP ${resp.status}`;

        renderData(data);

      } catch (e) {
        statusBox.textContent = `Erro de rede: ${e.message}`;
      }
    }

    // Renderizador principal
    function renderData(payload){
      // Se veio ok:true + result
      if (payload && payload.ok && payload.result) {
        const used = payload.used || {};
        // limpa diagnóstico
        fillDiagnostics([]);
        rawDiag.textContent = '';

        // converte result para lista de eventos normalizada (melhor esforço)
        const list = normalizeEvents(payload.result);

        fillEvents(list);

        // nota sobre como a requisição foi feita
        statusBox.textContent += used.name ? ` | name=${used.name} | ${used.method}/${used.timezone_format}` : '';
        return;
      }

      // Caso contrário, mostrar Diagnóstico (como no seu print)
      const tried = Array.isArray(payload?.tried) ? payload.tried : [];
      fillEvents([]); // esvazia (ou deixa o último carregado)
      fillDiagnostics(tried);
      rawDiag.textContent = JSON.stringify(payload, null, 2);
    }

    // Tabela de Eventos
    function fillEvents(list){
      evtBody.innerHTML = '';
      if (!Array.isArray(list) || list.length === 0) {
        evtBody.innerHTML = `<tr><td colspan="7" class="text-muted">Nenhum evento retornado neste intervalo.</td></tr>`;
        return;
      }
      list.forEach((ev, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${idx+1}</td>
          <td>${safe(ev.user_id || ev.person_id || ev.employee || '-')}</td>
          <td>${safe(ev.card || ev.face || ev.credential || '-')}</td>
          <td>${safe(ev.event_type || ev.type || '-')}</td>
          <td>${safe(ev.result || ev.status || '-')}</td>
          <td>${safe(ev.time || ev.timestamp || ev.date || '-')}</td>
          <td class="mono">${safe(JSON.stringify(ev._raw || ev, null, 0)).slice(0,220)}</td>
        `;
        evtBody.appendChild(tr);
      });
    }

    // Tabela de Diagnóstico (tentativas)
    function fillDiagnostics(tried){
      diagBody.innerHTML = '';
      if (!Array.isArray(tried) || tried.length === 0) {
        diagBody.innerHTML = `<tr><td colspan="8" class="text-muted">Sem diagnósticos…</td></tr>`;
        return;
      }
      tried.forEach((t, i) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${i+1}</td>
          <td><span class="badge text-bg-secondary">${safe(t.method)}</span></td>
          <td>${safe(t.tz)}</td>
          <td>${safe(t.name)}</td>
          <td class="mono" style="max-width:360px; word-break:break-all;">${safe(t.url || '')}${t.post ? `<div class="small text-muted">POST: ${safe(JSON.stringify(t.post))}</div>` : ''}</td>
          <td>${safe(t.code)}</td>
          <td class="mono">${safe(t.err || '')}</td>
          <td class="mono" style="max-width:360px; word-break:break-all;">${safe(t.sample || '')}</td>
        `;
        diagBody.appendChild(tr);
      });
    }

    // --- Normalização de eventos (melhor-esforço) ---
    function normalizeEvents(result){
      // result = { ok:true, format:'json'|'xml'|'text'|'empty', data|raw }
      if (!result || result.format === 'empty') return [];

      if (result.format === 'json' && result.data) {
        // Tente detectar listas conhecidas (cada firmware muda…)
        // Ex.: { Events: [ {...}, {...} ] } ou { Items: [...] } ou já é array
        let arr = [];
        if (Array.isArray(result.data)) arr = result.data;
        else if (Array.isArray(result.data.Events)) arr = result.data.Events;
        else if (Array.isArray(result.data.Event))  arr = result.data.Event;
        else if (Array.isArray(result.data.Items))  arr = result.data.Items;
        else if (Array.isArray(result.data.Records))arr = result.data.Records;

        return arr.map(x => mapEvent(x));
      }

      if (result.format === 'xml' && result.data) {
        // Já vem como objeto (via json_encode(simplexml))
        // Tente achar listas
        const d = result.data;
        let arr = [];
        const firstArray = findFirstArray(d);
        if (firstArray) arr = firstArray;
        return arr.map(x => mapEvent(x));
      }

      if (result.format === 'text' || result.format === 'xml_raw') {
        // Sem parsing (mostrar linha única)
        return [{ _raw: result.raw }];
      }

      return [];
    }

    function mapEvent(x){
      // Mapeamento heurístico — ajuste após vermos um exemplo “ok”
      const o = typeof x === 'object' && x ? x : { value: x };
      return {
        user_id: o.UserID || o.PersonID || o.userId || o.EmployeeNo || o.employee || null,
        card:    o.CardNo || o.CardID || o.card || null,
        face:    o.FaceID || o.face || null,
        event_type: o.EventType || o.eventType || o.Type || o.type || null,
        result:  o.Pass || o.Result || o.result || null,
        time:    o.Time || o.EventTime || o.time || o.Timestamp || o.timestamp || null,
        _raw:    o
      };
    }

    function findFirstArray(obj){
      if (Array.isArray(obj)) return obj;
      if (obj && typeof obj === 'object') {
        for (const k of Object.keys(obj)) {
          const r = findFirstArray(obj[k]);
          if (r) return r;
        }
      }
      return null;
    }

    function safe(v){
      if (v === null || v === undefined) return '';
      return String(v)
        .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }
  </script>
</body>
</html>
