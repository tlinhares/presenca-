# ✅ Reservas Adicionais e Horário Limite - Status

## 📋 Resumo

✅ **API de Reservas Adicionais:** Já implementada e funcionando  
✅ **Horário Limite:** Funciona para reserva própria E reserva adicional  
✅ **Valor Fora do Horário:** Aplicado em ambas as reservas

---

## 🔍 Verificação da API

### 1. Reservas Adicionais - APIs Disponíveis

#### ✅ Verificar Horário Adicional
```dart
GET /api/almoco/verificar_horario_adicional.php?id_dependente=251&data=2026-01-20&tipo=presencial
```

**Resposta:**
```json
{
  "status": "ok",
  "valores": {
    "valor_refeicao": 10.00,
    "valor_marmitex": 0.00,
    "fora_do_horario": false  // ← Indica se está fora do horário
  },
  "horario": {
    "hora_atual": "08:30",
    "horario_limite": "09:01",
    "fora_do_horario": false  // ← Confirmação do horário
  }
}
```

#### ✅ Criar Reserva Adicional
```dart
POST /api/almoco/reservar_adicional.php
Body: {
  "data": "2026-01-20",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 251,
  "fora_do_horario": false  // ← Parâmetro para indicar fora do horário
}
```

#### ✅ Listar Reservas Adicionais
```dart
GET /api/almoco/listar_reservas_adicionais_usuario.php?data_inicio=2026-01-01&data_fim=2026-01-31
```

#### ✅ Excluir Reserva Adicional
```dart
POST /api/almoco/excluir_reserva_adicional.php
Body: {"id": 2371}
```

---

## ⏰ Horário Limite - Como Funciona

### Regra Geral (Aplica-se a AMBAS as reservas)

**Configuração:** `hora_limite = "09:01"` (na tabela `configuracoes`)

**Regras:**
- **Para HOJE:** Se `hora_atual > hora_limite` → `fora_do_horario = true`
- **Para DATAS FUTURAS:** Sempre dentro do horário (`fora_do_horario = false`)
- **Para DATAS PASSADAS:** Não permite reservar

### Valores Quando Fora do Horário

#### Reserva Própria
- **Dentro do horário:** `valor_refeicao = valor do grupo do usuário`
- **Fora do horário:** `valor_refeicao = valor_fora_horario` (30.00)

#### Reserva Adicional
- **Dependente > 12 anos (`cobrar = 0`):**
  - **Dentro do horário:** 
    - `tipo = 'presencial'`: `valor_refeicao = valor do grupo do usuário`
    - `tipo = 'marmitex'`: `valor_marmitex = valor_marmitex padrão`
  - **Fora do horário:**
    - `tipo = 'presencial'`: `valor_refeicao = valor_fora_horario` (30.00)
    - `tipo = 'marmitex'`: `valor_marmitex = valor_fora_horario` (30.00)

- **Dependente ≤ 12 anos (`cobrar = 1`):**
  - Valores sempre zerados (0.00), independente do horário

---

## 📱 Status no App Flutter

### ✅ Implementado no Código

**Serviço:** `lib/core/api/reservas_service.dart`

1. ✅ `verificarHorarioAdicional()` - Linha 171
   - Verifica horário e retorna valores calculados
   - Inclui informação de `fora_do_horario`

2. ✅ `criarReservaAdicional()` - Linha 213
   - Cria reserva adicional
   - Parâmetro `foraDoHorario` implementado (linha 219)

3. ✅ `listarReservasAdicionais()` - Linha 250
   - Lista todas as reservas adicionais

4. ✅ `excluirReservaAdicional()` - Linha 288
   - Exclui reserva adicional

### ❌ Pendente: Tela no App

**Não existe tela para criar reservas adicionais!**

Atualmente só existe:
- ✅ `CriarReservaScreen` - Para reserva própria
- ❌ **Falta:** Tela para criar reserva adicional (dependentes)

---

## 🎯 Próximos Passos

### Opção 1: Criar Tela Separada
Criar `criar_reserva_adicional_screen.dart` com:
- Lista de dependentes
- Seleção de dependente
- Seleção de tipo (presencial/marmitex)
- Verificação de horário (com valores)
- Criação da reserva

### Opção 2: Adicionar na Tela Existente
Adicionar opção na `CriarReservaScreen` para escolher:
- Reserva própria OU
- Reserva adicional (dependente)

---

## 📊 Comparação: Reserva Própria vs Adicional

| Aspecto | Reserva Própria | Reserva Adicional |
|---------|----------------|-------------------|
| **Endpoint Verificar** | `/verificar_horario.php` | `/verificar_horario_adicional.php` |
| **Endpoint Criar** | `/reservar.php` | `/reservar_adicional.php` |
| **Parâmetro `fora_do_horario`** | ✅ Sim | ✅ Sim |
| **Valor dentro do horário** | Grupo do usuário | Grupo do usuário |
| **Valor fora do horário** | 30.00 | 30.00 |
| **Validação de horário** | ✅ Sim | ✅ Sim |
| **Tela no App** | ✅ Existe | ❌ Não existe |

---

## ✅ Confirmação

**SIM, a API consegue consultar reservas adicionais!**

**SIM, o horário limite funciona para ambas:**
- ✅ Reserva própria: valor muda após horário limite
- ✅ Reserva adicional: valor muda após horário limite

**O que falta:**
- ❌ Tela no app para criar reservas adicionais

---

**Última Atualização:** Janeiro 2025
