<?php
// API para exportar CSV de usuários sem CPF
// Não requer autenticação - acesso público para administradores

include_once(__DIR__ . '/../conexao.php');

// Buscar usuários sem CPF (apenas ativos)
$sql = "SELECT id, nome, email, categoria, ativo, ultimo_login 
        FROM usuarios 
        WHERE (cpf IS NULL OR cpf = '' OR cpf = '0' OR cpf = '000.000.000-00')
        AND ativo = 1
        ORDER BY nome ASC";

$result = $conn->query($sql);

if (!$result) {
    die("Erro na consulta: " . $conn->error);
}

$usuarios_sem_cpf = [];
while ($row = $result->fetch_assoc()) {
    $usuarios_sem_cpf[] = $row;
}

// Configurar headers para download
$filename = 'usuarios_sem_cpf_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00');

// Criar arquivo CSV
$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Função para escrever CSV com ponto e vírgula como separador
function write_csv_line($output, $fields) {
    $line = '';
    foreach ($fields as $i => $field) {
        if ($i > 0) $line .= ';';
        // Se o campo contém vírgula, ponto e vírgula ou aspas, colocar entre aspas
        if (strpos($field, ',') !== false || strpos($field, ';') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            $field = '"' . str_replace('"', '""', $field) . '"';
        }
        $line .= $field;
    }
    fwrite($output, $line . "\n");
}

// Cabeçalho do relatório
write_csv_line($output, ['RELATÓRIO DE USUÁRIOS ATIVOS SEM CPF']);
write_csv_line($output, ['Gerado em: ' . date('d/m/Y H:i:s')]);
write_csv_line($output, ['Total de usuários ativos: ' . count($usuarios_sem_cpf)]);
write_csv_line($output, []);

// Cabeçalho da tabela
write_csv_line($output, ['ID', 'Nome', 'E-mail', 'Categoria', 'Status', 'Último Login']);

// Dados dos usuários
foreach ($usuarios_sem_cpf as $usuario) {
    $status = $usuario['ativo'] == 1 ? 'Ativo' : 'Inativo';
    $ultimo_login = $usuario['ultimo_login'] ? date('d/m/Y', strtotime($usuario['ultimo_login'])) : 'Nunca';
    
    write_csv_line($output, [
        $usuario['id'],
        $usuario['nome'],
        $usuario['email'] ?: 'Não informado',
        $usuario['categoria'],
        $status,
        $ultimo_login
    ]);
}

// Linha em branco
write_csv_line($output, []);

// Resumo
write_csv_line($output, ['RESUMO']);
$total_usuarios = count($usuarios_sem_cpf);

write_csv_line($output, ['Total de usuários ativos sem CPF', $total_usuarios]);
write_csv_line($output, ['Status', 'Todos ativos']);

// Linha em branco
write_csv_line($output, []);

// Rodapé
write_csv_line($output, ['Relatório gerado automaticamente pelo Sistema de Presença']);
write_csv_line($output, ['Gerado em: ' . date('d/m/Y H:i:s')]);

fclose($output);
exit;
?>
