# Sistema de Cadastro de Usuários - SS3542

Este sistema permite cadastrar usuários no dispositivo de controle de acesso SS3542 da Intelbras.

## Arquivos Incluídos

1. **cadastro_usuarios.php** - Formulário para cadastro individual de usuários utilizando o método GET.
2. **cadastro_usuarios_json.php** - Formulário para cadastro de múltiplos usuários utilizando o método POST com JSON.
3. **process_users.php** - Script PHP para processar o envio de múltiplos usuários.

## Configuração

As configurações do dispositivo (IP, usuário e senha) já estão definidas nos arquivos PHP. Se você precisar alterar essas configurações, edite as seguintes variáveis nos arquivos:

```php
$deviceIp = '10.144.129.69';
$deviceUser = 'admin';
$devicePass = 'Arcs2901';
```

## Como Usar

### Método 1: Cadastro Individual (GET)

1. Acesse o arquivo `cadastro_usuarios.php` no navegador.
2. Preencha todos os campos do formulário.
3. Clique em "Cadastrar Usuário".
4. O resultado será exibido na parte inferior da página.

Este método utiliza a API do dispositivo via requisição GET para cadastrar um único usuário por vez.

### Método 2: Cadastro Múltiplo (POST com JSON)

1. Acesse o arquivo `cadastro_usuarios_json.php` no navegador.
2. Preencha os dados do usuário no formulário.
3. Clique em "Adicionar à Lista" para adicionar o usuário à lista.
4. Repita os passos 2 e 3 para cada usuário que deseja cadastrar.
5. Quando terminar de adicionar todos os usuários, clique em "Enviar Todos Usuários".
6. O resultado será exibido na parte inferior da página.

Este método utiliza a API do dispositivo via requisição POST com JSON para cadastrar múltiplos usuários de uma vez.

## Requisitos

- Servidor web com suporte a PHP (PHP 7.0 ou superior)
- Extensão cURL habilitada no PHP
- O dispositivo SS3542 deve estar acessível na rede

## Logs

Para depuração, o método de cadastro múltiplo grava logs no arquivo `log_users.txt`. Verifique este arquivo se encontrar problemas ao cadastrar usuários. 