<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../auth/verifica_sessao.php');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Painel de Refeições</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
  <h3 class="mb-4">Painel de Refeições</h3>

  <form id="filtroForm" class="row g-3">
    <div class="col-md-4">
      <label for="data_inicial" class="form-label">Data Inicial</label>
      <input type="date" class="form-control" id="data_inicial" name="data_inicial" required>
    </div>
    <div class="col-md-4">
      <label for="data_final" class="form-label">Data Final</label>
      <input type="date" class="form-control" id="data_final" name="data_final" required>
    </div>
    <div class="col-md-4 align-self-end">
      <button type="submit" class="btn btn-primary">Buscar</button>
    </div>
  </form>

  <div id="resultado" class="mt-4"></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function () {
  $('#filtroForm').submit(function (e) {
    e.preventDefault();

    const data_inicial = $('#data_inicial').val();
    const data_final = $('#data_final').val();

    $.ajax({
      url: '../api/almoco/relatorio_refeicoes.php',
      method: 'POST',
      data: { data_inicial, data_final },
      dataType: 'json',
      success: function (resposta) {
        if (resposta.status === 'ok') {
          let html = '<h5>Total de Refeições: ' + resposta.total_geral + '</h5>';
          html += '<h6>Valor estimado: R$ ' + resposta.valor_total + '</h6><br>';

          html += '<h5>Refeições Próprias</h5><table class="table table-bordered">';
          html += '<thead><tr><th>Nome</th><th>Data</th><th>Horário</th></tr></thead><tbody>';
          resposta.refeicoes_proprias.forEach(function (r) {
            html += '<tr><td>' + r.nome + '</td><td>' + r.data + '</td><td>' + r.horario + '</td></tr>';
          });
          html += '</tbody></table>';

          html += '<h5>Refeições Adicionais</h5><table class="table table-bordered">';
          html += '<thead><tr><th>Nome</th><th>Data</th><th>Quantidade</th><th>Tipo</th><th>Detalhe</th></tr></thead><tbody>';
          resposta.refeicoes_adicionais.forEach(function (r) {
            html += '<tr><td>' + r.nome + '</td><td>' + r.data + '</td><td>' + r.quantidade + '</td><td>' + r.tipo + '</td><td>' + r.detalhe + '</td></tr>';
          });
          html += '</tbody></table>';

          $('#resultado').html(html);
        } else {
          $('#resultado').html('<div class="alert alert-danger">' + resposta.mensagem + '</div>');
        }
      },
      error: function () {
        $('#resultado').html('<div class="alert alert-danger">Erro ao buscar dados.</div>');
      }
    });
  });
});
</script>
</body>
</html>
