// Função para detectar arquivos HEIC (escopo global)
function detectHeicFile(file) {
    const fileName = file.name.toLowerCase();
    const isHeicExtension = fileName.endsWith('.heic') || fileName.endsWith('.heif');
    const isHeicMime = file.type === 'image/heic' || file.type === 'image/heif';
    
    // Log detalhado para debug
    console.log('Detecção HEIC:', {
        fileName: file.name,
        fileType: file.type,
        isHeicExtension: isHeicExtension,
        isHeicMime: isHeicMime,
        userAgent: navigator.userAgent
    });
    
    return isHeicExtension || isHeicMime;
}

// Função para mostrar mensagem informativa sobre HEIC (escopo global)
function showHeicMessage(file) {
    exibirToast(
        '⚠️ Formato HEIC não suportado\n\n' +
        'Por favor, converta sua foto para JPG ou PNG antes de enviar.\n\n' +
        '💡 Dicas:\n' +
        '• iPhone: Configurações > Câmera > Formato Mais Compatível\n' +
        '• Android: Use app de câmera que salve em JPG\n' +
        '• Ou converta a foto antes de enviar\n\n' +
        'Formatos aceitos: JPG, PNG, GIF',
        'warning'
    );
    
    // Log para análise (sem afetar usuário)
    console.log('HEIC file rejected:', {
        fileName: file.name,
        fileSize: file.size,
        userAgent: navigator.userAgent,
        timestamp: new Date().toISOString()
    });
}

// Função para comprimir imagem (escopo global)
function comprimirImagem(base64Data, callback) {
    console.log('Iniciando compressão de imagem...');
    
    const img = new Image();
    img.onload = function() {
        console.log('Imagem carregada, dimensões originais:', img.width, 'x', img.height);
        
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Calcular novas dimensões mantendo proporção
        let { width, height } = img;
        const maxWidth = 800;
        const maxHeight = 600;
        
        console.log('Dimensões originais:', width, 'x', height);
        
        if (width > height) {
            if (width > maxWidth) {
                height = (height * maxWidth) / width;
                width = maxWidth;
            }
        } else {
            if (height > maxHeight) {
                width = (width * maxHeight) / height;
                height = maxHeight;
            }
        }
        
        console.log('Dimensões calculadas:', width, 'x', height);
        
        canvas.width = width;
        canvas.height = height;
        
        // Desenhar imagem redimensionada
        ctx.drawImage(img, 0, 0, width, height);
        console.log('Imagem desenhada no canvas');
        
        // Comprimir com qualidade ajustável
        let qualidade = 0.9;
        let imagemComprimida = canvas.toDataURL('image/jpeg', qualidade);
        
        // Verificar tamanho e ajustar qualidade se necessário
        const tamanhoKB = (imagemComprimida.length * 0.75) / 1024; // Aproximação do tamanho em KB
        console.log('Tamanho inicial com qualidade', qualidade, ':', tamanhoKB.toFixed(2), 'KB');
        
        if (tamanhoKB > 150) {
            console.log('Tamanho maior que 150KB, reduzindo qualidade...');
            // Reduzir qualidade até ficar abaixo de 150KB
            const qualidades = [0.8, 0.7, 0.6, 0.5, 0.4, 0.3, 0.2, 0.1];
            
            for (let q of qualidades) {
                imagemComprimida = canvas.toDataURL('image/jpeg', q);
                const novoKB = (imagemComprimida.length * 0.75) / 1024;
                console.log('Tentativa com qualidade', q, ':', novoKB.toFixed(2), 'KB');
                
                if (novoKB <= 150) {
                    console.log('✅ Qualidade final:', q, 'Tamanho:', novoKB.toFixed(2), 'KB');
                    break;
                }
            }
        } else {
            console.log('✅ Tamanho já está dentro do limite de 150KB');
        }
        
        // Extrair apenas a parte base64 (remover data:image/jpeg;base64,)
        const base64Final = imagemComprimida.split(',')[1];
        const tamanhoFinalKB = (base64Final.length * 0.75) / 1024;
        console.log('Tamanho final da imagem comprimida:', tamanhoFinalKB.toFixed(2), 'KB');
        
        callback(base64Final);
    };
    
    img.onerror = function(error) {
        console.error('❌ Erro ao carregar imagem para compressão:', error);
        // Se falhar, usar a imagem original
        const base64Original = base64Data.split(',')[1];
        console.log('Usando imagem original como fallback');
        callback(base64Original);
    };
    
    console.log('Definindo src da imagem...');
    img.src = base64Data;
}

$(document).ready(function () {
    verificarMarmitex();
    verificarStatusReserva();
    carregarReservasAdicionais();
    carregarSelectDependentes();
    carregarFotoPerfil();
    
    // Carregar dependentes quando a aba de dependentes for ativada
    $('#reserva-dependentes-tab').on('shown.bs.tab', function () {
        carregarDependentesMultiplos();
    });
    
    // Carregar reservas adicionais quando a aba for ativada
    $('#reservas-adicionais-tab').on('shown.bs.tab', function () {
        carregarReservasAdicionaisUsuario();
        carregarSelectDependentesFiltro();
    });
    
    // Carrega os dependentes quando o modal de configurações é aberto
    $('#modalConfiguracoes').on('show.bs.modal', function() {
        carregarDependentes();
    });
    
    // Carrega os dados do perfil quando o modal de configurações é aberto
    $('#modalConfiguracoes').on('shown.bs.modal', function() {
        // Verifica se a aba ativa é "Meu Perfil" e carrega os dados
        if ($('#perfil-tab').hasClass('active')) {
            carregarDadosPerfil();
        }
    });
    
    // Evento para quando o modal de edição de dependente abrir
    $('#modalEditarDependente').on('shown.bs.modal', function() {
        console.log('Modal de edição aberto');
        console.log('Valor atual do campo data:', $('#edit_nascimento_dependente').val());
        
        // Se o campo estiver vazio, tentar preencher novamente
        if (!$('#edit_nascimento_dependente').val()) {
            console.log('Campo data vazio, tentando preencher novamente');
            const dataArmazenada = $('#modalEditarDependente').data('nascimento');
            if (dataArmazenada) {
                $('#edit_nascimento_dependente').val(dataArmazenada);
                console.log('Data preenchida do data attribute:', dataArmazenada);
            }
        }
    });
    
    // Preview da foto ao selecionar arquivo
    $('#edit_foto_dependente').on('change', function() {
        const file = this.files[0];
        if (file) {
            console.log('Arquivo de edição selecionado:', {
                name: file.name,
                type: file.type,
                size: file.size
            });
            
            // Verificar se é arquivo HEIC
            if (detectHeicFile(file)) {
                console.log('HEIC detectado na edição!');
                showHeicMessage(file);
                this.value = ''; // Limpar o input
                $('#edit_previewFotoDependente').addClass('d-none');
                return;
            }
            
            console.log('Arquivo de edição aceito - não é HEIC');
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#edit_previewFotoDependente').attr('src', e.target.result).removeClass('d-none');
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Submissão do formulário de edição
    $('#formEditarDependente').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const file = $('#edit_foto_dependente')[0].files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                console.log('Arquivo de edição convertido para base64, comprimindo...');
                
                // Comprimir a imagem antes de enviar
                comprimirImagem(e.target.result, function(imagemComprimida) {
                    console.log('Imagem de edição comprimida, enviando...');
                    formData.append('foto_base64', imagemComprimida);
                    enviarEdicaoDependente(formData);
                });
            };
            reader.readAsDataURL(file);
        } else {
            enviarEdicaoDependente(formData);
        }
    });
    
    // Botão selecionar todos os dependentes
    $('#btnSelecionarTodos').on('click', function() {
        const checkboxes = $('.dependente-checkbox');
        const todosMarcados = checkboxes.length > 0 && checkboxes.filter(':checked').length === checkboxes.length;
        
        if (todosMarcados) {
            // Desmarcar todos
            checkboxes.prop('checked', false);
            $(this).html('<i class="bi bi-check-all"></i> Selecionar Todos');
        } else {
            // Marcar todos
            checkboxes.prop('checked', true);
            $(this).html('<i class="bi bi-x-lg"></i> Desmarcar Todos');
        }
    });

    $('#foto_dependente').on('change', function () {
        const file = this.files[0];
        if (file) {
            console.log('Arquivo selecionado:', {
                name: file.name,
                type: file.type,
                size: file.size
            });
            
            // Verificar se é arquivo HEIC
            if (detectHeicFile(file)) {
                console.log('HEIC detectado!');
                showHeicMessage(file);
                this.value = ''; // Limpar o input
                $('#previewFotoDependente').addClass('d-none');
                return;
            }
            
            console.log('Arquivo aceito - não é HEIC');
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#previewFotoDependente').attr('src', e.target.result).removeClass('d-none');
            };
            reader.readAsDataURL(file);
        }
    });


    $('#btnReservaPropria').on('click', function () {
        const estado = $(this).data('estado');

        if (estado === 'reservar') {
            reservarAlmoco();
        } else if (estado === 'cancelar') {
            cancelarReservaAlmoco();
        }
    });

 // Submissão da reserva adicional
    $('#formReservaAdicional').on('submit', function (e) {
        e.preventDefault();
        reservarAdicional();
    });


// Envio do novo dependente
$('#formDependente').on('submit', function (e) {
  e.preventDefault();
  
  console.log('Formulário de dependente submetido');

  const formData = new FormData();
  formData.append('nome_dependente', $('#nome_dependente').val());
  formData.append('parentesco_dependente', $('#parentesco_dependente').val());
  formData.append('nascimento_dependente', $('#nascimento_dependente').val());

  console.log('Dados do formulário:', {
    nome: $('#nome_dependente').val(),
    parentesco: $('#parentesco_dependente').val(),
    nascimento: $('#nascimento_dependente').val()
  });

  // Calcula a idade e define o valor do campo cobrar
  const dataNascimento = new Date($('#nascimento_dependente').val());
  const hoje = new Date();
  const idade = hoje.getFullYear() - dataNascimento.getFullYear();
  const mesAtual = hoje.getMonth();
  const mesNascimento = dataNascimento.getMonth();
  
  // Ajusta a idade se ainda não fez aniversário este ano
  const idadeAjustada = (mesAtual < mesNascimento || (mesAtual === mesNascimento && hoje.getDate() < dataNascimento.getDate())) 
    ? idade - 1 
    : idade;

  // Define o valor do campo cobrar (1 = menor de 12 anos, 0 = maior)
  const cobrar = idadeAjustada < 12 ? 1 : 0;
  formData.append('cobrar', cobrar);
  
  console.log('Idade calculada:', idadeAjustada, 'Cobrar:', cobrar);

  const file = $('#foto_dependente')[0].files[0];
  if (file) {
    console.log('Arquivo encontrado, processando...');
    const reader = new FileReader();
    reader.onload = function (e) {
      console.log('Arquivo convertido para base64, comprimindo...');
      
      // Comprimir a imagem antes de enviar
      comprimirImagem(e.target.result, function(imagemComprimida) {
        console.log('Imagem comprimida, enviando...');
        formData.append('foto_base64', imagemComprimida);
        enviarDependente(formData);
      });
    };
    reader.onerror = function(e) {
      console.error('Erro ao ler arquivo:', e);
      exibirToast('Erro ao processar arquivo', 'danger');
    };
    reader.readAsDataURL(file);
  } else {
    console.log('Nenhum arquivo selecionado, enviando sem foto...');
    enviarDependente(formData);
  }
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
                    .text(' Reservar meu almoço')
                    .addClass('bi-egg-fried')
                    .addClass('bi')
                    .removeClass('btn-danger btn-secondary')
                    .addClass('btn-success')
                    .prop('disabled', false)
                    .data('estado', 'reservar');
            }
        }
    });
}

function reservarAlmoco() {
    // Primeiro verificar se está fora do horário
    $.ajax({
        url: '../api/almoco/verificar_horario.php',
        method: 'GET',
        dataType: 'json',
        success: function (resposta) {
            // Verificar se está fora do horário de agendamento
            if (resposta.fora_do_horario_agendamento) {
                exibirToast(
                    `⚠️ Horário limite de agendamento ultrapassado!\n\n` +
                    `Após ${resposta.horario_limite_agendamento}.\n\n` +
                    `Horário atual: ${resposta.hora_atual}\n` +
                    `Horário limite: ${resposta.horario_limite_agendamento}`,
                    'warning'
                );
                return;
            }
            
            if (resposta.fora_do_horario && resposta.permitir_atraso) {
                // Mostrar modal de confirmação para reserva fora do horário
                $('#horarioLimite').text(resposta.hora_limite);
                $('#horarioAtual').text(resposta.hora_atual);
                $('#valorForaHorario').text(resposta.valor_fora_horario);
                
                // Configurar o botão de confirmação
                $('#btnConfirmarForaHorario').off('click').on('click', function() {
                    $('#modalConfirmacaoForaHorario').modal('hide');
                    executarReserva();
                });
                
                // Mostrar o modal
                $('#modalConfirmacaoForaHorario').modal('show');
            } else {
                // Reserva normal dentro do horário
                executarReserva();
            }
        },
        error: function () {
            exibirToast('Erro ao verificar horário.', 'danger');
        }
    });
}

function executarReserva() {
    $.ajax({
        url: '../api/almoco/reservar.php',
        method: 'POST',
        dataType: 'json',
        success: function (resposta) {
            if (resposta.status === 'ok') {
                exibirToast('Almoço reservado com sucesso!', 'success');
                verificarStatusReserva();
                carregarReservasAdicionais();
            } else {
                exibirToast(resposta.mensagem, 'danger');
            }
        },
        error: function () {
            exibirToast('Erro ao reservar. Veja o console para mais detalhes.', 'danger');
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
                exibirToast('Reserva cancelada', 'warning');
                verificarStatusReserva();
                carregarReservasAdicionais();
            } else {
                exibirToast(resposta.mensagem, 'danger');
            }
        },
        error: function () {
            exibirToast('Erro ao cancelar reserva', 'danger');
        }
    });
}

function reservarAdicional() {
    const id_dependente = $('#dependente').val();
    const tipo = $('#tipo').val();
    
    if (!id_dependente) {
        exibirToast('Selecione um dependente.', 'warning');
        return;
    }
    
    // Verificar se o dependente tem data de nascimento cadastrada
    const dependenteSelecionado = $('#dependente option:selected');
    const dataNascimento = dependenteSelecionado.data('nascimento');
    
    if (!dataNascimento || dataNascimento === '') {
        const nomeDependente = dependenteSelecionado.text();
        
        // Mostrar alerta e abrir modal para atualizar data de nascimento
        if (confirm(`⚠️ ATENÇÃO!\n\nO dependente "${nomeDependente}" não possui data de nascimento cadastrada.\n\nÉ obrigatório atualizar a data de nascimento antes de fazer reservas.\n\nDeseja abrir o formulário para atualizar a data de nascimento?`)) {
            // Buscar dados completos do dependente
            $.ajax({
                url: '../api/dependentes/obter.php',
                method: 'GET',
                data: { id: id_dependente },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'sucesso') {
                        const dependente = res.dados;
                        
                        // Preencher o modal de edição com os dados do dependente
                        $('#edit_id_dependente').val(dependente.id);
                        $('#edit_nome_dependente').val(dependente.nome);
                        $('#edit_parentesco_dependente').val(dependente.parentesco);
                        $('#edit_nascimento_dependente').val(dependente.data_nascimento);
                        
                        // Mostrar foto se existir
                        if (dependente.foto) {
                            $('#edit_previewFotoDependente').attr('src', 'data:image/jpeg;base64,' + dependente.foto).show();
                        } else {
                            $('#edit_previewFotoDependente').hide();
                        }
                        
                        // Alterar título do modal
                        $('#modalEditarDependenteLabel').text('Atualizar Data de Nascimento - ' + dependente.nome);
                        
                        // Mostrar o modal
                        $('#modalEditarDependente').modal('show');
                        
                        // Focar no campo de data de nascimento
                        setTimeout(function() {
                            $('#edit_nascimento_dependente').focus();
                        }, 500);
                        
                    } else {
                        exibirToast('Erro ao carregar dados do dependente: ' + res.mensagem, 'danger');
                    }
                },
                error: function() {
                    exibirToast('Erro ao carregar dados do dependente', 'danger');
                }
            });
        }
        return;
    }
    
    // Primeiro verificar se está fora do horário
    $.ajax({
        url: '../api/almoco/verificar_horario_adicional.php',
        method: 'GET',
        data: {
            id_dependente: id_dependente,
            tipo: tipo
        },
        dataType: 'json',
        success: function (resposta) {
            // Verificar se está fora do horário de agendamento
            if (resposta.fora_do_horario_agendamento) {
                exibirToast(
                    `⚠️ Horário limite de agendamento ultrapassado!\n\n` +
                    `Após ${resposta.horario_limite_agendamento}.\n\n` +
                    `Horário atual: ${resposta.hora_atual}\n` +
                    `Horário limite: ${resposta.horario_limite_agendamento}`,
                    'warning'
                );
                return;
            }
            
            if (resposta.fora_do_horario && resposta.permitir_atraso) {
                // Mostrar modal de confirmação para reserva fora do horário
                $('#horarioLimiteAdicional').text(resposta.hora_limite);
                $('#horarioAtualAdicional').text(resposta.hora_atual);
                $('#valorRefeicaoAdicional').text(resposta.valor_refeicao);
                $('#valorMarmitexAdicional').text(resposta.valor_marmitex);
                $('#tipoAdicional').text(resposta.tipo === 'presencial' ? 'Presencial' : 'Marmitex');
                
                // Buscar nome do dependente
                $.ajax({
                    url: '../api/dependentes/listar.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(deps) {
                        const dependente = deps.dependentes.find(d => d.id == id_dependente);
                        if (dependente) {
                            $('#dependenteNomeAdicional').text(dependente.nome);
                        }
                        
                        // Configurar o botão de confirmação
                        $('#btnConfirmarForaHorarioAdicional').off('click').on('click', function() {
                            $('#modalConfirmacaoForaHorarioAdicional').modal('hide');
                            executarReservaAdicional();
                        });
                        
                        // Mostrar o modal
                        $('#modalConfirmacaoForaHorarioAdicional').modal('show');
                    },
                    error: function() {
                        $('#dependenteNomeAdicional').text('N/A');
                        $('#modalConfirmacaoForaHorarioAdicional').modal('show');
                    }
                });
            } else if (resposta.fora_do_horario && !resposta.permitir_atraso) {
                exibirToast('Horário limite para reservar já passou.', 'danger');
            } else {
                // Reserva normal dentro do horário
                executarReservaAdicional();
            }
        },
        error: function () {
            exibirToast('Erro ao verificar horário.', 'danger');
        }
    });
}

function executarReservaAdicional() {
    const dados = {
        data: $('#data').val(),
        quantidade: $('#quantidade').val(),
        detalhe: $('#detalhe').val(),
        tipo: $('#tipo').val(),
        dependente: $('#dependente').val()
    };

    console.log('Dados sendo enviados:', dados);

    $.ajax({
        url: '../api/almoco/reservar_adicional.php',
        method: 'POST',
        data: dados,
        dataType: 'json',
        success: function (resposta) {
            console.log('Resposta da API:', resposta);
            if (resposta.status === 'ok') {
                exibirToast('Reserva adicional salva!', 'success');
                $('#formReservaAdicional')[0].reset();
                carregarReservasAdicionais();
            } else {
                exibirToast(resposta.mensagem, 'danger');
            }
        },
        error: function (xhr, status, error) {
            console.error('Erro na requisição:', xhr.responseText);
            exibirToast('Erro ao salvar reserva.', 'danger');
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
            const lista = dados.reservas;
            const total = dados.quantidade_total;
            let html = '';
            html += `<p class="mb-2"><strong>Total de reservas adicionais do dia:</strong> ${total}</p>`;
            if (Array.isArray(lista) && lista.length > 0) {
                html += `
                <div class="table-responsive">
                  <table class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Data</th>
                        <th class="d-none d-sm-table-cell">Qtd</th>
                        <th class="d-none d-md-table-cell">Tipo</th>
                        <th class="d-none d-lg-table-cell">Detalhe</th>
                        <th class="d-none d-lg-table-cell">Dependente</th>
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
                      <td class="d-none d-lg-table-cell">${item.nome_dependente}</td>
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
            } else {
                html += '<p class="text-muted">Nenhuma reserva adicional.</p>';
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
                                exibirToast('Reserva excluída com sucesso!', 'success');
                                carregarReservasAdicionais();
                            } else {
                                exibirToast(resposta.mensagem || 'Erro ao excluir reserva.', 'danger');
                            }
                        },
                        error: function () {
                            exibirToast('Erro ao excluir reserva.', 'danger');
                        }
                    });
                }
            });
        },
        error: function () {
            exibirToast('Erro ao carregar reservas.', 'danger');
        }
    });
}


function exibirToast(mensagem, tipo = 'success') {
    // Verificar se o sistema de feedback está disponível
    if (typeof window.feedbackSystem !== 'undefined') {
        // Usar o sistema de feedback moderno
        window.feedbackSystem.show(mensagem, tipo, { duration: 4000 });
    } else if (typeof window.exibirToast !== 'undefined') {
        // Usar função global de fallback
        window.exibirToast(mensagem, tipo);
    } else {
        // Fallback simples
        console.log(`[${tipo.toUpperCase()}] ${mensagem}`);
        
        // Criar toast simples se jQuery estiver disponível
        if (typeof $ !== 'undefined') {
            const alertClass = tipo === 'success' ? 'alert-success' : 
                             tipo === 'danger' ? 'alert-danger' : 
                             tipo === 'warning' ? 'alert-warning' : 'alert-info';
            
            const toast = $(`
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    ${mensagem}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            
            $('body').append(toast);
            
            // Auto-remove após 4 segundos
            setTimeout(() => {
                toast.alert('close');
            }, 4000);
        }
    }
}


function enviarDependente(formData) {
    console.log('Iniciando envio do dependente...');
    
    $.ajax({
        url: '../api/dependentes/salvar_dependente.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (res) {
            console.log('Resposta do servidor:', res);
            if (res.status === 'ok') {
                console.log('Dependente cadastrado com sucesso!');
                $('#modalDependente').modal('hide');
                $('#formDependente')[0].reset();
                $('#previewFotoDependente').addClass('d-none');
                carregarDependentes();
                carregarSelectDependentes();
                exibirToast('Dependente cadastrado com sucesso!', 'success');
            } else {
                console.error('Erro no cadastro:', res.mensagem);
                exibirToast(res.mensagem || 'Erro ao cadastrar dependente.', 'danger');
            }
        },
        error: function (xhr, status, error) {
            console.error('Erro na requisição AJAX:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            exibirToast('Erro ao cadastrar dependente.', 'danger');
        }
    });
}


function carregarDependentes() {
    $.ajax({
        url: '../api/dependentes/listar.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            let html = '';
            if (Array.isArray(res.dados) && res.dados.length > 0) {
                res.dados.forEach(function(dependente) {
                    // Calcular idade
                    let idade = '';
                    if (dependente.data_nascimento) {
                        const hoje = new Date();
                        const nasc = new Date(dependente.data_nascimento);
                        idade = hoje.getFullYear() - nasc.getFullYear();
                        const m = hoje.getMonth() - nasc.getMonth();
                        if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) {
                            idade--;
                        }
                    }
                    
                    // Extrair primeiro nome
                    const primeiroNome = dependente.nome.split(' ')[0];
                    
                    html += `
                        <tr>
                            <td class="d-none d-md-table-cell">${dependente.nome}</td>
                            <td class="d-table-cell d-md-none">${primeiroNome}</td>
                            <td class="d-none d-lg-table-cell">${dependente.parentesco}</td>
                            <td class="d-table-cell d-md-none">${formatarData(dependente.data_nascimento)}</td>
                            <td class="d-none d-lg-table-cell">${formatarData(dependente.data_nascimento)}</td>
                            <td class="d-none d-xl-table-cell">${idade}</td>
                            <td class="d-table-cell d-md-none">
                                ${dependente.foto_base64 ? 
                                    `<img src="data:image/jpeg;base64,${dependente.foto_base64}" alt="Foto" class="img-thumbnail" style="max-width: 40px; height: 40px; object-fit: cover;">` : 
                                    '<div class="bg-secondary rounded" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">Sem</div>'}
                            </td>
                            <td class="d-none d-lg-table-cell">
                                ${dependente.foto_base64 ? 
                                    `<img src="data:image/jpeg;base64,${dependente.foto_base64}" alt="Foto" class="img-thumbnail" style="max-width: 50px;">` : 
                                    'Sem foto'}
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-primary d-none d-md-inline-block" onclick="editarDependente(${dependente.id})">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button type="button" class="btn btn-primary d-inline-block d-md-none" onclick="editarDependente(${dependente.id})" title="Editar">
                                        ✏️
                                    </button>
                                    <button type="button" class="btn btn-danger d-none d-md-inline-block" onclick="excluirDependente(${dependente.id})">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                    <button type="button" class="btn btn-danger d-inline-block d-md-none" onclick="excluirDependente(${dependente.id})" title="Excluir">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="8" class="text-center">Nenhum dependente cadastrado</td></tr>';
            }
            $('#tabelaDependentes tbody').html(html);
        },
        error: function(xhr, status, error) {
            exibirToast('Erro ao carregar dependentes', 'danger');
        }
    });
}

function editarDependente(id) {
    $.ajax({
        url: '../api/dependentes/obter.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'sucesso') {
                const dependente = res.dados;
                console.log('Dados do dependente:', dependente);
                $('#edit_id_dependente').val(dependente.id);
                $('#edit_nome_dependente').val(dependente.nome);
                $('#edit_parentesco_dependente').val(dependente.parentesco);
                if (dependente.foto_base64) {
                    $('#edit_previewFotoDependente').attr('src', 'data:image/jpeg;base64,' + dependente.foto_base64).removeClass('d-none');
                } else {
                    $('#edit_previewFotoDependente').addClass('d-none');
                }
                $('#modalEditarDependente').modal('show');
                
                // Armazenar a data em um data attribute para usar depois
                $('#modalEditarDependente').data('nascimento', dependente.nascimento);
                
                // Aguardar o modal abrir antes de preencher a data
                setTimeout(function() {
                    $('#edit_nascimento_dependente').val(dependente.nascimento);
                    console.log('Data de nascimento definida:', dependente.nascimento);
                    console.log('Valor do campo após definição:', $('#edit_nascimento_dependente').val());
                }, 300);
            } else {
                exibirToast(res.mensagem || 'Erro ao carregar dados do dependente', 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao carregar dados do dependente', 'danger');
        }
    });
}

function excluirDependente(id) {
    mostrarConfirmacao('Tem certeza que deseja excluir este dependente?', function() {
        $.ajax({
            url: '../api/dependentes/excluir.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'ok') {
                    exibirToast(res.mensagem, 'success');
                    carregarDependentes();
                    // Atualiza o select de dependentes no formulário de reserva
                    carregarSelectDependentes();
                } else {
                    exibirToast(res.mensagem, 'danger');
                }
            },
            error: function() {
                exibirToast('Erro ao excluir dependente', 'danger');
            }
        });
    });
}

// Função auxiliar para formatar data
function formatarData(data) {
    if (!data) return '';
    const [ano, mes, dia] = data.split('-');
    return `${dia}/${mes}/${ano}`;
}



// Função para carregar dados do perfil
function carregarDadosPerfil() {
    $.ajax({
        url: '../api/usuarios/buscar_perfil.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            if (res.status === 'ok') {
                const u = res.usuario;
                $('#perfil_nome').val(u.nome);
                $('#perfil_email').val(u.email);
                $('#perfil_telefone').val(u.telefone);
                $('#perfil_id_valor').val(u.grupo_nome || u.id_valor);
                $('#perfil_entidade_id').val(u.entidade_nome || u.entidade_id);
                if (u.foto_base64) {
                    $('#perfil_previewFoto').attr('src', 'data:image/jpeg;base64,' + u.foto_base64).removeClass('d-none');
                } else {
                    $('#perfil_previewFoto').addClass('d-none');
                }
                
                // Atualizar também a foto do botão de configurações
                if (u.foto_base64) {
                    const fotoHtml = `<img src="data:image/jpeg;base64,${u.foto_base64}" alt="Foto de ${u.nome}">`;
                    $('#fotoPerfilMini').html(fotoHtml);
                } else {
                    $('#fotoPerfilMini').html('<i class="bi bi-person-fill text-muted"></i>');
                }
            } else {
                exibirToast(res.mensagem || 'Erro ao carregar perfil', 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao carregar perfil', 'danger');
        }
    });
}

function executarReservaMultipla(dias) {
    let sucesso = 0, erro = 0;
    let errosDetalhados = [];
    
    // Enviar todas as datas em um único POST
    $.ajax({
        url: '../api/almoco/reservar_multiplo.php',
        method: 'POST',
        data: { datas: dias },
        dataType: 'json',
        success: function(res) {
            if (res && Array.isArray(res.resultados)) {
                res.resultados.forEach(function(r) {
                    if (r.status === 'ok') {
                        sucesso++;
                    } else {
                        erro++;
                        errosDetalhados.push(`<li><strong>${r.data}:</strong> ${r.mensagem}</li>`);
                    }
                });
            } else {
                erro = dias.length;
                errosDetalhados.push('<li>Erro inesperado ao processar as reservas.</li>');
            }
            $('#modalReservaMultipla').modal('hide');
            let msg = `<div class='alert alert-success'>Reservas realizadas: ${sucesso}`;
            if (erro > 0) {
                msg += ` <span class='text-danger'>Falhas: ${erro}</span>`;
                msg += '<ul class="mb-0 small">' + errosDetalhados.join('') + '</ul>';
            }
            msg += '</div>';
            $('#mensagemPropria').html(msg);
            verificarStatusReserva();
            carregarReservasAdicionais();
        },
        error: function() {
            $('#modalReservaMultipla').modal('hide');
            $('#mensagemPropria').html('<div class="alert alert-danger">Erro ao processar reservas múltiplas.</div>');
        }
    });
}

function executarReservaMultiplaDependentes(dependentes, datas) {
    // Mostrar loading
    const btnSubmit = $('#formReservaMultipla button[type="submit"]');
    const textoOriginal = btnSubmit.html();
    btnSubmit.html('<i class="bi bi-hourglass-split"></i> Processando...').prop('disabled', true);

    $.ajax({
        url: '../api/almoco/reservar_multiplo_dependentes.php',
        method: 'POST',
        data: {
            dependentes: dependentes,
            data_inicio: $('#data_inicio_dependentes').val(),
            data_fim: $('#data_fim_dependentes').val()
        },
        dataType: 'json',
        success: function(resposta) {
            if (resposta.status === 'ok') {
                // Processar resultados detalhados
                let sucessos = 0;
                let erros = 0;
                let mensagens = [];

                resposta.resultados.forEach(function (resultado) {
                    if (resultado.status === 'ok') {
                        sucessos++;
                    } else {
                        erros++;
                        mensagens.push(`${resultado.dependente} - ${resultado.data}: ${resultado.mensagem}`);
                    }
                });

                // Exibir resumo detalhado
                let mensagem = `Reservas processadas: ${sucessos} sucessos`;
                if (erros > 0) {
                    mensagem += `, ${erros} erros`;
                }

                exibirToast(mensagem, erros > 0 ? 'warning' : 'success');
                
                // Se houver erros, mostrar detalhes
                if (erros > 0 && mensagens.length > 0) {
                    let detalhes = '<div class="alert alert-warning mt-2"><strong>Detalhes dos erros:</strong><ul class="mb-0 small">';
                    mensagens.forEach(function(msg) {
                        detalhes += `<li>${msg}</li>`;
                    });
                    detalhes += '</ul></div>';
                    $('#mensagemAdicional').html(detalhes);
                }
                
                // Fechar modal e atualizar interface
                $('#modalReservaMultipla').modal('hide');
                carregarReservasAdicionais();
            } else {
                exibirToast(resposta.mensagem, 'danger');
            }
        },
        error: function () {
            exibirToast('Erro ao processar reservas múltiplas de dependentes', 'danger');
        },
        complete: function () {
            // Restaurar botão
            btnSubmit.html(textoOriginal).prop('disabled', false);
        }
    });
}

function carregarDependentesMultiplos() {
    $.ajax({
        url: '../api/dependentes/listar.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            const container = $('#listaDependentesMultiplos');
            container.empty();
            
            if (Array.isArray(res.dados) && res.dados.length > 0) {
                res.dados.forEach(function(dep) {
                    const idade = calcularIdade(dep.data_nascimento);
                    const cobrarTexto = dep.cobrar == 1 ? ' (Gratuito)' : ' (Cobrar)';
                    const idadeTexto = idade === 'N/A' ? 'Idade não informada' : `${idade} anos`;
                    
                    container.append(`
                        <div class="form-check">
                            <input class="form-check-input dependente-checkbox" type="checkbox" value="${dep.id}" id="dep_${dep.id}">
                            <label class="form-check-label" for="dep_${dep.id}">
                                <strong>${dep.nome}</strong> - ${dep.parentesco} (${idadeTexto})${cobrarTexto}
                            </label>
                        </div>
                    `);
                });
                
                // Adicionar event listener para atualizar o botão "Selecionar Todos"
                $('.dependente-checkbox').on('change', function() {
                    const totalCheckboxes = $('.dependente-checkbox').length;
                    const checkboxesMarcados = $('.dependente-checkbox:checked').length;
                    
                    if (checkboxesMarcados === 0) {
                        $('#btnSelecionarTodos').html('<i class="bi bi-check-all"></i> Selecionar Todos');
                    } else if (checkboxesMarcados === totalCheckboxes) {
                        $('#btnSelecionarTodos').html('<i class="bi bi-x-lg"></i> Desmarcar Todos');
                    } else {
                        $('#btnSelecionarTodos').html('<i class="bi bi-check-all"></i> Selecionar Todos');
                    }
                });
            } else {
                container.html('<p class="text-muted">Nenhum dependente cadastrado</p>');
            }
        },
        error: function() {
            $('#listaDependentesMultiplos').html('<p class="text-danger">Erro ao carregar dependentes</p>');
        }
    });
}

function calcularIdade(dataNascimento) {
    if (!dataNascimento || dataNascimento === '0000-00-00' || dataNascimento === 'null') {
        return 'N/A';
    }
    
    try {
        const hoje = new Date();
        const nascimento = new Date(dataNascimento);
        
        // Verificar se a data é válida
        if (isNaN(nascimento.getTime())) {
            return 'N/A';
        }
        
        let idade = hoje.getFullYear() - nascimento.getFullYear();
        const mesAtual = hoje.getMonth();
        const mesNascimento = nascimento.getMonth();
        
        if (mesAtual < mesNascimento || (mesAtual === mesNascimento && hoje.getDate() < nascimento.getDate())) {
            idade--;
        }
        
        return idade;
    } catch (error) {
        return 'N/A';
    }
}

function enviarEdicaoDependente(formData) {
    $.ajax({
        url: '../api/dependentes/editar.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res) {
            if (res.status === 'ok') {
                exibirToast(res.mensagem, 'success');
                $('#modalEditarDependente').modal('hide');
                carregarDependentes();
                // Atualiza o select de dependentes no formulário de reserva
                carregarSelectDependentes();
            } else {
                exibirToast(res.mensagem, 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao atualizar dependente', 'danger');
        }
    });
}

function carregarSelectDependentes() {
    $.ajax({
        url: '../api/dependentes/listar.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
        const select = $('#dependente');
        select.empty().append('<option value="">Selecione um dependente</option>');
            if (Array.isArray(res.dados) && res.dados.length > 0) {
                res.dados.forEach(function(dep) {
            select.append(`<option value="${dep.id}" data-nascimento="${dep.data_nascimento || ''}">${dep.nome}</option>`);
        });
            }
        },
        error: function(xhr, status, error) {
            exibirToast('Erro ao carregar dependentes', 'danger');
}
    });
}

function enviarPerfilUsuario(formData) {
    $.ajax({
        url: '../api/usuarios/atualizar_perfil.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res) {
            if (res.status === 'ok') {
                exibirToast('Perfil atualizado com sucesso!', 'success');
                // Atualiza a foto exibida no perfil
                if (res.foto_base64) {
                    $('#perfil_previewFoto').attr('src', 'data:image/jpeg;base64,' + res.foto_base64).removeClass('d-none');
                } else {
                    $('#perfil_previewFoto').addClass('d-none');
                }
                
                // Atualiza também a foto do botão de configurações
                if (res.foto_base64) {
                    const fotoHtml = `<img src="data:image/jpeg;base64,${res.foto_base64}" alt="Foto do usuário">`;
                    $('#fotoPerfilMini').html(fotoHtml);
                } else {
                    $('#fotoPerfilMini').html('<i class="bi bi-person-fill text-muted"></i>');
                }
                
                // Verificar se deve atualizar no facial (apenas se foto foi alterada)
                if (formData.has('foto_base64')) {
                    verificarEAtualizarFacial();
                }
            } else {
                exibirToast(res.mensagem || 'Erro ao atualizar perfil', 'danger');
            }
        },
        error: function() {
            exibirToast('Erro ao atualizar perfil', 'danger');
        }
    });
}

// Função para carregar reservas do usuário
function carregarReservasUsuario(filtroInicio, filtroFim) {
    const hoje = new Date();
    const ano = hoje.getFullYear();
    const mes = String(hoje.getMonth() + 1).padStart(2, '0');
    const primeiroDia = `${ano}-${mes}-01`;
    const ultimoDia = new Date(ano, hoje.getMonth() + 1, 0);
    const ultimoDiaStr = `${ano}-${mes}-${String(ultimoDia.getDate()).padStart(2, '0')}`;
    const dataInicio = filtroInicio || primeiroDia;
    const dataFim = filtroFim || ultimoDiaStr;
    console.log('Carregando reservas do usuário:', { dataInicio, dataFim });
    
    $.ajax({
        url: '../api/almoco/listar_reservas_usuario.php',
        method: 'GET',
        data: { data_inicio: dataInicio, data_fim: dataFim },
        dataType: 'json',
        success: function(res) {
            console.log('Resposta da API de reservas:', res);
            if (res.status === 'ok') {
                const tbody = $('#tabelaReservasUsuario tbody');
                tbody.empty();
                if (res.reservas.length === 0) {
                    tbody.append('<tr><td colspan="4" class="text-center text-muted">Nenhuma reserva encontrada.</td></tr>');
                } else {
                    res.reservas.forEach(function(r) {
                        let btnExcluir = '';
                        if (r.pode_excluir) {
                            btnExcluir = `<button class='btn btn-sm btn-danger btn-excluir-reserva' data-id='${r.id}' data-data='${r.data}'><i class='bi bi-trash'></i> Excluir</button>`;
                        }
                        
                        // Definir cor do status
                        let statusClass = '';
                        let statusText = r.status || 'N/A';
                        switch (r.status) {
                            case 'Finalizada':
                                statusClass = 'badge bg-secondary';
                                break;
                            case 'Futura':
                                statusClass = 'badge bg-info';
                                break;
                            case 'Atual':
                                statusClass = 'badge bg-success';
                                break;
                            default:
                                statusClass = 'badge bg-light text-dark';
                        }
                        
                        tbody.append(`
                            <tr>
                                <td>${formatarData(r.data)}</td>
                                <td><span class="${statusClass}">${statusText}</span></td>
                                <td>R$ ${parseFloat(r.valor || 0).toFixed(2)}</td>
                                <td>${btnExcluir}</td>
                            </tr>
                        `);
                    });
                }
            } else {
                console.error('Erro na API:', res.mensagem);
                $('#tabelaReservasUsuario tbody').html('<tr><td colspan="4" class="text-center text-danger">Erro ao carregar reservas.</td></tr>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro na requisição:', xhr.responseText);
            $('#tabelaReservasUsuario tbody').html('<tr><td colspan="4" class="text-center text-danger">Erro ao carregar reservas.</td></tr>');
        }
    });
}

// Evento para filtro de reservas
$(document).on('submit', '#formFiltroReservas', function(e) {
    e.preventDefault();
    const inicio = $('#filtroDataInicio').val();
    const fim = $('#filtroDataFim').val();
    carregarReservasUsuario(inicio, fim);
});

// Sistema de confirmação personalizado
let confirmacaoCallback = null;

function mostrarConfirmacao(texto, callback) {
    $('#modalConfirmacaoTexto').text(texto);
    confirmacaoCallback = callback;
    $('#modalConfirmacao').modal('show');
}

// Evento para confirmar ação no modal
$('#btnConfirmarAcao').on('click', function() {
    if (confirmacaoCallback) {
        confirmacaoCallback();
        $('#modalConfirmacao').modal('hide');
        confirmacaoCallback = null;
    }
});

// Evento para excluir reserva
$(document).on('click', '.btn-excluir-reserva', function() {
    const id = $(this).data('id');
    const data = $(this).data('data');
    
    mostrarConfirmacao(`Tem certeza que deseja excluir a reserva do dia ${formatarData(data)}?`, function() {
        $.ajax({
            url: '../api/almoco/cancelar.php',
            method: 'POST',
            data: { data: data },
            dataType: 'json',
            success: function(resposta) {
                if (resposta.status === 'ok') {
                    exibirToast('Reserva excluída com sucesso!', 'success');
                    carregarReservasUsuario(); // Recarregar a lista do modal
                    
                    // Atualizar página principal
                    verificarStatusReserva(); // Atualizar botão de reserva
                    carregarReservasAdicionais(); // Atualizar lista de reservas adicionais
                } else {
                    exibirToast(resposta.mensagem || 'Erro ao excluir reserva', 'danger');
                }
            },
            error: function() {
                exibirToast('Erro ao excluir reserva', 'danger');
            }
        });
    });
});

// Evento para excluir reserva adicional
$(document).on('click', '.btn-excluir-reserva-adicional', function() {
    const id = $(this).data('id');
    const data = $(this).data('data');
    
    mostrarConfirmacao('Tem certeza que deseja excluir esta reserva adicional?', function() {
        $.ajax({
            url: '../api/almoco/excluir_reserva_adicional.php',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(resposta) {
                if (resposta.status === 'ok') {
                    exibirToast('Reserva adicional excluída com sucesso!', 'success');
                    carregarReservasAdicionaisUsuario(); // Recarregar a lista do modal
                    
                    // Atualizar página principal
                    carregarReservasAdicionais(); // Atualizar lista de reservas adicionais na página principal
                } else {
                    exibirToast(resposta.mensagem || 'Erro ao excluir reserva adicional', 'danger');
                }
            },
            error: function() {
                exibirToast('Erro ao excluir reserva adicional', 'danger');
            }
        });
    });
});

// Carregar reservas ao abrir a aba
$(document).on('shown.bs.tab', '#reservas-tab', function () {
    // Limpa filtros
    $('#filtroDataInicio').val('');
    $('#filtroDataFim').val('');
    carregarReservasUsuario();
});

// Evento de exclusão de reserva (removido - usando o sistema de modal personalizado)

// Evento para filtro de reservas adicionais
$(document).on('submit', '#formFiltroReservasAdicionais', function(e) {
    e.preventDefault();
    const dataInicio = $('#filtroDataInicioAdicionais').val();
    const dataFim = $('#filtroDataFimAdicionais').val();
    const dependente = $('#filtroDependenteAdicionais').val();
    carregarReservasAdicionaisUsuario(dataInicio, dataFim, dependente);
});

// Carregar reservas adicionais ao abrir a aba
$(document).on('shown.bs.tab', '#reservas-adicionais-tab', function () {
    // Limpa filtros
    $('#filtroDataInicioAdicionais').val('');
    $('#filtroDataFimAdicionais').val('');
    $('#filtroDependenteAdicionais').val('');
    carregarReservasAdicionaisUsuario();
    carregarSelectDependentesFiltro();
});

// Evento de exclusão de reserva adicional (removido - usando o sistema de modal personalizado)

// Função para carregar reservas adicionais do usuário
function carregarReservasAdicionaisUsuario(dataInicio, dataFim, dependente) {
    const hoje = new Date();
    const ano = hoje.getFullYear();
    const mes = String(hoje.getMonth() + 1).padStart(2, '0');
    const primeiroDia = `${ano}-${mes}-01`;
    const ultimoDia = new Date(ano, hoje.getMonth() + 1, 0);
    const ultimoDiaStr = `${ano}-${mes}-${String(ultimoDia.getDate()).padStart(2, '0')}`;
    
    const params = {
        data_inicio: dataInicio || primeiroDia,
        data_fim: dataFim || ultimoDiaStr
    };
    
    if (dependente) {
        params.dependente = dependente;
    }
    
    $.ajax({
        url: '../api/almoco/listar_reservas_adicionais_usuario.php',
        method: 'GET',
        data: params,
        dataType: 'json',
        success: function(res) {
            if (res.status === 'ok') {
                const tbody = $('#tabelaReservasAdicionaisUsuario tbody');
                tbody.empty();
                
                if (res.reservas.length === 0) {
                    tbody.append('<tr><td colspan="4" class="text-center text-muted d-table-cell d-md-none">Nenhuma reserva adicional encontrada.</td></tr>');
                    tbody.append('<tr><td colspan="8" class="text-center text-muted d-none d-md-table-cell">Nenhuma reserva adicional encontrada.</td></tr>');
                } else {
                    res.reservas.forEach(function(r) {
                        let btnExcluir = '';
                        if (r.pode_excluir) {
                            btnExcluir = `<button class='btn btn-sm btn-danger btn-excluir-reserva-adicional' data-id='${r.id}' data-data='${r.data}'><i class='bi bi-trash'></i> Excluir</button>`;
                        }
                        
                        const tipoClass = r.tipo === 'marmitex' ? 'badge bg-warning' : 'badge bg-primary';
                        
                        // Extrair primeiro nome
                        const primeiroNome = r.dependente_nome.split(' ')[0];
                        
                        // Definir cor do status
                        let statusClass = '';
                        let statusText = r.status || 'N/A';
                        switch (r.status) {
                            case 'Finalizada':
                                statusClass = 'badge bg-secondary';
                                break;
                            case 'Futura':
                                statusClass = 'badge bg-info';
                                break;
                            case 'Atual':
                                statusClass = 'badge bg-success';
                                break;
                            default:
                                statusClass = 'badge bg-light text-dark';
                        }
                        
                        tbody.append(`
                            <tr>
                                <td class="d-table-cell d-md-table-cell">${formatarData(r.data)}</td>
                                <td class="d-table-cell d-md-table-cell"><strong>${primeiroNome}</strong></td>
                                <td class="d-none d-md-table-cell"><strong>${r.dependente_nome}</strong></td>
                                <td class="d-none d-md-table-cell"><span class="${tipoClass}">${r.tipo}</span></td>
                                <td class="d-table-cell d-md-table-cell">R$ ${parseFloat(r.valor_total || 0).toFixed(2)}</td>
                                <td class="d-none d-md-table-cell">${r.data_cadastro || 'N/A'}</td>
                                <td class="d-none d-md-table-cell"><span class="${statusClass}">${statusText}</span></td>
                                <td class="d-table-cell d-md-table-cell">${btnExcluir}</td>
                            </tr>
                        `);
                    });
                }
            } else {
                $('#tabelaReservasAdicionaisUsuario tbody').html('<tr><td colspan="4" class="text-center text-danger d-table-cell d-md-none">Erro ao carregar reservas adicionais.</td></tr><tr><td colspan="8" class="text-center text-danger d-none d-md-table-cell">Erro ao carregar reservas adicionais.</td></tr>');
            }
        },
        error: function() {
            $('#tabelaReservasAdicionaisUsuario tbody').html('<tr><td colspan="4" class="text-center text-danger d-table-cell d-md-none">Erro ao carregar reservas adicionais.</td></tr><tr><td colspan="8" class="text-center text-danger d-none d-md-table-cell">Erro ao carregar reservas adicionais.</td></tr>');
        }
    });
}

// Função para carregar select de dependentes no filtro
function carregarSelectDependentesFiltro() {
    $.ajax({
        url: '../api/dependentes/listar.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            const select = $('#filtroDependenteAdicionais');
            select.empty().append('<option value="">Todos os dependentes</option>');
            
            if (Array.isArray(res.dados) && res.dados.length > 0) {
                res.dados.forEach(function(dep) {
                    select.append(`<option value="${dep.id}">${dep.nome} - ${dep.parentesco}</option>`);
                });
            }
        },
        error: function() {
            exibirToast('Erro ao carregar dependentes para filtro', 'danger');
        }
    });
}

// Função para carregar a foto do perfil no botão de configurações
function carregarFotoPerfil() {
    $.ajax({
        url: '../api/usuarios/buscar_perfil.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            if (res.status === 'ok' && res.usuario.foto_base64) {
                // Usuário tem foto - mostrar a imagem
                const fotoHtml = `<img src="data:image/jpeg;base64,${res.usuario.foto_base64}" alt="Foto de ${res.usuario.nome}">`;
                $('#fotoPerfilMini').html(fotoHtml);
            } else {
                // Usuário não tem foto - manter o ícone padrão
                $('#fotoPerfilMini').html('<i class="bi bi-person-fill text-muted"></i>');
            }
        },
        error: function() {
            // Em caso de erro, manter o ícone padrão
            $('#fotoPerfilMini').html('<i class="bi bi-person-fill text-muted"></i>');
        }
    });
}

// Função para verificar se pode atualizar no facial
function verificarEAtualizarFacial() {
    $.ajax({
        url: '../api/facial/verificar_atualizacao_foto.php',
        method: 'GET',
        dataType: 'json',
        success: function(resposta) {
            if (resposta.pode_atualizar) {
                // Pode atualizar no facial - fazer atualização silenciosa
                atualizarFotoFacial();
            }
            // Se não pode atualizar, não faz nada (processo silencioso)
        },
        error: function() {
            // Em caso de erro, não informa o usuário (processo silencioso)
            console.log('Erro ao verificar atualização facial');
        }
    });
    
    // Sempre sincronizar com dispositivos de culto quando a foto for atualizada
    if (typeof usuario_id !== 'undefined') {
        sincronizarUsuarioCulto(usuario_id);
    } else {
        // Tentar obter o ID do usuário da sessão
        $.ajax({
            url: '../api/usuarios/obter_id_usuario.php',
            method: 'GET',
            dataType: 'json',
            success: function (resposta) {
                if (resposta.usuario_id) {
                    sincronizarUsuarioCulto(resposta.usuario_id);
                }
            },
            error: function () {
                console.log('Erro ao obter ID do usuário para sincronização');
            }
        });
    }
}

// Função para atualizar foto no dispositivo facial
function atualizarFotoFacial() {
    $.ajax({
        url: '../api/facial/atualizar_foto.php',
        method: 'POST',
        dataType: 'json',
        success: function(resposta) {
            // Processo silencioso - não informa o usuário sobre o resultado
            if (resposta.status === 'ok') {
                console.log('Foto atualizada com sucesso no dispositivo facial');
            } else {
                console.log('Erro ao atualizar foto no dispositivo facial:', resposta.mensagem);
            }
        },
        error: function() {
            // Em caso de erro, não informa o usuário (processo silencioso)
            console.log('Erro ao atualizar foto no dispositivo facial');
        }
    });
}

// Função para sincronizar usuário com dispositivos de culto
function sincronizarUsuarioCulto(usuarioId) {
    $.ajax({
        url: '../api/culto/sincronizar_usuario_individual.php',
        method: 'POST',
        data: { usuario_id: usuarioId },
        dataType: 'json',
        success: function (resposta) {
            if (resposta.status === 'success') {
                console.log('Usuário sincronizado com dispositivos de culto:', resposta.data);
            } else {
                console.log('Erro ao sincronizar com dispositivos de culto:', resposta.message);
            }
        },
        error: function () {
            console.log('Erro na requisição de sincronização com dispositivos de culto');
        }
    });
}


