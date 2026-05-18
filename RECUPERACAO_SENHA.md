# Sistema de Recuperação de Senha - Presença AOM

## Visão Geral

O sistema de recuperação de senha permite que usuários redefinam suas senhas de forma segura através de um link enviado por e-mail.

## Funcionalidades

### 1. Solicitação de Recuperação
- **Arquivo**: `recuperar_senha.php`
- **Endpoint**: `api/auth/recuperar_senha.php`
- **Funcionalidade**: Permite ao usuário solicitar um link de recuperação de senha

### 2. Redefinição de Senha
- **Arquivo**: `redefinir_senha.php`
- **Endpoint**: `api/auth/redefinir_senha.php`
- **Funcionalidade**: Permite ao usuário definir uma nova senha usando o token

### 3. Limpeza Automática
- **Arquivo**: `limpar_tokens_expirados.php`
- **Funcionalidade**: Remove tokens expirados do banco de dados

## Configuração

### Configurações de E-mail
O sistema utiliza as seguintes configurações do banco de dados:

```sql
SELECT chave, valor FROM configuracoes WHERE chave IN (
    'email_notificacoes',
    'smtp_email', 
    'port_email',
    'senha_email'
);
```

### Configuração do Cron Job
Para limpar tokens expirados automaticamente, adicione ao crontab:

```bash
# Executar a cada 6 horas
0 */6 * * * /usr/bin/php /var/www/html/presenca/limpar_tokens_expirados.php
```

## Fluxo de Funcionamento

1. **Solicitação**: Usuário acessa `recuperar_senha.php` e informa seu e-mail
2. **Validação**: Sistema verifica se o e-mail existe e está ativo
3. **Token**: Gera um token único válido por 1 hora
4. **E-mail**: Envia e-mail com link de recuperação usando PHPMailer
5. **Redefinição**: Usuário clica no link e define nova senha
6. **Limpeza**: Token é removido após uso ou expiração

## Segurança

- Tokens são únicos e válidos por 1 hora
- Tokens são removidos após uso
- Senhas são criptografadas com `password_hash()`
- Validação de e-mail e senha no frontend e backend
- Proteção contra tokens expirados

## Arquivos Criados

1. `recuperar_senha.php` - Página de solicitação
2. `redefinir_senha.php` - Página de redefinição
3. `api/auth/recuperar_senha.php` - API de solicitação
4. `api/auth/redefinir_senha.php` - API de redefinição
5. `limpar_tokens_expirados.php` - Script de limpeza

## Integração

O link "Esqueceu sua senha?" foi adicionado à página de login (`index.php`).

## Teste

Para testar o sistema:

1. Acesse `recuperar_senha.php`
2. Digite um e-mail válido cadastrado no sistema
3. Verifique o e-mail recebido
4. Clique no link de recuperação
5. Defina uma nova senha

## Dependências

- PHPMailer (já configurado no sistema)
- Configurações de SMTP no banco de dados
- Tabela `tokens_senha` no banco de dados 