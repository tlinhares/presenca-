$(document).ready(function () {
    verificarMarmitex();
    verificarStatusReserva();
    carregarReservasAdicionais();

    $('#btnReservaPropria').on('click', function () {
        const estado = $(this).data('estado');

        if (estado === 'reservar') {
            reservarAlmoco();
        } else if (estado === 'cancelar') {
            cancelarReservaAlmoco();
        }
    });

    $('#formReservaAdicional').on('submit', function (e) {
        e.preventDefault();

        const dados = {
            data: $('#data').val(),
            quantidade: $('#quantidade').val(),
            detalhe: $('#detalhe').val(),
            tipo: $('#tipo').val()
        };

        $.ajax({
            url: '../api/almoco/reservar_adicional.php',
            method: 'POST',
            data: dados,
            dataType: 'json',
            success: function (resposta) {
                if (resposta.status === 'ok') {
                    $('#mensagemAdicional').html('<div class="alert alert-success">Reserva adicional salva!</div>');
                    $('#formReservaAdicional')[0].reset();
                    carregarReservasAdicionais();
                } else {
                    $('#mensagemAdicional').html('<div class="alert alert-danger">' + resposta.mensagem + '</div>');
                }
            },
            error: function () {
                $('#mensagemAdicional').html('<div class="alert alert-danger">Erro ao salvar reserva.</div>');
            }
        });
    });
});

function verificarStatusReserva() {
    $.ajax({
        url: '../api/almoco/status_reserva.php',
        method: 'GET',
        dataType: 'json',
        success: function (resposta) {
            const botao = $('#btnReservaPropria');

            if (resposta.reservou_hoje) {
                botao
                    .text('Cancelar meu almoço')
                    .removeClass('btn-success')
                    .addClass('btn-danger')
                    .prop('disabled', false)
                    .data('estado', 'cancelar');
            } else if (resposta.hora_excedida) {
                botao
                    .text('Horário excedido!')
                    .removeClass('btn-success btn-danger')
                    .addClass('btn-secondary')
                    .prop('disabled', true)
                    .data('estado', '');
            } else {
                botao
                    .text('Reservar meu almoço')
                    .removeClass('btn-danger btn-secondary')
                    .addClass('btn-success')
                    .prop('disabled', false)
                    .data('estado', 'reservar');
            }
        }
    });
}

function reservarAlmoco() {
    $.ajax({
        url: '../api/almoco/reservar.php',
        method: 'POST',
        dataType: 'json',
        success: function (resposta) {
            console.log("DEBUG RESERVA:", resposta.debug); // Mostra os logs

            if (resposta.status === 'ok') {
                $('#mensagemPropria').html('<div class="alert alert-success">Almoço reservado com sucesso!</div>');
                verificarStatusReserva();
                carregarReservasAdicionais();
            } else {
                $('#mensagemPropria').html('<div class="alert alert-danger">' + resposta.mensagem + '</div>');
            }
        },
            error: function (xhr, status, error) {
            console.error('Erro AJAX:', error);
            console.warn('Status:', status);
            console.warn('Resposta:', xhr.responseText);
            $('#mensagemPropria').html('<div class="alert alert-danger">Erro ao reservar. Veja o console para mais detalhes.</div>');
        }
    });
}

function cancelarReservaAlmoco() {
    $.ajax({
        url: '../api/almoco/cancelar.php',
        method: 'POST',
        dataType: 'json',
        success: function (resposta) {
            if (resposta.status === 'ok') {
                $('#mensagemPropria').html('<div class="alert alert-warning">Reserva cancelada.</div>');
                verificarStatusReserva();
                carregarReservasAdicionais();
            } else {
                $('#mensagemPropria').html('<div class="alert alert-danger">' + resposta.mensagem + '</div>');
            }
        },
        error: function () {
            $('#mensagemPropria').html('<div class="alert alert-danger">Erro ao cancelar reserva.</div>');
        }
    });
}

function verificarMarmitex() {
    $.ajax({
        url: '../api/config/buscar_config.php',
        method: 'GET',
        dataType: 'json',
        success: function (resposta) {
            if (resposta.marmitex_habilitado) {
                $('#grupo-marmitex').show();
            }
        }
    });
}

// Esta função já está correta:
function carregarReservasAdicionais() {
    $.ajax({
        url: '../api/almoco/listar_adicionais.php',
        method: 'GET',
        dataType: 'json',
        success: function (dados) {
            console.log("Resposta da API listar_adicionais:", dados);
            
            const lista = dados.reservas;
            const total = dados.quantidade_total;

            let html = '';
            html += `<p class="mb-2"><strong>Total de reservas adicionais do dia:</strong> ${total}</p>`;

            if (lista.length === 0) {
                html += '<p class="text-muted">Nenhuma reserva adicional.</p>';
            } else {
                html += `
                <div class="table-responsive">
                  <table class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Data</th>
                        <th class="d-none d-sm-table-cell">Qtd</th>
                        <th class="d-none d-md-table-cell">Tipo</th>
                        <th class="d-none d-lg-table-cell">Detalhe</th>
                        <th class="d-none d-xl-table-cell">Criado em</th>
                        <th class="d-none d-xl-table-cell">Valor Refeição</th>
                        <th class="d-none d-xl-table-cell">Valor Marmitex</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>`;

                lista.forEach(function (item) {
                    html += `<tr>
                      <td>${item.data}</td>
                      <td class="d-none d-sm-table-cell">${item.quantidade}</td>
                      <td class="d-none d-md-table-cell">${item.tipo}</td>
                      <td class="d-none d-lg-table-cell">${item.detalhe}</td>
                      <td class="d-none d-xl-table-cell">${item.data_cadastro}</td>
                      <td class="d-none d-xl-table-cell">R$ ${parseFloat(item.valor_refeicao).toFixed(2)}</td>
                      <td class="d-none d-xl-table-cell">R$ ${parseFloat(item.valor_marmitex).toFixed(2)}</td>
                      <td>`;
                    if (item.pode_excluir) {
                        html += `<button class="btn btn-sm btn-danger btnExcluir" data-id="${item.id}">Excluir</button>`;
                    }
                    html += `</td></tr>`;
                });

                html += '</tbody></table></div>';
            }

            $('#listaReservasAdicionais').html(html);

            // Evento de exclusão
            $('.btnExcluir').on('click', function () {
                const id = $(this).data('id');
                if (confirm('Deseja realmente excluir esta reserva?')) {
                    $.ajax({
                        url: '../api/almoco/excluir_adicional.php',
                        method: 'POST',
                        data: { id },
                        dataType: 'json',
                        success: function (resposta) {
                            if (resposta.status === 'ok') {
                                carregarReservasAdicionais();
                            } else {
                                alert('Erro ao excluir.');
                            }
                        },
                        error: function () {
                            alert('Erro ao excluir.');
                        }
                    });
                }
            });
        },
        error: function () {
            $('#listaReservasAdicionais').html('<p class="text-danger">Erro ao carregar reservas.</p>');
        }
    });
}