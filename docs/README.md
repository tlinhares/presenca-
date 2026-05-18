# Presença Mobile - Aplicativo Flutter

Aplicativo mobile do Sistema de Presença AOM desenvolvido em Flutter.

## Estrutura do Projeto

```
lib/
├── main.dart                 # Ponto de entrada
├── core/
│   ├── api/                  # Cliente API e serviços
│   ├── models/               # Modelos de dados
│   └── utils/                # Utilitários
├── features/                 # Funcionalidades do app
│   ├── auth/                 # Autenticação
│   ├── dashboard/            # Dashboard
│   ├── reservas/             # Reservas de refeições
│   └── culto/                # Presença em cultos
└── widgets/                  # Widgets reutilizáveis
```

## Configuração

1. Instale o Flutter SDK
2. Execute `flutter pub get` para instalar dependências
3. Configure a URL da API em `lib/core/api/endpoints.dart`

## API Base URL

Por padrão, o app aponta para: `https://presenca.aom.org.br/api/mobile/`

## Autenticação

O app usa autenticação Bearer Token (JWT). O token é armazenado de forma segura usando `flutter_secure_storage`.

## Desenvolvimento

```bash
# Instalar dependências
flutter pub get

# Executar em modo debug
flutter run

# Build para Android
flutter build apk

# Build para iOS
flutter build ios
```
