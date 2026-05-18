# 🔧 Correção: Valor Normal em Reserva Adicional

## 📋 Problema Identificado

Ao fazer reserva adicional fora do horário, a API não retornava o valor normal (baseado no grupo de valor), apenas o valor fora do horário.

**Logs do Problema:**
```
! AVISO: Valor normal não encontrado na resposta da API
🔵 Valor normal: 0.0
🔵 Valor fora do horário: 30.0
```

---

## ✅ Correção Implementada

### Arquivo Modificado
`api/almoco/verificar_horario_adicional.php`

### Mudanças Realizadas

1. **Cálculo separado de valores:**
   - `valor_normal_refeicao`: Valor normal baseado no grupo de valor
   - `valor_normal_marmitex`: Valor normal de marmitex
   - `valor_fora_horario`: Valor quando está fora do horário (30.00)
   - `valor_refeicao`: Valor que será cobrado (normal ou fora do horário)
   - `valor_marmitex`: Valor que será cobrado (normal ou fora do horário)

2. **Resposta atualizada:**
```json
{
    "status": "ok",
    "mensagem": "Reserva pode ser feita",
    "dependente": {
        "id": 20,
        "nome": "Angélica Renata Custódio Sella Linhares",
        "cobrar": 0
    },
    "valores": {
        "valor_refeicao": 30.0,
        "valor_marmitex": 0.0,
        "valor_normal_refeicao": 10.0,
        "valor_normal_marmitex": 0.0,
        "valor_fora_horario": 30.0,
        "fora_do_horario": true
    },
    "horario": {
        "hora_atual": "12:14:43",
        "horario_limite": "09:01",
        "fora_do_horario": true
    }
}
```

---

## 📊 Formato de Resposta Completo

### Quando está FORA do horário:
```json
{
    "valores": {
        "valor_refeicao": 30.0,           // Valor que será cobrado (fora do horário)
        "valor_marmitex": 0.0,
        "valor_normal_refeicao": 10.0,    // Valor normal (dentro do horário)
        "valor_normal_marmitex": 0.0,
        "valor_fora_horario": 30.0,       // Valor padrão fora do horário
        "fora_do_horario": true
    }
}
```

### Quando está DENTRO do horário:
```json
{
    "valores": {
        "valor_refeicao": 10.0,           // Valor que será cobrado (normal)
        "valor_marmitex": 0.0,
        "valor_normal_refeicao": 10.0,    // Valor normal
        "valor_normal_marmitex": 0.0,
        "valor_fora_horario": 30.0,       // Valor padrão fora do horário
        "fora_do_horario": false
    }
}
```

---

## 🔍 Onde Usar Cada Valor

### No App Flutter:

**Quando `fora_do_horario == true`:**
- Mostrar `valor_normal_refeicao` como "Valor Normal"
- Mostrar `valor_refeicao` como "Valor Fora do Horário"
- Exibir ambos para comparação

**Quando `fora_do_horario == false`:**
- Mostrar apenas `valor_refeicao` (que é igual ao valor normal)

---

## 🧪 Teste

```bash
# Obter token
TOKEN=$(curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","senha":"suasenha"}' | jq -r '.data.token')

# Testar reserva adicional (fora do horário)
curl -X GET "https://presenca.aom.org.br/api/almoco/verificar_horario_adicional.php?id_dependente=20&data=2026-01-20&tipo=presencial" \
  -H "Authorization: Bearer $TOKEN"
```

**Resultado Esperado:**
- ✅ `valor_normal_refeicao` presente na resposta
- ✅ `valor_fora_horario` presente na resposta
- ✅ `valor_refeicao` = valor que será cobrado

---

## 📝 Lógica de Cálculo

1. **Se dependente tem `cobrar == 0` (maior de 12 anos):**
   - Busca valor do grupo de valor do usuário titular
   - `valor_normal_refeicao` = valor do grupo
   - `valor_refeicao` = valor normal OU valor fora do horário (depende de `fora_do_horario`)

2. **Se dependente tem `cobrar == 1` (menor de 12 anos):**
   - Todos os valores = 0.00 (não cobra)

---

## ✅ Status

- ✅ Valor normal calculado separadamente
- ✅ Valor fora do horário calculado separadamente
- ✅ Ambos retornados na resposta
- ✅ App pode mostrar comparação de valores

---

**Data:** 2026-01-XX  
**Status:** ✅ Corrigido
