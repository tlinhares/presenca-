# 🔐 Problema: Autenticação na API /api/almoco/

## ❌ Erro Encontrado

Quando tenta acessar `/api/almoco/listar_reservas_usuario.php`:
- **Com CORS**: Erro `Failed to fetch` (bloqueado pelo navegador)
- **Sem CORS**: Erro 401 "Usuário não logado"

## 🔍 Causa do Problema

As APIs `/api/almoco/*` precisam do **middleware `MobileAuthMiddleware`** para converter o Bearer Token em sessão PHP.

### Como Funciona

1. Flutter envia: `Authorization: Bearer <token>`
2. Middleware converte token → `$_SESSION['usuario_id']`
3. API usa `$_SESSION['usuario_id']` para autenticação

### Problema

Se o middleware **não estiver sendo chamado** nas APIs `/api/almoco/*`, elas retornam 401 porque não encontram `$_SESSION['usuario_id']`.

## ✅ Solução no Backend

Cada arquivo PHP em `/api/almoco/` precisa incluir o middleware:

```php
<?php
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';

MobileAuthMiddleware::requireAuth();

// Resto do código da API...
```

### Arquivos que Precisam do Middleware

- `/api/almoco/verificar_horario.php`
- `/api/almoco/reservar.php`
- `/api/almoco/listar_reservas_usuario.php`
- `/api/almoco/cancelar_reserva_propria.php`
- `/api/almoco/verificar_horario_adicional.php`
- `/api/almoco/reservar_adicional.php`
- `/api/almoco/listar_reservas_adicionais_usuario.php`
- `/api/almoco/excluir_reserva_adicional.php`

## 🔧 Verificação Rápida

Teste no navegador (após fazer login no sistema web):

```
https://presenca.aom.org.br/api/almoco/listar_reservas_usuario.php?data_inicio=2026-01-01&data_fim=2026-01-31
```

Se funcionar no navegador mas não no Flutter, o problema é:
1. **CORS** (resolvido com Chrome sem CORS)
2. **Middleware não configurado** (precisa adicionar no backend)

## 📝 Nota

O token está sendo enviado corretamente pelo Flutter. O problema é que o servidor não está convertendo o token em sessão PHP.

## 🚀 Próximos Passos

1. Verificar se o middleware está sendo chamado nas APIs `/api/almoco/*`
2. Se não estiver, adicionar o middleware em cada arquivo
3. Testar novamente no Flutter
