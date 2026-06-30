$(document).ready(function() {
    // Variáveis globais
    let usuarios = [];
    let usuarioEditando = null;
    
    // Inicializar
    carregarUsuarios();
    carregarGruposValor();
    carregarEntidades();
    
    // Carregar usuários
    function carregarUsuarios(preservarFiltros = false) {
        // Mostrar loading
        $('#loadingUsuarios').fadeIn(200);
        
        $.ajax({
            url: '../api/usuarios/listar.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'sucesso') {
                    usuarios = response.dados || [];
                    if (preservarFiltros) {
                        // Aplicar filtros existentes após recarregar
                        filtrarUsuarios();
                    } else {
                        atualizarTabelaUsuarios();
                    }
                }
            },
            error: function() {
                console.error('Erro ao carregar usuários');
                exibirToast('Erro ao carregar usuários', 'error');
            },
            complete: function() {
                // Esconder loading após um pequeno delay para melhor UX
                setTimeout(function() {
                    $('#loadingUsuarios').fadeOut(200);
                }, 300);
            }
        });
    }
    
    // Função para recarregar usuários preservando filtros
    function recarregarUsuariosComFiltros() {
        const filtroNome = $('#filtroNome').val().trim();
        const filtroCategoria = $('#filtroCategoria').val();
        const filtroStatus = $('#filtroStatus').val();
        
        // Se há filtros ativos, preservar; senão, recarregar tudo
        if (filtroNome || filtroCategoria || filtroStatus) {
            carregarUsuarios(true);
        } else {
            carregarUsuarios(false);
        }
    }
    
    
    // Novo usuário
    $('#btnNovoUsuario').click(function() {
        usuarioEditando = null;
        $('#modalUsuario').modal('show');
        $('#formUsuario')[0].reset();
        $('#previewFoto').hide(); // Esconder foto no novo usuário
        $('#modalUsuario .modal-title').text('Novo Usuário');
    });
    
    // Editar usuário
    $(document).on('click', '.btn-editar', function() {
    const id = $(this).data('id');
        usuarioEditando = usuarios.find(u => u.id == id);
        
        if (usuarioEditando) {
            $('#nome').val(usuarioEditando.nome);
            $('#email').val(usuarioEditando.email);
            $('#categoria').val(usuarioEditando.categoria || '');
            $('#culto').prop('checked', usuarioEditando.culto == 1);
            $('#telefone').val(usuarioEditando.telefone || '');
            $('#cpf').val(usuarioEditando.cpf || '');
            $('#id_valor').val(usuarioEditando.id_valor || '');
            $('#entidade_id').val(usuarioEditando.entidade_id || '');
            
            // Exibir foto se existir
            if (usuarioEditando.foto_base64) {
                $('#previewFoto').attr('src', 'data:image/jpeg;base64,' + usuarioEditando.foto_base64).show();
                $('#previewFoto').on('error', function() {
                    console.error('Erro ao carregar foto do usuário');
                    $(this).hide();
                });
            } else {
                $('#previewFoto').hide();
            }
            
            $('#modalUsuario .modal-title').text('Editar Usuário');
            $('#modalUsuario').modal('show');
        }
    });
    
    // Notificar usuário
    $(document).on('click', '.btn-notificar', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Botão de notificação clicado');
        
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        const btn = $(this);
        
        console.log('ID do usuário:', id);
        console.log('Nome do usuário:', nome);
        
        if (!id || !nome) {
            console.error('ID ou nome do usuário não encontrado');
            exibirToast('Erro: Dados do usuário não encontrados', 'error');
            return;
        }
        
        // Usar modal de confirmação personalizado do sistema
        mostrarConfirmacao(
            `Deseja enviar notificação para "${nome}"?<br><br><small>O sistema enviará por WhatsApp se houver telefone cadastrado, caso contrário enviará por email.</small>`,
            function() {
                console.log('Confirmação aceita, enviando notificação...');
                
                // Desabilitar botão durante envio
                btn.prop('disabled', true).html('⏳');
                
                $.ajax({
                    url: '../api/notificacao/notificar_usuario.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ usuario_id: id }),
                    dataType: 'json',
                    success: function(response) {
                        console.log('Resposta da API:', response);
                        if (response.status === 'ok') {
                            const metodo = response.metodo === 'whatsapp' ? 'WhatsApp' : 'Email';
                            let mensagem = `Notificação enviada com sucesso via ${metodo}!`;
                            if (response.aviso) {
                                mensagem += '\n\n' + response.aviso;
                            }
                            exibirToast(mensagem, 'success');
                        } else {
                            exibirToast('Erro: ' + (response.mensagem || 'Erro desconhecido'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro ao enviar notificação:', error);
                        console.error('Status:', status);
                        console.error('Resposta:', xhr.responseText);
                        exibirToast('Erro ao enviar notificação. Tente novamente.', 'error');
                    },
                    complete: function() {
                        // Reabilitar botão
                        btn.prop('disabled', false).html('📧');
                    }
                });
            }
        );
    });
    
    // Excluir usuário
    $(document).on('click', '.btn-excluir', function() {
        const id = $(this).data('id');
        const usuario = usuarios.find(u => u.id == id);

        if (usuario) {
            mostrarConfirmacao(`Tem certeza que deseja excluir o usuário "${usuario.nome}"?`, function() {
                $.ajax({
                    url: '../api/usuarios/excluir.php',
                    method: 'GET',
                    data: { id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'ok') {
                            exibirToast('Usuário excluído com sucesso!', 'success');
                            recarregarUsuariosComFiltros();
                        } else {
                            exibirToast('Erro: ' + response.mensagem, 'error');
                        }
                    },
                    error: function() {
                        exibirToast('Erro ao excluir usuário', 'error');
                    }
                });
            });
        } else {
            exibirToast('Usuário não encontrado', 'error');
        }
    });

    // Reativar usuário (substitui o lixo cinza quando o usuário está inativo)
    $(document).on('click', '.btn-reativar', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome') || 'este usuário';
        mostrarConfirmacao(`Reativar o usuário "${nome}"?`, function() {
            $.ajax({
                url: '../api/usuarios/reativar.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        exibirToast(response.mensagem || 'Usuário reativado!', 'success');
                        recarregarUsuariosComFiltros();
                    } else {
                        exibirToast('Erro: ' + (response.mensagem || 'falha ao reativar'), 'error');
                    }
                },
                error: function() {
                    exibirToast('Erro ao reativar usuário', 'error');
                }
            });
        });
    });
    
    // Gerenciar dependentes
    $(document).on('click', '.btn-dependentes', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        abrirModalDependentes(id, nome);
    });
    
    // Salvar usuário
    $('#formUsuario').submit(function(e) {
        e.preventDefault();
        
        const dados = {
            nome: $('#nome').val(),
            email: $('#email').val(),
            categoria: $('#categoria').val(),
            culto: $('#culto').is(':checked') ? 1 : 0,
            telefone: $('#telefone').val(),
            cpf: $('#cpf').val(),
            id_valor: $('#id_valor').val(),
            entidade_id: $('#entidade_id').val()
        };
        
        // Debug: log dos dados sendo enviados
        console.log('Dados a serem enviados:', dados);
        console.log('Telefone capturado:', dados.telefone);
        
        // Adicionar senha se preenchida
        if ($('#senha').val()) {
            dados.senha = $('#senha').val();
        }
        
        // Validações
        if (!dados.nome || !dados.email) {
            exibirToast('Preencha os campos obrigatórios', 'danger');
            return;
        }
        
        // Se é um novo usuário, senha é obrigatória
        if (!usuarioEditando && !$('#senha').val()) {
            exibirToast('Senha é obrigatória para novos usuários', 'danger');
            return;
        }
        
        if (usuarioEditando) {
            dados.id = usuarioEditando.id;
        }
        
        // Processar foto se selecionada
        const fotoInput = $('#foto')[0];
        if (fotoInput.files.length > 0) {
            const file = fotoInput.files[0];
            console.log('Arquivo selecionado:', file.name, 'Tipo:', file.type, 'Tamanho:', file.size, 'bytes');
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                console.log('Arquivo lido com sucesso, iniciando compressão...');
                // Comprimir a imagem antes de enviar
                comprimirImagem(e.target.result, function(fotoComprimida) {
                    console.log('Compressão concluída, tamanho final:', fotoComprimida.length, 'caracteres');
                    dados.foto_base64 = fotoComprimida;
                    enviarDados(dados);
                });
            };
            
            reader.onerror = function() {
                console.error('Erro ao ler arquivo');
                exibirToast('Erro ao ler arquivo de foto', 'error');
            };
            
            reader.readAsDataURL(file);
        } else {
            // Se não há nova foto, enviar dados diretamente
            enviarDados(dados);
        }
    });
    
    // Função para enviar dados
    function enviarDados(dados) {
        $.ajax({
            url: '../api/usuarios/salvar.php',
            method: 'POST',
            data: dados,
            dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        exibirToast('Usuário salvo com sucesso!', 'success');
                        $('#modalUsuario').modal('hide');
                        recarregarUsuariosComFiltros();
                        
                        // Verificar se deve sincronizar foto no facial
                        if (usuarioEditando && $('#foto')[0].files.length > 0) {
                            verificarReservaESincronizar(usuarioEditando.id);
                        }
                    } else {
                        exibirToast('Erro: ' + response.mensagem, 'danger');
                    }
                },
            error: function() {
                exibirToast('Erro ao salvar usuário', 'danger');
            }
        });
    }
    
    // Máscara para CPF
    $('#cpf').mask('000.000.000-00');
    
    // Máscara para telefone
    //$('#telefone').mask('(00) 00000-0000');
    
    // Preview da foto
    $('#foto').change(function() {
        const file = this.files[0];
        if (file) {
            // Verificar tipo de arquivo
            if (!file.type.startsWith('image/')) {
                exibirToast('Por favor, selecione um arquivo de imagem válido', 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#previewFoto').attr('src', e.target.result).show();
                console.log('Preview da foto carregado com sucesso');
            };
            reader.onerror = function() {
                console.error('Erro ao carregar arquivo para preview');
                exibirToast('Erro ao carregar arquivo para preview', 'error');
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Função para comprimir imagem (versão melhorada)
    function comprimirImagem(base64Data, callback) {
        console.log('Iniciando compressão de imagem...');
        
        const img = new Image();
        img.onload = function() {
            console.log('Imagem carregada, dimensões originais:', img.width, 'x', img.height);
            
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // Calcular novas dimensões mantendo proporção
            let { width, height } = img;
            const maxWidth = 600;
            const maxHeight = 800;
            
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
            
            // Configurar contexto para melhor qualidade
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            
            // Desenhar imagem redimensionada
            ctx.drawImage(img, 0, 0, width, height);
            console.log('Imagem desenhada no canvas');
            
            // Comprimir com qualidade ajustável
            let qualidade = 0.85; // Qualidade inicial mais conservadora
            let imagemComprimida = canvas.toDataURL('image/jpeg', qualidade);
            
            // Verificar tamanho e ajustar qualidade se necessário
            const tamanhoKB = (imagemComprimida.length * 0.75) / 1024; // Aproximação do tamanho em KB
            console.log('Tamanho inicial com qualidade', qualidade, ':', tamanhoKB.toFixed(2), 'KB');
            
            if (tamanhoKB > 300) {
                console.log('Tamanho maior que 300KB, reduzindo qualidade...');
                // Reduzir qualidade até ficar abaixo de 300KB, mas não menos que 0.6
                const qualidades = [0.8, 0.75, 0.7, 0.65, 0.6];
                
                for (let q of qualidades) {
                    imagemComprimida = canvas.toDataURL('image/jpeg', q);
                    const novoKB = (imagemComprimida.length * 0.75) / 1024;
                    console.log('Tentativa com qualidade', q, ':', novoKB.toFixed(2), 'KB');
                    
                    if (novoKB <= 300) {
                        console.log('✅ Qualidade final:', q, 'Tamanho:', novoKB.toFixed(2), 'KB');
                        break;
                    }
                }
            } else {
                console.log('✅ Tamanho já está dentro do limite de 300KB');
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
        
        img.src = base64Data;
    }
    
    // Filtros
    $('#filtroNome').on('input', function() {
        filtrarUsuarios();
    });
    
    $('#filtroCategoria').change(function() {
        filtrarUsuarios();
    });
    
    $('#filtroStatus').change(function() {
        filtrarUsuarios();
    });
    
    // Função para filtrar usuários
    function filtrarUsuarios() {
        // Mostrar loading durante a filtragem
        $('#loadingUsuarios').fadeIn(200);
        
        // Usar setTimeout para dar feedback visual mesmo que a filtragem seja rápida
        setTimeout(function() {
            const nome = $('#filtroNome').val().toLowerCase();
            const categoria = $('#filtroCategoria').val();
            const status = $('#filtroStatus').val();
            
            const usuariosFiltrados = usuarios.filter(function(usuario) {
                const matchNome = !nome || usuario.nome.toLowerCase().includes(nome);
                const matchCategoria = !categoria || usuario.categoria === categoria;
                const matchStatus = !status || (status === '1' ? usuario.ativo : !usuario.ativo);
                
                return matchNome && matchCategoria && matchStatus;
            });
            
            atualizarTabelaUsuarios(usuariosFiltrados);
            
            // Esconder loading após atualizar a tabela
            setTimeout(function() {
                $('#loadingUsuarios').fadeOut(200);
            }, 200);
        }, 100);
    }
    
    // Atualizar tabela com dados filtrados
    function atualizarTabelaUsuarios(dadosUsuarios = usuarios) {
        const tbody = $('#tabelaUsuarios tbody');
        tbody.empty();
        
        if (dadosUsuarios.length === 0) {
            tbody.append('<tr><td colspan="6" class="text-center">Nenhum usuário encontrado</td></tr>');
            return;
        }
        
        dadosUsuarios.forEach(function(usuario) {
            // Criar miniatura da foto
            let fotoHtml = '<div class="text-center"><i class="bi bi-person-circle text-muted" style="font-size: 2rem;"></i></div>';
            if (usuario.foto_base64) {
                fotoHtml = `<img src="data:image/jpeg;base64,${usuario.foto_base64}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" alt="Foto do usuário" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"><div class="text-center" style="display: none;"><i class="bi bi-person-circle text-muted" style="font-size: 2rem;"></i></div>`;
            }
            
            // Verificar se o usuário está ativo
            const isAtivo = usuario.status === 'Ativo';
            const botaoEditar = isAtivo ? 
                `<button class="btn btn-sm btn-primary btn-editar" data-id="${usuario.id}" title="Editar usuário">✏️</button>` :
                `<button class="btn btn-sm btn-secondary" disabled title="Usuário inativo - não é possível editar">✏️</button>`;
            
            const botaoDependentes = isAtivo ? 
                `<button class="btn btn-sm btn-info btn-dependentes" data-id="${usuario.id}" data-nome="${usuario.nome}" title="Gerenciar dependentes">👥</button>` :
                `<button class="btn btn-sm btn-secondary" disabled title="Usuário inativo - não é possível gerenciar dependentes">👥</button>`;
            
            const botaoExcluir = isAtivo ?
                `<button class="btn btn-sm btn-danger btn-excluir" data-id="${usuario.id}" title="Inativar usuário">🗑️</button>` :
                `<button class="btn btn-sm btn-success btn-reativar" data-id="${usuario.id}" data-nome="${usuario.nome}" title="Reativar usuário">♻️</button>`;
            
            const botaoNotificar = 
                `<button class="btn btn-sm btn-success btn-notificar" data-id="${usuario.id}" data-nome="${usuario.nome}" title="Notificar usuário">📧</button>`;
            
            const row = $(`
                <tr ${!isAtivo ? 'class="table-secondary"' : ''}>
                    <td>${usuario.nome}</td>
                    <td>${usuario.email}</td>
                    <td>${usuario.cpf}</td>
                    <td><span class="badge ${usuario.status === 'Ativo' ? 'bg-success' : 'bg-danger'}">${usuario.status}</span></td>
                    <td class="text-center">${fotoHtml}</td>
                    <td>
                        ${botaoEditar}
                        ${botaoDependentes}
                        ${botaoNotificar}
                        ${botaoExcluir}
                    </td>
                </tr>
            `);
            tbody.append(row);
        });
    }
    
    // Funcionalidades de exportação/importação removidas - não implementadas no HTML atual
    
    // Funcionalidades de perfil removidas - não implementadas no HTML atual
    
    
    
    // Carregar grupos de valor
    function carregarGruposValor() {
        $.ajax({
            url: '../api/grupo_valor/listar_grupos_valor.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                const select = $('#id_valor');
                select.empty().append('<option value="">Selecione um grupo</option>');
                if (Array.isArray(response)) {
                    response.forEach(function(grupo) {
                        select.append(`<option value="${grupo.id}">${grupo.descricao} (R$ ${grupo.valor})</option>`);
                    });
                }
            },
            error: function() {
                console.error('Erro ao carregar grupos de valor');
                $('#id_valor').empty().append('<option value="">Erro ao carregar</option>');
            }
        });
    }
    
    // Carregar entidades
    function carregarEntidades() {
        $.ajax({
            url: '../api/entidade/listar.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'sucesso') {
                    const select = $('#entidade_id');
                    select.empty().append('<option value="">Selecione uma entidade</option>');
                    response.data.forEach(function(entidade) {
                        select.append(`<option value="${entidade.id}">${entidade.nome}</option>`);
                    });
                }
            },
            error: function() {
                console.error('Erro ao carregar entidades');
            }
        });
    }
    
    // Verificar se usuário tem reserva e sincronizar foto no facial
    function verificarReservaESincronizar(usuarioId) {
        $.ajax({
            url: '../api/usuarios/verificar_reserva_e_sincronizar.php',
            method: 'POST',
            data: { usuario_id: usuarioId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'sucesso') {
                    if (response.tem_reserva) {
                        console.log('Foto sincronizada no dispositivo facial:', response.mensagem);
                        // Não exibir toast para não incomodar o usuário
                    } else {
                        console.log('Usuário não possui reserva - sincronização não necessária');
                    }
                } else {
                    console.error('Erro na sincronização facial:', response.mensagem);
                }
            },
            error: function() {
                console.error('Erro ao verificar reserva e sincronizar');
            }
        });
    }

    // Verificar se dependente tem reserva e sincronizar foto no facial
    function verificarReservaDependenteESincronizar(dependenteId) {
        $.ajax({
            url: '../api/dependentes/verificar_reserva_e_sincronizar.php',
            method: 'POST',
            data: { dependente_id: dependenteId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'sucesso') {
                    if (response.tem_reserva) {
                        console.log('Foto do dependente sincronizada no dispositivo facial:', response.mensagem);
                        // Não exibir toast para não incomodar o usuário
                    } else {
                        console.log('Dependente não possui reserva - sincronização não necessária');
                    }
                } else {
                    console.error('Erro na sincronização do dependente:', response.mensagem);
                }
            },
            error: function() {
                console.error('Erro ao verificar reserva e sincronizar dependente');
            }
        });
    }
    
    // Sistema de confirmação personalizado
    let confirmacaoCallback = null;

    function mostrarConfirmacao(texto, callback) {
        console.log('mostrarConfirmacao chamada');
        console.log('Texto:', texto);
        console.log('Modal existe?', $('#modalConfirmacao').length > 0);
        console.log('Texto do modal existe?', $('#modalConfirmacaoTexto').length > 0);
        
        if ($('#modalConfirmacao').length === 0) {
            console.error('Modal de confirmação não encontrado no DOM!');
            // Fallback: usar confirm nativo
            if (confirm(texto.replace(/<br>/g, '\n').replace(/<small>/g, '').replace(/<\/small>/g, ''))) {
                callback();
            }
            return;
        }
        
        if ($('#modalConfirmacaoTexto').length === 0) {
            console.error('Elemento modalConfirmacaoTexto não encontrado!');
            return;
        }
        
        $('#modalConfirmacaoTexto').html(texto);
        confirmacaoCallback = callback;
        
        // Remover event listeners anteriores para evitar múltiplos bindings
        $('#modalConfirmacao').off('shown.bs.modal');
        
        // Quando o modal é exibido, ajustar z-index para aparecer acima de outros modais
        $('#modalConfirmacao').on('shown.bs.modal', function() {
            console.log('Modal de confirmação exibido');
            // Ajustar z-index do modal de confirmação
            $(this).css('z-index', '2060');
            
            // Ajustar z-index do backdrop do modal de confirmação (o último backdrop)
            // O Bootstrap 5 cria um backdrop para cada modal, então precisamos ajustar o último
            const backdrops = $('.modal-backdrop');
            if (backdrops.length > 0) {
                backdrops.last().css('z-index', '2055');
            }
        });
        
        console.log('Abrindo modal de confirmação...');
        
        // Verificar se Bootstrap está disponível
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            console.log('Usando Bootstrap 5 Modal API');
            // Usar Bootstrap 5 Modal API
            const modalElement = document.getElementById('modalConfirmacao');
            if (modalElement) {
                // Remover instância anterior se existir
                const existingModal = bootstrap.Modal.getInstance(modalElement);
                if (existingModal) {
                    existingModal.dispose();
                }
                
                const modal = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true
                });
                
                modal.show();
                
                // Verificar se abriu após um pequeno delay
                setTimeout(function() {
                    if ($('#modalConfirmacao').hasClass('show')) {
                        console.log('Modal aberto com sucesso via Bootstrap 5');
                    } else {
                        console.log('Modal não abriu via Bootstrap 5, tentando método alternativo...');
                        abrirModalManualmente();
                    }
                }, 200);
            } else {
                console.error('Elemento modal não encontrado!');
                abrirModalManualmente();
            }
        } else {
            console.log('Bootstrap 5 não disponível, usando método manual');
            abrirModalManualmente();
        }
        
        function abrirModalManualmente() {
            console.log('Abrindo modal manualmente...');
            const $modal = $('#modalConfirmacao');
            
            // Remover classes que podem estar escondendo
            $modal.removeClass('fade');
            
            // Adicionar classes necessárias
            $modal.addClass('show');
            
            // Forçar display e z-index
            $modal.css({
                'display': 'block',
                'z-index': '2060',
                'opacity': '1',
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'width': '100%',
                'height': '100%',
                'overflow-x': 'hidden',
                'overflow-y': 'auto'
            });
            
            // Adicionar backdrop
            $('body').addClass('modal-open');
            if ($('.modal-backdrop').length === 0) {
                $('body').append('<div class="modal-backdrop fade show" style="z-index: 2055;"></div>');
            }
            
            // Centralizar modal
            const $modalDialog = $modal.find('.modal-dialog');
            $modalDialog.css({
                'margin': '1.75rem auto',
                'max-width': '500px'
            });
            
            console.log('Modal aberto manualmente. Classes:', $modal.attr('class'));
            console.log('Display:', $modal.css('display'));
            console.log('Z-index:', $modal.css('z-index'));
        }
    }

    // Função para fechar modal
    function fecharModalConfirmacao() {
        const $modal = $('#modalConfirmacao');
        
        // Se Bootstrap está disponível, usar API
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modalElement = document.getElementById('modalConfirmacao');
            if (modalElement) {
                const existingModal = bootstrap.Modal.getInstance(modalElement);
                if (existingModal) {
                    existingModal.hide();
                    return;
                }
            }
        }
        
        // Método manual
        $modal.removeClass('show');
        $modal.css('display', 'none');
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    }
    
    // Evento para confirmar ação no modal
    $('#btnConfirmarAcao').on('click', function() {
        console.log('Botão confirmar clicado');
        console.log('Callback existe?', typeof confirmacaoCallback === 'function');
        if (confirmacaoCallback) {
            console.log('Executando callback...');
            confirmacaoCallback();
            fecharModalConfirmacao();
            confirmacaoCallback = null;
        } else {
            console.error('Callback não definido!');
        }
    });
    
    // Evento para fechar modal (botão X e Cancelar)
    $('#modalConfirmacao').on('click', '[data-bs-dismiss="modal"], .btn-close', function() {
        fecharModalConfirmacao();
    });
    
    // Fechar ao clicar no backdrop
    $('#modalConfirmacao').on('click', function(e) {
        if (e.target === this) {
            fecharModalConfirmacao();
        }
    });

    // Função para abrir modal de dependentes
    function abrirModalDependentes(usuarioId, usuarioNome) {
        $('#modalDependentesLabel').text(`Dependentes de ${usuarioNome}`);
        $('#usuario_id_dependentes').val(usuarioId);
        carregarDependentesUsuario(usuarioId);
        $('#modalDependentes').modal('show');
    }

    // Função para carregar dependentes do usuário
    function carregarDependentesUsuario(usuarioId) {
        $.ajax({
            url: '../api/dependentes/listar_por_usuario.php',
            method: 'GET',
            data: { usuario_id: usuarioId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    const tbody = $('#tabelaDependentesUsuario tbody');
                    tbody.empty();
                    
                    if (response.dados && response.dados.length > 0) {
                        response.dados.forEach(function(dependente) {
                            // Criar elemento de imagem com fallback
                            let fotoElement = '';
                            // Validar se a foto existe e é válida
                            const temFoto = dependente.foto_base64 && 
                                           dependente.foto_base64.trim() !== '' && 
                                           dependente.foto_base64 !== 'null' &&
                                           dependente.foto_base64.length > 100; // Mínimo de caracteres para ser uma imagem válida
                            
                            if (temFoto) {
                                try {
                                    // Tentar criar a URL da imagem
                                    const imgSrc = 'data:image/jpeg;base64,' + dependente.foto_base64.trim();
                                    fotoElement = `<img src="${imgSrc}" class="rounded-circle dependente-foto" style="width: 40px; height: 40px; object-fit: cover;" alt="Foto do dependente" data-dependente-id="${dependente.id}" onerror="this.onerror=null; this.replaceWith('<i class=\\'bi bi-person-circle\\' style=\\'font-size: 40px; color: #6c757d;\\'></i>');">`;
                                } catch (e) {
                                    console.error('Erro ao criar elemento de imagem para dependente', dependente.id, e);
                                    fotoElement = `<i class="bi bi-person-circle" style="font-size: 40px; color: #6c757d;"></i>`;
                                }
                            } else {
                                fotoElement = `<i class="bi bi-person-circle" style="font-size: 40px; color: #6c757d;"></i>`;
                            }
                            
                            const row = $(`
                                <tr>
                                    <td>${dependente.nome}</td>
                                    <td>${dependente.parentesco}</td>
                                    <td>${dependente.data_nascimento}</td>
                                    <td class="text-center">${fotoElement}</td>
                                    <td>
                                        <button class="btn btn-sm btn-primary btn-editar-dependente" data-id="${dependente.id}" title="Editar dependente">✏️</button>
                                        <button class="btn btn-sm btn-danger btn-excluir-dependente" data-id="${dependente.id}" title="Excluir dependente">🗑️</button>
                                    </td>
                                </tr>
                            `);
                            tbody.append(row);
                        });
                    } else {
                        tbody.append('<tr><td colspan="5" class="text-center text-muted">Nenhum dependente encontrado</td></tr>');
                    }
                } else {
                    exibirToast('Erro ao carregar dependentes: ' + response.mensagem, 'error');
                }
            },
            error: function() {
                exibirToast('Erro ao carregar dependentes', 'error');
            }
        });
    }

    // Event listeners para dependentes
    $('#btnNovoDependente').click(function() {
        const usuarioId = $('#usuario_id_dependentes').val();
        $('#dependente_usuario_id').val(usuarioId);
        $('#formDependente')[0].reset();
        
        // Limpar explicitamente o ID do dependente para garantir que é um novo cadastro
        $('#dependente_id').val('');
        
        // Limpar o campo de arquivo
        $('#dependente_foto').val('');
        
        // Limpar a miniatura da foto
        $('#previewFotoDependente').hide();
        $('#placeholderFotoDependente').show();
        
        $('#modalDependenteLabel').text('Novo Dependente');
        $('#modalDependente').modal('show');
    });

    $(document).on('click', '.btn-editar-dependente', function() {
        const id = $(this).data('id');
        editarDependente(id);
    });

    $(document).on('click', '.btn-excluir-dependente', function() {
        const id = $(this).data('id');
        const usuarioId = $('#usuario_id_dependentes').val();
        excluirDependente(id, usuarioId);
    });

    // Função para editar dependente
    function editarDependente(id) {
        $.ajax({
            url: '../api/dependentes/buscar.php',
            method: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    const dep = response.dados;
                    $('#dependente_id').val(dep.id);
                    $('#dependente_usuario_id').val(dep.usuario_id);
                    $('#dependente_nome').val(dep.nome);
                    $('#dependente_parentesco').val(dep.parentesco);
                    $('#dependente_nascimento').val(dep.data_nascimento);
                    
                    // Validar e exibir foto do dependente
                    const temFoto = dep.foto_base64 && 
                                   dep.foto_base64.trim() !== '' && 
                                   dep.foto_base64 !== 'null' &&
                                   dep.foto_base64.length > 100; // Mínimo de caracteres para ser uma imagem válida
                    
                    if (temFoto) {
                        try {
                            const imgSrc = 'data:image/jpeg;base64,' + dep.foto_base64.trim();
                            $('#previewFotoDependente').attr('src', imgSrc).show();
                            $('#placeholderFotoDependente').hide();
                            
                            // Adicionar handler de erro para a imagem
                            $('#previewFotoDependente').off('error').on('error', function() {
                                console.log('Erro ao carregar foto do dependente no modal:', dep.id);
                                $(this).hide();
                                $('#placeholderFotoDependente').show();
                            });
                        } catch (e) {
                            console.error('Erro ao processar foto do dependente:', dep.id, e);
                            $('#previewFotoDependente').hide();
                            $('#placeholderFotoDependente').show();
                        }
                    } else {
                        $('#previewFotoDependente').hide();
                        $('#placeholderFotoDependente').show();
                    }
                    
                    // Limpar o campo de arquivo para evitar envio acidental
                    $('#dependente_foto').val('');
                    
                    $('#modalDependenteLabel').text('Editar Dependente');
                    $('#modalDependente').modal('show');
                } else {
                    exibirToast('Erro ao carregar dependente: ' + response.mensagem, 'error');
                }
            },
            error: function() {
                exibirToast('Erro ao carregar dependente', 'error');
            }
        });
    }

    // Função para excluir dependente
    function excluirDependente(id, usuarioId) {
        mostrarConfirmacao('Tem certeza que deseja excluir este dependente?', function() {
            $.ajax({
                url: '../api/dependentes/excluir.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        exibirToast('Dependente excluído com sucesso!', 'success');
                        carregarDependentesUsuario(usuarioId);
                    } else {
                        exibirToast('Erro: ' + response.mensagem, 'error');
                    }
                },
                error: function() {
                    exibirToast('Erro ao excluir dependente', 'error');
                }
            });
        });
    }

    // Preview da foto do dependente
    $('#dependente_foto').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#previewFotoDependente').attr('src', e.target.result).show();
                $('#placeholderFotoDependente').hide();
            };
            reader.readAsDataURL(file);
        }
    });

    // Função para converter base64 para Blob
    function base64ToBlob(base64, mimeType) {
        const byteCharacters = atob(base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], { type: mimeType });
    }
    
    // Salvar dependente
    $('#formDependente').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const dependenteId = $('#dependente_id').val();
        const isEdit = dependenteId !== '';
        const usuarioId = $('#usuario_id_dependentes').val();
        
        // Verificar se há foto sendo enviada
        const fotoInput = $('#dependente_foto')[0];
        
        if (fotoInput && fotoInput.files.length > 0) {
            const file = fotoInput.files[0];
            console.log('Foto sendo enviada:', file.name);
            console.log('Tamanho original da foto:', file.size, 'bytes');
            
            // Comprimir a imagem antes de enviar
            const reader = new FileReader();
            reader.onload = function(e) {
                console.log('Iniciando compressão da foto do dependente...');
                comprimirImagem(e.target.result, function(fotoComprimida) {
                    console.log('Compressão concluída, tamanho final:', fotoComprimida.length, 'caracteres');
                    
                    // Remover foto antiga do FormData e adicionar como base64
                    formData.delete('foto');
                    formData.append('foto_base64', fotoComprimida);
                    
                    // Enviar dados
                    enviarDadosDependente(formData, dependenteId, isEdit, usuarioId);
                });
            };
            
            reader.onerror = function() {
                console.error('Erro ao ler arquivo');
                exibirToast('Erro ao processar foto. Tente novamente.', 'error');
            };
            
            reader.readAsDataURL(file);
        } else {
            // Não há foto, enviar diretamente
            enviarDadosDependente(formData, dependenteId, isEdit, usuarioId);
        }
    });
    
    // Função para enviar dados do dependente
    function enviarDadosDependente(formData, dependenteId, isEdit, usuarioId) {
        // Log dos dados sendo enviados para debug
        console.log('Enviando dados do dependente:', {
            dependenteId: dependenteId,
            isEdit: isEdit,
            usuarioId: usuarioId,
            temFotoBase64: formData.has('foto_base64'),
            temFoto: formData.has('foto')
        });
        
        $.ajax({
            url: isEdit ? '../api/dependentes/editar.php' : '../api/dependentes/criar.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('Resposta da API:', response);
                if (response.status === 'ok') {
                    exibirToast(isEdit ? 'Dependente atualizado com sucesso!' : 'Dependente criado com sucesso!', 'success');
                    
                    // Limpar o formulário e o campo de arquivo
                    $('#formDependente')[0].reset();
                    $('#dependente_foto').val('');
                    $('#previewFotoDependente').hide();
                    $('#placeholderFotoDependente').show();
                    $('#dependente_id').val('');
                    
                    $('#modalDependente').modal('hide');
                    
                    // Aguardar um pouco antes de recarregar para garantir que o banco foi atualizado
                    setTimeout(function() {
                        carregarDependentesUsuario(usuarioId);
                    }, 500);
                    
                    // Verificar se dependente tem reserva e sincronizar foto no facial (apenas se for edição)
                    if (isEdit) {
                        verificarReservaDependenteESincronizar(dependenteId);
                    }
                } else {
                    console.error('Erro na resposta da API:', response);
                    exibirToast('Erro: ' + (response.mensagem || 'Erro desconhecido'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao salvar dependente:', error);
                console.error('Status:', status);
                console.error('Resposta completa:', xhr.responseText);
                try {
                    const response = JSON.parse(xhr.responseText);
                    exibirToast('Erro: ' + (response.mensagem || 'Erro ao salvar dependente'), 'error');
                } catch (e) {
                    exibirToast('Erro ao salvar dependente. Verifique o console para mais detalhes.', 'error');
                }
            }
        });
    }

});