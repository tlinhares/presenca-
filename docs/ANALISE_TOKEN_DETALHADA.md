# 🔍 Análise Detalhada do Token JWT

## 📋 Token Enviado

```
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3Njg5MTMzMjAsImV4cCI6MTc2ODk5OTcyMCwidXNlcl9pZCI6MjIyMzYsIm5vbWUiOiJVc3VcdTAwZTFyaW8gdGVzdGUiLCJjYXRlZ29yaWEiOiJmdW5jaW9uYXJpbyIsImVtYWlsIjoidGlhZ28ubGluaGFyZXNAYWR2ZW50aXN0YXMub3JnIiwidHlwZSI6ImFjY2VzcyJ9.6xVUJ0-kOs4zJfyoIBFo96AaG423ShQVXZ1_vx1NDZU
```

## 🔓 Payload Decodificado

```json
{
  "iat": 1768913320,
  "exp": 1768999720,
  "user_id": 22236,
  "nome": "Usuário teste",
  "categoria": "funcionario",
  "email": "tiago.linhares@adventistas.org",
  "type": "access"
}
```

## ⏰ Análise de Datas

- **Emitido em (iat):** 1768913320 = **19/01/2026 18:08:40 UTC**
- **Expira em (exp):** 1768999720 = **20/01/2026 18:08:40 UTC**
- **Duração:** 24 horas (86400 segundos)

## ✅ Verificações

### Formato do Token
- ✅ Tem 3 partes (header.payload.signature)
- ✅ Formato JWT válido
- ✅ Header e payload são JSON válidos

### Validade do Token
- ⚠️ **Verificar se a data atual é anterior a 20/01/2026 18:08:40 UTC**
- Se a data atual for depois dessa data, o token está **expirado**

### Informações do Usuário
- ✅ User ID: 22236
- ✅ Nome: "Usuário teste"
- ✅ Email: "tiago.linhares@adventistas.org"
- ✅ Categoria: "funcionario"
- ✅ Tipo: "access"

## 🔍 Possíveis Causas do Erro

### 1. Token Expirado
Se a data atual for **depois de 20/01/2026 18:08:40 UTC**, o token está expirado.

**Solução:** Fazer login novamente para obter um novo token.

### 2. Middleware Não Processando Token
O backend pode não estar processando o Bearer Token corretamente.

**Verificar:**
- O arquivo `verificar_horario.php` inclui o middleware?
- O middleware está extraindo o token do header `Authorization`?
- O middleware está validando o token corretamente?

### 3. Token Não Sendo Validado
O servidor pode não estar validando a assinatura do token.

**Verificar:**
- A chave secreta usada para assinar o token está correta?
- O algoritmo de validação está correto (HS256)?

### 4. Problema de Configuração
Pode haver um problema de configuração no backend.

**Verificar:**
- Logs do servidor para ver se o token está chegando
- Se o middleware está sendo executado
- Se há erros no processamento do token

## 🛠️ Correções Implementadas

### 1. Decodificador JWT
Criado `lib/core/utils/jwt_decoder.dart` para:
- Decodificar tokens JWT
- Verificar expiração
- Obter informações do token

### 2. Logs Detalhados
Agora os logs mostram:
- Informações completas do token
- Data de expiração
- Se o token está expirado
- Tempo restante até expiração

### 3. Análise Automática
O código agora:
- Decodifica o token automaticamente
- Verifica se está expirado
- Mostra informações detalhadas nos logs

## 📋 Próximos Passos

1. **Execute o app novamente** - Os logs agora mostrarão se o token está expirado
2. **Verifique os logs** - Procure por:
   - "Token expira em:"
   - "Está expirado:"
   - "Expira em: X minutos"
3. **Se o token estiver expirado:**
   - Faça logout
   - Faça login novamente
   - Teste novamente
4. **Se o token NÃO estiver expirado:**
   - Verifique os logs do servidor
   - Confirme que o middleware está configurado
   - Verifique se há erros no processamento

## 🔧 Como Usar o Decodificador

```dart
import '../utils/jwt_decoder.dart';

// Verificar se token está expirado
bool expired = JwtDecoder.isExpired(token);

// Obter informações do token
Map<String, dynamic>? info = JwtDecoder.getTokenInfo(token);
if (info != null) {
  print('User ID: ${info['user_id']}');
  print('Expira em: ${info['expires_at']}');
  print('Está expirado: ${info['is_expired']}');
}
```

---

**Última Atualização:** Janeiro 2025
