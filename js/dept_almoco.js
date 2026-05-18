$(document).ready(function() {
    // Variáveis globais
    let reservasDepartamento = [];
    
    // Inicializar
    carregarDados();
    
    // Carregar dados iniciais
    function carregarDados() {
        carregarReservasDepartamento();
        carregarPerfilUsuario();
    }
    
    // Carregar reservas de departamento
    function carregarReservasDepartamento() {
        $.ajax({
            url: '../api/almoco/listar departamento.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    reservasDepartamento = response.reservas || [];
                    atualizarListaReservasDepartamento();
                }
            },
            error: function() {
                console.error('Erro ao carregar reservas de departamento');
            }
        });
    }
    
    // Atualizar lista de reservas de departamento
    function atualizarListaReservasDepartamento() {
        const container = $('#reservas-departamento');
        container.empty();
        
        if (reservasDepartamento.length === 0) {
            container.html('<p class="text-muted">Nenhuma reserva de departamento.</p>');
            return;
        }
        
        reservasDepartamento.forEach(function(reserva) {
            const item = $(`
                <div class="card mb-2">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>${reserva.nome}</strong><br>
                                <small class="text-muted">${reserva.tipo} - Qtd: ${reserva.quantidade}</small>
                            </div>
                            <div class="col-md-6 text-right">
                                <span class="badge badge-info">${reserva.data}</span>
                                <button class="btn btn-sm btn-danger ml-2" onclick="excluirReservaDepartamento(${reserva.id})">Excluir</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            container.append(item);
        });
    }
    
    // Carregar perfil do usuário
    function carregarPerfilUsuario() {
        $.ajax({
            url: '../api/usuarios/buscar_perfil.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'sucesso') {
                    const usuario = response.data;
                    $('#nome-usuario').text(usuario.nome);
                    $('#email-usuario').text(usuario.email);
                    
                    if (usuario.foto_base64) {
                        $('#foto-usuario').attr('src', 'data:image/jpeg;base64,' + usuario.foto_base64);
                    }
                }
            },
            error: function() {
                console.error('Erro ao carregar perfil do usuário');
            }
        });
    }
    
    // Fazer reserva de departamento
    $('#btn-reservar-departamento').click(function() {
        const quantidade = $('#quantidade-departamento').val();
        const tipo = $('#tipo-departamento').val();
        const detalhe = $('#detalhe-departamento').val();
        
        if (!quantidade || !tipo) {
            alert('Preencha todos os campos obrigatórios');
            return;
        }
        
        $.ajax({
            url: '../api/almoco/reservar_departamento.php',
            method: 'POST',
            data: {
                quantidade: quantidade,
                tipo: tipo,
                detalhe: detalhe,
                data: new Date().toISOString().split('T')[0]
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    alert('Reserva de departamento realizada com sucesso!');
                    carregarReservasDepartamento();
                    $('#form-reserva-departamento')[0].reset();
                } else {
                    alert('Erro: ' + response.mensagem);
                }
            },
            error: function() {
                alert('Erro ao realizar reserva de departamento');
            }
        });
    });
    
    // Excluir reserva de departamento
    window.excluirReservaDepartamento = function(id) {
        if (confirm('Tem certeza que deseja excluir esta reserva?')) {
            $.ajax({
                url: '../api/almoco/excluir_reserva_departamento.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        alert('Reserva excluída com sucesso!');
                        carregarReservasDepartamento();
                    } else {
                        alert('Erro: ' + response.mensagem);
                    }
                },
                error: function() {
                    alert('Erro ao excluir reserva');
                }
            });
        }
    };
    
    // Atualizar foto do perfil
    $('#btn-atualizar-foto').click(function() {
        const foto = $('#foto-perfil').val();
        if (!foto) {
            alert('Selecione uma foto');
            return;
        }
        
        $.ajax({
            url: '../api/usuarios/atualizar_foto.php',
            method: 'POST',
            data: { foto: foto },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    alert('Foto atualizada com sucesso!');
                    carregarPerfilUsuario();
                } else {
                    alert('Erro: ' + response.mensagem);
                }
            },
            error: function() {
                alert('Erro ao atualizar foto');
            }
        });
    });
    
    // Verificar e atualizar facial
    function verificarEAtualizarFacial() {
        $.ajax({
            url: '../api/usuarios/buscar_perfil.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'sucesso') {
                    const usuario = response.data;
                    if (usuario.foto_base64) {
                        atualizarFotoFacial(usuario.foto_base64);
                    }
                }
            },
            error: function() {
                console.error('Erro ao verificar perfil para atualização facial');
            }
        });
    }
    
    // Atualizar foto facial
    function atualizarFotoFacial(fotoBase64) {
        $.ajax({
            url: '../api/usuarios/atualizar_foto.php',
            method: 'POST',
            data: { foto: fotoBase64 },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    console.log('Foto facial atualizada com sucesso');
                }
            },
            error: function() {
                console.error('Erro ao atualizar foto facial');
            }
        });
    }
    
    // Atualizar foto
    function atualizarFoto() {
        const foto = $('#foto-perfil').val();
        if (!foto) {
            alert('Selecione uma foto');
            return;
        }
        
        $.ajax({
            url: '../api/usuarios/atualizar_foto.php',
            method: 'POST',
            data: { foto: foto },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    alert('Foto atualizada com sucesso!');
                    carregarPerfilUsuario();
                    verificarEAtualizarFacial();
                } else {
                    alert('Erro: ' + response.mensagem);
                }
            },
            error: function() {
                alert('Erro ao atualizar foto');
            }
        });
    }
});