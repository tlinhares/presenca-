# ✅ Modelo de Reserva Atualizado

## 🔄 Mudanças Realizadas

Baseado na estrutura real da tabela `reservas_almoco` do banco de dados, atualizei completamente o modelo e as telas.

### 📋 Estrutura Real da Tabela

```sql
reservas_almoco:
- id (INT, PK)
- id_usuario (BIGINT UNSIGNED)
- data (DATE) ← era "data_reserva"
- reservou_conjuge (TINYINT(1)) ← NOVO
- marmitex (TINYINT(1)) ← NOVO
- valor_refeicao (DECIMAL(10,2))
- valor_marmitex (DECIMAL(10,2)) ← NOVO
- horario_confirmacao (DATETIME) ← era "criado_em"
- observacao_especial (TEXT) ← era "observacoes"
```

### ❌ Campos Removidos (não existem no banco)

- `quantidade_dependentes` - Não existe mais
- `status` - É calculado pelo backend
- `criado_em` - Substituído por `horario_confirmacao`
- `atualizado_em` - Não existe

### ✅ Arquivos Atualizados

1. **`lib/core/models/reserva.dart`**
   - ✅ Modelo completamente reescrito
   - ✅ Campos corretos conforme banco
   - ✅ Métodos auxiliares: `dataFormatada`, `valorFormatado`
   - ✅ Tratamento correto de booleanos (tinyint(1))

2. **`lib/core/api/reservas_service.dart`**
   - ✅ Métodos atualizados para usar campos corretos
   - ✅ Modo mock desativado (`_useMockData = false`)
   - ✅ Parâmetros corretos em `criarReserva()`

3. **`lib/features/reservas/reservas_list_screen.dart`**
   - ✅ Exibição dos campos corretos
   - ✅ Status com cores (Atual=verde, Futura=azul, Finalizada=cinza)
   - ✅ Exibe valor da refeição
   - ✅ Exibe se tem cônjuge ou marmitex
   - ✅ Botão cancelar baseado em `podeExcluir`

4. **`lib/features/reservas/criar_reserva_screen.dart`**
   - ✅ Formulário completamente reescrito
   - ✅ Removido campo "quantidade de dependentes"
   - ✅ Adicionado checkbox "Incluir cônjuge"
   - ✅ Adicionado checkbox "Marmitex"
   - ✅ Campo "Observação Especial" (era "Observações")

## 🎯 Próximos Passos

1. **Faça Hot Restart** no app (pressione `R` no terminal)
2. **Teste a listagem** - Deve mostrar dados reais do banco
3. **Teste criar reserva** - Com os novos campos
4. **Verifique se o backend está retornando** os campos calculados (`status`, `pode_excluir`)

## ⚠️ Importante

### Campos Calculados pelo Backend

O backend precisa retornar estes campos calculados:

- `status` - String: "Atual", "Futura" ou "Finalizada"
- `pode_excluir` - Boolean: true/false

Se o backend não retornar esses campos, eles serão `null`/`false` no app.

### Formato Esperado da API

```json
{
  "success": true,
  "data": {
    "reservas": [
      {
        "id": 4159,
        "id_usuario": 22346,
        "data": "2026-01-16",
        "reservou_conjuge": 0,
        "marmitex": 0,
        "valor_refeicao": "30.00",
        "valor_marmitex": null,
        "horario_confirmacao": "2026-01-16 09:54:02",
        "observacao_especial": null,
        "status": "Atual",
        "pode_excluir": true
      }
    ]
  },
  "message": "Reservas listadas com sucesso"
}
```

## 🔍 Verificações

Após fazer Hot Restart, verifique:

- ✅ Lista mostra dados reais (não mais mock)
- ✅ Datas estão corretas
- ✅ Valores monetários aparecem
- ✅ Status está sendo exibido
- ✅ Botão cancelar aparece apenas quando `pode_excluir = true`
- ✅ Criar reserva funciona com novos campos

---

**Status:** ✅ Modelo atualizado conforme estrutura real do banco
**Próximo:** Testar integração com backend real
