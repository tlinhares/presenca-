# 📊 Estrutura do Banco de Dados - Informações Necessárias

## 🔍 O que Preciso Saber

Para garantir que o app funcione corretamente com os dados reais do banco, preciso das seguintes informações:

### 1. Tabela: `reservas_almoco`

Por favor, forneça:

```sql
-- Estrutura completa da tabela
DESCRIBE reservas_almoco;
-- ou
SHOW CREATE TABLE reservas_almoco;
```

**Informações importantes:**
- ✅ Nome exato de cada coluna
- ✅ Tipo de dados de cada coluna
- ✅ Campos obrigatórios vs opcionais
- ✅ Valores possíveis para campos enum (ex: status)
- ✅ Relacionamentos com outras tabelas (FKs)

### 2. Exemplo de Dados Reais

Se possível, um exemplo de registro real (sem dados sensíveis):

```sql
SELECT * FROM reservas_almoco LIMIT 1;
```

### 3. Regras de Negócio

- Quais são os valores possíveis para `status`? (ex: 'ativa', 'cancelada', 'confirmada', etc.)
- Há algum campo de data/hora específico?
- Como funciona a relação com dependentes?
- Há alguma validação especial?

### 4. Outras Tabelas Relacionadas

Se houver tabelas relacionadas (ex: `dependentes`, `cardapio`, etc.), também preciso saber:
- Estrutura das tabelas relacionadas
- Como fazer JOINs se necessário

---

## 📝 Formato Sugerido

Você pode me passar:

1. **SQL do CREATE TABLE** (ideal)
2. **Ou uma lista dos campos** assim:

```
reservas_almoco:
- id (INT, PK, AUTO_INCREMENT)
- id_usuario (INT, FK -> usuarios.id)
- data_reserva (DATE)
- quantidade_dependentes (INT, default 0)
- status (ENUM: 'ativa', 'cancelada', 'confirmada')
- observacoes (TEXT, nullable)
- criado_em (DATETIME)
- atualizado_em (DATETIME, nullable)
```

---

## 🎯 O que Vou Fazer com Essas Informações

1. ✅ Ajustar o modelo `Reserva` em `lib/core/models/reserva.dart`
2. ✅ Corrigir o mapeamento JSON se necessário
3. ✅ Ajustar o serviço para trabalhar com a estrutura real
4. ✅ Garantir que os dados sejam exibidos corretamente
5. ✅ Implementar validações conforme as regras do banco

---

## 💡 Alternativa: Consulta Direta

Se preferir, posso também:
- Conectar diretamente ao banco (se você fornecer acesso)
- Ou você pode executar e me passar o resultado:

```sql
-- Estrutura
SHOW CREATE TABLE reservas_almoco;

-- Exemplo de dados
SELECT * FROM reservas_almoco ORDER BY criado_em DESC LIMIT 5;
```

---

**Aguardando essas informações para ajustar o código corretamente! 📋**
