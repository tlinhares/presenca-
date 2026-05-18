# Sistema de Automação de Relatórios

## 📋 Visão Geral

Sistema automatizado para envio de relatórios via WhatsApp em horários e dias específicos configurados pelos administradores.

## 🚀 Funcionalidades

- **Execução Automática**: Script executa de hora em hora via cron
- **Tolerância de Tempo**: ±5 minutos de tolerância para o horário configurado
- **Envio Único**: Garante que cada relatório seja enviado apenas uma vez por dia
- **Múltiplos Tipos**: Suporte a PDF diário, PDF completo, CSV e CSV diário
- **Logs Detalhados**: Registro completo de todas as execuções
- **Controle de Erros**: Tratamento robusto de falhas

## 📁 Arquivos do Sistema

### Scripts Principais
- `executar_automacoes_cron.php` - Script principal executado pelo cron
- `teste_automacao_cron.php` - Interface web para testar automações
- `configurar_cron.php` - Interface para configurar o cron
- `executar_automacao_manual.php` - Execução manual para testes
- `limpar_logs_automacao.php` - Limpeza automática de logs antigos

### APIs
- `api/automacao/salvar.php` - Criar/editar automações
- `api/automacao/listar.php` - Listar automações
- `api/automacao/excluir.php` - Excluir automações
- `api/automacao/buscar.php` - Buscar automação específica
- `api/automacao/testar.php` - Testar envio de automação

### Interface
- `painel/automacao_relatorios.php` - Interface de gerenciamento

## ⚙️ Configuração

### 1. Configurar Cron Job

Execute o comando para editar o crontab:
```bash
crontab -e
```

Adicione esta linha para executar de hora em hora:
```
0 * * * * /usr/bin/php /var/www/html/presenca/executar_automacoes_cron.php
```

### 2. Configurar Limpeza de Logs (Opcional)

Para limpar logs antigos automaticamente, adicione:
```
0 0 * * * /usr/bin/php /var/www/html/presenca/limpar_logs_automacao.php
```

### 3. Verificar Permissões

Certifique-se de que os diretórios têm as permissões corretas:
```bash
chmod 755 /var/www/html/presenca/logs
chmod +x /var/www/html/presenca/executar_automacoes_cron.php
```

## 🎯 Como Funciona

### Lógica de Execução

1. **Verificação de Horário**: Script executa de hora em hora
2. **Tolerância**: ±5 minutos de tolerância para o horário configurado
3. **Dias da Semana**: Verifica se deve executar hoje baseado na configuração
4. **Último Envio**: Verifica se já foi enviado hoje (campo `ultimo_envio`)
5. **Execução**: Se todas as condições forem atendidas, executa o envio

### Exemplo de Funcionamento

- **Automação configurada**: Segunda-feira às 10:00
- **Cron executa**: 09:59 → Não executa (fora da tolerância)
- **Cron executa**: 10:03 → Executa (dentro da tolerância)
- **Cron executa**: 10:30 → Não executa (já foi enviada hoje)

## 📊 Monitoramento

### Logs
Os logs são salvos em: `/var/www/html/presenca/logs/automacao_cron_YYYY-MM-DD.log`

### Monitoramento em Tempo Real
```bash
tail -f /var/www/html/presenca/logs/automacao_cron_$(date +%Y-%m-%d).log
```

### Interface Web
- **Teste**: `https://presenca.aom.org.br/teste_automacao_cron.php`
- **Configuração**: `https://presenca.aom.org.br/configurar_cron.php`
- **Execução Manual**: `https://presenca.aom.org.br/executar_automacao_manual.php`

## 🔧 Estrutura do Banco de Dados

### Tabela `automacoes_relatorios`
```sql
CREATE TABLE automacoes_relatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    tipo_relatorio ENUM('diario', 'diario_completo', 'csv', 'csv_diario') NOT NULL,
    numero_whatsapp VARCHAR(20) NOT NULL,
    horario_envio TIME NOT NULL,
    dias_semana JSON NOT NULL,
    mensagem_personalizada TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    ultimo_envio DATETIME NULL,
    proximo_envio DATETIME NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabela `logs_automacao`
```sql
CREATE TABLE logs_automacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    automacao_id INT NOT NULL,
    data_hora_execucao DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sucesso', 'falha') NOT NULL,
    mensagem_log TEXT,
    FOREIGN KEY (automacao_id) REFERENCES automacoes_relatorios(id) ON DELETE CASCADE
);
```

## 🚨 Solução de Problemas

### Script não executa
1. Verificar se o cron está configurado: `crontab -l`
2. Verificar permissões do script: `ls -la executar_automacoes_cron.php`
3. Testar execução manual: `php executar_automacoes_cron.php`

### Relatórios não são enviados
1. Verificar logs: `tail -f logs/automacao_cron_*.log`
2. Verificar configuração da automação
3. Testar envio manual via interface web

### Erro de permissão
```bash
chmod +x executar_automacoes_cron.php
chmod 755 logs/
```

## 📈 Exemplo de Log

```
[2024-01-15 10:03:15] === INICIANDO EXECUÇÃO DE AUTOMAÇÕES ===
[2024-01-15 10:03:15] Encontradas 2 automações ativas
[2024-01-15 10:03:15] Processando automação ID 1: Relatório Diário
[2024-01-15 10:03:15]   → EXECUTANDO AUTOMAÇÃO
[2024-01-15 10:03:18]   → ✅ SUCESSO: Mensagem e arquivo enviados com sucesso
[2024-01-15 10:03:18] === RESUMO DA EXECUÇÃO ===
[2024-01-15 10:03:18] Automações processadas: 2
[2024-01-15 10:03:18] Relatórios enviados: 1
[2024-01-15 10:03:18] Erros: 0
[2024-01-15 10:03:18] === FIM DA EXECUÇÃO ===
```

## 🎉 Benefícios

- **Automação Completa**: Sem intervenção manual
- **Confiabilidade**: Sistema robusto com tratamento de erros
- **Flexibilidade**: Múltiplos tipos de relatório e horários
- **Monitoramento**: Logs detalhados para acompanhamento
- **Segurança**: Envio único por dia evita spam
- **Manutenção**: Limpeza automática de logs antigos

## 📞 Suporte

Para problemas ou dúvidas:
1. Verificar logs de execução
2. Testar via interface web
3. Verificar configuração do cron
4. Consultar este README
