# 🔍 Verificação Final: Erro "Dados inválidos"

## ✅ Status Atual

Vejo na tela que:
- ✅ Data: 19/01/2026 (Hoje) - Correto
- ✅ Dependente: Pedro Álvares (26 anos) - Selecionado
- ✅ Tipo: Presencial - Correto
- ✅ Fora do Horário: Sim (11:21:27 > 09:01) - Correto
- ✅ Valor: R$ 30,00 - Correto
- ❌ Erro: "Dados inválidos" ao criar

## 🔍 Análise

O código está **correto** e enviando:
```json
{
  "data": "2026-01-19",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 239,
  "fora_do_horario": 1,
  "detalhe": "Reserva Adicional"
}
```

O erro "Dados inválidos" vem do **backend PHP** e pode ser causado por:

### 1. ⚠️ Reserva Já Existe (MAIS PROVÁVEL)

A API de verificar horário pode ter retornado sucesso, mas quando vai criar, já existe uma reserva.

**Como Verificar:**
```sql
SELECT *
FROM reservas_adicionais
WHERE id_dependente = 239
  AND data = '2026-01-19';
```

**Solução:** Excluir reserva existente ou usar outra data.

### 2. ⚠️ Dependente Não Pertence ao Usuário

Mesmo que a verificação tenha passado, pode haver inconsistência.

**Como Verificar:**
```sql
-- Verificar qual usuário está logado (do token JWT)
-- Decodifique o token e veja o campo 'user_id'

-- Depois verifique:
SELECT d.id, d.nome, d.id_usuario, u.nome as usuario_nome
FROM dependentes d
JOIN usuarios u ON d.id_usuario = u.id
WHERE d.id = 239;
```

### 3. ⚠️ Validação de Data no Backend

O backend pode estar validando a data de forma diferente.

**Possível problema:** O backend pode estar comparando com `CURDATE()` do MySQL, que pode ter fuso horário diferente.

### 4. ⚠️ Campo `detalhe` Pode Ser Obrigatório

Mesmo sendo opcional na documentação, o backend pode estar exigindo.

**Já corrigido:** Agora sempre enviamos `detalhe: "Reserva Adicional"`.

---

## 🔧 Próximos Passos

### 1. Verificar no Banco de Dados

Execute estas queries:

```sql
-- 1. Verificar se já existe reserva
SELECT *
FROM reservas_adicionais
WHERE id_dependente = 239
  AND data = '2026-01-19';

-- 2. Verificar dependente
SELECT d.*, u.nome as usuario_nome, u.id as usuario_id
FROM dependentes d
JOIN usuarios u ON d.id_usuario = u.id
WHERE d.id = 239;

-- 3. Verificar data atual do servidor
SELECT CURDATE() as hoje_servidor,
       NOW() as agora_servidor,
       '2026-01-19' as data_reserva;
```

### 2. Verificar Logs do Backend

Verifique os logs do servidor PHP para ver qual validação específica está falhando.

### 3. Testar com Postman/Insomnia

Teste diretamente a API com:
```json
POST /api/almoco/reservar_adicional.php
Authorization: Bearer <token>
{
  "data": "2026-01-19",
  "quantidade": 1,
  "tipo": "presencial",
  "dependente": 239,
  "fora_do_horario": 1,
  "detalhe": "Reserva Adicional"
}
```

Se funcionar no Postman mas não no app, há diferença no formato.

---

## 💡 Melhorias Aplicadas

1. ✅ Logs mais detalhados na verificação de horário
2. ✅ Mensagem de erro melhorada
3. ✅ Campo `detalhe` sempre enviado
4. ✅ Conversão boolean → integer corrigida

---

## 🚨 Se Ainda Não Funcionar

O problema está no **backend PHP**. Verifique:

1. **Logs do servidor PHP** - Qual validação está falhando?
2. **Código PHP da API** - Há alguma validação não documentada?
3. **Banco de dados** - Execute as queries acima

---

**Última Atualização:** Janeiro 2025
