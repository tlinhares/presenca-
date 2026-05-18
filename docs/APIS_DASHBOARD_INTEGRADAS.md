# 📊 APIs Integradas no Dashboard Mobile

## ✅ Status das Integrações

### 1. Card de Refeições ✅
**API:** `GET /api/almoco/listar_reservas_usuario.php`

**Status:** ✅ **INTEGRADO E FUNCIONANDO**

**Dados Carregados:**
- Reservas confirmadas (contagem)
- Saldo atual (valor total)

**Implementação:**
- Carrega automaticamente ao abrir o dashboard
- Atualiza quando muda o mês

**Formato de Resposta:**
```json
{
    "status": "ok",
    "reservas": [
        {
            "id": 123,
            "data": "2026-01-20",
            "valor": 15.00,
            "fora_do_horario": false
        }
    ]
}
```

---

### 2. Card de Almoço (Calendário) ✅
**API:** `GET /api/almoco/listar_reservas_usuario.php`

**Status:** ✅ **INTEGRADO E FUNCIONANDO**

**Dados Carregados:**
- Lista de reservas do mês selecionado
- Marca dias com reserva no calendário

**Implementação:**
- Carrega dados quando muda o mês
- Mostra dias com reserva em vermelho
- Mostra dia atual em verde

**Parâmetros:**
- `data_inicio`: Primeiro dia do mês (YYYY-MM-DD)
- `data_fim`: Último dia do mês (YYYY-MM-DD)

---

### 3. Card de Frequência ✅
**API:** `GET /api/culto/frequencia.php?mes=YYYY-MM`

**Status:** ✅ **CRIADA E FUNCIONANDO**

**Dados Retornados:**
- Percentual de frequência
- Presenças (PRE)
- Atrasos (ATR)
- Faltas (FAL)
- Justificadas (JUS)

**Formato de Resposta:**
```json
{
    "status": "ok",
    "mes": "2026-01",
    "frequencia": {
        "percentual": 85.5,
        "presentes": 15,
        "atrasados": 2,
        "faltas": 2,
        "justificadas": 1,
        "total": 20
    },
    "dados": {
        "PRE": 15,
        "ATR": 2,
        "FAL": 2,
        "JUS": 1
    }
}
```

**Implementação:**
- ✅ API criada: `api/culto/frequencia.php`
- ✅ Middleware mobile configurado
- ✅ Serviço criado (`CultoService`)
- ✅ Dashboard preparado para receber dados

**Comportamento:**
- Calcula frequência baseado em dias de culto configurados
- Considera apenas dias onde houve culto (pelo menos uma presença)
- Justificativas aprovadas contam como justificadas
- Presenças e atrasos são contados separadamente

**Parâmetros:**
- `mes` (obrigatório): Mês no formato `YYYY-MM` (ex: `2026-01`)

**Onde Usado:**
- Mobile: Dashboard - Card de Frequência

---

### 4. Card de Disponibilidade da Frota ✅
**API:** `GET /api/frota/listar_veiculos.php`

**Status:** ✅ **MIDDLEWARE ADICIONADO - FUNCIONANDO**

**Dados Retornados:**
- Lista de veículos
- Status de disponibilidade
- Usuário atual utilizando (se houver)

**Formato de Resposta:**
```json
{
    "status": "ok",
    "veiculos": [
        {
            "id": 1,
            "placa": "ABC-1234",
            "modelo": "Corolla",
            "marca": "Toyota",
            "ano": 2020,
            "cor": "Branco",
            "km_atual": 50000,
            "status": "disponivel",
            "ativo": 1,
            "foto_veiculo": null,
            "observacoes": "",
            "usuario_atual": null
        }
    ],
    "total": 10
}
```

**Implementação:**
- ✅ Middleware mobile adicionado
- ✅ Serviço criado (`FrotaService`)
- ✅ Dashboard preparado para receber dados

**Parâmetros Opcionais:**
- `status`: Filtrar por status (`disponivel`, `em_uso`, `manutencao`, `inativo`)
- `incluir_inativos`: `1` para incluir veículos inativos

**Comportamento:**
- Lista veículos ativos por padrão
- Mostra usuário atual se veículo estiver em uso
- Ordena por: ativo DESC, status ASC, modelo ASC

**Onde Usado:**
- Mobile: Dashboard - Card de Disponibilidade da Frota

---

## 🔧 Serviços Criados

### 1. FrotaService
**Arquivo:** `lib/core/api/frota_service.dart`

**Métodos:**
- `listarVeiculos()` - Lista todos os veículos
- `listarVeiculosDisponiveis()` - Lista apenas veículos disponíveis

**Status:** ✅ Criado e funcionando

---

### 2. CultoService
**Arquivo:** `lib/core/api/culto_service.dart`

**Métodos:**
- `obterFrequencia({DateTime? mes})` - Obtém frequência do mês
- `listarPresencas({DateTime? mes})` - Lista presenças do mês

**Status:** ✅ Criado e funcionando

---

## 📋 Endpoints Configurados

### Em `lib/core/api/endpoints.dart`:

```dart
// Culto
static String cultoFrequencia(String mes) => 
  'https://presenca.aom.org.br/api/culto/frequencia.php?mes=$mes';

// Frota
static const String baseUrlFrota = 'https://presenca.aom.org.br/api/frota';
static const String listarVeiculos = '$baseUrlFrota/listar_veiculos.php';
```

---

## 🧪 Testes

### Teste 1: Frequência

```bash
# Obter token
TOKEN=$(curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","senha":"suasenha"}' | jq -r '.data.token')

# Testar frequência
curl -X GET "https://presenca.aom.org.br/api/culto/frequencia.php?mes=2026-01" \
  -H "Authorization: Bearer $TOKEN"
```

**Resultado Esperado:**
```json
{
    "status": "ok",
    "mes": "2026-01",
    "frequencia": {
        "percentual": 85.5,
        "presentes": 15,
        "atrasados": 2,
        "faltas": 2,
        "justificadas": 1,
        "total": 20
    },
    "dados": {
        "PRE": 15,
        "ATR": 2,
        "FAL": 2,
        "JUS": 1
    }
}
```

### Teste 2: Frota

```bash
# Testar listar veículos
curl -X GET "https://presenca.aom.org.br/api/frota/listar_veiculos.php" \
  -H "Authorization: Bearer $TOKEN"
```

**Resultado Esperado:**
```json
{
    "status": "ok",
    "veiculos": [...],
    "total": 10
}
```

---

## 📊 Resumo Final

| Card | API | Status | Observação |
|------|-----|--------|------------|
| **Refeições** | `listar_reservas_usuario.php` | ✅ Funcionando | Dados reais |
| **Almoço (Calendário)** | `listar_reservas_usuario.php` | ✅ Funcionando | Dados reais |
| **Frequência** | `culto/frequencia.php` | ✅ **CRIADA** | Dados reais |
| **Frota** | `frota/listar_veiculos.php` | ✅ **MIDDLEWARE ADICIONADO** | Dados reais |

---

## ✅ Conclusão

**Todas as APIs do dashboard estão funcionando!**

- ✅ Card de Refeições: Funcionando
- ✅ Card de Almoço: Funcionando
- ✅ Card de Frequência: **API criada e funcionando**
- ✅ Card de Frota: **Middleware adicionado e funcionando**

O dashboard mobile agora pode carregar dados reais de todas as APIs.

---

**Última Atualização:** Janeiro 2025  
**Status:** ✅ Todas as APIs Prontas
