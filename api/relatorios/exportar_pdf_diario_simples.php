<?php
session_start();

include_once(__DIR__ . '/../conexao.php');

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

$tipo = $_GET['tipo'] ?? 'diario';
$data = $_GET['data'] ?? date('Y-m-d');

try {
    // Buscar reservas próprias
    $sql_proprias = "SELECT u.nome, 1 as quantidade, 'Própria' as origem, ra.data, ra.valor_refeicao as valor
                    FROM reservas_almoco ra
                    JOIN usuarios u ON ra.id_usuario = u.id
                    WHERE ra.data = ?
                    ORDER BY u.nome ASC";
    
    $stmt = $conn->prepare($sql_proprias);
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result_proprias = $stmt->get_result();
    
    $reservas_proprias = [];
    while ($row = $result_proprias->fetch_assoc()) {
        $reservas_proprias[] = $row;
    }
    $stmt->close();
    
    // Buscar reservas adicionais
    $sql_adicionais = "SELECT u.nome as usuario_nome, d.nome as dependente_nome, ra.quantidade, 'Adicional' as origem, ra.data, 
                      (ra.valor_refeicao + ra.valor_marmitex) as valor
                      FROM reservas_adicionais ra
                      JOIN dependentes d ON ra.id_dependente = d.id
                      JOIN usuarios u ON d.id_usuario = u.id
                      WHERE ra.data = ?
                      ORDER BY u.nome ASC, d.nome ASC";
    
    $stmt = $conn->prepare($sql_adicionais);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta de reservas adicionais: " . $conn->error);
    }
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result_adicionais = $stmt->get_result();
    
    $reservas_adicionais = [];
    while ($row = $result_adicionais->fetch_assoc()) {
        $reservas_adicionais[] = $row;
    }
    $stmt->close();
    
    // Calcular totais
    $total_proprias = count($reservas_proprias);
    $total_adicionais = 0;
    $valor_total_proprias = 0;
    $valor_total_adicionais = 0;
    
    // Calcular valores das reservas próprias
    foreach ($reservas_proprias as $item) {
        $valor_total_proprias += $item['valor'];
    }
    
    // Calcular valores das reservas adicionais
    foreach ($reservas_adicionais as $item) {
        $total_adicionais += $item['quantidade'];
        $valor_total_adicionais += $item['valor'];
    }
    
    $total_geral = $total_proprias + $total_adicionais;
    $valor_total_geral = $valor_total_proprias + $valor_total_adicionais;
    
    // Retornar HTML simples
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Relatório Diário - <?= date('d/m/Y', strtotime($data)) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .total { font-weight: bold; background-color: #e6f3ff; }
        </style>
    </head>
    <body>
        <h1>Relatório Diário - <?= date('d/m/Y', strtotime($data)) ?></h1>
        
        <h2>Reservas Próprias</h2>
        <table>
            <tr>
                <th>Nome</th>
                <th>Quantidade</th>
                <?php if ($tipo === 'diario_completo'): ?>
                <th>Valor</th>
                <?php endif; ?>
            </tr>
            <?php foreach ($reservas_proprias as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['nome']) ?></td>
                <td><?= $item['quantidade'] ?></td>
                <?php if ($tipo === 'diario_completo'): ?>
                <td>R$ <?= number_format($item['valor'], 2, ',', '.') ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2>Reservas Adicionais</h2>
        <table>
            <tr>
                <th>Usuário</th>
                <th>Dependente</th>
                <th>Quantidade</th>
                <?php if ($tipo === 'diario_completo'): ?>
                <th>Valor</th>
                <?php endif; ?>
            </tr>
            <?php foreach ($reservas_adicionais as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['usuario_nome']) ?></td>
                <td><?= htmlspecialchars($item['dependente_nome']) ?></td>
                <td><?= $item['quantidade'] ?></td>
                <?php if ($tipo === 'diario_completo'): ?>
                <td>R$ <?= number_format($item['valor'], 2, ',', '.') ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2>Resumo</h2>
        <table>
            <tr>
                <th>Tipo</th>
                <th>Quantidade</th>
                <?php if ($tipo === 'diario_completo'): ?>
                <th>Valor Total</th>
                <?php endif; ?>
            </tr>
            <tr>
                <td>Reservas Próprias</td>
                <td><?= $total_proprias ?></td>
                <?php if ($tipo === 'diario_completo'): ?>
                <td>R$ <?= number_format($valor_total_proprias, 2, ',', '.') ?></td>
                <?php endif; ?>
            </tr>
            <tr>
                <td>Reservas Adicionais</td>
                <td><?= $total_adicionais ?></td>
                <?php if ($tipo === 'diario_completo'): ?>
                <td>R$ <?= number_format($valor_total_adicionais, 2, ',', '.') ?></td>
                <?php endif; ?>
            </tr>
            <tr class="total">
                <td><strong>TOTAL GERAL</strong></td>
                <td><strong><?= $total_geral ?></strong></td>
                <?php if ($tipo === 'diario_completo'): ?>
                <td><strong>R$ <?= number_format($valor_total_geral, 2, ',', '.') ?></strong></td>
                <?php endif; ?>
            </tr>
        </table>
        
        <p><small>Relatório gerado em: <?= date('d/m/Y H:i:s') ?></small></p>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    echo "Erro ao gerar relatório: " . $e->getMessage();
}
?>
