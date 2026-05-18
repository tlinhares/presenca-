# 🔧 Correção: Sistema de Preferências de Notificação

## 📋 Problema Identificado

Após implementações para o aplicativo mobile, o sistema parou de respeitar as escolhas do usuário sobre envio de mensagens WhatsApp. O sistema estava marcando WhatsApp como desativado ou não permitindo WhatsApp, enviando tudo por email.

---

## 🔍 Causa Raiz

### Problema 1: Função `usuarioQuerNotificacao()` retornava `false` por padrão

**Arquivo:** `api/notificacao/enviar_notificacao_reserva.php`

**Problema:**
- Quando um usuário não tinha configuração na tabela `notificacoes_usuario`, a função retornava `false`
- Isso impedia o envio de notificações mesmo quando o usuário queria receber

**Código Antigo:**
```php
function usuarioQuerNotificacao($usuario_id, $tipo_notificacao, $conn) {
    // ...
    if ($result->num_rows > 0) {
        return (bool)$row[$tipo_notificacao];
    }
    return false; // ❌ PROBLEMA: Bloqueava notificações por padrão
}
```

### Problema 2: Verificação de `desabilitar_whatsapp` muito restritiva

**Arquivo:** `core/services/WhatsAppService.php`

**Problema:**
- A verificação de `desabilitar_whatsapp` estava sendo feita mesmo quando não havia configuração
- Isso poderia bloquear WhatsApp incorretamente

---

## ✅ Correções Implementadas

### Correção 1: Padrão Permitir Notificações

**Arquivo:** `api/notificacao/enviar_notificacao_reserva.php`

**Mudança:**
- Quando não há configuração na tabela `notificacoes_usuario`, a função agora retorna `true` (permitir)
- Isso garante que usuários sem configuração explícita ainda recebam notificações

**Código Novo:**
```php
function usuarioQuerNotificacao($usuario_id, $tipo_notificacao, $conn) {
    // Verificar se tabela existe
    $tabela_existe = $conn->query("SHOW TABLES LIKE 'notificacoes_usuario'")->num_rows > 0;
    
    if (!$tabela_existe) {
        // Tabela não existe, permitir notificação por padrão
        return true;
    }
    
    // ... buscar configuração ...
    
    if ($result->num_rows > 0) {
        // Se tem configuração, respeitar a escolha do usuário
        return (bool)$row[$tipo_notificacao];
    }
    
    // ✅ CORRIGIDO: Se não tem configuração, PERMITIR por padrão
    return true;
}
```

### Correção 2: Verificação de `desabilitar_whatsapp` Mais Precisa

**Arquivo:** `core/services/WhatsAppService.php`

**Mudança:**
- Verificação de `desabilitar_whatsapp` só acontece se HOUVER configuração específica
- Se não há configuração, permite WhatsApp por padrão

**Código Novo:**
```php
// IMPORTANTE: Só verificar desabilitar_whatsapp se HOUVER configuração específica
// Se não há configuração, permitir WhatsApp por padrão
if ($config) {
    $desabilitar = isset($config['desabilitar_whatsapp']) ? intval($config['desabilitar_whatsapp']) : 0;
    $modo_desabilitado = isset($config['modo_selecao']) && $config['modo_selecao'] === 'desabilitado';
    
    if ($desabilitar || $modo_desabilitado) {
        // Bloquear WhatsApp apenas se explicitamente desabilitado
        return ['sucesso' => false, 'mensagem' => 'WhatsApp desabilitado...', 'fallback_email' => true];
    }
}
// Se não há configuração, continuar normalmente (permitir WhatsApp)
```

---

## 📊 Comportamento Esperado Agora

### Cenário 1: Usuário SEM configuração de preferências
- ✅ **Permite notificações por padrão**
- ✅ **Tenta WhatsApp primeiro** (se tiver telefone)
- ✅ **Faz fallback para email** se WhatsApp falhar

### Cenário 2: Usuário COM configuração explícita
- ✅ **Respeita a escolha do usuário**
- ✅ Se marcou "não receber", não envia
- ✅ Se marcou "receber", envia via WhatsApp ou email

### Cenário 3: Configuração de WhatsApp desabilitada
- ✅ **Bloqueia WhatsApp** apenas se `desabilitar_whatsapp = 1` OU `modo_selecao = 'desabilitado'`
- ✅ **Faz fallback para email** automaticamente

---

## 🧪 Como Testar

1. **Teste com usuário sem configuração:**
   - Criar reserva para usuário que não tem registro em `notificacoes_usuario`
   - ✅ Deve enviar notificação (WhatsApp ou email)

2. **Teste com usuário que desabilitou notificações:**
   - Marcar `notificar_reserva_propria = 0` na tabela `notificacoes_usuario`
   - Criar reserva
   - ✅ Não deve enviar notificação

3. **Teste com WhatsApp desabilitado na configuração:**
   - Marcar `desabilitar_whatsapp = 1` em `whatsapp_config_notificacoes` para um tipo
   - Criar reserva desse tipo
   - ✅ Deve enviar por email, não por WhatsApp

---

## ✅ Status

- ✅ Função `usuarioQuerNotificacao()` corrigida
- ✅ Verificação de `desabilitar_whatsapp` melhorada
- ✅ Padrão agora é PERMITIR notificações
- ✅ Sistema respeita escolhas explícitas do usuário

---

**Data:** 2026-01-XX  
**Status:** ✅ Corrigido
