# 🚀 Como Resolver Erro de CORS - Guia Rápido

## ⚠️ Problema

Você está vendo o erro: **"Erro de conexão. O endpoint pode não estar disponível ou há problema de CORS."**

## ✅ Solução Rápida (2 minutos)

### Passo 1: Execute o script especial

**Opção A - Script automático:**
```batch
executar_chrome_sem_cors.bat
```

**Opção B - Manual (PowerShell):**
```powershell
# Fechar Chrome
taskkill /F /IM chrome.exe

# Aguardar 2 segundos
Start-Sleep -Seconds 2

# Abrir Chrome sem CORS
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="$env:TEMP\chrome_dev" --disable-web-security --disable-features=VizDisplayCompositor
```

### Passo 2: Execute o Flutter

Em outro terminal:
```batch
flutter run -d chrome
```

## ✅ Solução Alternativa: Usar o executar_app.bat atualizado

Execute:
```batch
executar_app.bat
```

Escolha a opção **2** (Executar sem CORS)

## 📋 O que está acontecendo?

- ✅ A API está funcionando (você viu a resposta JSON correta)
- ❌ O navegador bloqueia requisições do Flutter Web por segurança (CORS)
- ✅ Solução: Desabilitar CORS temporariamente no Chrome para desenvolvimento

## ⚠️ IMPORTANTE

- **Use apenas para desenvolvimento**
- **Nunca use esse Chrome especial para navegação normal**
- **Feche esse Chrome quando terminar de desenvolver**

## 🔍 Verificar se funcionou

1. Execute o script acima
2. Execute `flutter run -d chrome`
3. Faça login no app
4. Acesse a tela de reservas
5. Deve carregar sem erro de CORS

## 📝 Nota Técnica

O erro de CORS acontece porque:
- Flutter Web roda em `localhost:porta`
- API está em `presenca.aom.org.br`
- Navegadores bloqueiam requisições entre domínios diferentes por segurança

Para produção, o servidor precisa configurar CORS corretamente, mas para desenvolvimento local, podemos desabilitar essa verificação.
