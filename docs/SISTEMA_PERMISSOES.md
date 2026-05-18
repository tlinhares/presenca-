# Sistema de Controle de Acesso por Módulos

## Visão Geral

Este documento descreve a implementação do sistema de controle de acesso por módulos,
permitindo granularidade de permissões além do simples admin/usuário.

## Módulos do Sistema

| Código | Nome | Descrição |
|--------|------|-----------|
| `gerenciamento` | Gerenciamento | Usuários, dispositivos, logs, configurações, justificativas |
| `refeicoes` | Refeições | Reservas, cardápios, relatórios de refeições |
| `culto` | Culto | Presença, relatórios de culto |
| `estoque` | Estoque | Controle de estoque (futuro) |
| `frota` | Frota | Controle de frota (futuro) |

## Níveis de Permissão

| Permissão | Valor | Descrição |
|-----------|-------|-----------|
| Nenhum | 0 | Sem acesso ao módulo |
| Visualizar | 1 | Pode ver dados (somente leitura) |
| Editar | 2 | Pode criar e editar registros |
| Excluir | 3 | Pode excluir registros |
| Administrar | 4 | Controle total do módulo |

## Estrutura do Banco de Dados

### Tabela: modulos
```sql
CREATE TABLE modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    icone VARCHAR(50) DEFAULT 'fas fa-cube',
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabela: usuario_permissoes
```sql
CREATE TABLE usuario_permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    modulo_id INT NOT NULL,
    nivel_permissao TINYINT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario_modulo (usuario_id, modulo_id)
);
```

## Regras de Negócio

### 1. Compatibilidade com Sistema Atual
- Usuários com `categoria = 'admin'` têm acesso TOTAL a todos os módulos
- O sistema de permissões é COMPLEMENTAR, não substitui o admin
- Se não houver registro em `usuario_permissoes`, usuário não tem acesso

### 2. Verificação de Permissão
```php
// Ordem de verificação:
// 1. Se for admin -> acesso total
// 2. Se tiver permissão específica no módulo -> usa o nível
// 3. Se não tiver registro -> sem acesso
```

### 3. Fallback Seguro
- Se algo falhar na verificação, NEGAR acesso (princípio da negação)
- Logs de tentativas de acesso negado

## Mapeamento de Páginas por Módulo

### Módulo: gerenciamento
- /painel/usuarios.php
- /painel/dispositivos_faciais.php
- /painel/logs*.php
- /painel/justificativas.php
- /painel/configuracoes.php
- /painel/reconhecimento_facial.php

### Módulo: refeicoes
- /painel/reservas*.php
- /painel/relatorio*.php (exceto culto)
- /painel/cardapio*.php
- /painel/automacao_relatorios.php
- /reservas/*.php

### Módulo: culto
- /culto/*.php
- /painel/relatorio*culto*.php

## Implementação Segura

### Fase 1: Criar estrutura (NÃO AFETA SISTEMA)
1. Criar tabelas no banco
2. Criar serviço PermissaoService.php
3. Criar verifica_permissao.php (wrapper)

### Fase 2: Integrar gradualmente
1. Adicionar verificação nas novas páginas primeiro
2. Migrar páginas existentes uma a uma
3. Manter sempre o fallback para admin

### Fase 3: Interface de administração
1. Painel para gerenciar permissões
2. Visualização de quem tem acesso a quê

## Uso do Serviço

```php
// Incluir o serviço
require_once __DIR__ . '/../core/services/PermissaoService.php';

// Verificar se pode acessar módulo
if (PermissaoService::podeAcessar('refeicoes')) {
    // Usuário pode acessar
}

// Verificar nível específico
if (PermissaoService::temPermissao('refeicoes', PermissaoService::EDITAR)) {
    // Pode editar
}

// Verificar se é admin OU tem permissão
if (PermissaoService::podeAdministrar('gerenciamento')) {
    // Pode administrar
}
```

## Exemplos de Perfis

### Funcionário Comum
```
gerenciamento: 0 (sem acesso)
refeicoes: 2 (pode reservar e editar suas reservas)
culto: 1 (apenas visualizar)
```

### Recepcionista
```
gerenciamento: 1 (visualizar logs e justificativas)
refeicoes: 3 (gerenciar reservas de todos)
culto: 2 (registrar presenças)
```

### Supervisor
```
gerenciamento: 2 (gerenciar usuários)
refeicoes: 4 (administrar módulo)
culto: 4 (administrar módulo)
```

### Administrador (via categoria)
```
Acesso total automático - não precisa de registros
```

## Arquivos Criados

| Arquivo | Descrição |
|---------|-----------|
| `core/services/PermissaoService.php` | Serviço centralizado de permissões |
| `auth/verifica_permissao.php` | Wrapper para uso nas páginas |
| `painel/permissoes.php` | Interface de administração |
| `api/permissoes/listar_usuarios.php` | API para listar usuários |
| `api/permissoes/atualizar.php` | API para atualizar permissão individual |
| `api/permissoes/atualizar_todas.php` | API para atualizar todas permissões |
| `api/permissoes/copiar.php` | API para copiar permissões entre usuários |

## Tabelas Criadas no Banco

- `modulos` - Lista de módulos do sistema
- `usuario_permissoes` - Permissões de cada usuário por módulo

## Próximos Passos (Opcional)

1. Adicionar verificação de permissão nas páginas existentes gradualmente
2. Criar página de "Acesso Negado" personalizada
3. Implementar logs de auditoria de acessos

---
**Versão:** 1.0
**Data:** 2025-12-02
**Autor:** Sistema de Presença AOM
**Status:** ✅ Implementado

