<?php
/**
 * DependenteService — regras de isenção de cobrança para dependentes.
 *
 * Centraliza a regra "até X anos não cobra". X é configurável pelo admin
 * em `configuracoes` (chave: `idade_isencao_dependente`, default 12).
 *
 * USE este helper em qualquer ponto que precise decidir `cobrar` ou calcular
 * idade — nunca duplique a regra com hardcoded `<= 12`.
 */

class DependenteService
{
    /** Cache da idade-limite na requisição (evita N selects). */
    private static $idadeIsencaoCache = null;

    /**
     * Idade-limite (em anos completos) para isenção de cobrança.
     * Lê de `configuracoes.idade_isencao_dependente`. Default 12.
     */
    public static function getIdadeIsencao(mysqli $conn): int
    {
        if (self::$idadeIsencaoCache !== null) {
            return self::$idadeIsencaoCache;
        }
        $r = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'idade_isencao_dependente' LIMIT 1");
        if ($r && ($row = $r->fetch_assoc()) && is_numeric($row['valor'])) {
            $v = (int) $row['valor'];
            // Bound defensivo: 0..18
            if ($v < 0)  $v = 0;
            if ($v > 18) $v = 18;
            self::$idadeIsencaoCache = $v;
        } else {
            self::$idadeIsencaoCache = 12;
        }
        return self::$idadeIsencaoCache;
    }

    /** Limpa o cache (use após salvar nova config). */
    public static function limparCache(): void
    {
        self::$idadeIsencaoCache = null;
    }

    /**
     * Calcula idade em anos completos a partir de data de nascimento (YYYY-MM-DD).
     * Retorna null se a data for inválida ou vazia.
     */
    public static function calcularIdade(?string $nascimento, ?string $dataReferencia = null): ?int
    {
        if (empty($nascimento)) return null;
        try {
            $nasc = new DateTime($nascimento);
            $ref  = $dataReferencia ? new DateTime($dataReferencia) : new DateTime();
            return $nasc->diff($ref)->y;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Retorna 1 (isento) ou 0 (cobra) com base na idade do dependente HOJE.
     *
     * - Se `idade <= idade_isencao_dependente` → 1 (não cobra).
     * - Se `idade > idade_isencao_dependente` → 0 (cobra).
     * - Se `nascimento` vazio/inválido → null (chamador decide o fallback).
     */
    public static function calcularCobrar(mysqli $conn, ?string $nascimento): ?int
    {
        $idade = self::calcularIdade($nascimento);
        if ($idade === null) return null;
        $limite = self::getIdadeIsencao($conn);
        return ($idade <= $limite) ? 1 : 0;
    }

    /**
     * Retorna 1 (isento) ou 0 (cobra) com base na idade do dependente
     * NA DATA da reserva (importante para reservas futuras / passadas).
     */
    public static function calcularCobrarNaData(mysqli $conn, ?string $nascimento, string $dataReserva): ?int
    {
        $idade = self::calcularIdade($nascimento, $dataReserva);
        if ($idade === null) return null;
        $limite = self::getIdadeIsencao($conn);
        return ($idade <= $limite) ? 1 : 0;
    }
}
