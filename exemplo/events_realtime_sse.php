<?php // events_realtime_sse.php ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Eventos em Tempo Real (SSE)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{padding:1rem}
    .mono{font-family:ui-monospace,Consolas,monospace}
    table{font-size:.95rem}
    .sticky{position:sticky;top:0;background:#fff;z-index:10;padding:10px 0}
  </style>
</head>
<body class="container">
  <div class="sticky">
    <h4 class="mb-2">Eventos do Dispositivo (realtime via SSE)</h4>
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">IP</label>
        <input id="ip" class="form-control" value="10.144.129.64">
      </div>
      <div class="col-md-2">
        <label class="form-label">Usuário</label>
        <input id="user" class="form-control" value="admin">
      </div>
      <div class="col-md-3">
        <label class="form-label">Senha</label>
        <input id="pass" class="form-control" value="Arcs2901" type="password">
      </div>
      <div class="col-md-3">
        <label class="form-label">Codes</label>
        <input id="codes" class="form-control" value="[AccessControl]">
        <div class="form-text">Ex.: [AccessControl],[VideoMotion]</div>
      </div>
      <div class="col-md-1 d-grid">
        <button id="btn" class="btn btn-primary">Iniciar</button>
      </div>
    </div>
    <div id="status" class="text-muted mt-1"></div>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-striped table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Evento</th>
          <th>User/ID</th>
          <th>Cartão/Face</th>
          <th>Resultado</th>
          <th>Hora</th>
          <th class="mono">Bruto</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="7" class="text-muted">Aguardando…</td></tr>
      </tbody>
    </table>
  </div>

<script>
  let es=null, count=0;
  const $=id=>document.getElementById(id);
  const tb=$('tbody'), status=$('status');

  $('btn').onclick=()=>{
    if(es){ es.close(); es=null; $('btn').textContent='Iniciar'; status.textContent='Desconectado.'; return; }
    const ip=$('ip').value.trim(), user=$('user').value.trim(), pass=$('pass').value.trim(), codes=$('codes').value.trim()||'[All]';
    const url=`stream_events.php?ip=${encodeURIComponent(ip)}&user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}&codes=${encodeURIComponent(codes)}&heartbeat=5`;
    start(url);
  };

  function start(url){
    tb.innerHTML='<tr><td colspan="7" class="text-muted">Conectando…</td></tr>';
    count=0;
    es = new EventSource(url);
    $('btn').textContent='Parar';
    status.textContent='Conectando…';

    es.addEventListener('hello', ()=> status.textContent='Conectado.');

    es.addEventListener('ping', ()=> {
      status.textContent = 'Conectado • ' + new Date().toLocaleTimeString();
    });

    // Agora tudo chega como 'message' (objeto JSON já unificado)
    es.addEventListener('message', e => {
      try { appendRow(JSON.parse(e.data)); }
      catch(_) { appendRaw(e.data); }
    });

    es.addEventListener('error', ()=> {
      status.textContent='Erro/Desconectado. Verifique IP/credenciais/codes.';
    });
  }

function appendRow(ev){
  // ignora keepalives/arrays vazios
  if (!ev || (Array.isArray(ev) && ev.length === 0) || (typeof ev === 'object' && !Array.isArray(ev) && Object.keys(ev).length === 0)) {
    return;
  }
  if(count===0) tb.innerHTML='';
  count++;

  const evento = ev.Event || ev.Code || '-';
  const user   = ev.UserID || ev.EmployeeNo || ev.PersonID || '-';
  const card   = ev.CardNo || ev.CardID || '-';
  const result = ev.Pass || ev.Result || ev.Status || '-';
  const hora   = ev.ISOTime || ev.Time || ev.DateTime || ev.UTC || ev.UTCTime || new Date().toLocaleTimeString();

  const tr=document.createElement('tr');
  tr.innerHTML = `
    <td>${count}</td>
    <td>${safe(evento)}</td>
    <td>${safe(user)}</td>
    <td>${safe(card)}</td>
    <td>${safe(result)}</td>
    <td>${safe(hora)}</td>
    <td class="mono" style="max-width:600px;white-space:pre-wrap;word-break:break-all;">${safe(JSON.stringify(ev).slice(0,2000))}</td>
  `;
  tb.prepend(tr);
  if(tb.rows.length>200) tb.deleteRow(tb.rows.length-1);
}


  function appendRaw(data){
    if(count===0) tb.innerHTML='';
    count++;
    const tr=document.createElement('tr');
    tr.innerHTML = `
      <td>${count}</td>
      <td>-</td><td>-</td><td>-</td><td>-</td>
      <td>${new Date().toLocaleTimeString()}</td>
      <td class="mono" style="max-width:600px;white-space:pre-wrap;word-break:break-all;">${safe(data||'')}</td>
    `;
    tb.prepend(tr);
    if(tb.rows.length>200) tb.deleteRow(tb.rows.length-1);
  }

  function safe(s){return String(s??'').replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}
</script>

</body>
</html>
