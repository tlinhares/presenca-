# 🔧 Correções: APIs de Usuários - Middleware Mobile

## 📋 Resumo para Outra Sessão do Cursor

Foram corrigidas as APIs de usuários que estavam retornando erro de autenticação mesmo com token válido.

---

## ✅ APIs Corrigidas

### 1. `buscar_perfil.php` ✅
**Endpoint:** `GET /api/usuarios/buscar_perfil.php`

**Problema:** Retornava `{"status":"erro","mensagem":"Usuário não logado"}` mesmo com token válido.

**Correção:**
- ✅ Middleware mobile adicionado
- ✅ Headers CORS configurados
- ✅ Suporte a Bearer Token

**Formato de Resposta:**
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

**Onde a Foto Está:**
- Caminho: `data.usuario.foto_base64`
- O Flutter deve buscar: `data['usuario']?['foto_base64']`

---

### 2. `atualizar_perfil.php` ✅
**Endpoint:** `POST /api/usuarios/atualizar_perfil.php`

**Problema:** Não tinha middleware mobile e não aceitava JSON.

**Correção:**
- ✅ Middleware mobile adicionado
- ✅ Headers CORS configurados
- ✅ Suporte a JSON (mobile) e form-data (web)

**Body (JSON ou form-data):**
```json
{
    "nome": "Nome do Usuário",
    "email": "email@exemplo.com",
    "telefone": "123456789",
    "senha": "nova_senha",
    "senha_confirma": "nova_senha",
    "foto_base64": "base64_string..."
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Perfil atualizado com sucesso"
}
```

---

## 🧪 Testes

### Teste 1: Buscar Perfil

```bash
# Obter token
TOKEN=$(curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","senha":"suasenha"}' | jq -r '.data.token')

# Buscar perfil
curl -X GET "https://presenca.aom.org.br/api/usuarios/buscar_perfil.php" \
  -H "Authorization: Bearer $TOKEN"
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ JSON com dados do usuário incluindo `foto_base64`

### Teste 2: Atualizar Perfil

```bash
# Atualizar perfil
curl -X POST "https://presenca.aom.org.br/api/usuarios/atualizar_perfil.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Novo Nome",
    "email": "novo@email.com",
    "telefone": "987654321"
  }'
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ `{"status":"ok","mensagem":"Perfil atualizado com sucesso"}`

---

## 📊 Status das APIs de Usuários

| API | Método | Middleware Mobile | Status |
|-----|--------|-------------------|--------|
| `buscar_perfil.php` | GET | ✅ SIM | ✅ Corrigido |
| `atualizar_perfil.php` | POST | ✅ SIM | ✅ Corrigido |
| `perfil.php` | GET | ❌ NÃO | ⚠️ Precisa middleware |
| `atualizar_foto.php` | POST | ❌ NÃO | ⚠️ Precisa middleware |
| `listar.php` | GET | ❌ NÃO | ⚠️ Precisa middleware |
| `cadastrar.php` | POST | ❌ NÃO | ⚠️ Precisa middleware |
| `editar.php` | POST | ❌ NÃO | ⚠️ Precisa middleware |
| `excluir.php` | POST | ❌ NÃO | ⚠️ Precisa middleware |

---

## 🔍 Formato Esperado pelo Flutter

### Buscar Perfil
```dart
final response = await http.get(
  Uri.parse('https://presenca.aom.org.br/api/usuarios/buscar_perfil.php'),
  headers: {'Authorization': 'Bearer $token'},
);

final data = json.decode(response.body);
final fotoBase64 = data['usuario']?['foto_base64'];
```

### Atualizar Perfil
```dart
final response = await http.post(
  Uri.parse('https://presenca.aom.org.br/api/usuarios/atualizar_perfil.php'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
  body: json.encode({
    'nome': nome,
    'email': email,
    'telefone': telefone,
    'foto_base64': fotoBase64, // Opcional
  }),
);
```

---

## ✅ Conclusão

- ✅ `buscar_perfil.php` agora funciona com Bearer Token
- ✅ `atualizar_perfil.php` agora aceita JSON e Bearer Token
- ✅ Foto está sendo retornada em `usuario.foto_base64`
- ✅ Flutter pode buscar a foto corretamente

**Próximo Passo:** Testar no app Flutter após fazer logout e login novamente.

---

**Data:** 2026-01-XX  
**Status:** ✅ Corrigido
