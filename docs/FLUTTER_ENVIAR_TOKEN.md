# 🔧 Flutter: Como Enviar Token Bearer nas Requisições

## 📋 Problema Identificado

O app Flutter não está enviando o token Bearer no header `Authorization`, resultando em erro "Usuário não logado".

### Logs do Problema
```
🔴 AVISO: Nenhum token sendo enviado!
🔵 Headers: Content-Type, Accept
🟢 Response: {"status":"erro","mensagem":"Usuário não logado"}
```

---

## ✅ Solução: Adicionar Header Authorization

### 1. Obter o Token Armazenado

Primeiro, certifique-se de que o token está sendo armazenado após o login:

```dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final storage = FlutterSecureStorage();

// Após login bem-sucedido
Future<void> salvarToken(String token) async {
  await storage.write(key: 'access_token', value: token);
}

// Obter token antes de fazer requisições
Future<String?> obterToken() async {
  return await storage.read(key: 'access_token');
}
```

---

### 2. Adicionar Header Authorization nas Requisições

#### Exemplo com `http` package:

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  static const String baseUrl = 'https://presenca.aom.org.br/api';
  final FlutterSecureStorage _storage = FlutterSecureStorage();
  
  // Método auxiliar para obter headers com autenticação
  Future<Map<String, String>> _getHeaders() async {
    final token = await _storage.read(key: 'access_token');
    
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    
    // Adiciona Authorization header se token existir
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
      print('🟢 Token sendo enviado: ${token.substring(0, 20)}...');
    } else {
      print('🔴 AVISO: Nenhum token sendo enviado!');
    }
    
    return headers;
  }
  
  // Exemplo: Verificar horário
  Future<Map<String, dynamic>> verificarHorario({
    String? data,
    String tipo = 'presencial',
  }) async {
    try {
      final headers = await _getHeaders();
      
      final queryParams = {
        if (data != null) 'data': data,
        'tipo': tipo,
      };
      
      final uri = Uri.parse('$baseUrl/almoco/verificar_horario.php')
          .replace(queryParameters: queryParams);
      
      print('🔵 GET Request: $uri');
      print('🔵 Headers: ${headers.keys.join(", ")}');
      
      final response = await http.get(uri, headers: headers);
      
      print('🟢 Response Status: ${response.statusCode}');
      print('🟢 Response Body: ${response.body}');
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        
        if (data['status'] == 'erro' && 
            data['mensagem']?.contains('não logado') == true) {
          print('🔴 Erro de autenticação detectado. Limpando tokens...');
          await _storage.deleteAll();
          throw Exception('Token inválido ou expirado');
        }
        
        return data;
      } else {
        throw Exception('Erro HTTP ${response.statusCode}');
      }
    } catch (e) {
      print('🔴 Erro ao verificar horário: $e');
      rethrow;
    }
  }
  
  // Exemplo: Listar dependentes
  Future<Map<String, dynamic>> listarDependentes() async {
    try {
      final headers = await _getHeaders();
      
      final uri = Uri.parse('$baseUrl/dependentes/listar.php');
      
      print('🔵 GET Request: $uri');
      print('🔵 Headers: ${headers.keys.join(", ")}');
      
      final response = await http.get(uri, headers: headers);
      
      print('🟢 Response Status: ${response.statusCode}');
      print('🟢 Response Body: ${response.body}');
      
      if (response.statusCode == 200) {
        return json.decode(response.body);
      } else {
        throw Exception('Erro HTTP ${response.statusCode}');
      }
    } catch (e) {
      print('🔴 Erro ao listar dependentes: $e');
      rethrow;
    }
  }
}
```

---

### 3. Usar Interceptor (Recomendado)

Para evitar repetir código, use um interceptor ou cliente HTTP customizado:

```dart
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'dart:convert';

class AuthenticatedHttpClient {
  final FlutterSecureStorage _storage = FlutterSecureStorage();
  final http.Client _client = http.Client();
  
  Future<http.Response> get(Uri url, {Map<String, String>? headers}) async {
    return _request('GET', url, headers: headers);
  }
  
  Future<http.Response> post(Uri url, {Map<String, String>? headers, Object? body}) async {
    return _request('POST', url, headers: headers, body: body);
  }
  
  Future<http.Response> _request(
    String method,
    Uri url, {
    Map<String, String>? headers,
    Object? body,
  }) async {
    // Obter token
    final token = await _storage.read(key: 'access_token');
    
    // Preparar headers
    final requestHeaders = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...?headers,
    };
    
    // Adicionar Authorization se token existir
    if (token != null && token.isNotEmpty) {
      requestHeaders['Authorization'] = 'Bearer $token';
      print('🟢 Token sendo enviado: ${token.substring(0, 20)}...');
    } else {
      print('🔴 AVISO: Nenhum token sendo enviado!');
    }
    
    // Fazer requisição
    http.Response response;
    switch (method.toUpperCase()) {
      case 'GET':
        response = await _client.get(url, headers: requestHeaders);
        break;
      case 'POST':
        response = await _client.post(
          url,
          headers: requestHeaders,
          body: body is String ? body : jsonEncode(body),
        );
        break;
      default:
        throw UnsupportedError('Método HTTP não suportado: $method');
    }
    
    // Log da resposta
    print('🟢 Response Status: ${response.statusCode}');
    print('🟢 Response Body: ${response.body}');
    
    // Verificar se precisa renovar token
    if (response.statusCode == 200) {
      try {
        final data = json.decode(response.body);
        if (data['status'] == 'erro' && 
            data['mensagem']?.toString().contains('não logado') == true) {
          print('🔴 Token inválido ou expirado. Limpando...');
          await _storage.deleteAll();
        }
      } catch (e) {
        // Ignorar erros de parsing
      }
    }
    
    return response;
  }
  
  void close() {
    _client.close();
  }
}

// Uso:
final httpClient = AuthenticatedHttpClient();

Future<void> verificarHorario() async {
  final uri = Uri.parse('https://presenca.aom.org.br/api/almoco/verificar_horario.php')
      .replace(queryParameters: {'data': '2026-01-20', 'tipo': 'presencial'});
  
  final response = await httpClient.get(uri);
  final data = json.decode(response.body);
  print('Resultado: $data');
}
```

---

### 4. Usar Dio Package (Alternativa)

Se estiver usando `dio`, configure um interceptor:

```dart
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthInterceptor extends Interceptor {
  final FlutterSecureStorage _storage = FlutterSecureStorage();
  
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    // Obter token
    final token = await _storage.read(key: 'access_token');
    
    // Adicionar Authorization header
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
      print('🟢 Token sendo enviado: ${token.substring(0, 20)}...');
    } else {
      print('🔴 AVISO: Nenhum token sendo enviado!');
    }
    
    super.onRequest(options, handler);
  }
  
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 401) {
      print('🔴 Token inválido ou expirado');
      _storage.deleteAll();
    }
    super.onError(err, handler);
  }
}

// Configurar Dio
final dio = Dio(BaseOptions(
  baseUrl: 'https://presenca.aom.org.br/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
));

dio.interceptors.add(AuthInterceptor());

// Uso:
Future<void> verificarHorario() async {
  final response = await dio.get(
    '/almoco/verificar_horario.php',
    queryParameters: {'data': '2026-01-20', 'tipo': 'presencial'},
  );
  print('Resultado: ${response.data}');
}
```

---

## 🔍 Debug: Verificar se Token Está Sendo Enviado

Adicione logs detalhados para verificar:

```dart
Future<Map<String, String>> _getHeaders() async {
  final token = await _storage.read(key: 'access_token');
  
  print('🔍 DEBUG: Token obtido do storage: ${token != null ? "EXISTE (${token.length} chars)" : "NÃO EXISTE"}');
  
  final headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  
  if (token != null && token.isNotEmpty) {
    headers['Authorization'] = 'Bearer $token';
    print('✅ Header Authorization adicionado: Bearer ${token.substring(0, 20)}...');
  } else {
    print('❌ ERRO: Token não encontrado! Verifique se o login foi feito corretamente.');
  }
  
  print('📋 Headers finais: ${headers.keys.join(", ")}');
  
  return headers;
}
```

---

## ✅ Checklist de Verificação

- [ ] Token está sendo salvo após login (`flutter_secure_storage`)
- [ ] Token está sendo recuperado antes de cada requisição
- [ ] Header `Authorization: Bearer <token>` está sendo adicionado
- [ ] Logs mostram que o token está sendo enviado
- [ ] Backend está recebendo o header (verificar logs do servidor)

---

## 🧪 Teste Manual

Teste com `curl` para verificar se o backend está funcionando:

```bash
# 1. Fazer login e obter token
curl -X POST https://presenca.aom.org.br/api/mobile/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","senha":"suasenha"}'

# 2. Usar o token recebido
curl -X GET "https://presenca.aom.org.br/api/almoco/verificar_horario.php?data=2026-01-20&tipo=presencial" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

Se funcionar com `curl`, o problema está no app Flutter não enviando o header.

---

## 📝 Formato Correto do Header

O header **DEVE** estar no formato exato:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**IMPORTANTE:**
- ✅ `Authorization` (com A maiúsculo)
- ✅ `Bearer` seguido de um espaço
- ✅ Token completo após o espaço
- ❌ Não usar aspas ao redor do token
- ❌ Não usar `token` em vez de `Bearer`

---

**Data:** 2026-01-XX  
**Status:** ✅ Documentação Completa  
**Versão:** 1.0
