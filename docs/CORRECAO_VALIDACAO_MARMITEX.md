# Correção: Validação de Marmitex Habilitado nas APIs Mobile

## Data: 2026-01-XX
## Status: ✅ Implementado e Testado

---

## 📋 Resumo Executivo

**Problema:** O aplicativo Flutter estava permitindo criar reservas de marmitex mesmo quando o marmitex estava desativado no sistema.

**Solução:** Adicionada validação que verifica se o marmitex está habilitado antes de permitir reservas do tipo 'marmitex', seguindo o mesmo padrão do sistema web.

**Impacto:** ✅ **ZERO impacto no sistema web** - validação já existia em outras APIs, apenas aplicada nas APIs mobile.

---

## 🔍 Problema Identificado

### Comportamento Incorreto
- App Flutter permitia criar reservas de marmitex mesmo quando `marmitex_habilitado = '0'` na configuração
- Sistema web já tinha essa validação, mas as APIs mobile não

### Causa Raiz
As APIs `reservar_adicional.php` e `verificar_horario_adicional.php` não verificavam a configuração `marmitex_habilitado` antes de processar reservas do tipo 'marmitex'.

---

## ✅ Solução Implementada

### Validação Adicionada

Agora todas as APIs verificam se o marmitex está habilitado antes de permitir reservas do tipo 'marmitex':

```php
// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}
```

### Onde a Validação é Aplicada

A validação é executada **imediatamente após** verificar se o tipo é válido e **antes** de qualquer processamento de dados ou cálculo de valores.

---

## 📁 Arquivos Alterados

### 1. `api/almoco/reservar_adicional.php`
**Linhas alteradas:** Após linha 64

**Antes:**
```php
if (empty($data) || $quantidade <= 0 || !in_array($tipo, ['presencial', 'marmitex']) || $id_dependente <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

// Verifica se o dependente pertence ao usuário...
```

**Depois:**
```php
if (empty($data) || $quantidade <= 0 || !in_array($tipo, ['presencial', 'marmitex']) || $id_dependente <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}

// Verifica se o dependente pertence ao usuário...
```

---

### 2. `api/almoco/verificar_horario_adicional.php`
**Linhas alteradas:** Após linha 45

**Antes:**
```php
if (!in_array($tipo, ['presencial', 'marmitex'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo de refeição inválido']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_reserva)) {
```

**Depois:**
```php
if (!in_array($tipo, ['presencial', 'marmitex'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo de refeição inválido']);
    exit;
}

// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_reserva)) {
```

---

### 3. `api/almoco/editar_reserva_adicional.php`
**Linhas alteradas:** Após linha 22

**Antes:**
```php
if ($reserva_id <= 0 || empty($data) || $quantidade <= 0 || !in_array($tipo, ['presencial', 'marmitex']) || $id_dependente <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

try {
```

**Depois:**
```php
if ($reserva_id <= 0 || empty($data) || $quantidade <= 0 || !in_array($tipo, ['presencial', 'marmitex']) || $id_dependente <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}

try {
```

---

## 🔒 Garantia de Retrocompatibilidade

### Como Funciona

1. **Validação Apenas para Tipo 'marmitex':**
   - Se `$tipo !== 'marmitex'` → Validação não é executada
   - Se `$tipo === 'marmitex'` → Verifica configuração

2. **Comportamento do Sistema Web:**
   - Sistema web já tinha essa validação em `reservar_adicional_ultimo.php`
   - Agora todas as APIs têm a mesma validação
   - **Comportamento consistente entre web e mobile**

3. **Configuração:**
   - `marmitex_habilitado = '1'` → Marmitex habilitado
   - `marmitex_habilitado = '0'` → Marmitex desabilitado (padrão)

### Por Que Não Quebra

- ✅ Validação só bloqueia quando tipo é 'marmitex' E configuração está desabilitada
- ✅ Reservas 'presencial' continuam funcionando normalmente
- ✅ Sistema web já tinha essa validação, apenas aplicada nas APIs mobile
- ✅ Mensagem de erro clara e informativa

---

## 📊 Impacto no Sistema

### ✅ Zero Impacto Negativo

- ✅ Sistema web continua funcionando normalmente
- ✅ Reservas presenciais não são afetadas
- ✅ Validação segue o mesmo padrão já existente
- ✅ Nenhuma dependência nova adicionada
- ✅ Nenhuma configuração adicional necessária

### ✅ Benefícios

- ✅ App mobile agora respeita a configuração de marmitex
- ✅ Consistência entre sistema web e mobile
- ✅ Previne reservas inválidas de marmitex quando desabilitado
- ✅ Mensagem de erro clara para o usuário

---

## 🧪 Como Testar

### Teste 1: Marmitex Desabilitado

1. Configure `marmitex_habilitado = '0'` no sistema
2. Tente criar reserva adicional do tipo 'marmitex' pelo app
3. ✅ Deve retornar: `{"status":"erro","mensagem":"Reservas para marmitex estão desabilitadas no sistema."}`

### Teste 2: Marmitex Habilitado

1. Configure `marmitex_habilitado = '1'` no sistema
2. Tente criar reserva adicional do tipo 'marmitex' pelo app
3. ✅ Deve funcionar normalmente

### Teste 3: Reserva Presencial (Não Afetada)

1. Com marmitex desabilitado ou habilitado
2. Tente criar reserva adicional do tipo 'presencial'
3. ✅ Deve funcionar normalmente (validação não interfere)

### Teste 4: Verificar Horário Adicional

1. Com marmitex desabilitado
2. Tente verificar horário para tipo 'marmitex'
3. ✅ Deve retornar erro antes de calcular valores

---

## 📝 Detalhes Técnicos

### Função `get_config()`

A função `get_config()` busca o valor da configuração na tabela `configuracoes`:

```php
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
```

- **Parâmetro 1:** Chave da configuração (`'marmitex_habilitado'`)
- **Parâmetro 2:** Valor padrão se não encontrar (`'0'`)
- **Retorno:** String `'1'` ou `'0'`

### Comparação Estrita

A validação usa comparação estrita (`!==`) para garantir que apenas o valor exato `'1'` seja aceito:

```php
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    // Bloqueia reserva
}
```

Isso garante que valores como `'true'`, `1`, `true` não sejam aceitos acidentalmente.

---

## 🔄 Padrão Aplicado

Esta validação segue o mesmo padrão já usado em `api/almoco/reservar_adicional_ultimo.php`:

```php
// Verifica se marmitex está habilitado
$marmitex_habilitado = get_config('marmitex_habilitado', '0');
if ($tipo === 'marmitex' && $marmitex_habilitado !== '1') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Reservas para marmitex estão desabilitadas no sistema.']);
    exit;
}
```

---

## 📞 Suporte

Se encontrar algum problema após esta correção:

1. Verificar configuração `marmitex_habilitado` na tabela `configuracoes`
2. Verificar se o tipo da reserva está sendo enviado corretamente
3. Verificar logs do servidor PHP
4. Comparar com o código antes/depois desta correção

---

## ✅ Checklist de Validação

- [x] Código testado localmente
- [x] Retrocompatibilidade garantida
- [x] Nenhum erro de lint
- [x] Documentação criada
- [x] Sistema web não afetado
- [x] App mobile agora respeita configuração
- [x] Validação consistente com sistema web

---

**Documento criado em:** 2026-01-XX  
**Autor:** Cursor AI Assistant  
**Versão:** 1.0
