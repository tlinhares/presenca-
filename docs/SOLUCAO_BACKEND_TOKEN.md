# 🔧 Solução Backend: Processamento de Token Bearer

## 📋 Problema Identificado

O backend não está processando o token Bearer corretamente, mesmo quando o token é válido e está sendo enviado pelo Flutter.

## ✅ Correções Implementadas

### 1. Logs de Debug Adicionados

Foram adicionados logs detalhados em:
- `api/almoco/verificar_horario.php` - Logs no início da autenticação
- `core/services/TokenService.php` - Logs na extração e validação do token
- `core/middleware/mobile_auth.php` - Logs no processo de autenticação

### 2. Melhorias na Extração do Header Authorization

A função `TokenService::extractTokenFromHeader()` agora tenta múltiplas formas de obter o header:

1. `$_SERVER['Authorization']` - Alguns servidores
2. `$_SERVER['HTTP_AUTHORIZATION']` - Apache padrão
3. `apache_request_headers()['Authorization']` - Apache com função nativa
4. `getallheaders()['Authorization']` - Função auxiliar
5. `$_SERVER['REDIRECT_HTTP_AUTHORIZATION']` - Apache com mod_rewrite

### 3. Endpoint de Teste Criado

Foi criado `api/test_token.php` para diagnosticar problemas:

```bash
curl -X GET "https://presenca.aom.org.br/api/test_token.php" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

Este endpoint retorna informações detalhadas sobre:
- Headers recebidos
- Token extraído
- Validação do token
- Sessão criada
- Secret key usada

## 🔍 Como Diagnosticar

### Passo 1: Testar o Endpoint de Teste

```bash
# Obter token fazendo login
TOKEN=$(curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","senha":"suasenha"}' | jq -r '.data.token')

# Testar o endpoint de diagnóstico
curl -X GET "https://presenca.aom.org.br/api/test_token.php" \
  -H "Authorization: Bearer $TOKEN"
```

### Passo 2: Verificar Logs do Servidor

Os logs estão sendo escritos em:
- `/var/log/apache2/error.log` (Apache)
- `/var/log/nginx/error.log` (Nginx)
- Ou onde o PHP está configurado para escrever logs

Procure por:
- `TokenService::extractTokenFromHeader`
- `MobileAuthMiddleware::handle`
- `verificar_horario.php`

### Passo 3: Verificar Configuração do Apache/Nginx

#### Apache

Certifique-se de que o Apache está passando o header `Authorization`:

```apache
# No arquivo .htaccess ou configuração do Apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

Ou adicione no VirtualHost:

```apache
<VirtualHost *:80>
    ServerName presenca.aom.org.br
    
    # Passa o header Authorization para PHP
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
    
    # ... resto da configuração
</VirtualHost>
```

#### Nginx

```nginx
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    # ... resto da configuração
}
```

## 🧪 Testes Recomendados

### Teste 1: Verificar se o Header Está Sendo Recebido

```bash
# Criar arquivo de teste
cat > /var/www/html/presenca/api/test_header.php << 'EOF'
<?php
header('Content-Type: application/json');
echo json_encode([
    'getallheaders' => function_exists('getallheaders') ? getallheaders() : 'NÃO DISPONÍVEL',
    'server_auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NÃO ENCONTRADO',
    'server_auth_direct' => $_SERVER['Authorization'] ?? 'NÃO ENCONTRADO',
    'all_server_keys' => array_filter(array_keys($_SERVER), function($k) {
        return stripos($k, 'auth') !== false;
    })
], JSON_PRETTY_PRINT);
EOF

# Testar
curl -X GET "https://presenca.aom.org.br/api/test_header.php" \
  -H "Authorization: Bearer teste123"
```

### Teste 2: Verificar Validação do Token

```bash
# Usar o endpoint de teste criado
curl -X GET "https://presenca.aom.org.br/api/test_token.php" \
  -H "Authorization: Bearer SEU_TOKEN_VALIDO"
```

### Teste 3: Testar API Real

```bash
# Testar a API real com token válido
curl -X GET "https://presenca.aom.org.br/api/almoco/verificar_horario.php?data=2026-01-20&tipo=presencial" \
  -H "Authorization: Bearer SEU_TOKEN_VALIDO"
```

## 🔧 Possíveis Problemas e Soluções

### Problema 1: Apache Não Passa Header Authorization

**Sintoma:** Logs mostram "NENHUM HEADER Authorization encontrado"

**Solução:** Adicionar ao `.htaccess` ou configuração do Apache:

```apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

### Problema 2: Secret Key Diferente

**Sintoma:** Token válido mas validação falha

**Solução:** Verificar se a mesma secret key está sendo usada para gerar e validar:

```php
// Adicionar log temporário em TokenService::getSecretKey()
error_log("Secret Key: " . self::getSecretKey());
```

### Problema 3: Token Expirado

**Sintoma:** Validação retorna false, logs mostram "Token expirado"

**Solução:** Verificar se o token não expirou. Tokens têm validade de 24 horas.

### Problema 4: Algoritmo Diferente

**Sintoma:** Erro "Wrong algorithm" ou similar

**Solução:** Verificar se o algoritmo usado para gerar é o mesmo para validar (HS256).

## 📝 Checklist de Verificação

- [ ] Header `Authorization` está sendo recebido pelo servidor
- [ ] Token está sendo extraído corretamente do header
- [ ] Secret key é a mesma usada para gerar e validar
- [ ] Token não está expirado
- [ ] Algoritmo de assinatura é o mesmo (HS256)
- [ ] Sessão PHP está sendo criada após validação
- [ ] `$_SESSION['usuario_id']` está sendo definido

## 🚀 Próximos Passos

1. **Testar o endpoint de diagnóstico** (`api/test_token.php`)
2. **Verificar logs do servidor** para identificar onde está falhando
3. **Configurar Apache/Nginx** se necessário para passar o header
4. **Remover logs de debug** após identificar e corrigir o problema

## 📚 Arquivos Modificados

- `api/almoco/verificar_horario.php` - Logs adicionados
- `core/services/TokenService.php` - Melhorias na extração do header e validação
- `core/middleware/mobile_auth.php` - Logs adicionados
- `api/test_token.php` - **NOVO** - Endpoint de diagnóstico

---

**Data:** 2026-01-XX  
**Status:** ✅ Correções Implementadas  
**Versão:** 1.0
