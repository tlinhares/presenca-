# 📋 Mapeamento Completo de APIs - Sistema de Presença AOM

## 📋 Resumo para Outra Sessão do Cursor

Este documento mapeia **TODAS** as APIs do sistema, indicando:
- Status de suporte mobile (Bearer Token)
- Método HTTP
- Parâmetros
- Resposta esperada
- Onde é usado no sistema
- Comportamento

---

## 🔐 Autenticação

Todas as APIs que requerem autenticação devem incluir:
```php
require_once __DIR__ . '/../../core/middleware/mobile_auth.php';
if (!isset($_SESSION['usuario_id'])) {
    if (!MobileAuthMiddleware::handle()) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado']);
        exit;
    }
}
```

---

## 📦 Módulo: Almoço (`/api/almoco/`)

### ✅ APIs com Suporte Mobile (Bearer Token)

| API | Método | Descrição | Status Mobile |
|-----|--------|-----------|---------------|
| `verificar_horario.php` | GET | Verifica horário disponível para reserva | ✅ SIM |
| `verificar_horario_adicional.php` | GET | Verifica horário para reserva adicional | ✅ SIM |
| `reservar.php` | POST | Criar reserva de almoço | ✅ SIM |
| `reservar_adicional.php` | POST | Criar reserva adicional (dependente) | ✅ SIM |
| `cancelar_reserva_propria.php` | POST | Cancelar reserva própria | ✅ SIM |
| `excluir_reserva_adicional.php` | POST | Excluir reserva adicional | ✅ SIM |
| `listar_reservas_usuario.php` | GET | Listar reservas do usuário | ✅ SIM |
| `listar_reservas_adicionais_usuario.php` | GET | Listar reservas adicionais do usuário | ✅ SIM |
| `listar_adicionais.php` | GET | Listar tipos de adicionais disponíveis | ✅ SIM |
| `status_reserva.php` | GET | Status da reserva do dia | ✅ SIM |
| `editar_reserva_adicional.php` | POST | Editar reserva adicional existente | ✅ SIM |

### ⚠️ APIs SEM Suporte Mobile (Precisam Adicionar Middleware)

| API | Método | Descrição | Onde Usado | Prioridade |
|-----|--------|-----------|------------|------------|
| `listar_todas_reservas.php` | GET | Listar todas as reservas (admin) | Painel admin | Média |
| `excluir_reserva_admin.php` | POST | Excluir reserva (admin) | Painel admin | Média |
| `reservar_multiplo.php` | POST | Reservar para vários dias | Web | Alta |
| `reservar_departamento.php` | POST | Reservar para departamento | Web | Baixa |
| `listar.php` | GET | Listar reservas (admin) | Painel admin | Média |
| `listar_entidades.php` | GET | Listar entidades | Web | Baixa |
| `listar departamento.php` | GET | Listar por departamento | Web | Baixa |
| `dados_dashboard.php` | GET | Dados para dashboard | Dashboard web | Média |
| `dados_grafico.php` | GET | Dados para gráficos | Dashboard web | Média |
| `grafico_semana.php` | GET | Gráfico da semana | Dashboard web | Média |
| `buscar_reserva_adicional.php` | GET | Buscar reserva adicional | Web | Média |
| `excluir_adicional.php` | POST | Excluir adicional | Web | Média |
| `excluir_reserva.php` | POST | Excluir reserva | Web | Média |
| `excluir_reserva_departamento.php` | POST | Excluir reserva departamento | Web | Baixa |
| `cancelar.php` | POST | Cancelar reserva | Web | Média |
| `listar_reservas_proprias.php` | GET | Listar reservas próprias | Web | Alta |
| `reservas_adicionais.php` | GET | Listar reservas adicionais | Web | Alta |
| `verificar_horario_limite.php` | GET | Verificar horário limite | Web | Média |
| `salvar_dependente.php` | POST | Salvar dependente | Web | Alta |
| `instalar_menu_gestao_reservas.php` | GET | Instalar menu (admin) | Admin | Baixa |

---

## 👥 Módulo: Dependentes (`/api/dependentes/`)

### ✅ APIs com Suporte Mobile

| API | Método | Descrição | Status Mobile |
|-----|--------|-----------|---------------|
| `listar.php` | GET | Listar dependentes do usuário | ✅ SIM |

### ⚠️ APIs SEM Suporte Mobile

| API | Método | Descrição | Onde Usado | Prioridade |
|-----|--------|-----------|------------|------------|
| `criar.php` | POST | Criar dependente | Web | Alta |
| `editar.php` | POST | Editar dependente | Web | Alta |
| `excluir.php` | POST | Excluir dependente | Web | Alta |
| `buscar.php` | GET | Buscar dependente por ID | Web | Média |
| `obter.php` | GET | Obter dependente | Web | Média |
| `listar_por_usuario.php` | GET | Listar por usuário | Web | Média |
| `salvar_dependente.php` | POST | Salvar dependente | Web | Alta |
| `verificar_reserva_e_sincronizar.php` | POST | Verificar e sincronizar | Web | Baixa |

---

## 👤 Módulo: Usuários (`/api/usuarios/`)

### ⚠️ APIs SEM Suporte Mobile

| API | Método | Descrição | Onde Usado | Prioridade |
|-----|--------|-----------|------------|------------|
| `listar.php` | GET | Listar usuários | Admin | Alta |
| `buscar.php` | GET | Buscar usuário | Web | Alta |
| `cadastrar.php` | POST | Cadastrar usuário | Admin | Alta |
| `editar.php` | POST | Editar usuário | Admin | Alta |
| `excluir.php` | POST | Excluir usuário | Admin | Média |
| `atualizar_perfil.php` | POST | Atualizar perfil próprio | Web | Alta |
| `buscar_perfil.php` | GET | Buscar perfil próprio | Web | Alta |
| `perfil.php` | GET | Perfil do usuário | Web | Alta |
| `atualizar_foto.php` | POST | Atualizar foto | Web | Alta |
| `sincronizar_facial.php` | POST | Sincronizar reconhecimento facial | Web | Média |
| `verificar_facial.php` | GET | Verificar facial | Web | Média |
| `remover_facial.php` | POST | Remover facial | Web | Média |
| `obter.php` | GET | Obter usuário | Web | Média |
| `salvar.php` | POST | Salvar usuário | Admin | Alta |
| `verificar_reserva_e_sincronizar.php` | POST | Verificar e sincronizar | Web | Baixa |

---

## ⛪ Módulo: Culto (`/api/culto/`)

### ⚠️ APIs SEM Suporte Mobile

| API | Método | Descrição | Onde Usado | Prioridade |
|-----|--------|-----------|------------|------------|
| `listar_faltas_usuario.php` | GET | Listar faltas do usuário | Web | Alta |
| `enviar_justificativa.php` | POST | Enviar justificativa de falta | Web | Alta |
| `detalhes_justificativa.php` | GET | Detalhes da justificativa | Web | Média |
| `listar_justificativas_admin.php` | GET | Listar justificativas (admin) | Admin | Média |
| `decidir_justificativa.php` | POST | Decidir justificativa (admin) | Admin | Média |
| `decidir_justificativas_lote.php` | POST | Decidir em lote (admin) | Admin | Baixa |
| `enviar_justificativa_lote.php` | POST | Enviar justificativa em lote | Web | Baixa |
| `alterar_presenca_admin.php` | POST | Alterar presença (admin) | Admin | Média |
| `listar_usuarios_admin.php` | GET | Listar usuários (admin) | Admin | Média |
| `listar_usuarios_simples.php` | GET | Listar usuários simples | Web | Média |
| `historico_usuario.php` | GET | Histórico do usuário | Web | Alta |
| `detalhes_falhas.php` | GET | Detalhes de faltas | Web | Média |
| `buscar_config.php` | GET | Buscar configurações | Web | Baixa |
| `salvar_configuracoes.php` | POST | Salvar configurações | Admin | Baixa |
| `processar_leitura_facial.php` | POST | Processar leitura facial | Sistema | Baixa |
| `receber_leitura_facial.php` | POST | Receber leitura facial | Sistema | Baixa |
| `buscar_dados_facial.php` | GET | Buscar dados facial | Sistema | Baixa |
| `verificar_dispositivos.php` | GET | Verificar dispositivos | Sistema | Baixa |
| `verificar_usuario_dispositivo.php` | GET | Verificar usuário/dispositivo | Sistema | Baixa |
| `sincronizar_usuario_permanente.php` | POST | Sincronizar permanente | Sistema | Baixa |
| `ressincronizar_usuario.php` | POST | Ressincronizar usuário | Sistema | Baixa |
| `preparar_sincronizacao_culto.php` | POST | Preparar sincronização | Sistema | Baixa |
| `executar_sync.php` | POST | Executar sincronização | Sistema | Baixa |
| `sincronizacao_lote.php` | POST | Sincronização em lote | Sistema | Baixa |
| `sincronizacao_inteligente.php` | POST | Sincronização inteligente | Sistema | Baixa |
| `processar_sincronizacao_automatica.php` | POST | Processar sync automática | Sistema | Baixa |
| `verificar_e_preparar.php` | POST | Verificar e preparar | Sistema | Baixa |
| `estatisticas_sincronizacao.php` | GET | Estatísticas de sync | Sistema | Baixa |
| `limpar_usuarios_nao_culto.php` | POST | Limpar usuários não culto | Admin | Baixa |
| `relatorios/exportar_excel.php` | GET | Exportar Excel | Admin | Baixa |

---

## 🚗 Módulo: Frota (`/api/frota/`)

### ⚠️ APIs SEM Suporte Mobile

| API | Método | Descrição | Onde Usado | Prioridade |
|-----|--------|-----------|------------|------------|
| `listar_veiculos.php` | GET | Listar veículos | Web | Alta |
| `buscar_veiculo.php` | GET | Buscar veículo | Web | Alta |
| `salvar_veiculo.php` | POST | Salvar veículo | Admin | Alta |
| `excluir_veiculo.php` | POST | Excluir veículo | Admin | Média |
| `reativar_veiculo.php` | POST | Reativar veículo | Admin | Média |
| `registrar_saida.php` | POST | Registrar saída de veículo | Web | Alta |
| `registrar_entrada.php` | POST | Registrar entrada de veículo | Web | Alta |
| `minha_utilizacao.php` | GET | Minha utilização | Web | Alta |
| `meu_historico.php` | GET | Meu histórico | Web | Alta |
| `buscar_utilizacao.php` | GET | Buscar utilização | Web | Média |
| `listar_manutencoes.php` | GET | Listar manutenções | Web | Média |
| `buscar_manutencao.php` | GET | Buscar manutenção | Web | Média |
| `salvar_manutencao.php` | POST | Salvar manutenção | Admin | Média |
| `excluir_manutencao.php` | POST | Excluir manutenção | Admin | Média |
| `estatisticas.php` | GET | Estatísticas | Dashboard | Média |
| `exportar_pdf.php` | GET | Exportar PDF | Web | Baixa |
| `exportar_excel.php` | GET | Exportar Excel | Web | Baixa |
| `comprovante_pdf.php` | GET | Comprovante PDF | Web | Baixa |
| `relatorio_preview.php` | GET | Preview de relatório | Web | Baixa |
| `listar_entidades.php` | GET | Listar entidades | Web | Baixa |

---

## 📦 Módulo: Estoque (`/api/estoque/`)

### ⚠️ APIs SEM Suporte Mobile

| API | Método | Descrição | Onde Usado | Prioridade |
|-----|--------|-----------|------------|------------|
| `produtos/listar.php` | GET | Listar produtos | Web | Alta |
| `produtos/salvar.php` | POST | Salvar produto | Admin | Alta |
| `categorias/listar.php` | GET | Listar categorias | Web | Média |
| `categorias/salvar.php` | POST | Salvar categoria | Admin | Média |
| `categorias/excluir.php` | POST | Excluir categoria | Admin | Baixa |
| `fornecedores/listar.php` | GET | Listar fornecedores | Web | Média |
| `fornecedores/salvar.php` | POST | Salvar fornecedor | Admin | Média |
| `fornecedores/excluir.php` | POST | Excluir fornecedor | Admin | Baixa |
| `departamentos/listar.php` | GET | Listar departamentos | Web | Média |
| `departamentos/salvar.php` | POST | Salvar departamento | Admin | Média |
| `departamentos/excluir.php` | POST | Excluir departamento | Admin | Baixa |
| `localizacoes/listar.php` | GET | Listar localizações | Web | Média |
| `localizacoes/salvar.php` | POST | Salvar localização | Admin | Média |
| `localizacoes/excluir.php` | POST | Excluir localização | Admin | Baixa |
| `unidades/listar.php` | GET | Listar unidades | Web | Média |
| `unidades/salvar.php` | POST | Salvar unidade | Admin | Média |
| `responsaveis/listar.php` | GET | Listar responsáveis | Web | Média |
| `responsaveis/salvar.php` | POST | Salvar responsável | Admin | Média |
| `requisicoes/listar.php` | GET | Listar requisições | Web | Alta |
| `requisicoes/criar.php` | POST | Criar requisição | Web | Alta |
| `requisicoes/buscar.php` | GET | Buscar requisição | Web | Média |
| `requisicoes/decidir.php` | POST | Decidir requisição | Admin | Média |
| `requisicoes/pdf.php` | GET | PDF da requisição | Web | Baixa |
| `inventarios/listar.php` | GET | Listar inventários | Web | Média |
| `inventarios/criar.php` | POST | Criar inventário | Admin | Média |
| `inventarios/buscar.php` | GET | Buscar inventário | Web | Média |
| `inventarios/produtos.php` | GET | Produtos do inventário | Web | Média |
| `inventarios/registrar_contagem.php` | POST | Registrar contagem | Web | Média |
| `inventarios/finalizar.php` | POST | Finalizar inventário | Admin | Média |
| `inventarios/pdf.php` | GET | PDF do inventário | Web | Baixa |
| `movimentacoes/listar.php` | GET | Listar movimentações | Web | Média |
| `movimentacoes/registrar_entrada.php` | POST | Registrar entrada | Web | Média |
| `nf/importar.php` | POST | Importar nota fiscal | Web | Baixa |
| `nf/processar_xml.php` | POST | Processar XML | Web | Baixa |
| `dashboard/estatisticas.php` | GET | Estatísticas | Dashboard | Média |
| `dashboard/alertas.php` | GET | Alertas | Dashboard | Média |
| `relatorios/gerar.php` | GET | Gerar relatório | Web | Baixa |

---

## 🔔 Módulo: Notificação (`/api/notificacao/`)

### ⚠️ APIs SEM Suporte Mobile

| API | Método | Descrição | Onde Usado | Prioridade |
|-----|--------|-----------|------------|------------|
| `notificar_usuario.php` | POST | Notificar usuário | Sistema | Média |
| `enviar_notificacao_reserva.php` | POST | Notificar reserva | Sistema | Média |
| `enviar_notificacao_justificativa.php` | POST | Notificar justificativa | Sistema | Média |
| `buscar_configuracao.php` | GET | Buscar configuração | Web | Média |
| `salvar_configuracao.php` | POST | Salvar configuração | Web | Média |
| `listar_preferencias_admin.php` | GET | Listar preferências (admin) | Admin | Média |
| `atualizar_preferencias_admin.php` | POST | Atualizar preferências (admin) | Admin | Média |
| `instalar_menu_preferencias.php` | GET | Instalar menu | Admin | Baixa |

---

## 📅 Módulo: Calendário (`/api/calendario/`)

### ⚠️ APIs SEM Suporte Mobile

| API | Método | Descrição | Onde Usado | Prioridade |
|-----|--------|-----------|------------|------------|
| `detalhes_presenca_dia.php` | GET | Detalhes presença do dia | Web | Média |
| `detalhes_reservas_dia.php` | GET | Detalhes reservas do dia | Web | Média |
| `resumo_refeicoes.php` | GET | Resumo refeições | Web | Média |
| `estatisticas_presenca.php` | GET | Estatísticas presença | Web | Média |

---

## 🔐 Módulo: Autenticação Mobile (`/api/mobile/auth/`)

### ✅ APIs com Suporte Mobile

| API | Método | Descrição | Status Mobile |
|-----|--------|-----------|---------------|
| `login.php` | POST | Login e obter token | ✅ SIM |
| `refresh.php` | POST | Renovar token | ✅ SIM |
| `logout.php` | POST | Logout | ✅ SIM |

---

## 📊 Resumo por Prioridade

### 🔴 Alta Prioridade (APIs Críticas para Mobile)
- Dependentes: criar, editar, excluir, listar
- Usuários: perfil, atualizar perfil, buscar perfil
- Culto: listar faltas, enviar justificativa, histórico
- Frota: minha utilização, meu histórico, registrar saída/entrada
- Estoque: listar produtos, criar requisição, listar requisições
- Almoço: reservar múltiplo, listar reservas próprias

### 🟡 Média Prioridade
- APIs administrativas
- Relatórios
- Estatísticas

### 🟢 Baixa Prioridade
- Instalação de menus
- Sincronização de dispositivos
- Exportação de arquivos

---

**Total de APIs Mapeadas:** ~200+  
**APIs com Suporte Mobile:** 11  
**APIs que Precisam Middleware:** ~190+

---

**Data:** 2026-01-XX  
**Versão:** 1.0
