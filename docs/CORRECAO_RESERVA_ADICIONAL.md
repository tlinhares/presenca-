# 🔧 Correção: Erro "Dados inválidos" na Reserva Adicional

## ❌ Problema Identificado

Ao criar reserva adicional, a API retornava:
```json
{
  "status": "erro",
  "mensagem": "Dados inválidos."
}
```

## 🔍 Causa do Problema

A API PHP espera valores `0/1` para campos booleanos, mas estávamos enviando `true/false`.

### Requisição Enviada (Incorreta):
```json
{
  "data": "2026-01-19",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 88,
  "fora_do_horario": true  // ❌ Boolean
}
```

### Requisição Esperada (Correta):
```json
{
  "data": "2026-01-19",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 88,
  "fora_do_horario": 1  // ✅ Integer (0 ou 1)
}
```

---

## ✅ Correções Aplicadas

### 1. Conversão de Boolean para Integer

**Arquivo:** `lib/core/api/reservas_service.dart`

#### Reserva Adicional (linha 236):
```dart
'fora_do_horario': foraDoHorario ? 1 : 0, // PHP espera 0/1 ao invés de true/false
```

#### Reserva Própria (linha 85):
```dart
'fora_do_horario': foraDoHorario ? 1 : 0, // PHP espera 0/1 ao invés de true/false
```

### 2. Logs Melhorados

Adicionados logs detalhados para facilitar debug:

```dart
debugPrint('🌐 CRIANDO RESERVA ADICIONAL NA API REAL:');
debugPrint('🌐 URL: ${ApiEndpoints.criarReservaAdicional}');
debugPrint('🌐 Body: $body');
```

### 3. Validações na Tela

**Arquivo:** `lib/features/reservas/criar_reserva_adicional_screen.dart`

Adicionados logs de debug antes de criar a reserva:
```dart
debugPrint('🔵 Criando reserva adicional:');
debugPrint('🔵 Dependente ID: ${_dependenteSelecionado!.id}');
debugPrint('🔵 Data: ${hoje.year}-${hoje.month.toString().padLeft(2, '0')}-${hoje.day.toString().padLeft(2, '0')}');
debugPrint('🔵 Tipo: $_tipo');
debugPrint('🔵 Fora do horário: $_foraDoHorario');
```

---

## 📊 Comparação: Antes vs Depois

### Antes (Incorreto)
```dart
body: {
  'fora_do_horario': foraDoHorario,  // true/false
}
```

**JSON Enviado:**
```json
{"fora_do_horario": true}
```

### Depois (Correto)
```dart
body: {
  'fora_do_horario': foraDoHorario ? 1 : 0,  // 0/1
}
```

**JSON Enviado:**
```json
{"fora_do_horario": 1}
```

---

## ✅ Teste

Após a correção, a requisição deve funcionar corretamente:

**Requisição:**
```http
POST /api/almoco/reservar_adicional.php
Body: {
  "data": "2026-01-19",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 88,
  "fora_do_horario": 1  // ✅ Agora envia 0/1
}
```

**Resposta Esperada:**
```json
{
  "status": "ok"
}
```

---

## 🔍 Outras Possíveis Causas de "Dados inválidos"

Se ainda houver erro após a correção, verifique:

1. **Data no passado:**
   - A API não permite datas passadas (exceto hoje)
   - Verifique se a data não está no passado

2. **Dependente inválido:**
   - O dependente deve pertencer ao usuário logado
   - Verifique se o ID do dependente está correto

3. **Reserva já existe:**
   - Só é permitida uma reserva adicional por dependente por dia
   - Verifique se já existe reserva para esse dependente nessa data

4. **Dependente inativo:**
   - O dependente deve estar ativo (`ativo = 1`)
   - Verifique no banco de dados

---

## 📝 Arquivos Modificados

1. ✅ `lib/core/api/reservas_service.dart`
   - Conversão de boolean para integer em `criarReservaPropria()`
   - Conversão de boolean para integer em `criarReservaAdicional()`
   - Logs melhorados

2. ✅ `lib/features/reservas/criar_reserva_adicional_screen.dart`
   - Logs de debug adicionados

---

**Última Atualização:** Janeiro 2025  
**Status:** ✅ Corrigido
