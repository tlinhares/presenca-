# Resumo: Validação Marmitex Habilitado

## ⚠️ IMPORTANTE: NÃO QUEBRA O SISTEMA WEB

Todas as alterações seguem o mesmo padrão já usado no sistema web. Apenas aplicamos a validação existente nas APIs mobile.

---

## 🔧 O Que Foi Alterado

### Problema
App Flutter permitia criar reservas de marmitex mesmo quando desativado no sistema.

### Solução
Adicionada validação que verifica `marmitex_habilitado` antes de permitir reservas do tipo 'marmitex'.

---

## 📁 Arquivos Modificados

### 1. `api/almoco/reservar_adicional.php`
**Mudança:** Após validação de tipo
- Adicionada verificação: se tipo é 'marmitex' E `marmitex_habilitado !== '1'` → Erro

### 2. `api/almoco/verificar_horario_adicional.php`
**Mudança:** Após validação de tipo
- Adicionada verificação: se tipo é 'marmitex' E `marmitex_habilitado !== '1'` → Erro

### 3. `api/almoco/editar_reserva_adicional.php`
**Mudança:** Após validação de tipo
- Adicionada verificação: se tipo é 'marmitex' E `marmitex_habilitado !== '1'` → Erro

---

## 💻 Código Adicionado

```php
// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}
```

**Onde:** Logo após validar se o tipo é válido, antes de processar dados.

---

## ✅ Validação

- ✅ Sistema web não afetado (validação já existia)
- ✅ App mobile agora respeita configuração
- ✅ Reservas presenciais não são afetadas
- ✅ Nenhum erro de lint

---

## 🧪 Teste Rápido

1. Configure `marmitex_habilitado = '0'` no sistema
2. Tente criar reserva 'marmitex' pelo app
3. ✅ Deve retornar erro: "Reservas para marmitex estão desabilitadas no sistema."

---

**Data:** 2026-01-XX  
**Status:** ✅ Implementado
