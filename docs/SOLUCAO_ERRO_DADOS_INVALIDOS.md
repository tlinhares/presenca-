# 🔧 Solução: Erro "Dados inválidos" - Reserva Adicional

## ❌ Problema

A API retorna erro mesmo após correção do formato boolean:
```json
{
  "status": "erro",
  "mensagem": "Dados inválidos."
}
```

## 🔍 Possíveis Causas

### 1. Código não foi recompilado
O log ainda mostra `"fora_do_horario":true` ao invés de `"fora_do_horario":1`.

**Solução:** Execute `flutter clean` e depois `flutter run` novamente.

### 2. Data no passado
A API não permite datas passadas (exceto hoje).

**Verificação:** A data `2026-01-19` pode estar no passado se hoje for depois disso.

### 3. Dependente inválido
O dependente pode:
- Não pertencer ao usuário logado
- Estar inativo
- Não existir

### 4. Reserva já existe
Só é permitida uma reserva adicional por dependente por dia.

### 5. Validação no backend
O backend pode estar validando outros campos que não estão sendo enviados corretamente.

---

## ✅ Soluções Aplicadas

### 1. Conversão Boolean → Integer
```dart
'fora_do_horario': foraDoHorario ? 1 : 0
```

### 2. Logs Melhorados
Adicionados logs para debug antes e depois da conversão.

### 3. Validações na Tela
Validações de data e dependente antes de enviar.

---

## 🔧 Próximos Passos para Debug

### 1. Recompilar o App
```bash
flutter clean
flutter pub get
flutter run -d chrome
```

### 2. Verificar Logs
Procure por estes logs no console:
```
🌐 CRIANDO RESERVA ADICIONAL NA API REAL:
🌐 URL: https://presenca.aom.org.br/api/almoco/reservar_adicional.php
🌐 Body: {data: 2026-01-19, quantidade: 1, tipo: presencial, dependente: 239, fora_do_horario: 1}
```

**Se ainda mostrar `true` ao invés de `1`, o código não foi recompilado.**

### 3. Verificar no Banco de Dados

Execute estas queries para verificar:

```sql
-- Verificar se dependente existe e pertence ao usuário
SELECT d.id, d.nome, d.ativo, d.id_usuario, u.nome as usuario_nome
FROM dependentes d
JOIN usuarios u ON d.id_usuario = u.id
WHERE d.id = 239;

-- Verificar se já existe reserva para esse dependente nessa data
SELECT *
FROM reservas_adicionais
WHERE id_dependente = 239
  AND data = '2026-01-19';

-- Verificar usuário logado (do token)
-- Decodifique o token JWT e veja o user_id
```

### 4. Testar com Data de Hoje

Tente criar reserva para hoje (data atual) ao invés de uma data específica:

```dart
final hoje = DateTime.now();
// Usar hoje ao invés de data fixa
```

### 5. Verificar Formato da Data

Certifique-se que a data está no formato correto:
- ✅ Correto: `"2026-01-19"` (YYYY-MM-DD)
- ❌ Errado: `"19/01/2026"` ou `"2026-1-19"`

---

## 📋 Checklist de Debug

- [ ] Executou `flutter clean` e recompilou
- [ ] Log mostra `fora_do_horario: 1` (não `true`)
- [ ] Data não está no passado
- [ ] Dependente existe e pertence ao usuário
- [ ] Dependente está ativo (`ativo = 1`)
- [ ] Não existe reserva para esse dependente nessa data
- [ ] Formato da data está correto (YYYY-MM-DD)
- [ ] Token está válido e não expirado

---

## 🚨 Se Ainda Não Funcionar

### Opção 1: Verificar Backend
O erro "Dados inválidos" pode vir de validações no backend PHP. Verifique:
- Se o campo `detalhe` é obrigatório (mesmo sendo opcional na doc)
- Se há outras validações não documentadas
- Logs do servidor PHP para ver qual validação está falhando

### Opção 2: Testar com Postman/Insomnia
Teste diretamente a API com:
```json
{
  "data": "2026-01-19",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 239,
  "fora_do_horario": 1
}
```

Se funcionar no Postman mas não no app, o problema está no código Flutter.

### Opção 3: Adicionar Campo `detalhe`
Mesmo sendo opcional, tente enviar:
```dart
body['detalhe'] = 'Reserva Adicional';
```

---

**Última Atualização:** Janeiro 2025
