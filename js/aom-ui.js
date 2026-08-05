/**
 * AOM UI — utilidades compartilhadas do design system.
 *
 * REGRA DO BOTÃO VOLTAR (definida pelo admin em 2026-08-05): a navegação de
 * volta é uma CADEIA FIXA, não histórico do navegador —
 *   telas de módulo → dashboard do módulo → /painel/dashboard.php → /resumo.php
 * Portanto os botões Voltar usam href fixo pro "pai" na hierarquia.
 * (Não usar history.back(): o admin quer previsibilidade, não origem real.)
 */
