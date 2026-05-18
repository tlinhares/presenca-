# Sistema de Sincronização Facial Automática

## 📋 Visão Geral

O sistema de sincronização facial automática foi implementado para gerenciar automaticamente a sincronização de usuários com dispositivos faciais de culto. O sistema funciona de forma completamente automática, processando usuários cadastrados, ativados ou inativados em tempo real.

## 🔄 Como Funciona

### 1. **Triggers Automáticos**
- **Cadastro de usuário**: Quando um usuário é cadastrado e ativado, é automaticamente inserido na tabela `facial_sync_culto` com status `pendente`
- **Ativação de usuário**: Quando um usuário inativo é reativado, é inserido na sincronização
- **Inativação de usuário**: Quando um usuário é inativado, é marcado para remoção dos dispositivos

### 2. **Processamento Automático**
- **Cron Job**: Executa a cada 5 minutos para processar registros pendentes
- **Sincronização**: Envia dados do usuário (nome, foto) para dispositivos faciais
- **Compressão**: Comprime fotos automaticamente se necessário
- **Logs**: Registra todas as operações para monitoramento

### 3. **Monitoramento**
- **Painel Administrativo**: Interface web para visualizar status e logs
- **Logs em Tempo Real**: Acompanhamento das operações
- **Estatísticas**: Contadores de sincronizados, pendentes e falhas

## 🛠️ Arquivos Implementados

### **Cron Jobs**
- `cron/sincronizacao_facial_automatica.php` - Script principal do cron
- `configurar_cron_automatico.php` - Configurador automático do cron

### **APIs**
- `api/culto/processar_sincronizacao_automatica.php` - Processamento de sincronizações
- `api/culto/estatisticas_sincronizacao.php` - Estatísticas do sistema
- `api/culto/status_sincronizacao.php` - Status detalhado
- `api/culto/historico_logs.php` - Histórico de logs
- `api/culto/logs_tempo_real.php` - Logs em tempo real

### **Interface Administrativa**
- `painel/sincronizacao_facial.php` - Painel de monitoramento
- Link adicionado em `painel/index.php`

## 📊 Painel Administrativo

### **Funcionalidades**
- **Estatísticas em Tempo Real**: Usuários sincronizados, pendentes, falhas
- **Logs em Tempo Real**: Acompanhamento das operações
- **Status de Sincronização**: Lista detalhada de usuários e dispositivos
- **Histórico de Logs**: Logs históricos do sistema
- **Execução Manual**: Botão para executar sincronização imediatamente
- **Auto-refresh**: Atualização automática a cada 30 segundos

### **Acesso**
1. Faça login como administrador
2. Acesse o Painel Administrativo
3. Clique em "Sincronização Facial"

## ⚙️ Configuração do Cron

### **Configuração Automática**
```bash
php configurar_cron_automatico.php
```

### **Configuração Manual**
```bash
crontab -e
```

Adicione a linha:
```
*/5 * * * * /usr/bin/php /var/www/html/presenca/cron/sincronizacao_facial_automatica.php >> /var/log/sincronizacao_facial.log 2>&1
```

### **Verificação**
```bash
# Verificar se o cron está ativo
crontab -l

# Verificar logs do cron
tail -f /var/log/sincronizacao_facial.log

# Verificar logs do sistema
tail -f /var/www/html/presenca/logs/cron_sincronizacao_$(date +%Y-%m-%d).log
```

## 📝 Logs e Monitoramento

### **Tipos de Logs**
- **Cron**: Logs de execução do cron job
- **Processamento**: Logs de processamento de sincronizações
- **Sistema**: Logs salvos no banco de dados

### **Localização dos Logs**
- `/var/log/sincronizacao_facial.log` - Log principal do cron
- `/var/www/html/presenca/logs/cron_sincronizacao_YYYY-MM-DD.log` - Logs diários
- `/var/www/html/presenca/logs/processamento_automatico_YYYY-MM-DD.log` - Logs de processamento
- Tabela `logs_sistema` no banco de dados

### **Monitoramento**
- **Painel Web**: Interface gráfica para monitoramento
- **Logs em Tempo Real**: Atualização automática
- **Alertas**: Indicadores visuais de status

## 🔧 Manutenção

### **Teste Manual**
```bash
# Testar processamento
php /var/www/html/presenca/api/culto/processar_sincronizacao_automatica.php

# Testar cron
php /var/www/html/presenca/cron/sincronizacao_facial_automatica.php
```

### **Verificação de Status**
```sql
-- Verificar registros pendentes
SELECT COUNT(*) FROM facial_sync_culto WHERE status = 'pendente';

-- Verificar falhas
SELECT COUNT(*) FROM facial_sync_culto WHERE status = 'falha';

-- Verificar sincronizados
SELECT COUNT(*) FROM facial_sync_culto WHERE status = 'sincronizado';
```

### **Limpeza de Logs**
```bash
# Limpar logs antigos (manter últimos 30 dias)
find /var/www/html/presenca/logs -name "*.log" -mtime +30 -delete
```

## 🚨 Solução de Problemas

### **Cron Não Executa**
1. Verificar se o cron está configurado: `crontab -l`
2. Verificar permissões do script
3. Verificar logs do sistema: `journalctl -u cron`

### **Sincronização Falha**
1. Verificar conectividade com dispositivos faciais
2. Verificar credenciais dos dispositivos
3. Verificar logs de processamento
4. Testar manualmente via painel

### **Usuários Não Sincronizam**
1. Verificar se o trigger está ativo
2. Verificar se o usuário está ativo
3. Verificar se há dispositivos ativos
4. Verificar logs de erro

## 📈 Estatísticas

### **Métricas Disponíveis**
- **Usuários Sincronizados**: Total de usuários com status `sincronizado`
- **Pendentes**: Usuários aguardando sincronização
- **Falhas**: Usuários com erro na sincronização
- **Dispositivos Ativos**: Número de dispositivos de culto ativos

### **Relatórios**
- Status por usuário
- Status por dispositivo
- Histórico de operações
- Logs de erro

## 🔒 Segurança

### **Autenticação**
- Acesso restrito a administradores
- Verificação de sessão
- Logs de acesso

### **Dados Sensíveis**
- Credenciais de dispositivos criptografadas
- Logs de operações
- Backup automático de configurações

## 📞 Suporte

Para problemas ou dúvidas:
1. Verificar logs do sistema
2. Consultar painel administrativo
3. Testar operações manuais
4. Verificar configurações de rede

---

**Sistema implementado em:** 18/09/2025  
**Versão:** 1.0  
**Status:** ✅ Ativo e Funcionando
