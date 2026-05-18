$(document).ready(function () {
  listarUsuarios();

  // Atualiza a listagem ao mudar os filtros
  $('#filtroCategoria, #filtroStatus').on('change', listarUsuarios);

  // Novo usuário
  $('#btnNovoUsuario').click(function () {
    $('#formUsuario')[0].reset();
    $('#usuario_id').val('');
    $('#senha').val('');
    $('#gerar_qrcode').prop('checked', true);
    $('#modalUsuarioLabel').text('Novo Usuário');
    $('#modalUsuario').modal('show');
  });

  // Submissão do formulário
  $('#formUsuario').submit(function (e) {
    e.preventDefault();
    const dados = $(this).serialize() + '&acao=cadastrar';

    $.ajax({
      url: '../api/usuarios/salvar.php',
      method: 'POST',
      data: dados,
      dataType: 'json',
      success: function (resposta) {
        if (resposta.status === 'ok') {
          $('#modalUsuario').modal('hide');
          exibirToast('Usuário salvo com sucesso!', 'success');
          listarUsuarios();
        } else {
          exibirToast(resposta.mensagem, 'danger');
        }
      },
      error: function () {
        exibirToast('Erro ao salvar o usuário.', 'danger');
      }
    });
  });

  // Botão editar
  $('#tabelaUsuarios').on('click', '.btn-editar', function () {
    const id = $(this).data('id');

    $.getJSON('../api/usuarios/obter.php?id=' + id, function (resposta) {
      if (resposta.status === 'ok') {
        const u = resposta.usuario;
        $('#usuario_id').val(u.id);
        $('#nome').val(u.nome);
        $('#email').val(u.email);
        $('#categoria').val(u.categoria_usuario);
        $('#senha').val('');
        $('#gerar_qrcode').prop('checked', false);
        $('#modalUsuarioLabel').text('Editar Usuário');
        $('#modalUsuario').modal('show');
      } else {
        exibirToast('Erro ao carregar dados do usuário.', 'danger');
      }
    });
  });
});

function listarUsuarios() {
  const categoria = $('#filtroCategoria').val();
  const status = $('#filtroStatus').val();

  $.getJSON('../api/usuarios/listar.php', function (usuarios) {
    let html = '';
    usuarios.forEach(function (u) {
      const mostrarCategoria = !categoria || u.categoria === categoria;
      const mostrarStatus = status === '' || u.ativo == status;

      if (mostrarCategoria && mostrarStatus) {
        html += `<tr>
          <td>${u.nome}</td>
          <td class="d-none d-md-table-cell">${u.email}</td>
          <td class="d-none d-sm-table-cell">${u.status}</td>
          <td class="d-none d-lg-table-cell">${u.qrcode ?? ''}</td>
          <td>
            <button class="btn btn-sm btn-primary btn-editar" data-id="${u.id}">Editar</button>
          </td>
        </tr>`;
      }
    });

    $('#tabelaUsuarios tbody').html(html);
  });
}





function exibirToast(mensagem, tipo = 'success') {
  const toast = $(`<div class="toast align-items-center text-bg-${tipo} border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">${mensagem}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
    </div>
  </div>`);
  $('.container').append(toast);
  toast.toast({ delay: 4000 });
  toast.toast('show');
}
