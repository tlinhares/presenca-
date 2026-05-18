# 🔍 Como Verificar se Está Usando a API Real

## ✅ Verificações Necessárias

### 1. Verificar se `_useMockData = false`

Abra os arquivos e confirme:

- ✅ `lib/core/api/reservas_service.dart` → linha 10: `static const bool _useMockData = false;`
- ✅ `lib/core/api/dependentes_service.dart` → linha 9: `static const bool _useMockData = false;`

### 2. Verificar os Endpoints

Abra `lib/core/api/endpoints.dart` e confirme que está usando:

- ✅ `https://presenca.aom.org.br/api/almoco/` (não `localhost` ou `127.0.0.1`)
- ✅ `https://presenca.aom.org.br/api/mobile/` (não `localhost` ou `127.0.0.1`)

### 3. Verificar Logs no Console

Quando você criar uma reserva, deve aparecer no console:

```
🔵 POST Request: https://presenca.aom.org.br/api/almoco/reservar.php
🔵 Body: {"data":"2026-01-20","fora_do_horario":false}
🔵 Headers: Content-Type, Accept, Authorization
🔵 Token Preview: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
🟢 Response Status: 200
🟢 Response Body: {"status":"ok","mensagem":"Reserva realizada com sucesso",...}
```

**Se você ver `localhost` ou `127.0.0.1` nos logs, há um problema!**

### 4. Verificar no Banco de Dados

Após criar uma reserva:

1. Conecte ao banco de dados MySQL
2. Execute: `SELECT * FROM reservas_almoco ORDER BY id DESC LIMIT 5;`
3. Verifique se a reserva criada aparece na tabela

**Se a reserva não aparecer no banco, significa que não está usando a API real!**

## 🔧 Como Corrigir

### Se `_useMockData` estiver `true`:

1. Abra `lib/core/api/reservas_service.dart`
2. Mude linha 10 para: `static const bool _useMockData = false;`
3. Salve o arquivo
4. Execute `flutter clean` e depois `flutter pub get`
5. Reinicie o app

### Se os endpoints estiverem errados:

1. Abra `lib/core/api/endpoints.dart`
2. Verifique se está usando `https://presenca.aom.org.br`
3. Não deve ter `localhost` ou `127.0.0.1`

### Se ainda não funcionar:

1. Verifique os logs no console do navegador (F12)
2. Procure por erros de CORS ou conexão
3. Verifique se o token está sendo enviado corretamente
4. Tente fazer login novamente

## 📋 Checklist de Diagnóstico

- [ ] `_useMockData = false` em ambos os serviços
- [ ] Endpoints apontam para `https://presenca.aom.org.br`
- [ ] Logs mostram URL correta (não localhost)
- [ ] Token está sendo enviado (veja nos logs)
- [ ] Reserva aparece no banco de dados após criar
- [ ] Não há erros de CORS no console

## 🚨 Problemas Comuns

### "Reserva criada mas não aparece no banco"
- **Causa**: Usando dados mock (`_useMockData = true`)
- **Solução**: Mude para `false` e reinicie o app

### "Erro de CORS"
- **Causa**: Navegador bloqueando requisições
- **Solução**: Use Chrome sem CORS (`executar_chrome_sem_cors.bat`)

### "Usuário não logado"
- **Causa**: Token expirado ou não enviado
- **Solução**: Faça login novamente

### "Erro de conexão"
- **Causa**: Servidor offline ou URL incorreta
- **Solução**: Verifique se o servidor está acessível
