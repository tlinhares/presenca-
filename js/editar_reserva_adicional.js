// Função para editar reserva adicional
function editarReservaAdicional(reservaId) {
    console.log('Função editarReservaAdicional chamada com ID:', reservaId);
    
    // Buscar dados da reserva
    $.ajax({
        url: '../api/almoco/buscar_reserva_adicional.php',
        method: 'GET',
        data: { id: reservaId },
        dataType: 'json',
        success: function(resposta) {
            if (resposta.status === 'ok') {
                const reserva = resposta.data;
                
                // Preencher o formulário de reserva adicional
                $('#dataAdicional').val(reserva.data);
                $('#quantidadeAdicional').val(reserva.quantidade);
                $('#tipoAdicional').val(reserva.tipo);
                $('#dependenteAdicional').val(reserva.id_dependente);
                $('#detalheAdicional').val(reserva.detalhe);
                
                // Verificar se é fora do horário
                if (reserva.fora_do_horario == 1) {
                    $('#foraDoHorarioAdicional').prop('checked', true);
                } else {
                    $('#foraDoHorarioAdicional').prop('checked', false);
                }
                
                // Armazenar ID da reserva para edição
                $('#formReservaAdicional').data('edit-id', reservaId);
                
                // Alterar texto do botão
                $('#btnReservarAdicional').text('Atualizar Reserva');
                
                // Abrir modal
                $('#modalReservaAdicional').modal('show');
                
                // Atualizar título do modal
                $('#modalReservaAdicionalLabel').text('Editar Reserva Adicional');
                
            } else {
                exibirToast(resposta.mensagem || 'Erro ao carregar dados da reserva', 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao carregar dados da reserva', 'danger');
        }
    });
}

// Evento para editar reserva adicional
$(document).on('click', '.btn-editar-reserva-adicional', function() {
    console.log('Botão editar clicado!');
    alert('Botão editar clicado!'); // Debug temporário
    const reservaId = $(this).data('id');
    console.log('ID da reserva:', reservaId);
    editarReservaAdicional(reservaId);
});
