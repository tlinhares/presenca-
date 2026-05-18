<?php
// Configuração de fuso horário baseada na configuração do sistema
try {
    // Tentar conectar ao banco para ler o fuso horário
    $host = 'localhost';
    $usuario = 'root';
    $senha = '@Arcs2901';
    $banco = 'presenca_aom';
    
    // Usar uma variável local para não interferir na conexão global
    $conn_timezone = new mysqli($host, $usuario, $senha, $banco);
    
    if (!$conn_timezone->connect_error) {
        $stmt = $conn_timezone->prepare("SELECT valor FROM configuracoes WHERE chave = 'fuso_horario'");
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows > 0) {
            $config = $resultado->fetch_assoc();
            $fuso_horario = $config['valor'];
            date_default_timezone_set($fuso_horario);
        } else {
            // Fallback para o fuso horário padrão
            date_default_timezone_set('America/Cuiaba');
        }
        
        $conn_timezone->close();
    } else {
        // Fallback se não conseguir conectar
        date_default_timezone_set('America/Cuiaba');
    }
} catch (Exception $e) {
    // Fallback em caso de erro
    date_default_timezone_set('America/Cuiaba');
}
?>
