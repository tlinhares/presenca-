# ✅ Solução: Garantir Uso da API Real

## 🔧 Correções Aplicadas

### 1. Verificação Explícita de Mock
- ✅ Adicionada função `_verificarModoMock()` que lança erro se `_useMockData = true`
- ✅ Logs explícitos mostrando qual URL está sendo chamada
- ✅ Comentários claros indicando que está usando API real

### 2. Logs Melhorados
Agora quando você criar uma reserva, verá no console:

```
🌐 CRIANDO RESERVA NA API REAL:
🌐 URL: https://presenca.aom.org.br/api/almoco/reservar.php
🌐 Body: data=2026-01-20, fora_do_horario=false
🔵 POST Request: https://presenca.aom.org.br/api/almoco/reservar.php
🔵 Body: {"data":"2026-01-20","fora_do_horario":false}
🔵 Headers: Content-Type, Accept, Authorization
🟢 Response Status: 200
🟢 Response Body: {"status":"ok","mensagem":"Reserva realizada com sucesso",...}
```

## 📋 Como Verificar se Está Usando API Real

### Passo 1: Limpar Cache e Recompilar

Execute no terminal:

```bash
flutter clean
flutter pub get
```

Ou use o script criado:

```bash
.\limpar_cache_e_recompilar.bat
```

### Passo 2: Verificar Código

Abra e confirme:

**`lib/core/api/reservas_service.dart`** (linha 10):
```dart
static const bool _useMockData = false; // ✅ USANDO API REAL - NÃO ALTERAR!
```

**`lib/core/api/endpoints.dart`** (linhas 4, 16, 31):
```dart
static const String baseUrl = 'https://presenca.aom.org.br/api/mobile';
static const String baseUrlAlmoco = 'https://presenca.aom.org.br/api/almoco';
static const String baseUrlDependentes = 'https://presenca.aom.org.br/api/dependentes';
```

### Passo 3: Executar e Verificar Logs

1. Execute o app: `flutter run -d chrome`
2. Abra o console do navegador (F12 → Console)
3. Faça login
4. Crie uma reserva
5. **Verifique os logs** - deve aparecer:
   - `🌐 CRIANDO RESERVA NA API REAL:`
   - `🌐 URL: https://presenca.aom.org.br/api/almoco/reservar.php`
   - **NÃO deve aparecer `localhost` ou `127.0.0.1`**

### Passo 4: Verificar no Banco de Dados

Após criar uma reserva:

1. Conecte ao MySQL
2. Execute:
   ```sql
   SELECT * FROM reservas_almoco 
   ORDER BY id DESC 
   LIMIT 5;
   ```
3. **A reserva criada DEVE aparecer na lista**

## 🚨 Se Ainda Não Funcionar

### Problema: Logs mostram `localhost` ou `127.0.0.1`
**Solução**: Verifique `lib/core/api/endpoints.dart` - não deve ter nenhuma referência a localhost

### Problema: Reserva criada mas não aparece no banco
**Solução**: 
1. Verifique se `_useMockData = false`
2. Limpe o cache: `flutter clean`
3. Recompile: `flutter pub get`
4. Reinicie o app completamente

### Problema: Erro "Usuário não logado"
**Solução**: 
1. Faça logout
2. Faça login novamente
3. Verifique se o token está sendo salvo

### Problema: Erro de CORS
**Solução**: 
1. Use Chrome sem CORS: `.\executar_chrome_sem_cors.bat`
2. Em outro terminal: `flutter run -d chrome`

## ✅ Checklist Final

- [ ] `flutter clean` executado
- [ ] `flutter pub get` executado
- [ ] `_useMockData = false` em ambos os serviços
- [ ] Endpoints apontam para `https://presenca.aom.org.br`
- [ ] Logs mostram URL correta (não localhost)
- [ ] Reserva aparece no banco de dados após criar
- [ ] Console não mostra erros de CORS ou conexão

## 📞 Próximos Passos

1. Execute `flutter clean` e `flutter pub get`
2. Reinicie o app completamente
3. Crie uma reserva de teste
4. Verifique os logs no console (F12)
5. Verifique no banco de dados se a reserva foi criada

**Se após seguir todos os passos ainda não funcionar, envie:**
- Screenshot dos logs do console (F12)
- Resultado da query no banco de dados
- Mensagem de erro (se houver)
