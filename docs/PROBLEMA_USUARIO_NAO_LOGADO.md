# 🔍 Problema: "Usuário não logado" ao verificar horário

## ⚠️ Problema Identificado

Ao selecionar uma data para reservar, a API retorna:
```json
{"status":"erro","mensagem":"Usuário não logado"}
```

**Logs mostram:**
- ✅ Token está sendo enviado: `Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...`
- ✅ Requisição GET para: `https://presenca.aom.org.br/api/almoco/verificar_horario.php?data=2026-01-20&tipo=presencial`
- ❌ API retorna erro: "Usuário não logado"

## 🔍 Causa Provável

A API `/api/almoco/verificar_horario.php` **não está usando o middleware** que converte o Bearer Token em sessão PHP.

### Verificação Necessária no Backend

O arquivo `verificar_horario.php` precisa incluir o middleware:

```php
<?php
require_once __DIR__ . '/../../middleware/MobileAuthMiddleware.php';

// O middleware converte o Bearer Token em sessão PHP
// Isso permite que a API funcione com autenticação mobile

// Resto do código da API...
```

## ✅ Correções Aplicadas no Frontend

1. **Removida verificação de token** para endpoints de verificação de horário (permite fazer requisição mesmo sem token válido, deixando a API decidir)
2. **Texto do botão dinâmico** implementado:
   - "Reservar meu almoço" quando dentro do horário
   - "Fora do Horário" quando fora do horário

## 🔧 Solução no Backend (Necessária)

### Arquivo: `api/almoco/verificar_horario.php`

Adicione no início do arquivo:

```php
<?php
// Incluir middleware de autenticação mobile
require_once __DIR__ . '/../../middleware/MobileAuthMiddleware.php';

// O middleware já faz a conversão do Bearer Token para sessão PHP
// Agora você pode usar $_SESSION['user_id'] normalmente

// Resto do código...
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Usuário não logado'
    ]);
    exit;
}

// Continuar com a lógica da API...
```

### Verificar se o Middleware Existe

O middleware deve estar em:
```
api/middleware/MobileAuthMiddleware.php
```

E deve fazer algo como:
```php
<?php
// Ler Bearer Token do header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
    
    // Validar e decodificar o token JWT
    // Converter em sessão PHP
    $_SESSION['user_id'] = $userId; // Do token
    // etc...
}
```

## 📋 Checklist

- [ ] Verificar se `MobileAuthMiddleware.php` existe
- [ ] Verificar se `verificar_horario.php` inclui o middleware
- [ ] Testar a API diretamente com Postman/curl enviando Bearer Token
- [ ] Verificar se outras APIs `/api/almoco/*` estão funcionando (podem ter o mesmo problema)

## 🧪 Teste Manual

```bash
# Testar diretamente a API
curl -X GET "https://presenca.aom.org.br/api/almoco/verificar_horario.php?data=2026-01-20&tipo=presencial" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json"
```

**Se retornar erro**, o problema está no backend (middleware não configurado).

**Se retornar sucesso**, o problema pode estar no frontend (mas já foi corrigido).

---

**Última Atualização:** Janeiro 2025
