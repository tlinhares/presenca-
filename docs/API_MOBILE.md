# API REST Mobile - Documentação Completa

Documentação da API REST para o aplicativo mobile Flutter do Sistema de Presença AOM.

## URL Base

```
https://presenca.aom.org.br/api/mobile/
```

## Autenticação

A API utiliza autenticação **Bearer Token** (JWT). Todas as requisições autenticadas devem incluir o header:

```
Authorization: Bearer <token>
```

### Fluxo de Autenticação

1. **Login**: POST `/auth/login.php` com email e senha
2. **Recebe tokens**: Access token e refresh token
3. **Usa access token**: Em todas as requisições subsequentes
4. **Renova token**: Quando o access token expirar, use o refresh token

## Formato de Resposta

Todas as respostas seguem o formato padrão:

### Sucesso

```json
{
  "success": true,
  "data": { ... },
  "message": "Operação realizada com sucesso",
  "timestamp": "2025-01-XXT00:00:00+00:00"
}
```

### Erro

```json
{
  "success": false,
  "message": "Mensagem de erro",
  "timestamp": "2025-01-XXT00:00:00+00:00",
  "errors": { ... } // Opcional
}
```

## Endpoints

### Autenticação

#### POST /auth/login.php

Realiza login e retorna tokens de autenticação.

**Request Body:**
```json
{
  "email": "usuario@exemplo.com",
  "senha": "senha123"
}
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 86400,
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "nome": "João Silva",
      "email": "usuario@exemplo.com",
      "categoria": "admin"
    }
  },
  "message": "Login realizado com sucesso"
}
```

**Response 401:**
```json
{
  "success": false,
  "message": "Credenciais inválidas"
}
```

**Response 400:**
```json
{
  "success": false,
  "message": "Email e senha são obrigatórios"
}
```

---

#### POST /auth/refresh.php

Renova o access token usando o refresh token.

**Request Headers:**
```
Authorization: Bearer <refresh_token>
```

**Ou Request Body:**
```json
{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 86400,
    "token_type": "Bearer"
  },
  "message": "Token renovado com sucesso"
}
```

**Response 401:**
```json
{
  "success": false,
  "message": "Refresh token inválido ou expirado"
}
```

---

#### POST /auth/logout.php

Realiza logout (principalmente para logging no servidor).

**Request Headers:**
```
Authorization: Bearer <token>
```

**Response 200:**
```json
{
  "success": true,
  "message": "Logout realizado com sucesso"
}
```

---

## Códigos de Status HTTP

| Código | Descrição |
|--------|-----------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Requisição inválida |
| 401 | Não autorizado (token inválido ou ausente) |
| 403 | Acesso negado (sem permissão) |
| 404 | Recurso não encontrado |
| 422 | Erro de validação |
| 500 | Erro interno do servidor |

## Tokens JWT

### Estrutura do Access Token

```json
{
  "iat": 1234567890,
  "exp": 1234654290,
  "user_id": 1,
  "nome": "João Silva",
  "categoria": "admin",
  "email": "usuario@exemplo.com",
  "type": "access"
}
```

### Estrutura do Refresh Token

```json
{
  "iat": 1234567890,
  "exp": 1235169090,
  "user_id": 1,
  "type": "refresh"
}
```

### Tempo de Expiração

- **Access Token**: 24 horas (86400 segundos)
- **Refresh Token**: 7 dias (604800 segundos)

## CORS

A API suporta CORS para requisições de origem cruzada:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

## Exemplos de Uso

### Exemplo 1: Login

```bash
curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "usuario@exemplo.com",
    "senha": "senha123"
  }'
```

### Exemplo 2: Requisição Autenticada

```bash
curl -X GET https://presenca.aom.org.br/api/mobile/users \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

### Exemplo 3: Renovar Token

```bash
curl -X POST https://presenca.aom.org.br/api/mobile/auth/refresh.php \
  -H "Authorization: Bearer <refresh_token>"
```

## Integração com Flutter

### Exemplo de Cliente API

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class ApiClient {
  static const String baseUrl = 'https://presenca.aom.org.br/api/mobile';
  
  Future<Map<String, dynamic>> login(String email, String senha) async {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/login.php'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'email': email,
        'senha': senha,
      }),
    );
    
    return jsonDecode(response.body);
  }
  
  Future<Map<String, dynamic>> get(String endpoint, String token) async {
    final response = await http.get(
      Uri.parse('$baseUrl/$endpoint'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );
    
    return jsonDecode(response.body);
  }
}
```

## Segurança

### Boas Práticas

1. **Nunca exponha tokens** em logs ou mensagens de erro
2. **Armazene tokens** de forma segura (flutter_secure_storage)
3. **Renove tokens** antes de expirarem
4. **Valide tokens** no cliente antes de usar
5. **Use HTTPS** sempre em produção

### Armazenamento de Tokens

No Flutter, use `flutter_secure_storage` para armazenar tokens:

```dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final storage = FlutterSecureStorage();

// Salvar token
await storage.write(key: 'access_token', value: token);

// Ler token
final token = await storage.read(key: 'access_token');

// Limpar tokens (logout)
await storage.deleteAll();
```

## Tratamento de Erros

### Erro 401 - Não Autorizado

Quando receber 401, o app deve:
1. Tentar renovar o token usando refresh token
2. Se falhar, redirecionar para tela de login
3. Limpar tokens armazenados

### Erro 403 - Acesso Negado

Usuário não tem permissão para acessar o recurso. Exibir mensagem apropriada.

### Erro 500 - Erro do Servidor

Erro interno do servidor. Registrar erro e informar ao usuário.

## Rate Limiting

Atualmente não há rate limiting implementado. Pode ser adicionado no futuro.

## Changelog

### v1.0.0 (2025-01-XX)
- Implementação inicial da API mobile
- Autenticação Bearer Token (JWT)
- Endpoints de login, refresh e logout
- Suporte a CORS

## Suporte

Para dúvidas ou problemas, entre em contato com a equipe de desenvolvimento.
