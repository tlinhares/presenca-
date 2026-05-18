# 🌓 Implementação: Tema Claro/Escuro no Dashboard

## 📋 Objetivo

Implementar um sistema de alternância entre tema claro e escuro no `dashboard.php`, com persistência da preferência do usuário no banco de dados.

---

## ✅ Arquivos Criados/Modificados

### Arquivos Criados

1. **`sql/adicionar_campo_tema_usuarios.sql`**
   - Script SQL para adicionar campo `tema` na tabela `usuarios`

2. **`api/usuarios/salvar_tema.php`**
   - API para salvar preferência de tema do usuário

3. **`api/usuarios/buscar_tema.php`**
   - API para buscar preferência de tema do usuário

4. **`api/usuarios/instalar_campo_tema.php`**
   - Script de instalação para criar o campo tema (requer admin)

### Arquivos Modificados

1. **`dashboard.php`**
   - Adicionado botão de toggle no header
   - Adicionado CSS para modo escuro
   - Adicionado JavaScript para gerenciar tema

---

## 🎨 Funcionalidades Implementadas

### 1. Botão de Toggle

- **Localização**: Header do dashboard, ao lado dos botões "Voltar" e "Sair"
- **Ícone**: 
  - ☀️ Sol (`bi-sun-fill`) quando tema é claro
  - 🌙 Lua (`bi-moon-fill`) quando tema é escuro
- **Comportamento**: Alterna entre claro e escuro ao clicar

### 2. Estilos de Modo Escuro

**Elementos com tema escuro:**
- `body`: Fundo escuro (`#1a1d29`)
- `.filter-card`: Card de filtros com fundo escuro
- `.chart-card`: Card de gráfico com fundo escuro
- `.stat-card`: Cards de estatísticas com fundo escuro
- `.form-control`, `.form-select`: Campos de formulário com fundo escuro
- Textos: Cor clara (`#e9ecef`)

### 3. Persistência

- **Salvamento**: Preferência salva no banco de dados (tabela `usuarios`, campo `tema`)
- **Carregamento**: Tema carregado automaticamente ao abrir a página
- **Sessão**: Tema também armazenado em `$_SESSION['usuario_tema']`

---

## 🗄️ Estrutura do Banco de Dados

### Campo Adicionado

```sql
ALTER TABLE usuarios 
ADD COLUMN tema VARCHAR(10) DEFAULT 'light' COMMENT 'Tema preferido: light ou dark';
```

**Valores possíveis:**
- `light`: Tema claro (padrão)
- `dark`: Tema escuro

---

## 🔧 Como Usar

### Instalação Inicial

1. **Executar script SQL** (opcional, o sistema cria automaticamente):
   ```sql
   -- Via MySQL
   ALTER TABLE usuarios 
   ADD COLUMN tema VARCHAR(10) DEFAULT 'light' COMMENT 'Tema preferido: light ou dark';
   ```

2. **Ou executar script PHP** (requer admin):
   ```
   Acesse: /api/usuarios/instalar_campo_tema.php
   ```

### Uso pelo Usuário

1. **Alternar tema**: Clique no botão de sol/lua no header
2. **Preferência salva**: A escolha é salva automaticamente
3. **Próximo login**: O tema escolhido será aplicado automaticamente

---

## 📊 APIs Criadas

### 1. `GET /api/usuarios/buscar_tema.php`

**Descrição**: Busca a preferência de tema do usuário logado

**Resposta:**
```json
{
    "status": "ok",
    "tema": "dark"
}
```

**Comportamento:**
- Se campo não existir, cria automaticamente
- Retorna `light` como padrão se não encontrar

---

### 2. `POST /api/usuarios/salvar_tema.php`

**Descrição**: Salva a preferência de tema do usuário

**Body:**
```json
{
    "tema": "dark"
}
```

**Resposta:**
```json
{
    "status": "ok",
    "mensagem": "Tema salvo com sucesso",
    "tema": "dark"
}
```

**Validação:**
- Aceita apenas `light` ou `dark`
- Valores inválidos são convertidos para `light`

---

## 🎨 Estilos CSS

### Modo Claro (Padrão)
- Fundo: `#f0f2f5`
- Cards: Branco
- Texto: `#212529`

### Modo Escuro
- Fundo: `#1a1d29`
- Cards: `#2d3142`
- Bordas: `#3d4154`
- Texto: `#e9ecef`
- Texto secundário: `#adb5bd`

---

## 🔄 Fluxo de Funcionamento

1. **Ao carregar página:**
   - JavaScript chama `buscar_tema.php`
   - Aplica tema retornado
   - Atualiza ícone do botão

2. **Ao clicar no botão:**
   - Alterna tema visualmente
   - Chama `salvar_tema.php` para salvar
   - Atualiza ícone do botão

3. **Próximo acesso:**
   - Tema salvo é carregado automaticamente
   - Usuário vê sua preferência aplicada

---

## ✅ Garantias

- ✅ **Compatibilidade**: Funciona mesmo se campo não existir (cria automaticamente)
- ✅ **Fallback**: Sempre usa `light` como padrão em caso de erro
- ✅ **Persistência**: Preferência salva no banco e sessão
- ✅ **Transição suave**: Animação CSS de 0.3s entre temas
- ✅ **Não quebra sistema**: Criação de campo é não-bloqueante

---

## 🧪 Teste

1. **Teste básico:**
   - Acesse `dashboard.php`
   - Clique no botão de tema
   - Verifique se alterna entre claro/escuro
   - Recarregue a página
   - Verifique se mantém a escolha

2. **Teste de persistência:**
   - Faça logout
   - Faça login novamente
   - Verifique se tema escolhido foi mantido

3. **Teste de instalação:**
   - Acesse `/api/usuarios/instalar_campo_tema.php` (como admin)
   - Verifique se campo foi criado
   - Teste novamente o toggle

---

## 📝 Status

- ✅ Botão de toggle adicionado
- ✅ CSS de modo escuro implementado
- ✅ JavaScript de gerenciamento criado
- ✅ APIs de salvar/buscar tema criadas
- ✅ Script SQL de instalação criado
- ✅ Persistência no banco de dados
- ✅ Carregamento automático ao abrir página

---

**Data:** 2026-01-XX  
**Status:** ✅ Implementado e pronto para uso
