# 📋 Resumo Rápido: API Verificação de Horário - Reserva de Almoço

## 🎯 Objetivo
Documentação rápida para outra sessão do Cursor saber como verificar se está no horário permitido para exibir texto correto no botão "Reservar meu almoço".

---

## 🔑 APIs Principais

### 1. `status_reserva.php` - Para Atualizar Botão
```
GET /api/almoco/status_reserva.php
```

**Retorna:**
```json
{
    "reservou_hoje": false,
    "hora_excedida": false,
    "hora_atual": "08:30",
    "hora_limite": "09:00"
}
```

**Uso:** Chamar periodicamente para atualizar texto do botão.

**Lógica do Botão:**
- Se `reservou_hoje == true` → "Cancelar Reserva" (vermelho)
- Se `hora_excedida == true` → "Reservar meu almoço (Fora do horário)" (verde)
- Caso contrário → "Reservar meu almoço" (verde)

---

### 2. `verificar_horario.php` - Antes de Reservar
```
GET /api/almoco/verificar_horario.php?data=2026-01-15&tipo=presencial
```

**Retorna:**
```json
{
    "status": "sucesso",
    "fora_do_horario": false,
    "hora_atual": "08:30",
    "horario_limite": "09:00",
    "valor_normal": 15.00,
    "valor_fora_horario": 30.00
}
```

**Uso:** Chamar antes de criar reserva para verificar horário e valores.

---

## 📊 Comparação Rápida

| API | Quando Usar | Retorna Valores | Suporta Mobile |
|-----|-------------|-----------------|----------------|
| `status_reserva.php` | Atualizar botão | ❌ Não | ❌ Não |
| `verificar_horario.php` | Antes de reservar | ✅ Sim | ✅ Sim |

---

## 💡 Exemplo de Uso Flutter

```dart
// 1. Verificar status para atualizar botão
Future<void> atualizarBotao() async {
  final response = await http.get(
    Uri.parse('$baseUrl/api/almoco/status_reserva.php'),
  );
  
  final status = json.decode(response.body);
  
  if (status['reservou_hoje'] == true) {
    // Botão: "Cancelar Reserva" (vermelho)
  } else if (status['hora_excedida'] == true) {
    // Botão: "Reservar meu almoço (Fora do horário)" (verde)
  } else {
    // Botão: "Reservar meu almoço" (verde)
  }
}

// 2. Verificar antes de reservar
Future<void> verificarAntesReservar() async {
  final response = await http.get(
    Uri.parse('$baseUrl/api/almoco/verificar_horario.php?data=2026-01-15'),
  );
  
  final resultado = json.decode(response.body);
  
  if (resultado['fora_do_horario'] == true) {
    // Mostrar diálogo com valor diferente
  } else {
    // Reserva normal
  }
}
```

---

## ⚙️ Configurações

- **Horário Limite:** `configuracoes.hora_limite` (padrão: `'09:00'`)
- **Valor Fora Horário:** `configuracoes.valor_fora_horario` (padrão: `'30.00'`)

---

## 📚 Documentação Completa

Para detalhes completos, consulte: **`docs/API_VERIFICACAO_HORARIO_RESERVA_ALMOCO.md`**

---

**Data:** 2026-01-XX  
**Versão:** 1.0
