# Sincronização Facial para Reservas de Almoço

Este documento descreve o sistema de sincronização entre as reservas de almoço e o dispositivo de reconhecimento facial Intelbras SS3542.

## Visão Geral

O sistema integra o controle de reservas de almoço com o dispositivo de reconhecimento facial, permitindo:

1. Sincronização automática dos usuários que fizeram reservas para o dia atual
2. Visualização de estatísticas de sincronização
3. Gerenciamento manual de sincronizações pendentes
4. Monitoramento de check-ins realizados

## Arquitetura

O sistema consiste nos seguintes componentes:

- **Interface de administração** (`painel/facial.php`) - Painel para gerenciar e visualizar o processo de sincronização
- **API de sincronização** (`api/facial/`) - Endpoints para verificar, preparar e executar a sincronização
- **Script de CRON** (`cron/sincronizar_facial.php`) - Automatiza a sincronização diária
- **Utilitários** (`utils/sync_facial.php`) - Funções auxiliares para comunicação com o dispositivo

## Implementação

### 1. Configuração do Banco de Dados

O sistema utiliza as seguintes tabelas:

- `reservas_almoco` - Armazena as reservas de almoço dos usuários
- `facial_sync` - Registra o status de sincronização de cada usuário
- `configuracoes` - Armazena configurações do sistema

Para configurar o dispositivo, adicione as seguintes entradas na tabela `configuracoes`:

```sql
INSERT INTO configuracoes (chave, valor, descricao) VALUES 
('ip_dispositivo_facial', '10.144.129.69', 'Endereço IP do dispositivo facial SS3542'),
('porta_dispositivo_facial', '80', 'Porta do dispositivo facial'),
('usuario_dispositivo_facial', 'admin', 'Usuário de acesso ao dispositivo'),
('senha_dispositivo_facial', 'Arcs2901', 'Senha de acesso ao dispositivo');
```

### 2. Configuração do CRON

Para automatizar a sincronização diária, configure uma tarefa cron para executar o script `cron/sincronizar_facial.php`. Exemplo:

```
# Executar sincronização diária às 05:00
0 5 * * * php /caminho/completo/para/cron/sincronizar_facial.php >> /caminho/para/logs/cron_facial.log 2>&1
```

Isso irá:
1. Verificar todas as reservas para o dia atual
2. Preparar a lista de usuários para sincronização
3. Executar a sincronização com o dispositivo facial

### 3. Acesso ao Painel de Administração

O painel de administração está disponível em `painel/facial.php` e requer permissões de administrador.

Neste painel, você pode:

- Visualizar estatísticas de sincronização
- Executar sincronização manual
- Verificar status de usuários sincronizados
- Monitorar check-ins realizados

## Fluxo de Operação

### Sincronização Automática (via CRON)

1. A tarefa cron executa automaticamente às 05:00
2. O sistema identifica usuários com reservas para o dia atual
3. Registros são criados na tabela `facial_sync` com status "pendente"
4. O sistema processa as sincronizações pendentes:
   - Envia dados do usuário para o dispositivo
   - Envia foto do usuário quando disponível
   - Atualiza o status na tabela `facial_sync`

### Sincronização Manual (via Interface)

1. Acesse `painel/facial.php`
2. Use o botão "Verificar e Preparar" para identificar novas reservas
3. Use o botão "Sincronizar Agora" para iniciar o processo de sincronização
4. Monitore o progresso em tempo real na interface

### Sincronização Individual

Para sincronizar um usuário específico:

1. Localize o usuário na tabela de sincronizações
2. Clique no botão "Sincronizar" ao lado do nome do usuário
3. O sistema tentará sincronizar apenas esse usuário

## Solucionando Problemas

### Logs

Os logs do sistema são armazenados no diretório `logs/`:

- `sincronizacao_YYYY-MM-DD.log` - Log do processo de sincronização
- `verificar_preparar_YYYY-MM-DD.log` - Log do processo de verificação
- `php_errors.log` - Erros PHP gerais
- `cron_facial.log` - Log da execução do cron

### Problemas Comuns

1. **Dispositivo inacessível**
   - Verifique a conectividade de rede
   - Confirme se o IP, porta, usuário e senha estão corretos

2. **Erro na sincronização de fotos**
   - Verifique se as fotos estão no formato correto
   - Confirme se o tamanho da foto não excede o limite

3. **Falha na autenticação**
   - Verifique as credenciais do dispositivo
   - Confirme se as configurações estão atualizadas

## Referências

- [Documentação do SS3542](http://www.intelbras.com/pt-br/controle-de-acesso/ss3542)
- [API do dispositivo facial](http://IP_DO_DISPOSITIVO/doc/index.html) 