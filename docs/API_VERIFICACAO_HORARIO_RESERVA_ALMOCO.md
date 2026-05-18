# API: Verificação de Horário para Reserva de Almoço

## 📋 Resumo para Outra Sessão do Cursor

### Objetivo
Documentação completa das APIs que verificam se está no horário permitido para reserva de almoço, permitindo que o frontend (Flutter/Web) exiba o texto correto no botão "Reservar meu almoço".

### APIs Principais
1. **`api/almoco/status_reserva.php`** - Verifica status da reserva e horário (usado para atualizar botão)
2. **`api/almoco/verificar_horario.php`** - Verifica horário completo com valores (usado antes de reservar)

---

## 🔍 API 1: Status da Reserva (`status_reserva.php`)

### Endpoint
```
GET /api/almoco/status_reserva.php
```

### Autenticação
- **Web:** Requer sessão PHP ativa (`$_SESSION['usuario_id']`)
- **Mobile:** Não suporta autenticação mobile (apenas web)

### Parâmetros
Nenhum parâmetro necessário. Usa automaticamente:
- `id_usuario` da sessão
- `data` = data atual (`date('Y-m-d')`)
- `hora_atual` = hora atual (`date('H:i')`)

### Resposta de Sucesso

```json
{
    "reservou_hoje": false,
    "hora_excedida": false,
    "hora_atual": "08:30",
    "hora_limite": "09:00"
}
```

### Campos da Resposta

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `reservou_hoje` | `boolean` | `true` se o usuário já tem reserva para hoje, `false` caso contrário |
| `hora_excedida` | `boolean` | `true` se a hora atual passou do horário limite, `false` caso contrário |
| `hora_atual` | `string` | Hora atual no formato `HH:mm` (ex: "08:30") |
| `hora_limite` | `string` | Horário limite configurado no sistema (padrão: "09:00") |

### Resposta de Erro

```json
{
    "erro": "Usuário não logado"
}
```

### Lógica de Horário

- **Horário limite:** Configurado na tabela `configuracoes` com chave `hora_limite` (padrão: `'09:00'`)
- **Verificação:** Compara `hora_atual > hora_limite`
- **Aplicação:** Apenas informativo, **não bloqueia** a reserva (comportamento legado)

### Uso no Frontend

```javascript
// Exemplo de uso no JavaScript
function verificarStatusReserva() {
    $.ajax({
        url: '../api/almoco/status_reserva.php',
        method: 'GET',
        dataType: 'json',
        success: function (resposta) {
            const botao = $('#btnReservaPropria');
            
            if (resposta.reservou_hoje) {
                // Já reservou - botão vermelho para cancelar
                botao.html('Cancelar Reserva')
                      .removeClass('btn-success')
                      .addClass('btn-danger');
            } else {
                // Sem reserva - botão verde para reservar
                let textoBotao = 'Reservar meu almoço';
                
                if (resposta.hora_excedida) {
                    // Fora do horário - adicionar indicador
                    textoBotao = 'Reservar meu almoço (Fora do horário)';
                }
                
                botao.html(textoBotao)
                      .removeClass('btn-danger')
                      .addClass('btn-success');
            }
        }
    });
}
```

### Exemplo Flutter/Dart

```dart
Future<Map<String, dynamic>> verificarStatusReserva() async {
  final response = await http.get(
    Uri.parse('$baseUrl/api/almoco/status_reserva.php'),
    headers: {
      'Cookie': 'PHPSESSID=$sessionId', // Se usar sessão PHP
      // OU usar autenticação Bearer Token se implementado
    },
  );
  
  if (response.statusCode == 200) {
    return json.decode(response.body);
  } else {
    throw Exception('Erro ao verificar status');
  }
}

// Uso no widget
void atualizarBotaoReserva() async {
  final status = await verificarStatusReserva();
  
  if (status['reservou_hoje'] == true) {
    // Botão: "Cancelar Reserva" (vermelho)
    setState(() {
      textoBotao = 'Cancelar Reserva';
      corBotao = Colors.red;
      podeReservar = false;
    });
  } else {
    // Botão: "Reservar meu almoço"
    String texto = 'Reservar meu almoço';
    
    if (status['hora_excedida'] == true) {
      // Adicionar indicador de fora do horário
      texto = 'Reservar meu almoço (Fora do horário)';
    }
    
    setState(() {
      textoBotao = texto;
      corBotao = Colors.green;
      podeReservar = true;
    });
  }
}
```

---

## 🔍 API 2: Verificar Horário Completo (`verificar_horario.php`)

### Endpoint
```
GET /api/almoco/verificar_horario.php
```

### Autenticação
- **Web:** Requer sessão PHP ativa (`$_SESSION['usuario_id']`)
- **Mobile:** Suporta autenticação Bearer Token via `MobileAuthMiddleware`

### Parâmetros Query String

| Parâmetro | Tipo | Obrigatório | Padrão | Descrição |
|-----------|------|-------------|--------|-----------|
| `data` | `string` | Não | Data atual | Data da reserva no formato `YYYY-MM-DD` |
| `tipo` | `string` | Não | `'presencial'` | Tipo de refeição: `'presencial'` ou `'marmitex'` |

### Exemplo de Requisição

```
GET /api/almoco/verificar_horario.php?data=2026-01-15&tipo=presencial
```

### Resposta de Sucesso

```json
{
    "status": "sucesso",
    "mensagem": "Horário disponível para reserva",
    "data": "2026-01-15",
    "tipo": "presencial",
    "fora_do_horario": false,
    "hora_atual": "08:30",
    "horario_limite": "09:00",
    "valor_normal": 15.00,
    "valor_fora_horario": 30.00
}
```

### Campos da Resposta

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `status` | `string` | `"sucesso"` ou `"erro"` |
| `mensagem` | `string` | Mensagem descritiva do resultado |
| `data` | `string` | Data verificada (formato `YYYY-MM-DD`) |
| `tipo` | `string` | Tipo de refeição (`'presencial'` ou `'marmitex'`) |
| `fora_do_horario` | `boolean` | `true` se está fora do horário limite, `false` caso contrário |
| `hora_atual` | `string` | Hora atual no formato `HH:mm` |
| `horario_limite` | `string` | Horário limite configurado (padrão: `"09:00"`) |
| `valor_normal` | `float` | Valor da refeição dentro do horário |
| `valor_fora_horario` | `float` | Valor da refeição fora do horário |

### Respostas de Erro

#### Erro: Data Inválida
```json
{
    "status": "erro",
    "mensagem": "Data inválida"
}
```

#### Erro: Data no Passado
```json
{
    "status": "erro",
    "mensagem": "Não é possível agendar para datas passadas"
}
```

#### Erro: Data Muito no Futuro
```json
{
    "status": "erro",
    "mensagem": "Não é possível agendar com mais de 30 dias de antecedência"
}
```

#### Erro: Já Possui Reserva
```json
{
    "status": "erro",
    "mensagem": "Você já possui uma reserva para esta data"
}
```

#### Erro: Usuário Não Autenticado
```json
{
    "status": "erro",
    "mensagem": "Usuário não logado"
}
```

### Validações Realizadas

1. ✅ **Formato de data:** Deve ser `YYYY-MM-DD`
2. ✅ **Data no passado:** Não permite datas anteriores à hoje (exceto hoje)
3. ✅ **Limite futuro:** Máximo de 30 dias no futuro
4. ✅ **Reserva duplicada:** Verifica se já existe reserva para a data
5. ✅ **Horário limite:** Verifica se está fora do horário (apenas para data = hoje)

### Lógica de Horário

- **Aplicação:** Apenas quando `data === hoje`
- **Comparação:** `hora_atual > horario_limite`
- **Configuração:** Busca `hora_limite` na tabela `configuracoes` (padrão: `'09:00'`)
- **Efeito:** Não bloqueia a reserva, apenas indica que será cobrado valor diferente

### Cálculo de Valores

- **Valor Normal:** Baseado no grupo de valor do usuário (`grupo_valor`) ou configuração padrão
- **Valor Fora do Horário:** Configurado em `configuracoes` com chave `valor_fora_horario` (padrão: `'30.00'`)

### Uso no Frontend

```javascript
// Exemplo de uso antes de fazer reserva
function verificarHorarioAntesReservar(data, tipo) {
    $.ajax({
        url: '../api/almoco/verificar_horario.php',
        method: 'GET',
        data: {
            data: data,
            tipo: tipo || 'presencial'
        },
        dataType: 'json',
        success: function (resposta) {
            if (resposta.status === 'erro') {
                exibirToast(resposta.mensagem, 'danger');
                return;
            }
            
            if (resposta.fora_do_horario) {
                // Mostrar modal de confirmação com valor diferente
                mostrarModalConfirmacaoForaHorario({
                    horaAtual: resposta.hora_atual,
                    horarioLimite: resposta.horario_limite,
                    valorForaHorario: resposta.valor_fora_horario
                });
            } else {
                // Reserva normal dentro do horário
                executarReserva(resposta.valor_normal);
            }
        }
    });
}
```

### Exemplo Flutter/Dart

```dart
Future<Map<String, dynamic>> verificarHorario({
  String? data,
  String tipo = 'presencial',
}) async {
  final queryParams = {
    if (data != null) 'data': data,
    'tipo': tipo,
  };
  
  final uri = Uri.parse('$baseUrl/api/almoco/verificar_horario.php')
      .replace(queryParameters: queryParams);
  
  final response = await http.get(
    uri,
    headers: {
      'Authorization': 'Bearer $token', // Se usar Bearer Token
      // OU 'Cookie': 'PHPSESSID=$sessionId', // Se usar sessão PHP
    },
  );
  
  if (response.statusCode == 200) {
    return json.decode(response.body);
  } else {
    throw Exception('Erro ao verificar horário');
  }
}

// Uso no widget
void verificarAntesReservar() async {
  final resultado = await verificarHorario(
    data: DateTime.now().toIso8601String().split('T')[0],
    tipo: 'presencial',
  );
  
  if (resultado['status'] == 'erro') {
    // Mostrar erro
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(resultado['mensagem'])),
    );
    return;
  }
  
  if (resultado['fora_do_horario'] == true) {
    // Mostrar diálogo de confirmação com valor diferente
    mostrarDialogoConfirmacaoForaHorario(
      horaAtual: resultado['hora_atual'],
      horarioLimite: resultado['horario_limite'],
      valorForaHorario: resultado['valor_fora_horario'],
    );
  } else {
    // Executar reserva normal
    executarReserva(valor: resultado['valor_normal']);
  }
}
```

---

## 📊 Tabela Comparativa das APIs

| Característica | `status_reserva.php` | `verificar_horario.php` |
|----------------|---------------------|------------------------|
| **Uso Principal** | Atualizar texto do botão | Verificar antes de reservar |
| **Autenticação Mobile** | ❌ Não suporta | ✅ Suporta Bearer Token |
| **Parâmetros** | Nenhum | `data`, `tipo` |
| **Retorna Valores** | ❌ Não | ✅ Sim |
| **Retorna Status Reserva** | ✅ Sim | ❌ Não |
| **Validações Completas** | ❌ Não | ✅ Sim |
| **Quando Usar** | Polling periódico | Antes de criar reserva |

---

## 🎯 Fluxo Recomendado para Frontend

### 1. Inicialização da Tela
```javascript
// Ao carregar a tela, verificar status atual
verificarStatusReserva(); // Usa status_reserva.php
```

### 2. Atualização Periódica (Opcional)
```javascript
// Atualizar status a cada 30 segundos
setInterval(function() {
    verificarStatusReserva(); // Usa status_reserva.php
}, 30000);
```

### 3. Antes de Criar Reserva
```javascript
// Quando usuário clicar em "Reservar meu almoço"
function aoClicarReservar() {
    verificarHorarioAntesReservar(data, tipo); // Usa verificar_horario.php
}
```

### 4. Lógica de Exibição do Botão

```javascript
function atualizarTextoBotao(status) {
    if (status.reservou_hoje) {
        // Já reservou
        return {
            texto: 'Cancelar Reserva',
            cor: 'danger',
            habilitado: true
        };
    } else {
        // Não reservou ainda
        let texto = 'Reservar meu almoço';
        
        if (status.hora_excedida) {
            // Fora do horário - adicionar indicador
            texto = 'Reservar meu almoço (Fora do horário)';
        }
        
        return {
            texto: texto,
            cor: 'success',
            habilitado: true
        };
    }
}
```

---

## ⚙️ Configurações do Sistema

### Tabela: `configuracoes`

| Chave | Descrição | Padrão | Tipo |
|-------|-----------|--------|------|
| `hora_limite` | Horário limite para reservas do dia | `'09:00'` | `string` (HH:mm) |
| `valor_fora_horario` | Valor cobrado fora do horário | `'30.00'` | `string` (decimal) |
| `valor_refeicao` | Valor padrão de refeição | `'0.00'` | `string` (decimal) |
| `valor_marmitex` | Valor padrão de marmitex | `'0.00'` | `string` (decimal) |

### Exemplo de Consulta SQL

```sql
-- Ver configurações atuais
SELECT chave, valor FROM configuracoes 
WHERE chave IN ('hora_limite', 'valor_fora_horario', 'valor_refeicao', 'valor_marmitex');

-- Atualizar horário limite
UPDATE configuracoes 
SET valor = '09:30' 
WHERE chave = 'hora_limite';

-- Atualizar valor fora do horário
UPDATE configuracoes 
SET valor = '35.00' 
WHERE chave = 'valor_fora_horario';
```

---

## 🧪 Casos de Teste

### Caso 1: Dentro do Horário
- **Hora atual:** `08:30`
- **Horário limite:** `09:00`
- **Resultado esperado:**
  - `status_reserva.php`: `hora_excedida: false`
  - `verificar_horario.php`: `fora_do_horario: false`
  - **Botão:** "Reservar meu almoço" (verde)

### Caso 2: Fora do Horário
- **Hora atual:** `09:15`
- **Horário limite:** `09:00`
- **Resultado esperado:**
  - `status_reserva.php`: `hora_excedida: true`
  - `verificar_horario.php`: `fora_do_horario: true`
  - **Botão:** "Reservar meu almoço (Fora do horário)" (verde)

### Caso 3: Já Reservou Hoje
- **Reserva existente:** Sim
- **Resultado esperado:**
  - `status_reserva.php`: `reservou_hoje: true`
  - **Botão:** "Cancelar Reserva" (vermelho)

### Caso 4: Data Futura
- **Data:** `2026-01-20` (5 dias no futuro)
- **Hora atual:** `10:00`
- **Resultado esperado:**
  - `verificar_horario.php`: `fora_do_horario: false` (não aplica limite para datas futuras)

---

## 📝 Notas Importantes

1. **Horário Limite:** Apenas aplicado quando `data === hoje`. Para datas futuras, sempre retorna `fora_do_horario: false`.

2. **Não Bloqueia Reserva:** O sistema **permite** reservas fora do horário, apenas cobra valor diferente.

3. **Valor Dinâmico:** O valor normal é calculado com base no grupo de valor do usuário (`grupo_valor`), não apenas na configuração padrão.

4. **Autenticação:** `status_reserva.php` não suporta autenticação mobile (Bearer Token). Use `verificar_horario.php` se precisar de suporte mobile.

5. **Timezone:** O sistema usa o timezone do servidor PHP. Certifique-se de que está configurado corretamente.

---

## 🔗 Arquivos Relacionados

- `api/almoco/status_reserva.php` - API de status da reserva
- `api/almoco/verificar_horario.php` - API de verificação de horário
- `api/almoco/verificar_horario_adicional.php` - API para reservas adicionais (dependentes)
- `js/almoco.js` - JavaScript que usa essas APIs
- `utils/config.php` - Função `get_config()` usada para buscar configurações

---

**Data:** 2026-01-XX  
**Status:** ✅ Documentação Completa  
**Versão:** 1.0
