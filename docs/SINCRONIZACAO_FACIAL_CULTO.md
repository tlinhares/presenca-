# Documentação: Sincronização de Usuários e Fotos para Dispositivos de Culto

**Versão:** 1.0  
**Data:** 09/02/2026  
**Sistema:** Presença AOM - Módulo de Reconhecimento Facial

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura do Sistema](#arquitetura-do-sistema)
3. [Estrutura de Banco de Dados](#estrutura-de-banco-de-dados)
4. [Fluxo de Sincronização](#fluxo-de-sincronização)
5. [APIs Disponíveis](#apis-disponíveis)
6. [Automação e Triggers](#automação-e-triggers)
7. [Cron Jobs](#cron-jobs)
8. [Protocolo de Comunicação](#protocolo-de-comunicação)
9. [Exemplos de Uso](#exemplos-de-uso)
10. [Troubleshooting](#troubleshooting)

---

## 🎯 Visão Geral

O sistema de sincronização facial para dispositivos de culto permite que usuários cadastrados no sistema sejam automaticamente sincronizados com dispositivos de reconhecimento facial (Intelbras/Dahua compatíveis) configurados especificamente para controle de presença em cultos.

### Características Principais

- ✅ **Sincronização Automática**: Usuários são sincronizados automaticamente quando suas fotos são atualizadas
- ✅ **Múltiplos Dispositivos**: Suporte a múltiplos dispositivos faciais do tipo "culto"
- ✅ **Sincronização Inteligente**: Verifica se usuário já existe antes de sincronizar
- ✅ **Controle de Tentativas**: Sistema de retry automático para falhas
- ✅ **Logs Detalhados**: Registro completo de todas as operações
- ✅ **Cron Automático**: Processamento periódico de sincronizações pendentes

---

## 🏗️ Arquitetura do Sistema

### Componentes Principais

```
┌─────────────────────────────────────────────────────────────┐
│                    SISTEMA DE PRESENÇA                       │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────┐      ┌──────────────┐                     │
│  │   Usuários   │──────│   Fotos      │                     │
│  │  (culto=1)   │      │  (base64)    │                     │
│  └──────┬───────┘      └──────┬───────┘                     │
│         │                     │                             │
│         └──────────┬──────────┘                             │
│                    │                                         │
│         ┌──────────▼──────────┐                              │
│         │   TRIGGER MySQL     │                              │
│         │  (Detecta mudança)  │                              │
│         └──────────┬──────────┘                              │
│                    │                                         │
│         ┌──────────▼──────────┐                              │
│         │ facial_sync_culto    │                              │
│         │  (status: pendente)  │                              │
│         └──────────┬──────────┘                              │
│                    │                                         │
│         ┌──────────▼──────────┐                              │
│         │   CRON Job          │                              │
│         │  (A cada 5 min)     │                              │
│         └──────────┬──────────┘                              │
│                    │                                         │
│         ┌──────────▼──────────┐                              │
│         │   API Sincronização │                              │
│         │  (HTTP cURL)        │                              │
│         └──────────┬──────────┘                              │
│                    │                                         │
│         ┌──────────▼──────────┐                              │
│         │ Dispositivos Faciais│                              │
│         │   (Intelbras/Dahua) │                              │
│         └─────────────────────┘                              │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### Fluxo de Dados

1. **Cadastro/Atualização de Usuário**: Usuário com `culto=1` e `ativo=1` tem foto cadastrada/atualizada
2. **Trigger MySQL**: Detecta mudança na foto e cria registros em `facial_sync_culto`
3. **Cron Job**: Processa registros pendentes periodicamente
4. **API de Sincronização**: Envia dados via HTTP para dispositivos faciais
5. **Dispositivo Facial**: Recebe e armazena usuário + foto

---

## 🗄️ Estrutura de Banco de Dados

### Tabela: `dispositivos_faciais`

Armazena informações sobre os dispositivos faciais configurados no sistema.

```sql
CREATE TABLE dispositivos_faciais (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    ip VARCHAR(15) NOT NULL,
    porta INT(5) DEFAULT 80,
    usuario VARCHAR(50) NOT NULL,
    senha VARCHAR(100) NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    tipo_dispositivo ENUM('restaurante', 'culto') DEFAULT 'restaurante',
    ultima_sincronizacao DATETIME DEFAULT NULL,
    status_conexao ENUM('online', 'offline', 'erro') DEFAULT 'offline',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_ip_porta (ip, porta)
);
```

**Campos Importantes:**
- `tipo_dispositivo`: Define se o dispositivo é para "culto" ou "restaurante"
- `ativo`: Controla se o dispositivo está ativo para sincronização
- `ip` + `porta`: Endereço de rede do dispositivo
- `usuario` + `senha`: Credenciais de autenticação HTTP Digest

### Tabela: `facial_sync_culto`

Controla o estado de sincronização de cada usuário com cada dispositivo.

```sql
CREATE TABLE facial_sync_culto (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_usuario INT(11) NOT NULL,
    id_dispositivo INT(11) NOT NULL,
    data DATE NOT NULL,
    status ENUM('pendente', 'sincronizado', 'falha', 'removido', 'erro_remocao') DEFAULT 'pendente',
    origem VARCHAR(50) DEFAULT 'culto',
    tentativas INT(11) DEFAULT 0,
    ultima_tentativa DATETIME DEFAULT NULL,
    detalhes TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY unique_usuario_dispositivo_data (id_usuario, id_dispositivo, data),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivos_faciais(id)
);
```

**Status Possíveis:**
- `pendente`: Aguardando sincronização
- `sincronizado`: Sincronizado com sucesso
- `falha`: Falha na sincronização (será tentado novamente)
- `removido`: Usuário removido do dispositivo
- `erro_remocao`: Erro ao remover usuário

### Tabela: `usuarios`

Tabela principal de usuários do sistema.

**Campos Relevantes para Sincronização:**
- `id`: ID único do usuário (usado como UserID no dispositivo)
- `nome`: Nome do usuário
- `foto_base64`: Foto em formato base64 (sem prefixo `data:image`)
- `culto`: Flag indicando se usuário participa do culto (1 = sim, 0 = não)
- `ativo`: Flag indicando se usuário está ativo (1 = sim, 0 = não)

---

## 🔄 Fluxo de Sincronização

### 1. Detecção de Mudança (Trigger)

Quando uma foto de usuário é atualizada, um trigger MySQL detecta a mudança:

**Arquivo:** `sql_scripts/trigger_atualizacao_foto_final.sql`

```sql
CREATE TRIGGER tr_usuario_foto_atualizada_culto
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    IF (
        (OLD.foto_base64 IS NULL AND NEW.foto_base64 IS NOT NULL) OR
        (OLD.foto_base64 IS NOT NULL AND NEW.foto_base64 IS NULL) OR
        (OLD.foto_base64 IS NOT NULL AND NEW.foto_base64 IS NOT NULL AND OLD.foto_base64 != NEW.foto_base64)
    ) AND NEW.ativo = 1 THEN
        
        INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, status, data, origem, detalhes)
        SELECT 
            NEW.id,
            df.id,
            'pendente',
            CURDATE(),
            'culto',
            CONCAT('Foto atualizada automaticamente: ', NEW.nome)
        FROM dispositivos_faciais df
        WHERE df.tipo_dispositivo = 'culto' AND df.ativo = 1
        ON DUPLICATE KEY UPDATE
            status = 'pendente',
            detalhes = CONCAT('Foto atualizada automaticamente: ', NEW.nome),
            ultima_tentativa = NULL;
    END IF;
END
```

**O que acontece:**
- Detecta mudança na coluna `foto_base64`
- Verifica se usuário está ativo (`ativo = 1`)
- Cria registros `pendente` em `facial_sync_culto` para todos os dispositivos de culto ativos
- Se registro já existe, atualiza para `pendente` novamente

### 2. Preparação Automática (Cron)

O cron job prepara sincronizações para novos usuários do culto:

**Arquivo:** `cron/sincronizacao_culto_automatica.php`

```php
// Insere registros pendentes para usuários do culto que ainda não têm sincronização
INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, data, status, origem, tentativas, detalhes)
SELECT u.id, d.id, CURDATE(), 'pendente', 'culto', 0, 'Sincronização automática via cron'
FROM usuarios u
CROSS JOIN dispositivos_faciais d
WHERE u.culto = 1 
AND u.ativo = 1 
AND u.foto_base64 IS NOT NULL 
AND u.foto_base64 != ''
AND d.ativo = 1 
AND d.tipo_dispositivo = 'culto'
AND NOT EXISTS (
    SELECT 1 FROM facial_sync_culto fs 
    WHERE fs.id_usuario = u.id 
    AND fs.id_dispositivo = d.id 
    AND fs.data = CURDATE()
)
```

### 3. Processamento de Sincronização

O cron processa até 10 registros pendentes por execução:

```php
SELECT fs.id, fs.id_usuario, fs.id_dispositivo, fs.tentativas,
       u.nome, u.foto_base64,
       d.nome as dispositivo_nome, d.ip, d.porta, d.usuario, d.senha
FROM facial_sync_culto fs
JOIN usuarios u ON fs.id_usuario = u.id
JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id
WHERE fs.data = CURDATE() 
AND fs.status IN ('pendente', 'falha')
AND fs.tentativas < 3
ORDER BY fs.id
LIMIT 10
```

**Critérios de Seleção:**
- Data = hoje (`CURDATE()`)
- Status = `pendente` ou `falha`
- Tentativas < 3 (máximo de 3 tentativas)
- Limite de 10 registros por execução (para não sobrecarregar)

### 4. Envio para Dispositivo

Para cada registro pendente, o sistema:

1. **Prepara dados JSON:**
```json
{
    "UserID": "123",
    "Name": "João Silva",
    "PhotoData": ["base64_encoded_image_data"],
    "Type": "culto",
    "Status": "active"
}
```

2. **Faz requisição HTTP POST:**
```
POST http://{IP}:{PORTA}/cgi-bin/AccessFace.cgi?action=updateMulti
Content-Type: application/json
Authorization: Digest (usuario:senha)
```

3. **Atualiza status:**
- Sucesso (HTTP 200 + resposta "OK"): `status = 'sincronizado'`
- Falha: `status = 'falha'`, incrementa `tentativas`

---

## 🔌 APIs Disponíveis

### 1. Sincronização Inteligente

**Endpoint:** `POST /api/culto/sincronizacao_inteligente.php`

**Descrição:** Verifica se usuário já está sincronizado antes de sincronizar.

**Parâmetros:**
```json
{
    "usuario_id": 123
}
```

**Fluxo:**
1. Busca dados do usuário (deve ter `culto=1`, `ativo=1`, `foto_base64`)
2. Busca todos os dispositivos de culto ativos
3. Para cada dispositivo:
   - Verifica se usuário existe (`/cgi-bin/AccessUser.cgi?action=list`)
   - Verifica se tem foto (`/cgi-bin/AccessFace.cgi?action=list`)
   - Se já existe e tem foto: marca como `ja_sincronizado`
   - Se não existe ou não tem foto: sincroniza

**Resposta de Sucesso:**
```json
{
    "status": "sucesso",
    "usuario": "João Silva",
    "total_verificados": 2,
    "total_sincronizados": 1,
    "total_ja_sincronizados": 1,
    "total_falhas": 0,
    "resultados": [
        {
            "dispositivo": "Culto Principal",
            "ip": "10.144.129.69",
            "status": "ja_sincronizado",
            "mensagem": "Usuário já está sincronizado com foto"
        },
        {
            "dispositivo": "Culto Secundário",
            "ip": "10.144.129.70",
            "status": "sincronizado",
            "mensagem": "Usuário sincronizado com sucesso"
        }
    ]
}
```

### 2. Sincronização Permanente

**Endpoint:** `POST /api/culto/sincronizar_usuario_permanente.php`

**Descrição:** Sincroniza usuário com todos os dispositivos de culto sem verificação prévia.

**Parâmetros:**
```json
{
    "usuario_id": 123
}
```

**Resposta:**
```json
{
    "status": "sucesso",
    "mensagem": "Sincronização permanente concluída",
    "usuario": "João Silva",
    "dispositivos_total": 2,
    "sincronizados": 2,
    "falhas": 0,
    "detalhes": [
        "✓ Sincronizado com sucesso no dispositivo Culto Principal (10.144.129.69)",
        "✓ Sincronizado com sucesso no dispositivo Culto Secundário (10.144.129.70)"
    ],
    "data": "2026-02-09"
}
```

### 3. Sincronização Manual (API Genérica)

**Endpoint:** `GET /api/facial/sincronizar.php?limite=10`

**Descrição:** Processa sincronizações pendentes da tabela `facial_sync` (para restaurante).

**Parâmetros:**
- `limite` (opcional): Número máximo de registros a processar (padrão: 10)

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Processo concluído. Foram sincronizados 5 usuários, com 0 falhas.",
    "processados": 5,
    "sincronizados": 5,
    "falhas": 0,
    "registros": [...]
}
```

---

## ⚙️ Automação e Triggers

### Trigger: Atualização de Foto

**Arquivo:** `sql_scripts/trigger_atualizacao_foto_final.sql`

**Quando é acionado:**
- Após `UPDATE` na tabela `usuarios`
- Quando `foto_base64` é alterada
- Quando usuário está ativo (`ativo = 1`)

**O que faz:**
- Cria registros `pendente` em `facial_sync_culto` para todos os dispositivos de culto ativos
- Se registro já existe, atualiza para `pendente` novamente

### Trigger: Novo Dispositivo

**Arquivo:** `sql_scripts/triggers_sincronizacao_facial.sql`

**Quando é acionado:**
- Após `INSERT` na tabela `dispositivos_faciais`
- Quando `tipo_dispositivo = 'culto'` e `ativo = 1`

**O que faz:**
- Cria registros `pendente` para todos os usuários do culto com foto

### Trigger: Ativação de Dispositivo

**Quando é acionado:**
- Após `UPDATE` na tabela `dispositivos_faciais`
- Quando dispositivo muda de `ativo = 0` para `ativo = 1`
- Quando `tipo_dispositivo = 'culto'`

**O que faz:**
- Cria registros `pendente` para todos os usuários do culto com foto

---

## ⏰ Cron Jobs

### Cron: Sincronização Automática

**Arquivo:** `cron/sincronizacao_culto_automatica.php`

**Frequência Recomendada:** A cada 5 minutos

**Configuração no crontab:**
```bash
*/5 * * * * /usr/bin/php /var/www/html/presenca/cron/sincronizacao_culto_automatica.php
```

**O que faz:**

1. **Prepara Sincronizações:**
   - Cria registros `pendente` para novos usuários do culto
   - Apenas para dispositivos ativos do tipo "culto"

2. **Processa Pendências:**
   - Processa até 10 registros por execução
   - Apenas registros com `tentativas < 3`
   - Atualiza status conforme resultado

3. **Limpeza:**
   - Remove registros com mais de 7 dias

**Logs:**
- Arquivo: `logs/cron_culto_YYYY-MM-DD.log`
- Contém detalhes de cada execução

---

## 📡 Protocolo de Comunicação

### Dispositivos Suportados

- **Intelbras**: Compatível com API Intelbras
- **Dahua**: Compatível com API Dahua
- **Outros**: Qualquer dispositivo compatível com protocolo Dahua/Intelbras

### Endpoints da API do Dispositivo

#### 1. Inserir/Atualizar Usuário

**Endpoint:** `/cgi-bin/AccessUser.cgi?action=insertMulti`

**Método:** `POST`

**Autenticação:** HTTP Digest

**Corpo (JSON):**
```json
{
    "UserList": [
        {
            "UserID": "123",
            "UserName": "João Silva",
            "UserType": 0,
            "Authority": 2,
            "Password": "123456",
            "Doors": [0],
            "TimeSections": [255],
            "ValidFrom": "2026-02-09 00:00:00",
            "ValidTo": "2027-02-09 23:59:59"
        }
    ]
}
```

**Resposta de Sucesso:** `OK`

#### 2. Inserir/Atualizar Foto Facial

**Endpoint:** `/cgi-bin/AccessFace.cgi?action=updateMulti`

**Método:** `POST`

**Autenticação:** HTTP Digest

**Corpo (JSON):**
```json
{
    "FaceList": [
        {
            "UserID": "123",
            "PhotoData": ["base64_encoded_image_without_prefix"]
        }
    ]
}
```

**Resposta de Sucesso:** `OK`

**Nota:** A foto deve estar em base64 **sem** o prefixo `data:image/jpeg;base64,`

#### 3. Listar Usuário

**Endpoint:** `/cgi-bin/AccessUser.cgi?action=list&UserIDList[0]=123`

**Método:** `GET`

**Autenticação:** HTTP Digest

**Resposta:** XML com dados do usuário

#### 4. Listar Foto Facial

**Endpoint:** `/cgi-bin/AccessFace.cgi?action=list&UserIDList[0]=123`

**Método:** `GET`

**Autenticação:** HTTP Digest

**Resposta:** XML com dados da foto

### Configuração cURL

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
curl_setopt($ch, CURLOPT_USERPWD, "{$usuario}:{$senha}");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($dados))
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
```

---

## 💡 Exemplos de Uso

### Exemplo 1: Sincronizar Usuário Manualmente

```php
// Via API de sincronização inteligente
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://presenca.aom.org.br/api/culto/sincronizacao_inteligente.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['usuario_id' => 123]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['status'] === 'sucesso') {
    echo "Usuário sincronizado com sucesso!\n";
    echo "Sincronizados: " . $result['total_sincronizados'] . "\n";
    echo "Já sincronizados: " . $result['total_ja_sincronizados'] . "\n";
}
```

### Exemplo 2: Verificar Status de Sincronização

```sql
-- Verificar sincronizações pendentes de hoje
SELECT 
    u.nome,
    d.nome as dispositivo,
    fs.status,
    fs.tentativas,
    fs.ultima_tentativa,
    fs.detalhes
FROM facial_sync_culto fs
JOIN usuarios u ON fs.id_usuario = u.id
JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id
WHERE fs.data = CURDATE()
AND fs.status = 'pendente'
ORDER BY fs.id;
```

### Exemplo 3: Forçar Sincronização de Todos os Usuários

```sql
-- Criar registros pendentes para todos os usuários do culto
INSERT INTO facial_sync_culto (id_usuario, id_dispositivo, data, status, origem, detalhes)
SELECT 
    u.id,
    d.id,
    CURDATE(),
    'pendente',
    'culto',
    'Sincronização manual forçada'
FROM usuarios u
CROSS JOIN dispositivos_faciais d
WHERE u.culto = 1 
AND u.ativo = 1 
AND u.foto_base64 IS NOT NULL 
AND u.foto_base64 != ''
AND d.ativo = 1 
AND d.tipo_dispositivo = 'culto'
ON DUPLICATE KEY UPDATE
    status = 'pendente',
    tentativas = 0,
    ultima_tentativa = NULL;
```

### Exemplo 4: Usar DispositivoFacialService

```php
require_once __DIR__ . '/core/services/DispositivoFacialService.php';

// Sincronizar usuário completo
$resultado = DispositivoFacialService::sincronizarUsuarioIntelbras(
    $ip = '10.144.129.69',
    $porta = 80,
    $usuario_disp = 'admin',
    $senha_disp = 'Arcs2901',
    $user_id = 123,
    $user_name = 'João Silva',
    $foto_base64 = $foto_base64_sem_prefixo,
    $opcoes = [],
    $log_callback = function($msg) { error_log($msg); }
);

if ($resultado['sucesso']) {
    echo "Sincronizado: " . $resultado['mensagem'] . "\n";
} else {
    echo "Erro: " . $resultado['mensagem'] . "\n";
}
```

---

## 🔧 Troubleshooting

### Problema: Usuário não está sendo sincronizado

**Verificações:**

1. **Usuário tem os requisitos?**
```sql
SELECT id, nome, culto, ativo, 
       CASE WHEN foto_base64 IS NOT NULL AND foto_base64 != '' THEN 'SIM' ELSE 'NÃO' END as tem_foto
FROM usuarios 
WHERE id = 123;
```
- Deve ter: `culto = 1`, `ativo = 1`, `foto_base64` preenchido

2. **Dispositivo está ativo?**
```sql
SELECT id, nome, ip, porta, ativo, tipo_dispositivo
FROM dispositivos_faciais
WHERE tipo_dispositivo = 'culto';
```
- Deve ter: `ativo = 1`, `tipo_dispositivo = 'culto'`

3. **Existe registro pendente?**
```sql
SELECT * FROM facial_sync_culto
WHERE id_usuario = 123
AND data = CURDATE();
```

4. **Cron está rodando?**
```bash
# Verificar logs do cron
tail -f /var/www/html/presenca/logs/cron_culto_$(date +%Y-%m-%d).log
```

### Problema: Sincronização falha repetidamente

**Verificações:**

1. **Dispositivo está acessível?**
```bash
curl -v http://10.144.129.69:80/cgi-bin/global.cgi?action=getCurrentTime \
  --user admin:Arcs2901 \
  --digest
```

2. **Credenciais estão corretas?**
- Verificar `usuario` e `senha` na tabela `dispositivos_faciais`

3. **Foto está em formato correto?**
- Deve ser base64 sem prefixo `data:image`
- Tamanho máximo recomendado: 300KB após compressão

4. **Verificar detalhes da falha:**
```sql
SELECT detalhes, tentativas, ultima_tentativa
FROM facial_sync_culto
WHERE id_usuario = 123
AND status = 'falha'
ORDER BY ultima_tentativa DESC
LIMIT 1;
```

### Problema: Trigger não está funcionando

**Verificações:**

1. **Trigger existe?**
```sql
SHOW TRIGGERS LIKE 'tr_usuario_foto_atualizada_culto';
```

2. **Trigger está habilitado?**
```sql
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, STATUS
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_NAME = 'tr_usuario_foto_atualizada_culto';
```

3. **Testar trigger manualmente:**
```sql
-- Atualizar foto de um usuário de teste
UPDATE usuarios 
SET foto_base64 = 'teste_base64' 
WHERE id = 123;

-- Verificar se registro foi criado
SELECT * FROM facial_sync_culto
WHERE id_usuario = 123
AND data = CURDATE();
```

### Problema: Cron não está executando

**Verificações:**

1. **Cron está configurado?**
```bash
crontab -l | grep sincronizacao_culto_automatica
```

2. **Permissões do arquivo:**
```bash
ls -la /var/www/html/presenca/cron/sincronizacao_culto_automatica.php
# Deve ter permissão de execução
```

3. **PHP está no PATH?**
```bash
which php
# Ou usar caminho completo: /usr/bin/php
```

4. **Testar manualmente:**
```bash
/usr/bin/php /var/www/html/presenca/cron/sincronizacao_culto_automatica.php
```

---

## 📊 Monitoramento

### Queries Úteis para Monitoramento

#### Estatísticas Gerais

```sql
SELECT 
    COUNT(*) as total_registros,
    SUM(CASE WHEN status = 'sincronizado' THEN 1 ELSE 0 END) as sincronizados,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas
FROM facial_sync_culto
WHERE data = CURDATE();
```

#### Usuários Pendentes por Dispositivo

```sql
SELECT 
    d.nome as dispositivo,
    COUNT(*) as pendentes
FROM facial_sync_culto fs
JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id
WHERE fs.data = CURDATE()
AND fs.status = 'pendente'
GROUP BY d.id, d.nome;
```

#### Taxa de Sucesso por Dispositivo

```sql
SELECT 
    d.nome as dispositivo,
    COUNT(*) as total,
    SUM(CASE WHEN fs.status = 'sincronizado' THEN 1 ELSE 0 END) as sucessos,
    ROUND(SUM(CASE WHEN fs.status = 'sincronizado' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taxa_sucesso
FROM facial_sync_culto fs
JOIN dispositivos_faciais d ON fs.id_dispositivo = d.id
WHERE fs.data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY d.id, d.nome;
```

---

## 📝 Notas Importantes

1. **Formato da Foto:**
   - A foto deve estar em base64 **sem** o prefixo `data:image/jpeg;base64,`
   - O sistema remove automaticamente o prefixo se presente
   - Tamanho recomendado: máximo 300KB após compressão

2. **UserID no Dispositivo:**
   - O `UserID` usado no dispositivo é o mesmo `id` da tabela `usuarios`
   - Deve ser único por dispositivo

3. **Limite de Tentativas:**
   - Máximo de 3 tentativas por registro
   - Após 3 falhas, registro permanece com status `falha`
   - Pode ser resetado manualmente alterando `tentativas = 0`

4. **Performance:**
   - Cron processa máximo 10 registros por execução
   - Intervalo recomendado: 5 minutos
   - Para muitos usuários, pode levar várias execuções para sincronizar todos

5. **Segurança:**
   - Credenciais dos dispositivos são armazenadas em texto plano na tabela `dispositivos_faciais`
   - Recomenda-se proteger acesso à tabela
   - Comunicação HTTP não é criptografada (recomenda-se HTTPS se disponível)

---

## 📚 Referências

- **Arquivos Principais:**
  - `api/culto/sincronizacao_inteligente.php` - Sincronização inteligente
  - `api/culto/sincronizar_usuario_permanente.php` - Sincronização permanente
  - `cron/sincronizacao_culto_automatica.php` - Cron automático
  - `core/services/DispositivoFacialService.php` - Serviço centralizado
  - `sql_scripts/trigger_atualizacao_foto_final.sql` - Trigger de atualização

- **Documentação Externa:**
  - API Intelbras: Manual do dispositivo
  - API Dahua: Manual do dispositivo

---

**Fim da Documentação**
