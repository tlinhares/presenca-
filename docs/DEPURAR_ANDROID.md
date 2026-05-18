# 🔍 Como Depurar o App Android Conectado via USB

## ✅ Pré-requisitos

1. **Dispositivo conectado via USB**
2. **Depuração USB habilitada** no celular:
   - Configurações → Sobre o telefone → Toque 7 vezes em "Número da versão"
   - Configurações → Opções do desenvolvedor → Depuração USB (ativar)
   - Conecte o USB e autorize quando aparecer o popup

## 🚀 Passo 1: Verificar Conexão

```powershell
# Adicionar ADB ao PATH
$env:Path += ";$env:LOCALAPPDATA\Android\Sdk\platform-tools"

# Verificar dispositivos conectados
adb devices
```

**Deve aparecer:**
```
List of devices attached
RXCY9043JMZ     device
```

Se aparecer `unauthorized`, autorize no celular quando aparecer o popup.

## 🔧 Passo 2: Executar App em Modo Debug

**Opção A: Via Flutter (Recomendado)**
```powershell
# Executar em modo debug conectado ao dispositivo
flutter run -d RXCY9043JMZ
```

**Opção B: Via ADB (se já instalado)**
```powershell
# Desinstalar versão release
adb uninstall com.example.app_presenca

# Executar em modo debug
flutter run -d RXCY9043JMZ
```

## 📊 Passo 3: Ver Logs em Tempo Real

**Em outro terminal PowerShell, execute:**
```powershell
# Ver logs do Flutter
flutter logs

# OU ver logs do Android diretamente
adb logcat | Select-String "flutter"
```

**Para filtrar apenas erros:**
```powershell
adb logcat *:E flutter:*
```

## 🔍 Passo 4: Verificar Problema de Conexão

O erro "não foi possível conectar ao servidor" geralmente indica:

### 1. Verificar URL da API
```powershell
# Verificar se a URL está correta
adb shell "run-as com.example.app_presenca cat /data/data/com.example.app_presenca/shared_prefs/*.xml" 2>$null
```

### 2. Testar Conexão no Celular
```powershell
# Abrir navegador no celular e testar a API
# https://presenca.aom.org.br/api/mobile/auth/login.php
```

### 3. Verificar Permissões de Internet
O AndroidManifest.xml deve ter:
```xml
<uses-permission android:name="android.permission.INTERNET" />
```

## 🐛 Passo 5: Depuração Avançada

### Ver Logs Detalhados do App
```powershell
# Logs completos do Flutter
adb logcat -c  # Limpar logs
flutter run -d RXCY9043JMZ --verbose
```

### Inspecionar Requisições HTTP
```powershell
# Ver todas as requisições HTTP
adb logcat | Select-String "http"
```

### Verificar Certificados SSL
```powershell
# Ver erros de certificado
adb logcat | Select-String "SSL\|certificate\|TLS"
```

## 🔧 Solução de Problemas Comuns

### Problema: "Não foi possível conectar ao servidor"

**Causas possíveis:**
1. **Celular sem internet** - Verifique Wi-Fi/dados móveis
2. **URL da API incorreta** - Verifique `lib/core/api/endpoints.dart`
3. **Firewall bloqueando** - Verifique configurações de rede
4. **Certificado SSL inválido** - Verifique data/hora do celular

**Solução:**
```powershell
# 1. Verificar se o celular tem internet
adb shell ping -c 3 8.8.8.8

# 2. Testar acesso à API diretamente
adb shell "curl -v https://presenca.aom.org.br/api/mobile/auth/login.php"

# 3. Verificar logs detalhados
flutter run -d RXCY9043JMZ --verbose
```

### Problema: App não aparece na lista de dispositivos

**Solução:**
```powershell
# Reiniciar ADB
adb kill-server
adb start-server
adb devices
```

### Problema: "device unauthorized"

**Solução:**
1. No celular, aparecerá um popup pedindo autorização
2. Marque "Sempre permitir deste computador"
3. Toque em "Permitir"
4. Execute `adb devices` novamente

## 📱 Hot Reload e Hot Restart

Quando o app estiver rodando em modo debug:

- **Hot Reload:** Pressione `r` no terminal
- **Hot Restart:** Pressione `R` no terminal
- **Quit:** Pressione `q` no terminal

## 🔍 Verificar Configuração da API

Edite `lib/core/api/endpoints.dart` e verifique se está usando:
```dart
static const String baseUrl = 'https://presenca.aom.org.br/api/mobile';
```

## 📋 Checklist de Depuração

- [ ] Dispositivo aparece em `adb devices`
- [ ] Depuração USB habilitada no celular
- [ ] Celular tem conexão com internet
- [ ] URL da API está correta
- [ ] Logs estão sendo exibidos
- [ ] App está rodando em modo debug (não release)

## 🎯 Comandos Rápidos

```powershell
# Ver dispositivos
adb devices

# Executar app em debug
flutter run -d RXCY9043JMZ

# Ver logs
flutter logs

# Reiniciar ADB
adb kill-server && adb start-server

# Limpar dados do app
adb shell pm clear com.example.app_presenca
```

---

**Última Atualização:** Janeiro 2025
