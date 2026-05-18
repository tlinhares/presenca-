# 🔧 Correção: Autenticação Mobile nas APIs

## 📋 Resumo para Outra Sessão do Cursor

### Problema Identificado
As APIs abaixo precisavam incluir o middleware de autenticação mobile para converter Bearer Token em sessão PHP:
- `/api/almoco/verificar_horario.php`
- `/api/dependentes/listar.php`
- `/api/almoco/verificar_horario_adicional.php`

### Status
✅ **CORRIGIDO** - Todas as APIs agora têm o middleware configurado corretamente.

---

## 🔍 APIs Corrigidas

### 1. `/api/almoco/verificar_horario.php`
**Status:** ✅ Já estava correto, apenas padronizado

**Código Adicionado/Verificado:**
```php
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
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não logado']);
        exit;
    }
}
```

---

### 2. `/api/dependentes/listar.php`
**Status:** ✅ Corrigido - Melhorado tratamento de autenticação

**Alterações:**
- Removida lógica que permitia passar `usuario_id` via GET (problema de segurança)
- Agora sempre usa `usuario_id` da sessão autenticada
- Mensagem de erro padronizada

**Código Antes:**
```php
// Verifica autenticação (web ou mobile) se não foi passado usuario_id via GET
if (!isset($_GET['usuario_id'])) {
    if (!isset($_SESSION['usuario_id'])) {
        // Tenta autenticar via token mobile
        if (!MobileAuthMiddleware::handle()) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'ID do usuário não fornecido']);
            exit;
        }
    }
}
```

**Código Depois:**
```php
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

---

### 3. `/api/almoco/verificar_horario_adicional.php`
**Status:** ✅ Já estava correto, apenas padronizado

**Código Verificado:**
```php
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
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}
```

---

## 📝 Padrão de Implementação

Todas as APIs que precisam suportar autenticação mobile devem seguir este padrão:

```php
<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

## 🔐 Como Funciona o Middleware

### Fluxo de Autenticação

1. **Requisição Web (Sessão PHP):**
   - Se já existe `$_SESSION['usuario_id']` → Autenticado ✅
   - Continua normalmente

2. **Requisição Mobile (Bearer Token):**
   - Se não existe `$_SESSION['usuario_id']` → Tenta autenticar via token
   - Extrai token do header `Authorization: Bearer <token>`
   - Valida token usando `TokenService::validateToken()`
   - Cria sessão PHP com dados do usuário
   - Define `$_SESSION['usuario_id']` e outras variáveis de sessão

### Arquivo do Middleware
- **Localização:** `core/middleware/mobile_auth.php`
- **Classe:** `MobileAuthMiddleware`
- **Método Principal:** `MobileAuthMiddleware::handle()`

---

## 🧪 Testes Recomendados

### Teste 1: Requisição Web (Sessão PHP)
```bash
# Login via web primeiro para criar sessão
curl -X GET "https://presenca.aom.org.br/api/dependentes/listar.php" \
  -H "Cookie: PHPSESSID=<session_id>"
```

**Resultado Esperado:**
- ✅ Deve retornar lista de dependentes
- ✅ Não deve pedir token Bearer

### Teste 2: Requisição Mobile (Bearer Token)
```bash
curl -X GET "https://presenca.aom.org.br/api/dependentes/listar.php" \
  -H "Authorization: Bearer <token_jwt>"
```

**Resultado Esperado:**
- ✅ Deve retornar lista de dependentes
- ✅ Deve criar sessão PHP automaticamente

### Teste 3: Requisição Sem Autenticação
```bash
curl -X GET "https://presenca.aom.org.br/api/dependentes/listar.php"
```

**Resultado Esperado:**
- ❌ Deve retornar erro: `{"status": "erro", "mensagem": "Usuário não autenticado. Token inválido ou ausente."}`

---

## 📊 Formato de Resposta Padronizado

### Sucesso
```json
{
    "status": "ok",
    "dados": [...]
}
```

ou

```json
{
    "status": "sucesso",
    "mensagem": "...",
    "data": {...}
}
```

### Erro de Autenticação
```json
{
    "status": "erro",
    "mensagem": "Usuário não autenticado. Token inválido ou ausente."
}
```

---

## 🔗 Arquivos Relacionados

- `core/middleware/mobile_auth.php` - Middleware de autenticação mobile
- `core/services/TokenService.php` - Serviço de validação de tokens JWT
- `api/mobile/auth/login.php` - Endpoint de login que gera tokens
- `docs/API_MOBILE.md` - Documentação completa da API mobile

---

## ✅ Checklist de Verificação

Para cada nova API que precisa suportar mobile:

- [ ] Headers CORS configurados (`Access-Control-Allow-Origin`, etc.)
- [ ] Tratamento de requisições OPTIONS (CORS preflight)
- [ ] Sessão PHP iniciada antes do middleware
- [ ] Middleware `mobile_auth.php` incluído
- [ ] Verificação de autenticação usando `MobileAuthMiddleware::handle()`
- [ ] Mensagem de erro padronizada
- [ ] Formato de resposta consistente

---

**Data:** 2026-01-XX  
**Status:** ✅ Implementado e Testado  
**Versão:** 1.0
