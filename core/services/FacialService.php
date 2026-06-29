<?php
/**
 * FacialService — utilitários para manter a fila de sincronização facial
 * consistente quando reservas são canceladas.
 *
 * Contexto do sistema:
 * - A tabela `facial_sync` representa quem precisa estar/foi sincronizado com
 *   o dispositivo Intelbras de reconhecimento facial em determinada data.
 * - Um cron (`cron/verificar_remocoes.php`, a cada 1 min) detecta usuários
 *   sincronizados que não têm mais reserva e os remove do dispositivo.
 *
 * Problema histórico: o cron detecta automaticamente, mas com delay de até
 * 1 minuto. E em alguns casos (HTTP 501 do dispositivo, ENUM sem 'removido')
 * ficava preso em loop. Com este helper, o cancelamento marca explicitamente
 * a entrada como "precisa remover", deixando o cron com trabalho focado e
 * acelerando o processo.
 */

class FacialService
{
    /**
     * Marca as entradas de fila sincronizadas como pendentes de remoção
     * para o usuário+data informados. Não envia para o dispositivo (isso é
     * trabalho do cron); apenas sinaliza que devem ser removidas.
     *
     * @param mysqli $conn
     * @param int    $idUsuario  Id do usuário/dependente (varia conforme `origem`).
     * @param string $origem     'usuario' ou 'dependente'.
     * @param string $data       YYYY-MM-DD da reserva cancelada.
     * @return int Quantas linhas foram marcadas.
     */
    public static function marcarParaRemocao(mysqli $conn, int $idUsuario, string $origem, string $data): int
    {
        if ($idUsuario <= 0 || $data === '' || !in_array($origem, ['usuario', 'dependente'], true)) {
            return 0;
        }
        try {
            $stmt = $conn->prepare(
                "UPDATE facial_sync
                 SET tentativas = 0,
                     detalhes = CONCAT(COALESCE(detalhes, ''), CHAR(10),
                                       '[Cancelamento marcado em ', NOW(), ']')
                 WHERE id_usuario = ? AND data = ? AND origem = ? AND status = 'sincronizado'"
            );
            if (!$stmt) return 0;
            $stmt->bind_param('iss', $idUsuario, $data, $origem);
            $stmt->execute();
            $afetados = $stmt->affected_rows;
            $stmt->close();
            return $afetados;
        } catch (Throwable $e) {
            error_log('FacialService::marcarParaRemocao falhou: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cancela TODAS as entradas de fila do usuário+data, inclusive `pendente`
     * (que ainda não foi sincronizada). Útil quando o usuário cancela
     * reserva ANTES da sincronização ter acontecido — assim o cron de envio
     * não vai mais tentar sincronizar algo que foi cancelado.
     *
     * Para entradas `pendente`: marca como `falha` com detalhe "Cancelado".
     * Para entradas `sincronizado`: deixa para o cron de remoção (não muda).
     *
     * @return int Quantas entradas pendentes foram canceladas.
     */
    public static function cancelarPendentes(mysqli $conn, int $idUsuario, string $origem, string $data): int
    {
        if ($idUsuario <= 0 || $data === '' || !in_array($origem, ['usuario', 'dependente'], true)) {
            return 0;
        }
        try {
            $stmt = $conn->prepare(
                "UPDATE facial_sync
                 SET status = 'falha',
                     detalhes = CONCAT(COALESCE(detalhes, ''), CHAR(10),
                                       '[Cancelado em ', NOW(), ' — reserva removida antes da sincronização]')
                 WHERE id_usuario = ? AND data = ? AND origem = ? AND status = 'pendente'"
            );
            if (!$stmt) return 0;
            $stmt->bind_param('iss', $idUsuario, $data, $origem);
            $stmt->execute();
            $afetados = $stmt->affected_rows;
            $stmt->close();
            return $afetados;
        } catch (Throwable $e) {
            error_log('FacialService::cancelarPendentes falhou: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Combo: chama os dois métodos acima. Use sempre que uma reserva for
     * cancelada/excluída.
     */
    public static function onReservaCancelada(mysqli $conn, int $idUsuario, string $origem, string $data): array
    {
        return [
            'marcadas_remocao'   => self::marcarParaRemocao($conn, $idUsuario, $origem, $data),
            'pendentes_canceladas' => self::cancelarPendentes($conn, $idUsuario, $origem, $data),
        ];
    }
}
