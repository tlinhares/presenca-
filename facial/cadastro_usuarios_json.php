<?php
// Ativar reportamento de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Múltiplos Usuários - SS3542</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }
        .card {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        #userList {
            min-height: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Cadastro de Múltiplos Usuários - SS3542</h1>
        
        <!-- Formulário Individual -->
        <div class="card">
            <div class="card-header">
                Adicionar Usuário à Lista
            </div>
            <div class="card-body">
                <form id="userForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="userid" class="form-label">ID do Usuário</label>
                            <input type="text" class="form-control" id="userid" name="userid" required>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">Nome do Usuário</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Senha (opcional)</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Deixe em branco para usar "123456"</small>
                        </div>
                        <div class="col-md-6">
                            <label for="validfrom" class="form-label">Válido De</label>
                            <input type="datetime-local" class="form-control" id="validfrom" name="validfrom" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="validto" class="form-label">Válido Até</label>
                            <input type="datetime-local" class="form-control" id="validto" name="validto" required>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="usePredefinedFields" name="usePredefinedFields" checked>
                                <label class="form-check-label" for="usePredefinedFields">
                                    Usar valores padrão para os campos não obrigatórios
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" id="addUserBtn" class="btn btn-primary">Adicionar à Lista</button>
                </form>
            </div>
        </div>
        
        <!-- Lista de Usuários -->
        <div class="card">
            <div class="card-header">
                Lista de Usuários para Cadastro
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <textarea class="form-control" id="userList" rows="10" readonly></textarea>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" id="clearListBtn" class="btn btn-warning">Limpar Lista</button>
                    <button type="button" id="submitUsersBtn" class="btn btn-success">Enviar Todos Usuários</button>
                </div>
            </div>
        </div>
        
        <!-- Resultado -->
        <div id="resultArea"></div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Lista para armazenar usuários
            let usersList = [];
            
            // Adicionar usuário à lista
            document.getElementById('addUserBtn').addEventListener('click', function() {
                try {
                    // Obter valores do formulário
                    const userId = document.getElementById('userid').value;
                    const userName = document.getElementById('username').value;
                    const password = document.getElementById('password').value || '123456'; // Valor padrão
                    const validFrom = document.getElementById('validfrom').value;
                    const validTo = document.getElementById('validto').value;
                    const usePredefinedFields = document.getElementById('usePredefinedFields').checked;
                    
                    // Validar campos obrigatórios
                    if (!userId || !userName || !validFrom || !validTo) {
                        alert('Por favor, preencha todos os campos obrigatórios (ID, Nome, Validade).');
                        return;
                    }
                    
                    // Criar objeto de usuário com valores padrão
                    const user = {
                        UserID: userId,
                        UserName: userName,
                        UserType: 0, // Tipo normal
                        Authority: 1, // Autorizado
                        UserStatus: 0, // Ativo
                        Password: password,
                        Doors: [0],
                        TimeSections: [255], // Zona de tempo padrão
                        ValidFrom: formatDateTime(validFrom),
                        ValidTo: formatDateTime(validTo)
                    };
                    
                    // Adicionar à lista
                    usersList.push(user);
                    
                    // Atualizar a visualização da lista
                    updateUserListView();
                    
                    // Limpar formulário e focar no próximo ID
                    document.getElementById('userForm').reset();
                    document.getElementById('usePredefinedFields').checked = true;
                    document.getElementById('userid').focus();
                } catch (error) {
                    alert('Erro ao adicionar usuário: ' + error.message);
                }
            });
            
            // Limpar lista
            document.getElementById('clearListBtn').addEventListener('click', function() {
                usersList = [];
                updateUserListView();
            });
            
            // Enviar usuários
            document.getElementById('submitUsersBtn').addEventListener('click', function() {
                if (usersList.length === 0) {
                    alert('Adicione pelo menos um usuário à lista antes de enviar.');
                    return;
                }
                
                // Preparar dados para envio
                const postData = {
                    UserList: usersList
                };
                
                // Mostrar status de carregamento
                const resultElement = document.getElementById('resultArea');
                resultElement.innerHTML = `
                    <div class="alert alert-info">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Enviando...</span>
                        </div>
                        Enviando dados para o dispositivo...
                    </div>
                `;
                
                // Enviar via AJAX
                fetch('process_users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(postData)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta do servidor: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    // Exibir resultado
                    if (data.success) {
                        resultElement.innerHTML = `
                            <div class="result success">
                                <h4>Usuários cadastrados com sucesso!</h4>
                                <p>Resposta: ${data.message || 'Operação concluída'}</p>
                                <p>Status Code: ${data.statusCode || 'N/A'}</p>
                            </div>
                        `;
                        
                        // Limpar lista após sucesso
                        usersList = [];
                        updateUserListView();
                    } else {
                        resultElement.innerHTML = `
                            <div class="result error">
                                <h4>Erro ao cadastrar usuários</h4>
                                <p>Mensagem: ${data.message || 'Erro desconhecido'}</p>
                                <p>Status Code: ${data.statusCode || 'N/A'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    resultElement.innerHTML = `
                        <div class="result error">
                            <h4>Erro ao processar requisição</h4>
                            <p>${error.message}</p>
                        </div>
                    `;
                });
            });
            
            // Função para atualizar a visualização da lista de usuários
            function updateUserListView() {
                try {
                    const textarea = document.getElementById('userList');
                    textarea.value = JSON.stringify(usersList, null, 2);
                } catch (error) {
                    console.error('Erro ao atualizar lista:', error);
                }
            }
            
            // Função para formatar data e hora no formato esperado pelo dispositivo
            function formatDateTime(dateTimeString) {
                try {
                    const dt = new Date(dateTimeString);
                    
                    // Formato: YYYY-MM-DD HH:MM:SS
                    const year = dt.getFullYear();
                    const month = String(dt.getMonth() + 1).padStart(2, '0');
                    const day = String(dt.getDate()).padStart(2, '0');
                    const hours = String(dt.getHours()).padStart(2, '0');
                    const minutes = String(dt.getMinutes()).padStart(2, '0');
                    const seconds = String(dt.getSeconds()).padStart(2, '0');
                    
                    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
                } catch (error) {
                    console.error('Erro ao formatar data:', error);
                    return dateTimeString; // Retorna a string original em caso de erro
                }
            }
        });
    </script>
</body>
</html> 