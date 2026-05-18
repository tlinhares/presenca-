# 🔧 Correção: API historico_usuario.php - Campos dias_culto e tem_culto

## 📋 Problema Identificado

O calendário do Flutter precisa saber quais dias têm culto programado para:
- **Verde**: Dia com culto e presença confirmada
- **Vermelho**: Dia com culto mas sem presença (falta)
- **Cinza**: Dia sem culto programado (não conta como falta)

Sem essa informação, o calendário marcava incorretamente sábados/domingos como falta quando não havia culto.

---

## ✅ Correção Implementada

### Arquivo Modificado
`api/culto/historico_usuario.php`

### Mudanças Realizadas

1. **Buscar configuração de dias da semana:**
   - Busca configuração `dias_semana` da tabela `configuracoes_culto`
   - Padrão: segunda a sexta (1,2,3,4,5)

2. **Gerar lista de dias de culto programados:**
   - Gera lista de todos os dias do período que são dias de culto configurados
   - Baseado nos dias da semana configurados

3. **Campo `dias_culto` na resposta:**
   - Lista completa de dias com culto programado no período
   - Exemplo: `["2026-01-05", "2026-01-12", "2026-01-19", "2026-01-26"]`

4. **Campo `tem_culto` em cada presença:**
   - Indica se há culto programado OU realmente aconteceu naquele dia
   - `true`: Há culto (programado ou confirmado)
   - `false`: Não há culto

5. **Campo `tem_culto_programado` em cada presença:**
   - Indica se o dia é um dia de culto programado (baseado na configuração)
   - `true`: É dia de culto programado
   - `false`: Não é dia de culto programado

---

## 📊 Formato de Resposta Atualizado

### Resposta Completa
```json
{
    "status": "ok",
    "presencas": [
        {
            "data": "2026-01-20",
            "horario_confirmacao": "19:00:00",
            "status": "presente",
            "tipo_confirmacao": "facial",
            "tem_culto": true,
            "tem_culto_programado": true,
            "justificativa": null
        },
        {
            "data": "2026-01-19",
            "horario_confirmacao": null,
            "status": "falta",
            "tipo_confirmacao": null,
            "tem_culto": true,
            "tem_culto_programado": true,
            "justificativa": {
                "id": 123,
                "motivo": "Motivo",
                "status": "pendente"
            }
        },
        {
            "data": "2026-01-18",
            "horario_confirmacao": null,
            "status": "sem-presenca",
            "tipo_confirmacao": null,
            "tem_culto": false,
            "tem_culto_programado": false,
            "justificativa": null
        }
    ],
    "estatisticas": {
        "total_presentes": 15,
        "total_atrasados": 2,
        "total_faltas": 6,
        "total_justificativas": 1
    },
    "periodo": {
        "inicio": "2026-01-01",
        "fim": "2026-01-31"
    },
    "dias_culto": [
        "2026-01-05",
        "2026-01-12",
        "2026-01-19",
        "2026-01-20",
        "2026-01-26"
    ]
}
```

---

## 🔍 Lógica de Determinação de Culto

### Campo `tem_culto`
- `true` se:
  - É dia de culto programado (baseado em `dias_semana` configurado) **OU**
  - Realmente houve culto (pelo menos uma presença confirmada nesta data)
- `false` se:
  - Não é dia de culto programado **E** não houve presenças confirmadas

### Campo `tem_culto_programado`
- `true` se: É um dos dias da semana configurados em `dias_semana`
- `false` se: Não é um dia de culto programado

### Campo `dias_culto`
- Lista completa de todos os dias do período que são dias de culto programados
- Baseado na configuração `dias_semana`
- Útil para o calendário saber quais dias devem ser considerados

---

## 🎨 Como Usar no Calendário Flutter

### Opção 1: Usar `dias_culto` (Recomendado)
```dart
final diasCulto = data['dias_culto'] as List<String>;

// Para cada dia do calendário
if (diasCulto.contains(dataFormatada)) {
    // É dia de culto programado
    if (temPresenca) {
        // Verde: presença confirmada
    } else {
        // Vermelho: falta
    }
} else {
    // Cinza: não é dia de culto
}
```

### Opção 2: Usar `tem_culto` em cada presença
```dart
for (var presenca in presencas) {
    if (presenca['tem_culto'] == true) {
        if (presenca['status'] == 'presente' || presenca['status'] == 'atrasado') {
            // Verde: presença confirmada
        } else {
            // Vermelho: falta
        }
    } else {
        // Cinza: não é dia de culto
    }
}
```

---

## 🧪 Teste

```bash
# Obter token
TOKEN=$(curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","senha":"suasenha"}' | jq -r '.data.token')

# Testar histórico
curl -X GET "https://presenca.aom.org.br/api/culto/historico_usuario.php?mes=2026-01" \
  -H "Authorization: Bearer $TOKEN"
```

**Verificar na Resposta:**
- ✅ Campo `dias_culto` presente na raiz
- ✅ Campo `tem_culto` em cada item de `presencas`
- ✅ Campo `tem_culto_programado` em cada item de `presencas`
- ✅ Dias sem culto têm `tem_culto: false`

---

## ✅ Status

- ✅ Campo `dias_culto` adicionado na raiz da resposta
- ✅ Campo `tem_culto` adicionado em cada presença
- ✅ Campo `tem_culto_programado` adicionado em cada presença
- ✅ Lógica baseada em configuração de dias da semana
- ✅ Compatível com dias que realmente tiveram culto

---

**Data:** 2026-01-XX  
**Status:** ✅ Corrigido
