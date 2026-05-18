# Correção: Gravação de Notificações no Banco de Dados

## Problema Identificado
Várias notificações não estavam sendo salvas na tabela `notificacoes_enviadas` ao serem enviadas, dificultando o rastreamento e gestão do histórico de notificações.

## Estratégia de Correção
Todas as notificações (WhatsApp e Email) devem ser gravadas automaticamente na tabela `notificacoes_enviadas` usando o `NotificacaoService`, seguindo o padrão já estabelecido no sistema.

## Arquivos Corrigidos

### 1. `api/notificacao/testar.php`
**Problema:** Enviava WhatsApp e Email sem gravar no histórico.

**Correção:**
- Adicionado `require_once` para `NotificacaoService`
- Adicionado opções `usuario_id`, `nome_destinatario`, `tipo_mensagem` e `tipo_notificacao` ao chamar `WhatsAppService::enviarMensagem()`
- Adicionada chamada a `NotificacaoService::gravarEmail()` após envio de email
- Passando conexão via `$GLOBALS['db_conn']` para os serviços

**Tipo de mensagem:** `teste_notificacao`

---

### 2. `api/automacao/testar.php`
**Problema:** Enviava WhatsApp via `enviarMensagemEArquivo()` sem passar opções para gravar.

**Correção:**
- Adicionado opções `tipo_mensagem`, `tipo_notificacao` e `nome_destinatario` ao chamar `WhatsAppService::enviarMensagemEArquivo()`

**Tipo de mensagem:** `automacao_relatorio`

---

### 3. `api/auth/recuperar_senha.php`
**Problema:** Enviava email de recuperação de senha sem gravar no histórico.

**Correção:**
- Adicionado `require_once` para `NotificacaoService`
- Adicionada chamada a `NotificacaoService::gravarEmail()` após envio bem-sucedido
- Adicionada chamada a `NotificacaoService::gravarEmail()` em caso de falha (com status 'falha')

**Tipo de mensagem:** `recuperacao_senha`

---

### 4. `api/notificacao/notificar_email.php`
**Problema:** Enviava emails em lote sem gravar no histórico.

**Correção:**
- Adicionado `require_once` para `NotificacaoService`
- Adicionada chamada a `NotificacaoService::gravarEmail()` após cada envio (sucesso ou falha)
- Passando conexão via `$GLOBALS['db_conn']` para os serviços

**Tipo de mensagem:** `cadastro_usuario`

---

### 5. `core/services/WhatsAppService.php`
**Problema:** Método `enviarMensagensEmLote()` não passava todas as opções necessárias para gravar.

**Correção:**
- Adicionado opções `usuario_id`, `nome_destinatario` e `tipo_mensagem` ao chamar `enviarMensagem()` dentro do método `enviarMensagensEmLote()`

---

## Arquivos que JÁ Estavam Corretos

Os seguintes arquivos já estavam gravando notificações corretamente:

1. ✅ `api/notificacao/enviar_notificacao_reserva.php` - Grava WhatsApp e Email
2. ✅ `api/notificacao/enviar_notificacao_justificativa.php` - Grava WhatsApp e Email
3. ✅ `api/notificacao/notificar_usuario.php` - Grava WhatsApp e Email
4. ✅ `cron/notificacao_reserva.php` - Grava Email de lembrete diário
5. ✅ `core/services/WhatsAppService.php` - Grava automaticamente ao enviar mensagens e arquivos (métodos `enviarMensagem()` e `enviarArquivo()`)

---

## Padrão de Gravação

### Para WhatsApp:
O `WhatsAppService` grava automaticamente quando:
- `enviarMensagem()` é chamado (linha 829)
- `enviarArquivo()` é chamado (linha 1048)

**Opções necessárias:**
```php
WhatsAppService::enviarMensagem($telefone, $mensagem, [
    'usuario_id' => $usuario_id,
    'nome_destinatario' => $nome,
    'tipo_mensagem' => 'tipo_da_mensagem',
    'tipo_notificacao' => 'tipo_da_notificacao'
]);
```

### Para Email:
Deve chamar manualmente `NotificacaoService::gravarEmail()` após o envio:

```php
try {
    $mail->send();
    
    // Gravar sucesso
    NotificacaoService::gravarEmail(
        $email,
        $assunto,
        $mensagem_texto,
        true, // sucesso
        null, // erro
        $usuario_id,
        $nome,
        'tipo_mensagem'
    );
} catch (Exception $e) {
    // Gravar falha
    NotificacaoService::gravarEmail(
        $email,
        $assunto,
        $mensagem_texto,
        false, // falha
        $e->getMessage(), // erro
        $usuario_id,
        $nome,
        'tipo_mensagem'
    );
}
```

---

## Tipos de Mensagem Utilizados

| Tipo | Descrição | Onde é usado |
|------|-----------|--------------|
| `teste_notificacao` | Teste de notificação | `api/notificacao/testar.php` |
| `automacao_relatorio` | Automação de relatórios | `api/automacao/testar.php` |
| `recuperacao_senha` | Recuperação de senha | `api/auth/recuperar_senha.php` |
| `cadastro_usuario` | Cadastro de usuário | `api/notificacao/notificar_email.php`, `api/notificacao/notificar_usuario.php` |
| `propria` | Reserva própria | `api/notificacao/enviar_notificacao_reserva.php` |
| `adicional` | Reserva adicional | `api/notificacao/enviar_notificacao_reserva.php` |
| `multipla` | Reservas múltiplas | `api/notificacao/enviar_notificacao_reserva.php` |
| `cancelada` | Reserva cancelada | `api/notificacao/enviar_notificacao_reserva.php` |
| `justificativa_culto` | Justificativa de culto | `api/notificacao/enviar_notificacao_justificativa.php` |
| `lembrete_reserva` | Lembrete diário de reserva | `cron/notificacao_reserva.php` |

---

## Verificação

Para verificar se todas as notificações estão sendo gravadas:

1. Acesse `painel/gerenciar_notificacoes_enviadas.php`
2. Verifique se aparecem notificações de todos os tipos acima
3. Verifique se o status está sendo gravado corretamente (sucesso/falha)

---

## Observações Importantes

1. **WhatsAppService grava automaticamente:** Quando `enviarMensagem()` ou `enviarArquivo()` são chamados, eles já gravam automaticamente no histórico. Não é necessário chamar `NotificacaoService` manualmente para WhatsApp.

2. **Email precisa gravar manualmente:** Emails enviados diretamente via PHPMailer precisam chamar `NotificacaoService::gravarEmail()` manualmente.

3. **Conexão compartilhada:** Os serviços compartilham a conexão via `$GLOBALS['db_conn']` para evitar múltiplas conexões.

4. **Não quebra o sistema:** Se a gravação falhar, apenas registra um erro no log, mas não impede o envio da notificação.

---

**Data da Correção:** Janeiro 2025
**Status:** ✅ Todas as correções aplicadas
