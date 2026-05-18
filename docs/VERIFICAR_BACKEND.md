# 🔍 Verificar se Backend Está Funcionando

## ⚠️ Situação Atual

Você mencionou que está vendo dados de **2023** na tela de reservas. Isso pode significar:

1. ✅ **Backend está funcionando** e retornando dados reais
2. ⚠️ **Dados mock estão sendo usados** mas com datas antigas

## 🔧 Como Verificar

### Opção 1: Verificar no Código

Abra `lib/core/api/reservas_service.dart` e veja a linha 9:

```dart
static const bool _useMockData = true; // Se TRUE = usando dados mock
```

- Se `true` → Está usando dados mock (não são dados reais)
- Se `false` → Está tentando usar API real

### Opção 2: Verificar no Terminal

Quando o app carrega as reservas, veja no terminal:
- Se aparecer "Reservas carregadas (modo desenvolvimento)" → Dados mock
- Se não aparecer essa mensagem → Pode estar usando API real

### Opção 3: Testar Endpoint Diretamente

Execute no terminal (substitua SEU_TOKEN pelo token real):

```powershell
# Obter token fazendo login primeiro
# Depois testar:
curl -X GET "https://presenca.aom.org.br/api/mobile/reservas" `
  -H "Authorization: Bearer SEU_TOKEN" `
  -H "Content-Type: application/json"
```

## 🎯 Próximos Passos

### Se Backend ESTÁ Funcionando:

1. Desative dados mock:
   ```dart
   static const bool _useMockData = false;
   ```

2. Verifique se a estrutura dos dados retornados está correta
3. Ajuste o modelo se necessário

### Se Backend NÃO Está Funcionando:

1. Mantenha `_useMockData = true` para desenvolvimento
2. Implemente o endpoint no backend
3. Depois mude para `false`

## 📋 Informações que Preciso

Para garantir que tudo funcione corretamente, preciso saber:

1. **Estrutura da tabela `reservas_almoco`**
   - Campos e tipos
   - Valores possíveis para status
   - Relacionamentos

2. **Formato dos dados retornados pela API**
   - Exemplo de resposta JSON real

3. **Se o endpoint já existe no backend**
   - Caminho: `/var/www/html/presenca/api/mobile/reservas/index.php`

---

**Me informe qual é a situação atual para eu ajustar o código corretamente! 🚀**
