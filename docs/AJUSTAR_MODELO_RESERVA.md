# 🔧 Ajustar Modelo de Reserva Baseado na Estrutura Real

## 📋 Estrutura Atual Assumida

Baseado no código PHP de exemplo encontrado, a estrutura da tabela `reservas_almoco` parece ser:

```sql
CREATE TABLE reservas_almoco (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    data_reserva DATE NOT NULL,
    quantidade_dependentes INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'ativa',
    observacoes TEXT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);
```

## ✅ Modelo Atual no Flutter

O modelo `Reserva` em `lib/core/models/reserva.dart` está mapeado assim:

```dart
- id → json['id']
- idUsuario → json['id_usuario'] ✅
- dataReserva → json['data_reserva'] ✅
- quantidadeDependentes → json['quantidade_dependentes'] ✅
- status → json['status'] ✅
- observacoes → json['observacoes'] ✅
- criadoEm → json['criado_em'] ✅
- atualizadoEm → json['atualizado_em'] ✅
```

## 🔍 Verificações Necessárias

Se você está vendo dados de **2023**, isso indica que:

1. ✅ **Backend está funcionando** - O endpoint `/api/mobile/reservas` existe e retorna dados
2. ⚠️ **Modo mock pode estar ativo** - Verifique `lib/core/api/reservas_service.dart` linha 9

## 🎯 Próximos Passos

### 1. Desativar Modo Mock (se backend estiver funcionando)

Abra `lib/core/api/reservas_service.dart` e mude:

```dart
static const bool _useMockData = false; // Mude para false
```

### 2. Verificar Formato dos Dados Retornados

Se os dados de 2023 estão aparecendo, verifique:

- ✅ Formato da data está correto? (YYYY-MM-DD)
- ✅ Campos estão sendo mapeados corretamente?
- ✅ Status está sendo exibido corretamente?

### 3. Ajustar Modelo se Necessário

Se houver diferenças na estrutura real, me informe:

- Nomes diferentes de campos
- Tipos diferentes
- Campos adicionais que não estão no modelo
- Campos que estão no modelo mas não existem no banco

## 📝 Informações que Ainda Preciso

Para garantir 100% de compatibilidade:

1. **Estrutura exata da tabela:**
   ```sql
   SHOW CREATE TABLE reservas_almoco;
   ```

2. **Exemplo de dados reais:**
   ```sql
   SELECT * FROM reservas_almoco ORDER BY criado_em DESC LIMIT 3;
   ```

3. **Valores possíveis para status:**
   - Quais são os valores válidos? (ex: 'ativa', 'cancelada', 'confirmada')

4. **Formato de data/hora:**
   - Como as datas são armazenadas? (DATE, DATETIME, TIMESTAMP?)
   - Formato de retorno da API? (YYYY-MM-DD, ISO8601?)

## 🚀 Ação Imediata

Se o backend está funcionando e você está vendo dados reais:

1. **Desative o modo mock:**
   ```dart
   static const bool _useMockData = false;
   ```

2. **Faça Hot Restart** (pressione `R` no terminal)

3. **Teste novamente** - Deve mostrar dados reais do banco

---

**Me informe se há alguma diferença na estrutura ou se tudo está funcionando corretamente! 📋**
