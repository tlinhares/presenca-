# 🔧 Guia: Adicionar Middleware Mobile em APIs

## 📋 Resumo para Outra Sessão do Cursor

Este guia explica como adicionar suporte a autenticação mobile (Bearer Token) em todas as APIs do sistema.

---

## 🎯 Objetivo

Todas as APIs que requerem autenticação devem suportar:
1. **Autenticação Web:** Via sessão PHP (`$_SESSION['usuario_id']`)
2. **Autenticação Mobile:** Via Bearer Token (convertido em sessão PHP)

---

## 📝 Padrão de Implementação

### Template Completo para Nova API

```php
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once(__DIR__ . '/../conexao.php');
include_once(__DIR__ . '/../../utils/config.php'); // Se necessário

// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode([
            'status' => 'erro', 
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}

// A partir daqui, $_SESSION['usuario_id'] está disponível
$id_usuario = $_SESSION['usuario_id'];

// ... resto da lógica da API ...
```

---

## 🔄 Como Adicionar em API Existente

### Passo 1: Adicionar Headers CORS

Adicione após `<?php`:

```php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
```

### Passo 2: Substituir session_start() Antigo

**ANTES:**
```php
session_start();
```

**DEPOIS:**
```php
// Inicia sessão ANTES do middleware (compatível com web)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### Passo 3: Adicionar Middleware

Adicione após os `include`/`require`:

```php
// Middleware mobile: converte Bearer Token em sessão PHP se necessário
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

// Verifica autenticação (web ou mobile)
if (!isset($_SESSION['usuario_id'])) {
    // Tenta autenticar via token mobile
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode([
            'status' => 'erro', 
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}
```

### Passo 4: Substituir Verificação Antiga

**ANTES:**
```php
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['erro' => 'Usuário não logado']);
    exit;
}
```

**DEPOIS:** (Já está incluído no middleware acima)

---

## 🤖 Script Automatizado

Foi criado um script que adiciona o middleware automaticamente:

```bash
# Executar script
php scripts/adicionar_middleware_mobile.php
```

**⚠️ ATENÇÃO:**
- O script cria backups dos arquivos originais
- Revise os arquivos modificados antes de fazer commit
- Teste cada API após modificação

---

## 📋 Checklist de Verificação

Após adicionar middleware, verifique:

- [ ] Headers CORS estão configurados
- [ ] Tratamento de OPTIONS (CORS preflight)
- [ ] Sessão PHP iniciada antes do middleware
- [ ] Middleware `mobile_auth.php` incluído
- [ ] Verificação de autenticação usando `MobileAuthMiddleware::handle()`
- [ ] Mensagem de erro padronizada
- [ ] API funciona com sessão web (teste no navegador)
- [ ] API funciona com Bearer Token (teste com curl/Postman)

---

## 🧪 Testes

### Teste 1: Requisição Web (Sessão PHP)

```bash
# Login via web primeiro para criar sessão
curl -X GET "https://presenca.aom.org.br/api/sua-api.php" \
  -H "Cookie: PHPSESSID=<session_id>"
```

**Resultado Esperado:** ✅ Deve funcionar normalmente

### Teste 2: Requisição Mobile (Bearer Token)

```bash
# Obter token fazendo login
TOKEN=$(curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","senha":"suasenha"}' | jq -r '.data.token')

# Testar API com token
curl -X GET "https://presenca.aom.org.br/api/sua-api.php" \
  -H "Authorization: Bearer $TOKEN"
```

**Resultado Esperado:** ✅ Deve funcionar normalmente

### Teste 3: Requisição Sem Autenticação

```bash
curl -X GET "https://presenca.aom.org.br/api/sua-api.php"
```

**Resultado Esperado:** ❌ Deve retornar erro de autenticação

---

## 📊 Status das APIs

Consulte `docs/MAPEAMENTO_COMPLETO_APIS.md` para ver:
- Quais APIs já têm middleware
- Quais APIs precisam de middleware
- Prioridade de implementação

---

## 🔗 Arquivos Relacionados

- `core/middleware/mobile_auth.php` - Middleware de autenticação mobile
- `core/services/TokenService.php` - Serviço de validação de tokens JWT
- `api/mobile/utils/auth_helper.php` - Helper para facilitar uso
- `scripts/adicionar_middleware_mobile.php` - Script automatizado

---

**Data:** 2026-01-XX  
**Versão:** 1.0
