# 🔧 Correção: Marmitex Desativado no Sistema

## ⚠️ Problema Identificado

O sistema web tem marmitex **desativado**, mas o app Flutter permitia criar reservas de marmitex.

## ✅ Solução Implementada

Foi implementada **detecção dinâmica** que verifica se marmitex está habilitado através da resposta da API. O backend já valida e retorna erro quando marmitex está desabilitado (conforme documentação em `docs/CORRECAO_VALIDACAO_MARMITEX.md`).

### Arquivo Modificado

**`lib/features/reservas/criar_reserva_adicional_screen.dart`**

### Mudanças Aplicadas

1. **Detecção Dinâmica:**
   ```dart
   bool _marmitexHabilitado = true; // Detectado dinamicamente pela API
   ```
   - Quando usuário seleciona "Marmitex", o app chama a API de verificar horário
   - Se a API retornar erro indicando que marmitex está desabilitado, o app detecta automaticamente
   - A opção de marmitex é desabilitada dinamicamente

2. **Desabilitar Opção de Marmitex:**
   - A opção de marmitex fica **desabilitada** quando `_marmitexHabilitado = false`
   - Mostra mensagem "Marmitex desativado" no subtítulo
   - Volta automaticamente para "Presencial" se detectar que marmitex está desabilitado

3. **Validação ao Salvar:**
   - Verifica se tentou criar reserva de marmitex quando está desativado
   - Mostra mensagem de erro explicativa
   - Backend também valida e retorna erro (validação dupla)

---

## 🔄 Como Ativar/Desativar Marmitex

### No Backend (Tabela `configuracoes`)

```sql
-- Desativar marmitex
UPDATE configuracoes SET valor = '0' WHERE chave = 'marmitex_habilitado';

-- Ativar marmitex
UPDATE configuracoes SET valor = '1' WHERE chave = 'marmitex_habilitado';
```

**O app detecta automaticamente** através da resposta da API. Não é necessário alterar código no app!

---

## 📋 Como Funciona

### Detecção Automática

1. Usuário seleciona "Marmitex" na interface
2. App chama `verificar_horario_adicional.php` com `tipo='marmitex'`
3. Backend verifica `marmitex_habilitado` na tabela `configuracoes`
4. Se desabilitado, API retorna erro: `"Reservas para marmitex estão desabilitadas no sistema."`
5. App detecta o erro e desabilita a opção automaticamente

### Validação Dupla

- **Frontend (App):** Desabilita opção quando detecta que marmitex está desativado
- **Backend (API):** Valida e retorna erro se tentar criar reserva de marmitex quando desabilitado

**Vantagem:** Não precisa alterar código no app quando marmitex é ativado/desativado!

---

## ✅ Status

- ✅ Detecção dinâmica através da API
- ✅ Opção de marmitex desabilitada automaticamente quando detectado como desativado
- ✅ Validação antes de criar reserva
- ✅ Mensagem de erro explicativa
- ✅ Backend já valida e retorna erro (conforme `docs/CORRECAO_VALIDACAO_MARMITEX.md`)
- ✅ Não precisa alterar código quando marmitex é ativado/desativado

---

**Última Atualização:** Janeiro 2025
