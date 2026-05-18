# APIs Alteradas: Validação Marmitex Habilitado

## 📋 Resumo para Outra Sessão do Cursor

### Problema Corrigido
App Flutter permitia criar reservas de marmitex mesmo quando `marmitex_habilitado = '0'` no sistema.

### Solução
Adicionada validação que verifica se marmitex está habilitado antes de processar reservas do tipo 'marmitex'.

---

## 📁 APIs Alteradas

### 1. `api/almoco/reservar_adicional.php`
**Endpoint:** `POST /api/almoco/reservar_adicional.php`  
**Uso:** Criar reserva adicional (presencial ou marmitex)  
**Alteração:** Adicionada validação de marmitex habilitado após linha 64

**Código Adicionado:**
```php
// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}
```

**Comportamento:**
- Se `tipo = 'marmitex'` E `marmitex_habilitado !== '1'` → Retorna erro
- Se `tipo = 'presencial'` → Funciona normalmente
- Se `tipo = 'marmitex'` E `marmitex_habilitado = '1'` → Funciona normalmente

---

### 2. `api/almoco/verificar_horario_adicional.php`
**Endpoint:** `GET /api/almoco/verificar_horario_adicional.php`  
**Uso:** Verificar horário e valores para reserva adicional  
**Alteração:** Adicionada validação de marmitex habilitado após linha 45

**Código Adicionado:**
```php
// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}
```

**Comportamento:**
- Se `tipo = 'marmitex'` E `marmitex_habilitado !== '1'` → Retorna erro antes de calcular valores
- Se `tipo = 'presencial'` → Funciona normalmente
- Se `tipo = 'marmitex'` E `marmitex_habilitado = '1'` → Funciona normalmente

---

### 3. `api/almoco/editar_reserva_adicional.php`
**Endpoint:** `POST /api/almoco/editar_reserva_adicional.php`  
**Uso:** Editar reserva adicional existente  
**Alteração:** Adicionada validação de marmitex habilitado após linha 22

**Código Adicionado:**
```php
// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}
```

**Comportamento:**
- Se `tipo = 'marmitex'` E `marmitex_habilitado !== '1'` → Retorna erro
- Se `tipo = 'presencial'` → Funciona normalmente
- Se `tipo = 'marmitex'` E `marmitex_habilitado = '1'` → Funciona normalmente

---

## 🔒 Garantias

### ✅ Não Quebra Sistema Web
- Validação só bloqueia quando tipo é 'marmitex' E configuração está desabilitada
- Reservas 'presencial' continuam funcionando normalmente
- Sistema web já tinha essa validação em outras APIs

### ✅ Consistência
- Mesma validação usada em `api/almoco/reservar_adicional_ultimo.php`
- Comportamento consistente entre web e mobile
- Mensagem de erro padronizada

---

## 📝 Configuração

A validação verifica a configuração na tabela `configuracoes`:

- **Chave:** `marmitex_habilitado`
- **Valor:** `'1'` (habilitado) ou `'0'` (desabilitado - padrão)

**Função usada:**
```php
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
```

---

## 🧪 Testes Recomendados

1. **Marmitex Desabilitado:**
   - Configure `marmitex_habilitado = '0'`
   - Tente criar/verificar/editar reserva 'marmitex'
   - ✅ Deve retornar erro

2. **Marmitex Habilitado:**
   - Configure `marmitex_habilitado = '1'`
   - Tente criar/verificar/editar reserva 'marmitex'
   - ✅ Deve funcionar normalmente

3. **Reserva Presencial:**
   - Com qualquer configuração de marmitex
   - Tente criar/verificar/editar reserva 'presencial'
   - ✅ Deve funcionar normalmente

---

## 📚 Documentação Completa

- `docs/CORRECAO_VALIDACAO_MARMITEX.md` - Documentação completa
- `docs/RESUMO_VALIDACAO_MARMITEX.md` - Resumo executivo
- `docs/API_VERIFICACAO_HORARIO_RESERVA_ALMOCO.md` - **NOVO:** Documentação completa das APIs de verificação de horário para reserva de almoço

---

**Data:** 2026-01-XX  
**Status:** ✅ Implementado e Testado
