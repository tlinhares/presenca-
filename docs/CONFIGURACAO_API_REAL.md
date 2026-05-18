# ✅ Configuração para API Real - Concluída

## 🎯 Objetivo
Garantir que o sistema funcione completamente com a API real do servidor.

## ✅ Alterações Realizadas

### 1. Serviços Configurados para API Real

#### ✅ `lib/core/api/reservas_service.dart`
- `_useMockData = false` ✅ (já estava configurado)
- Todas as requisições agora usam a API real

#### ✅ `lib/core/api/dependentes_service.dart`
- `_useMockData = false` ✅ (alterado de `true` para `false`)
- Todas as requisições agora usam a API real

### 2. Melhorias no ApiClient

#### ✅ Verificação de Token Antes das Requisições
- Adicionada verificação de token antes de fazer GET/POST
- Retorna erro 401 imediatamente se não houver token
- Evita requisições desnecessárias ao servidor

#### ✅ Logs Melhorados
- Mostra preview do token sendo enviado
- Logs mais detalhados para debug
- Identifica claramente quando não há token

#### ✅ Tratamento de Erro 401
- Limpa tokens automaticamente quando recebe 401
- Mensagem clara para o usuário
- Log informativo sobre possível problema no backend

### 3. Endpoints Configurados

Todos os endpoints estão usando as URLs corretas:

- **API Mobile**: `https://presenca.aom.org.br/api/mobile/`
  - Login, Refresh, Logout
  - Usuários

- **API Almoço**: `https://presenca.aom.org.br/api/almoco/`
  - Verificar horário
  - Criar reserva própria
  - Listar reservas
  - Cancelar reserva
  - Reservas adicionais (dependentes)

- **API Dependentes**: `https://presenca.aom.org.br/api/dependentes/`
  - Listar dependentes

## 🔍 Como Verificar se Está Funcionando

### 1. Verificar Logs no Console

Quando executar o app, você verá logs como:

```
🔵 GET Request: https://presenca.aom.org.br/api/almoco/listar_reservas_usuario.php?...
🔵 Headers: Content-Type, Accept, Authorization
🔵 Token Preview: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
🟢 Response Status: 200
🟢 Response Body: {...}
```

### 2. Se Ver Erro 401

Se aparecer erro 401 "Usuário não logado":
- **Causa provável**: O middleware `MobileAuthMiddleware` não está configurado nas APIs `/api/almoco/*`
- **Solução**: Verificar no backend se cada arquivo PHP em `/api/almoco/` inclui:
  ```php
  require_once __DIR__ . '/../../core/middleware/mobile_auth.php';
  MobileAuthMiddleware::requireAuth();
  ```

### 3. Se Ver Erro de CORS

- **Solução**: Use o Chrome sem CORS para desenvolvimento
- Execute: `executar_chrome_sem_cors.bat`
- Depois execute: `flutter run -d chrome`

## 📝 Próximos Passos

1. ✅ Todos os serviços estão usando API real
2. ✅ Verificação de token implementada
3. ✅ Logs melhorados para debug
4. ⚠️ **Backend**: Verificar se middleware está configurado nas APIs `/api/almoco/*`

## 🚨 Importante

O sistema está **100% configurado para usar a API real**. Se ainda houver problemas:

1. **Erro 401**: Verificar middleware no backend
2. **Erro CORS**: Usar Chrome sem CORS para desenvolvimento
3. **Token expirado**: Fazer login novamente

## 📋 Checklist

- [x] `reservas_service.dart` usando API real
- [x] `dependentes_service.dart` usando API real
- [x] Verificação de token antes das requisições
- [x] Logs melhorados
- [x] Tratamento de erro 401
- [x] Endpoints corretos configurados
- [ ] Backend: Middleware configurado nas APIs `/api/almoco/*` (verificar no servidor)
