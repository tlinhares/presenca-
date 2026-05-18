# 🔧 Correção: API buscar_perfil.php - Middleware Mobile

## 📋 Problema Identificado

A API `buscar_perfil.php` estava retornando erro de autenticação mesmo com token válido:

```
{"status":"erro","mensagem":"Usuário não logado"}
```

**Causa:** Middleware mobile não estava configurado no arquivo.

---

## ✅ Correção Implementada

### Arquivo Modificado
`api/usuarios/buscar_perfil.php`

### Mudanças Realizadas

1. **Headers CORS adicionados:**
```php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

2. **Tratamento de OPTIONS (CORS preflight):**
```php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
```

3. **Middleware mobile adicionado:**
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
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Usuário não autenticado. Token inválido ou ausente.'
        ]);
        exit;
    }
}
```

---

## 📊 Formato de Resposta

### Sucesso
```json
{
    "status": "ok",
    "usuario": {
        "id": 22222,
        "nome": "Tiago Linhares Tavares",
        "email": "tlinhares@gmail.com",
        "telefone": "123456789",
        "foto_base64": "/9j/4AAQSkZJRgABAQEAYABgAAD...",
        "id_valor": 1,
        "entidade_id": 1,
        "grupo_nome": "Grupo A",
        "entidade_nome": "Entidade 1"
    }
}
```

### Erro
```json
{
    "status": "erro",
    "mensagem": "Usuário não autenticado. Token inválido ou ausente."
}
```

---

## 🔍 Onde a Foto Está na Resposta

A foto está em: **`data.usuario.foto_base64`**

O Flutter deve buscar assim:
```dart
final fotoBase64 = data['usuario']?['foto_base64'];
```

---

## 🧪 Teste

```bash
# Obter token
TOKEN=$(curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","senha":"suasenha"}' | jq -r '.data.token')

# Testar buscar perfil
curl -X GET "https://presenca.aom.org.br/api/usuarios/buscar_perfil.php" \
  -H "Authorization: Bearer $TOKEN"
```

**Resultado Esperado:**
```json
{
    "status": "ok",
    "usuario": {
        "id": 22222,
        "nome": "...",
        "foto_base64": "..."
    }
}
```

---

## ✅ Status

- ✅ Middleware mobile configurado
- ✅ Headers CORS configurados
- ✅ Suporte a Bearer Token
- ✅ Compatível com autenticação web (sessão PHP)

---

**Data:** 2026-01-XX  
**Status:** ✅ Corrigido e Testado
