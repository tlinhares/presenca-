# 🔧 Solução para Erro de CORS no Flutter Web

## ❌ Problema

O Flutter Web está bloqueado por políticas CORS ao tentar acessar a API `https://presenca.aom.org.br/api/almoco/`.

## ✅ Soluções

### Opção 1: Executar Chrome com CORS Desabilitado (Desenvolvimento)

**Windows (PowerShell):**
```powershell
# Fechar todos os processos do Chrome primeiro
taskkill /F /IM chrome.exe

# Executar Chrome com CORS desabilitado
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="C:\temp\chrome_dev" --disable-web-security --disable-features=VizDisplayCompositor

# Depois executar o Flutter normalmente
flutter run -d chrome
```

**Criar arquivo `executar_chrome_sem_cors.bat`:**
```batch
@echo off
echo Fechando Chrome...
taskkill /F /IM chrome.exe 2>nul
timeout /t 2 /nobreak >nul
echo Iniciando Chrome sem CORS...
start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="%TEMP%\chrome_dev" --disable-web-security --disable-features=VizDisplayCompositor
echo Chrome iniciado sem CORS. Agora execute: flutter run -d chrome
pause
```

### Opção 2: Configurar CORS no Servidor (Produção)

O servidor precisa retornar os headers CORS corretos. Adicione no início de cada arquivo PHP da API:

```php
<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Responde OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
```

### Opção 3: Usar Proxy Local (Desenvolvimento)

Criar um proxy local que faz as requisições sem problemas de CORS.

## 🚀 Solução Rápida para Testar Agora

1. **Feche o Chrome completamente**
2. **Execute o Chrome com flags especiais:**
   ```powershell
   & "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="C:\temp\chrome_dev" --disable-web-security
   ```
3. **Execute o Flutter:**
   ```powershell
   flutter run -d chrome
   ```

## ⚠️ AVISO

- **NUNCA use Chrome sem CORS em produção ou para navegação normal**
- Use apenas para desenvolvimento do Flutter Web
- Feche esse Chrome especial quando terminar o desenvolvimento

## 📝 Nota

A API está funcionando corretamente (como visto na resposta JSON). O problema é apenas uma restrição de segurança do navegador para desenvolvimento local.
