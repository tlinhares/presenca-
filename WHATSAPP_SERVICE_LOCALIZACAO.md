# 📍 Localização do Serviço Centralizado de WhatsApp

## ⚠️ IMPORTANTE: Script Centralizado

**TODAS as alterações relacionadas ao envio de WhatsApp devem ser feitas APENAS no arquivo:**

```
/var/www/html/presenca/core/services/WhatsAppService.php
```

## 📂 Estrutura

```
/presenca/
└── core/
    └── services/
        └── WhatsAppService.php  ← ⭐ ÚNICO ARQUIVO PARA ALTERAÇÕES
```

## 🔧 Métodos Disponíveis

### 1. `normalizarTelefone($telefone)`
Normaliza número de telefone brasileiro:
- Remove caracteres não numéricos
- Adiciona código do país (55) se necessário
- Remove nono dígito se presente
- Retorna número no formato: `55XXXXXXXXXXX` (12 dígitos)

**Exemplo:**
```php
$normalizado = WhatsAppService::normalizarTelefone('(65) 99979-3296');
// Retorna: '556599793296'
```

### 2. `enviarMensagem($telefone, $mensagem, $opcoes = [])`
Envia mensagem de texto via WhatsApp.

**Parâmetros:**
- `$telefone`: Número de telefone (será normalizado automaticamente)
- `$mensagem`: Texto da mensagem
- `$opcoes`: Array opcional com:
  - `log_callback`: Função para logging customizado
  - `retornar_detalhes`: Se `true`, retorna array com detalhes da resposta

**Retorno:**
```php
['sucesso' => bool, 'mensagem' => string]
```

**Exemplo:**
```php
$resultado = WhatsAppService::enviarMensagem('6599793296', 'Olá!', [
    'log_callback' => function($msg) {
        error_log("WhatsApp: $msg");
    }
]);
```

### 3. `enviarArquivo($telefone, $caminho_arquivo, $caption = null, $opcoes = [])`
Envia arquivo via WhatsApp (base64).

**Parâmetros:**
- `$telefone`: Número de telefone
- `$caminho_arquivo`: Caminho completo do arquivo
- `$caption`: Legenda do arquivo (opcional)
- `$opcoes`: Array opcional com `log_callback`

**Exemplo:**
```php
$resultado = WhatsAppService::enviarArquivo(
    '6599793296', 
    '/tmp/relatorio.pdf',
    '📊 Relatório PDF - ' . date('d/m/Y')
);
```

### 4. `enviarMensagemEArquivo($telefone, $mensagem, $caminho_arquivo = null, $opcoes = [])`
Envia mensagem e arquivo juntos (método combinado).

**Exemplo:**
```php
$resultado = WhatsAppService::enviarMensagemEArquivo(
    '6599793296',
    'Segue o relatório em anexo',
    '/tmp/relatorio.pdf'
);
```

## 🔑 Configurações da API

As configurações estão definidas como constantes na classe:

```php
private const API_URL_MESSAGE = 'http://10.144.128.34:21465/api/servidor/send-message';
private const API_URL_FILE = 'http://10.144.128.34:21465/api/servidor/send-file';
private const API_TOKEN = 'Bearer $2b$10$HXuccMTGKs8y7aZuhrrxdOfPBw3DAFheEg6.pdZBBn6_7nPS4XLG2';
```

**Para alterar a URL da API ou o token, edite apenas o arquivo `WhatsAppService.php`.**

## 📝 Arquivos Migrados

Todos os seguintes arquivos foram migrados para usar o `WhatsAppService`:

✅ `api/notificacao/testar.php`
✅ `api/automacao/testar.php`
✅ `cron/notificacao_reserva.php`
✅ `cron/notificacao_diaria.php`
✅ `api/notificacao/enviar_notificacao_reserva.php`
✅ `api/notificacao/notificar_usuario.php`
✅ `executar_automacoes.php`
✅ `executar_automacoes_cron.php`

## 🧪 Scripts de Teste

- `teste_whatsapp_service.php` - Teste de mensagem
- `teste_whatsapp_arquivo.php` - Teste de arquivo
- `teste_whatsapp_service_ajax.php` - Endpoint AJAX para testes

## ⚠️ IMPORTANTE

**NUNCA altere as funções de envio de WhatsApp diretamente nos arquivos migrados!**

Todas as alterações devem ser feitas **APENAS** em:
```
/var/www/html/presenca/core/services/WhatsAppService.php
```

Qualquer alteração feita diretamente nos arquivos migrados será perdida ou causará inconsistências no sistema.

## 📞 Suporte

Em caso de dúvidas ou problemas, verifique:
1. O arquivo `WhatsAppService.php` está correto?
2. Os logs do sistema (`/var/www/html/presenca/logs/`)
3. Os logs do Apache (`/var/log/apache2/error.log`)

