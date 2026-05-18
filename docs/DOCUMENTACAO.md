Documentação completa do projeto
Crie um arquivo chamado PROJETO_DOCUMENTACAO.md na pasta /var/www/html/presenca-mobile/ com o seguinte conteúdo:
# 📱 Presença Mobile - Documentação Completa do Projeto**Data de Criação:** Janeiro 2025  **Status:** ✅ Backend implementado | 🚧 Frontend em desenvolvimento  **Versão:** 1.0.0---## 📋 Índice1. [Visão Geral do Projeto](#visão-geral-do-projeto)2. [Contexto do Sistema](#contexto-do-sistema)3. [Arquitetura Implementada](#arquitetura-implementada)4. [Estrutura de Arquivos](#estrutura-de-arquivos)5. [Backend (PHP) - O que foi implementado](#backend-php---o-que-foi-implementado)6. [Frontend (Flutter) - O que foi implementado](#frontend-flutter---o-que-foi-implementado)7. [Como Continuar o Desenvolvimento](#como-continuar-o-desenvolvimento)8. [Próximos Passos](#próximos-passos)9. [Informações Técnicas](#informações-técnicas)---## 🎯 Visão Geral do ProjetoEste projeto é a transformação do **Sistema de Presença AOM** (sistema web PHP) em um **aplicativo mobile Flutter** para Android e iOS.### Objetivo PrincipalCriar uma camada de API REST com autenticação Bearer Token (JWT) que permite ao aplicativo Flutter acessar todas as funcionalidades do sistema web existente, **sem alterar nenhum código do sistema atual**.### Princípio Fundamental✅ **ZERO IMPACTO** no sistema web existente - nenhum arquivo foi modificado  ✅ **Compatibilidade total** - sistema web continua funcionando normalmente  ✅ **Reutilização** - mesmas APIs, apenas nova camada de autenticação---## 🏢 Contexto do Sistema### Sistema Web OriginalO **Sistema de Presença AOM** é um sistema web PHP completo com os seguintes módulos:| Módulo | Descrição | Status ||--------|-----------|--------|| **Gerenciamento** | Usuários, dispositivos, logs, configurações | ✅ Ativo || **Refeições** | Reservas de almoço, cardápios, relatórios | ✅ Ativo || **Culto** | Presença em cultos, reconhecimento facial | ✅ Ativo || **Estoque** | Controle de estoque | ✅ Ativo || **Frota** | Controle de frota de veículos | ✅ Ativo |### Tecnologias do Sistema Web- **Backend:** PHP 8.0.30- **Banco de Dados:** MySQL- **Frontend:** HTML, JavaScript, Bootstrap- **Autenticação:** Sessões PHP- **Servidor:** Ubuntu Server 20.04### Funcionalidades Principais1. **Autenticação de Usuários**   - Login com email/senha   - Sessões PHP   - Controle de acesso por módulos2. **Reservas de Refeições**   - Reserva de almoço   - Gestão de dependentes   - Relatórios e automações3. **Presença em Cultos**   - Reconhecimento facial   - Marcação automática de presença   - Geração de faltas4. **Estoque**   - Controle de produtos   - Requisições   - Inventários5. **Frota**   - Controle de veículos   - Registro de utilização---## 🏗️ Arquitetura Implementada### Diagrama de Arquitetura
┌─────────────────────────────────────────────────────────────┐
│ SISTEMA WEB (PHP) │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ │
│ │ Frontend │ │ Sessões │ │ APIs │ │
│ │ (HTML/JS) │──│ PHP │──│ Existentes │ │
│ └──────────────┘ └──────────────┘ └──────────────┘ │
└─────────────────────────────────────────────────────────────┘
▲
│
│ (Middleware converte token → sessão)
│
┌─────────────────────────────────────────────────────────────┐
│ NOVA CAMADA MOBILE (PHP) │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ │
│ │ Endpoints │ │ Middleware │ │ TokenService │ │
│ │ Mobile │──│ Mobile Auth │──│ (JWT) │ │
│ └──────────────┘ └──────────────┘ └──────────────┘ │
└─────────────────────────────────────────────────────────────┘
▲
│
│ (Bearer Token)
│
┌─────────────────────────────────────────────────────────────┐
│ APLICATIVO FLUTTER (Mobile) │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ │
│ │ Telas UI │ │ AuthService │ │ ApiClient │ │
│ │ (Widgets) │──│ (State) │──│ (HTTP) │ │
│ └──────────────┘ └──────────────┘ └──────────────┘ │
└─────────────────────────────────────────────────────────────┘
### Fluxo de Autenticação1. **App Flutter** faz POST para `/api/mobile/auth/login.php` com email/senha2. **Backend valida** credenciais usando mesma lógica do sistema web3. **TokenService** gera JWT tokens (access + refresh)4. **App armazena** tokens localmente (flutter_secure_storage)5. **Próximas requisições** incluem header `Authorization: Bearer <token>`6. **Middleware** valida token e cria sessão PHP temporária7. **APIs existentes** funcionam normalmente com a sessão criada---## 📁 Estrutura de Arquivos### Backend (PHP) - `/var/www/html/presenca/`
presenca/
├── api/
│ └── mobile/ # ✨ NOVA - API Mobile
│ ├── auth/
│ │ ├── login.php # POST - Login e geração de token
│ │ ├── refresh.php # POST - Renovação de token
│ │ └── logout.php # POST - Logout
│ └── utils/
│ └── response.php # Helpers de resposta padronizada
│
├── core/
│ ├── services/
│ │ ├── TokenService.php # ✨ NOVO - Gerenciamento JWT
│ │ └── MenuPermissaoService.php # Existente
│ └── middleware/
│ └── mobile_auth.php # ✨ NOVO - Middleware de autenticação
│
├── vendor/ # Dependências Composer
│ └── firebase/php-jwt/ # ✨ NOVO - Biblioteca JWT
│
└── docs/
└── API_MOBILE.md # ✨ NOVO - Documentação da API
### Frontend (Flutter) - `/var/www/html/presenca-mobile/`
presenca-mobile/
├── lib/
│ ├── main.dart # Ponto de entrada do app
│ │
│ ├── core/
│ │ ├── api/
│ │ │ ├── api_client.dart # Cliente HTTP com Bearer Token
│ │ │ ├── auth_service.dart # Serviço de autenticação
│ │ │ └── endpoints.dart # Configuração de URLs
│ │ ├── models/
│ │ │ └── user.dart # Modelo de usuário
│ │ └── utils/
│ │ └── storage.dart # Armazenamento seguro
│ │
│ └── features/
│ ├── auth/
│ │ └── login_screen.dart # Tela de login
│ └── dashboard/
│ └── dashboard_screen.dart # Dashboard básico
│
├── pubspec.yaml # Dependências Flutter
├── README.md # Documentação básica
└── PROJETO_DOCUMENTACAO.md # ✨ ESTE ARQUIVO
---## 🔧 Backend (PHP) - O que foi implementado### 1. TokenService.php**Localização:** `core/services/TokenService.php`**Responsabilidades:**- Geração de tokens JWT (access + refresh)- Validação de tokens- Renovação de tokens- Conversão de token em sessão PHP**Métodos Principais:**TokenService::generateToken($userId, $nome, $categoria, $email)TokenService::validateToken($token)TokenService::refreshAccessToken($refreshToken, $conn)TokenService::createSessionFromToken($token)TokenService::extractTokenFromHeader()
Configurações:
Access Token: 24 horas de validade
Refresh Token: 7 dias de validade
Algoritmo: HS256
Chave secreta: Variável de ambiente JWT_SECRET_KEY ou hash baseado no domínio
2. Endpoints de Autenticação Mobile
POST /api/mobile/auth/login.php
Request:
{  "email": "usuario@exemplo.com",  "senha": "senha123"}
Response (Sucesso):
{  "success": true,  "data": {    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",    "expires_in": 86400,    "token_type": "Bearer",    "user": {      "id": 1,      "nome": "João Silva",      "email": "usuario@exemplo.com",      "categoria": "admin"    }  },  "message": "Login realizado com sucesso"}
POST /api/mobile/auth/refresh.php
Renova o access token usando o refresh token.
POST /api/mobile/auth/logout.php
Invalida a sessão (principalmente para logging).
3. Middleware de Autenticação
Localização: core/middleware/mobile_auth.php
Funcionalidade:
Intercepta requisições com header Authorization: Bearer <token>
Valida o token JWT
Cria sessão PHP compatível com sistema existente
Permite que APIs existentes funcionem sem modificação
Uso:
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';MobileAuthMiddleware::requireAuth();
4. Helpers de Resposta
Localização: api/mobile/utils/response.php
Classe: MobileResponse
Métodos:
MobileResponse::success($data, $message, $httpCode)MobileResponse::error($message, $httpCode, $errors)MobileResponse::unauthorized($message)MobileResponse::forbidden($message)MobileResponse::notFound($message)MobileResponse::paginated($data, $page, $perPage, $total)
5. Dependências Instaladas
Composer:
firebase/php-jwt: ^7.0 - Biblioteca JWT
📱 Frontend (Flutter) - O que foi implementado
1. Estrutura Base
Dependências Principais (pubspec.yaml):
http: ^1.1.0 - Requisições HTTP
flutter_secure_storage: ^9.0.0 - Armazenamento seguro
provider: ^6.1.1 - Gerenciamento de estado
shared_preferences: ^2.2.2 - Preferências locais
intl: ^0.18.1 - Internacionalização
2. Cliente API
Arquivo: lib/core/api/api_client.dart
Classe: ApiClient (Singleton)
Funcionalidades:
Requisições HTTP (GET, POST, PUT, DELETE)
Inclusão automática de Bearer Token
Tratamento de respostas padronizadas
Renovação automática de token
Métodos:
ApiClient().get(url, queryParams)ApiClient().post(url, body)ApiClient().put(url, body)ApiClient().delete(url)ApiClient().refreshToken()
3. Serviço de Autenticação
Arquivo: lib/core/api/auth_service.dart
Classe: AuthService (extends ChangeNotifier)
Funcionalidades:
Login com email/senha
Logout
Verificação de autenticação
Gerenciamento de estado do usuário
Armazenamento seguro de tokens
Estado:
User? user - Usuário logado
bool isLoading - Estado de carregamento
String? errorMessage - Mensagem de erro
4. Modelos de Dados
Arquivo: lib/core/models/user.dart
Classe: User
Propriedades:
int id
String nome
String email
String categoria
bool isAdmin (getter)
5. Armazenamento Seguro
Arquivo: lib/core/utils/storage.dart
Classe: SecureStorage
Funcionalidades:
Salvar/ler access token
Salvar/ler refresh token
Salvar/ler dados do usuário
Limpar todos os dados (logout)
6. Telas Implementadas
Login Screen
Arquivo: lib/features/auth/login_screen.dart
Funcionalidades:
Formulário de login
Validação de campos
Integração com AuthService
Redirecionamento para Dashboard após login
Verificação automática de login existente
Dashboard Screen
Arquivo: lib/features/dashboard/dashboard_screen.dart
Funcionalidades:
Exibição de dados do usuário
Botão de logout
Estrutura básica para expansão
7. Configuração de Endpoints
Arquivo: lib/core/api/endpoints.dart
Classe: ApiEndpoints
URL Base: https://presenca.aom.org.br/api/mobile
Endpoints Configurados:
Login: /auth/login.php
Refresh: /auth/refresh.php
Logout: /auth/logout.php
🚀 Como Continuar o Desenvolvimento
1. Configuração Inicial
No Computador Local:
# 1. Copiar projeto do servidorscp -r root@SEU_SERVIDOR:/var/www/html/presenca-mobile ~/presenca-mobile# 2. Instalar Flutter SDK (se ainda não tiver)# Windows: https://docs.flutter.dev/get-started/install/windows# macOS: brew install flutter# Linux: https://docs.flutter.dev/get-started/install/linux# 3. Instalar dependênciascd ~/presenca-mobileflutter pub get# 4. Verificar dispositivosflutter devices# 5. Executar appflutter run
2. Estrutura de Desenvolvimento Recomendada
Organização por Features
Cada funcionalidade deve ter sua própria pasta em lib/features/:
lib/features/├── auth/                    # ✅ Implementado│   ├── login_screen.dart│   └── auth_service.dart (no core/api)│├── dashboard/              # ✅ Implementado (básico)│   └── dashboard_screen.dart│├── reservas/              # 🚧 PRÓXIMO│   ├── reservas_list_screen.dart│   ├── criar_reserva_screen.dart│   ├── reservas_service.dart│   └── models/│       └── reserva.dart│├── culto/                 # 🚧 PRÓXIMO│   ├── presenca_screen.dart│   ├── historico_screen.dart│   └── culto_service.dart│└── estoque/               # 🚧 FUTURO    └── ...
3. Padrões de Código
Criar Novo Endpoint Mobile (Backend)
Criar arquivo em api/mobile/[modulo]/[acao].php
Incluir middleware de autenticação
Usar MobileResponse para respostas
Seguir padrão de resposta JSON
Exemplo:
<?phpheader('Content-Type: application/json; charset=UTF-8');require_once __DIR__ . '/../../../core/middleware/mobile_auth.php';require_once __DIR__ . '/../utils/response.php';MobileAuthMiddleware::requireAuth();// Sua lógica aquiecho json_encode(MobileResponse::success($data, 'Sucesso'));
Criar Nova Feature (Flutter)
Criar pasta em lib/features/[feature]/
Criar service em lib/core/api/[feature]_service.dart
Criar modelos em lib/core/models/ ou lib/features/[feature]/models/
Criar telas em lib/features/[feature]/
Usar ApiClient para requisições
Usar Provider para gerenciamento de estado
Exemplo de Service:
import '../api/api_client.dart';import '../api/endpoints.dart';class ReservasService {  final _api = ApiClient();    Future<ApiResponse> listarReservas() async {    return await _api.get(ApiEndpoints.reservas);  }    Future<ApiResponse> criarReserva(Map<String, dynamic> dados) async {    return await _api.post(ApiEndpoints.reservas, body: dados);  }}
4. Testando Novas Funcionalidades
Testar API (Backend)
# Testar endpointcurl -X POST https://presenca.aom.org.br/api/mobile/[endpoint] \  -H "Content-Type: application/json" \  -H "Authorization: Bearer SEU_TOKEN" \  -d '{"campo": "valor"}' | python3 -m json.tool
Testar no App Flutter
# Executar em modo debugflutter run# Ver logsflutter logs# Hot reload durante desenvolvimento# Pressione 'r' no terminal onde o app está rodando
📝 Próximos Passos
Fase 1: Funcionalidades Básicas (Prioridade Alta)
Melhorar Dashboard
[ ] Cards com informações resumidas
[ ] Menu de navegação
[ ] Notificações
Módulo de Reservas
[ ] Listar reservas do usuário
[ ] Criar nova reserva
[ ] Cancelar reserva
[ ] Ver histórico
[ ] Gestão de dependentes
Módulo de Culto
[ ] Ver presenças
[ ] Ver faltas
[ ] Justificar falta
[ ] Histórico de presenças
Fase 2: Funcionalidades Avançadas (Prioridade Média)
Módulo de Estoque
[ ] Listar produtos
[ ] Criar requisição
[ ] Ver requisições
Módulo de Frota
[ ] Listar veículos disponíveis
[ ] Registrar saída
[ ] Ver histórico
Melhorias Gerais
[ ] Tratamento de erros melhorado
[ ] Loading states
[ ] Pull to refresh
[ ] Cache offline
Fase 3: Otimizações (Prioridade Baixa)
Performance
[ ] Cache de dados
[ ] Lazy loading
[ ] Otimização de imagens
UX/UI
[ ] Animações
[ ] Temas (dark mode)
[ ] Internacionalização
Publicação
[ ] Build para produção
[ ] Publicar na Google Play
[ ] Publicar na App Store
🔍 Informações Técnicas
URLs Importantes
API Base: https://presenca.aom.org.br/api/mobile/
Sistema Web: https://presenca.aom.org.br/
Configurações de Ambiente
Backend (PHP)
PHP Version: 8.0.30
Composer: 2.8.8
JWT Library: firebase/php-jwt ^7.0
Chave JWT: Variável de ambiente JWT_SECRET_KEY ou hash automático
Frontend (Flutter)
Flutter Version: 3.38.7
Dart Version: 3.10.7
Min SDK: Android API 21+ / iOS 12+
Banco de Dados
Tabelas Principais:
usuarios - Usuários do sistema
reservas_almoco - Reservas de refeições
presencas_culto - Presenças em cultos
modulos - Módulos do sistema
usuario_permissoes - Permissões por módulo
Autenticação
Tokens JWT:
Access Token: 24 horas
Refresh Token: 7 dias
Algoritmo: HS256
Header: Authorization: Bearer <token>
CORS
A API suporta CORS com as seguintes configurações:
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
Estrutura de Resposta Padrão
Sucesso:
{  "success": true,  "data": { ... },  "message": "Mensagem de sucesso",  "timestamp": "2025-01-XXT00:00:00+00:00"}
Erro:
{  "success": false,  "message": "Mensagem de erro",  "timestamp": "2025-01-XXT00:00:00+00:00",  "errors": { ... } // Opcional}
Códigos HTTP
Código	Significado
200	Sucesso
201	Criado
400	Requisição inválida
401	Não autorizado
403	Acesso negado
404	Não encontrado
422	Erro de validação
500	Erro interno
📚 Documentação Adicional
Arquivos de Referência
API Mobile: /var/www/html/presenca/docs/API_MOBILE.md
Documentação completa da API REST
Exemplos de uso
Códigos de erro
Sistema de Permissões: /var/www/html/presenca/docs/SISTEMA_PERMISSOES.md
Módulos do sistema
Níveis de permissão
Como verificar permissões
README Flutter: /var/www/html/presenca-mobile/README.md
Estrutura do projeto
Comandos básicos
Links Úteis
Flutter Docs: https://docs.flutter.dev/
Dart Docs: https://dart.dev/
Provider Package: https://pub.dev/packages/provider
HTTP Package: https://pub.dev/packages/http
Secure Storage: https://pub.dev/packages/flutter_secure_storage
⚠️ Avisos Importantes
⚠️ NÃO MODIFICAR
Nunca modifique os seguintes arquivos do sistema web:
Qualquer arquivo em /api/ (exceto /api/mobile/)
Qualquer arquivo em /core/services/ (exceto TokenService.php)
Qualquer arquivo em /painel/
Sistema de sessões PHP existente
✅ SEMPRE FAZER
Novos endpoints mobile: Criar em /api/mobile/
Usar middleware: Sempre incluir MobileAuthMiddleware::requireAuth()
Padronizar respostas: Usar MobileResponse
Testar: Sempre testar API antes de integrar no app
Documentar: Documentar novos endpoints
🐛 Troubleshooting
Problema: Token inválido
Solução:
Verificar se token está sendo enviado corretamente
Verificar expiração do token (24 horas)
Tentar renovar com refresh token
Problema: CORS error
Solução:
Verificar se headers CORS estão configurados (já configurado)
Verificar se servidor está acessível
Problema: App não conecta
Solução:
Verificar URL em endpoints.dart
Verificar se servidor está online
Verificar logs do Flutter (flutter logs)
Problema: Erro 500 na API
Solução:
Verificar logs do PHP (/var/log/apache2/error.log)
Verificar se TokenService está carregando
Verificar se dependências estão instaladas
📞 Suporte
Para dúvidas ou problemas:
Consultar esta documentação
Verificar logs do sistema
Consultar documentação da API em docs/API_MOBILE.md
Última Atualização: Janeiro 2025
Versão do Documento: 1.0.0