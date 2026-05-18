# 🚀 Como Abrir o App Novamente

## ⚡ Método Rápido (PowerShell)

Abra o PowerShell no diretório do projeto e execute:

```powershell
cd C:\Users\CPD\Desktop\app_presenca
flutter run -d chrome
```

## 📋 Passo a Passo Detalhado

### 1. Abrir PowerShell

- Pressione `Win + X`
- Escolha "Windows PowerShell" ou "Terminal"
- Ou pesquise "PowerShell" no menu Iniciar

### 2. Navegar até o Projeto

```powershell
cd C:\Users\CPD\Desktop\app_presenca
```

### 3. Executar o App

```powershell
flutter run -d chrome
```

## 🎯 Outras Opções de Execução

### No Edge (navegador)
```powershell
flutter run -d edge
```

### No Windows Desktop
```powershell
flutter run -d windows
```

### Ver Dispositivos Disponíveis
```powershell
flutter devices
```

## 🐛 Problemas Comuns

### Erro: "flutter: command not found"

**Solução:** O PATH pode não estar carregado. Execute:

```powershell
$env:Path += ";C:\src\flutter\bin"
flutter run -d chrome
```

Ou feche e reabra o PowerShell.

### Erro: "No devices found"

**Solução:** Verifique se o Chrome está instalado e aberto:

```powershell
flutter devices
```

### Erro de Compilação

**Solução:** Limpe e reinstale dependências:

```powershell
flutter clean
flutter pub get
flutter run -d chrome
```

### App não abre automaticamente

**Solução:** 
1. Verifique o terminal - pode haver erros de compilação
2. Procure por uma URL no terminal (ex: `http://localhost:xxxxx`)
3. Abra essa URL manualmente no Chrome

## 💡 Dicas

- **Hot Reload:** Durante a execução, pressione `r` para recarregar
- **Hot Restart:** Pressione `R` para reiniciar completamente
- **Sair:** Pressione `q` para encerrar
- **Logs:** Os logs aparecem no terminal onde o app está rodando

## 📝 Criar Atalho (Opcional)

Você pode criar um arquivo `.bat` para abrir rapidamente:

**Criar arquivo `executar_app.bat`:**

```batch
@echo off
cd /d C:\Users\CPD\Desktop\app_presenca
set PATH=%PATH%;C:\src\flutter\bin
flutter run -d chrome
pause
```

Depois é só dar duplo clique no arquivo `.bat`!

## 🔄 Comandos Durante Execução

Quando o app estiver rodando, você pode usar:

- **r** - Hot reload (recarrega mudanças sem perder estado)
- **R** - Hot restart (reinicia completamente)
- **h** - Mostrar ajuda
- **q** - Sair do app

## 📚 Mais Informações

- `COMANDOS_UTEIS.md` - Lista completa de comandos
- `GUIA_INSTALACAO_WINDOWS.md` - Guia completo
- `DOCUMENTACAO.md` - Documentação do projeto
