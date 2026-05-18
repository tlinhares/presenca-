# 🔍 Diagnóstico: Erro "Dados inválidos" - Reserva Adicional

## ✅ Status Atual

O código está **correto** - está enviando:
```json
{
  "data": "2026-01-19",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 239,
  "fora_do_horario": 1  // ✅ Correto (integer, não boolean)
}
```

O erro "Dados inválidos" vem de **validações no backend PHP**.

---

## 🔍 Possíveis Causas (Ordem de Probabilidade)

### 1. ⚠️ Data no Passado (MAIS PROVÁVEL)

**Problema:** A data `2026-01-19` pode estar no passado se hoje for depois dessa data.

**Validação no Backend:**
- Não permite datas passadas (exceto hoje)
- Limite de 30 dias no futuro

**Como Verificar:**
```sql
-- Verificar data atual do servidor
SELECT CURDATE() as hoje, '2026-01-19' as data_reserva,
       DATEDIFF('2026-01-19', CURDATE()) as dias_diferenca;
```

**Solução:**
- Use data de hoje ou futura
- Verifique se a data não está mais de 30 dias no futuro

---

### 2. ⚠️ Dependente Não Pertence ao Usuário

**Problema:** O dependente ID 239 pode não pertencer ao usuário logado.

**Validação no Backend:**
```php
// Backend verifica:
WHERE dependentes.id = ? AND dependentes.id_usuario = $_SESSION['usuario_id']
```

**Como Verificar:**
```sql
-- Verificar dependente e usuário
SELECT d.id, d.nome, d.id_usuario, d.ativo,
       u.id as usuario_id, u.nome as usuario_nome
FROM dependentes d
LEFT JOIN usuarios u ON d.id_usuario = u.id
WHERE d.id = 239;

-- Verificar qual usuário está logado (do token JWT)
-- Decodifique o token e veja o campo 'user_id'
```

**Solução:**
- Certifique-se que o dependente pertence ao usuário logado
- Verifique se o token está correto

---

### 3. ⚠️ Dependente Inativo

**Problema:** O dependente pode estar inativo (`ativo = 0`).

**Validação no Backend:**
```php
// Backend verifica:
WHERE dependentes.ativo = 1
```

**Como Verificar:**
```sql
SELECT id, nome, ativo
FROM dependentes
WHERE id = 239;
```

**Solução:**
- Ative o dependente: `UPDATE dependentes SET ativo = 1 WHERE id = 239;`

---

### 4. ⚠️ Reserva Já Existe

**Problema:** Já existe uma reserva adicional para esse dependente nessa data.

**Validação no Backend:**
```php
// Backend verifica:
SELECT COUNT(*) FROM reservas_adicionais 
WHERE id_dependente = ? AND data = ?
```

**Como Verificar:**
```sql
SELECT *
FROM reservas_adicionais
WHERE id_dependente = 239
  AND data = '2026-01-19';
```

**Solução:**
- Use outra data
- Ou exclua a reserva existente primeiro

---

### 5. ⚠️ Validação de Quantidade

**Problema:** A quantidade pode precisar ser maior que 0 ou ter um limite máximo.

**Como Verificar:**
- Tente com `quantidade: 1` (já está correto)
- Verifique se há limite máximo no backend

---

### 6. ⚠️ Validação de Tipo

**Problema:** O tipo pode precisar ser exatamente `'presencial'` ou `'marmitex'` (case-sensitive).

**Como Verificar:**
- Já está enviando `'presencial'` (correto)
- Verifique se não há espaços extras ou caracteres especiais

---

## 🔧 Como Diagnosticar

### Passo 1: Verificar Horário Primeiro

Antes de criar, sempre verifique o horário:

```http
GET /api/almoco/verificar_horario_adicional.php?id_dependente=239&data=2026-01-19&tipo=presencial
```

**Se esta API retornar erro, o problema está aqui:**
- Dependente inválido
- Data inválida
- Reserva já existe

**Se esta API retornar sucesso, o problema está na criação:**
- Validação adicional no backend
- Campo faltando ou incorreto

### Passo 2: Verificar no Banco de Dados

Execute estas queries:

```sql
-- 1. Verificar dependente
SELECT d.*, u.nome as usuario_nome
FROM dependentes d
JOIN usuarios u ON d.id_usuario = u.id
WHERE d.id = 239;

-- 2. Verificar se já existe reserva
SELECT *
FROM reservas_adicionais
WHERE id_dependente = 239
  AND data = '2026-01-19';

-- 3. Verificar data atual
SELECT CURDATE() as hoje, 
       '2026-01-19' as data_reserva,
       DATEDIFF('2026-01-19', CURDATE()) as dias_diferenca;
```

### Passo 3: Testar com Data de Hoje

Tente criar reserva para hoje ao invés de data fixa:

```dart
final hoje = DateTime.now();
// Usar hoje ao invés de data específica
```

---

## 📋 Checklist de Verificação

- [ ] Data não está no passado (exceto hoje)
- [ ] Data não está mais de 30 dias no futuro
- [ ] Dependente existe no banco de dados
- [ ] Dependente pertence ao usuário logado
- [ ] Dependente está ativo (`ativo = 1`)
- [ ] Não existe reserva para esse dependente nessa data
- [ ] Token JWT está válido e não expirado
- [ ] API de verificar horário retorna sucesso antes de criar

---

## 🚨 Próximos Passos

1. **Verifique primeiro com a API de verificar horário:**
   - Se retornar erro, o problema está na validação básica
   - Se retornar sucesso, o problema está na criação

2. **Execute as queries SQL acima** para verificar cada ponto

3. **Teste com data de hoje** para eliminar problema de data

4. **Verifique os logs do servidor PHP** para ver qual validação específica está falhando

---

## 💡 Sugestão de Melhoria no Código

Adicionar verificação antes de criar:

```dart
// Sempre verificar horário antes de criar
final verificarResponse = await _reservasService.verificarHorarioAdicional(
  idDependente: _dependenteSelecionado!.id,
  data: hoje,
  tipo: _tipo,
);

if (!verificarResponse.success) {
  // Mostrar erro da verificação
  setState(() {
    _errorMessage = verificarResponse.message;
  });
  return;
}

// Só criar se verificação passou
final criarResponse = await _reservasService.criarReservaAdicional(...);
```

---

**Última Atualização:** Janeiro 2025
