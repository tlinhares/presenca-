# 🔧 Solução: "Não foi possível conectar ao servidor"

## ⚠️ Problema Identificado

Ao tentar fazer login no app instalado via APK, aparece a mensagem:
**"Não foi possível conectar ao servidor"**

## ✅ Correções Aplicadas

### 1. Permissão de Internet Adicionada
**Arquivo:** `android/app/src/main/AndroidManifest.xml`
- Adicionado: `<uses-permission android:name="android.permission.INTERNET"/>`
- Adicionado: `<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE"/>`

**IMPORTANTE:** Você precisa **recompilar o APK** após essa alteração!

## 🔄 Como Aplicar a Correção

### Opção 1: Recompilar APK (Recomendado)
```powershell
# Limpar build anterior
flutter clean

# Gerar novo APK com as correções
flutter build apk --release

# Instalar no celular
adb install -r build\app\outputs\flutter-apk\app-release.apk
```

### Opção 2: Executar em Modo Debug (Para Testar Agora)
```powershell
# Desinstalar versão release
adb uninstall com.example.app_presenca

# Executar em modo debug (usa código atualizado)
flutter run -d RXCY9043JMZ
```

## 🔍 Depuração

### Ver Logs em Tempo Real
```powershell
# Em um terminal, execute o app
flutter run -d RXCY9043JMZ

# Em outro terminal, veja os logs
flutter logs
```

### Verificar Erros Específicos
Os logs mostrarão:
- 🔵 Requisições sendo feitas
- 🟢 Respostas recebidas
- 🔴 Erros de conexão

### Verificar Conexão do Celular
```powershell
# Testar ping
adb shell ping -c 3 8.8.8.8

# Testar acesso à API
adb shell "curl -v https://presenca.aom.org.br/api/mobile/auth/login.php"
```

## 📋 Checklist de Verificação

- [ ] Permissão de INTERNET adicionada no AndroidManifest.xml
- [ ] APK recompilado após adicionar permissão
- [ ] Celular tem conexão com internet (Wi-Fi ou dados móveis)
- [ ] URL da API está correta: `https://presenca.aom.org.br/api/mobile`
- [ ] Logs mostram requisições sendo feitas
- [ ] Não há erros de certificado SSL

## 🚨 Outras Causas Possíveis

### 1. Celular sem Internet
- Verifique Wi-Fi ou dados móveis
- Teste abrindo um site no navegador do celular

### 2. Firewall ou Proxy
- Verifique se há firewall bloqueando conexões
- Desative VPN temporariamente para testar

### 3. Certificado SSL Inválido
- Verifique se a data/hora do celular está correta
- Tente acessar a API no navegador do celular

### 4. URL Incorreta
- Verifique `lib/core/api/endpoints.dart`
- Deve ser: `https://presenca.aom.org.br/api/mobile`

## 🎯 Próximos Passos

1. **Recompile o APK** com as correções
2. **Instale no celular** novamente
3. **Teste o login** e verifique os logs
4. Se ainda não funcionar, **execute em modo debug** para ver logs detalhados

---

**Última Atualização:** Janeiro 2025
