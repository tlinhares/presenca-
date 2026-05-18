<?php
// verificar_remocoes.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

//header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../api/conexao.php';
include_once(__DIR__ . '/../utils/config.php');

// Caminho do log
$dataLog = date('Y-m-d');
$log_file = __DIR__ . "/../logs/remocoes_facial_$dataLog.log";
function registrar_log($mensagem) {
    global $log_file;
    $hora = date('H:i:s');
    file_put_contents($log_file, "[$hora] $mensagem\n", FILE_APPEND);
    echo "[$hora] $mensagem<br>\n";
}


// Obter configurações do dispositivo facial
$ip = get_config('ip_dispositivo_facial');
$porta = get_config('porta_dispositivo_facial', '80');
$usuario = get_config('usuario_dispositivo_facial');
$senha = get_config('senha_dispositivo_facial');

if (!$ip || !$usuario || !$senha) {
    registrar_log("Erro: Dados do dispositivo facial incompletos.");
    exit;
}

$hoje = date('Y-m-d');

registrar_log("------------------------Processo Iniciado------------------------");

// Busca todos os registros sincronizados
$query = "SELECT id, id_usuario, origem, id_reserva, status FROM facial_sync WHERE status = 'sincronizado' AND data = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $hoje);
$stmt->execute();
$result = $stmt->get_result();

$removidos = [];
$erros = [];

while ($row = $result->fetch_assoc()) {
    $id_sync = $row['id'];
    $id_usuario = $row['id_usuario'];
    $origem = $row['origem'];
    $id_reserva = $row['id_reserva'];

    $temReservausuario = "nao";
    $temReservaadicional = "nao";

    if ($origem === 'usuario') {
        $check = $conn->prepare("SELECT id, id_usuario FROM reservas_almoco WHERE id = ?");
        $check->bind_param("i", $id_reserva);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) 
            {

            $temReservausuario = "sim";
        

               
                    echo 'temReservausuario: '. $temReservausuario ."<br>";
                    echo 'excluir: '.  'NAO' ."<br>";
                    echo 'id_usuario: '.  $id_usuario ."<br>";
                    echo 'id_reserva: '.  $id_reserva ."<br>";
                    echo 'id_sync: '. $id_sync."<br>";
                    echo "<br>";
                

         }
            else
         {
                    
                    /*
                    echo 'temReservausuario: '. $temReservausuario ."<br>";
                    echo 'excluir: '.  'SIM' ."<br>"; 
                    echo 'id_sync: '. $id_sync."<br>";
                    echo "<br>";  
                    */

                    // Remover da tabela facial_sync
                    $sql = "DELETE FROM facial_sync WHERE id = $id_sync";
                    $conn->query($sql);
                    registrar_log("Removendo ID $id_usuario ($origem) - Sync ID $id_sync");
                    
                    $url = "http://$ip:$porta/cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]=$id_usuario";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                    curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$senha");
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                    $resposta = curl_exec($ch);
                    $erro = curl_error($ch);
                    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($codigo == 200 && strpos($resposta, 'OK') !== false) {
                        registrar_log("→ Dispositivo: Remoção de $id_usuario OK.");;
                    } else {
                        registrar_log("→ Erro ao remover $id_usuario do dispositivo. Código: $codigo | Erro: $erro | Resposta: $resposta");
                    }
                 }
        

        $check->close();
    }

    if ($origem === 'dependente') {
        $check = $conn->prepare("SELECT id, id_dependente FROM reservas_adicionais WHERE id = ?");
        $check->bind_param("i", $id_reserva);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {


            $temReservaadicional = "sim";
                    echo 'temReservaadicional: '. $temReservaadicional ."<br>";
                    echo 'excluir: '.  'NAO' ."<br>";
                    echo 'id_usuario: '.  $id_usuario ."<br>";
                    echo 'id_reserva: '.  $id_reserva ."<br>";
                    echo 'id_sync: '. $id_sync."<br>";
                    echo "<br>";
   

    }
    else
         {
                    echo 'temReservaadicional: '. $temReservaadicional ."<br>";
                    echo 'excluir: '. 'SIM' ."<br>"; 
                    echo 'id_sync: '. $id_sync."<br>";
                    echo "<br>"; 

                     // Remover da tabela facial_sync
                    // Remover da tabela facial_sync
                    $sql = "DELETE FROM facial_sync WHERE id = $id_sync";
                    $conn->query($sql);
                    registrar_log("Removendo ID $id_usuario ($origem) - Sync ID $id_sync");
                    
                    $url = "http://$ip:$porta/cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]=$id_usuario";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                    curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$senha");
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                    $resposta = curl_exec($ch);
                    $erro = curl_error($ch);
                    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($codigo == 200 && strpos($resposta, 'OK') !== false) {
                        registrar_log("→ Dispositivo: Remoção de $id_usuario OK.");
                    } else {
                        registrar_log("→ Erro ao remover $id_usuario do dispositivo. Código: $codigo | Erro: $erro | Resposta: $resposta");
                    }
     }



        $check->close();
    }


    


}
    /*


    if (!$temReserva) {
        // Remover da tabela facial_sync
        $conn->query("DELETE FROM facial_sync WHERE id = $id_sync");

        // Definir o identificador correto para exclusão no facial
        //$id_facial = ($origem === 'usuario') ? $id_usuario : $id_reserva;

        // Monta URL da API de remoção
        $url = "http://$ip:$porta/cgi-bin/AccessUser.cgi?action=removeMulti&UserIDList[0]=$id_usuario";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$senha");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($codigo == 200 && strpos($resposta, 'OK') !== false) {
            $removidos[] = ["id" => $id_usuario, "origem" => $origem];
        } else {
            $erros[] = ["id" => $id_usuario, "erro" => $erro ?: $resposta];
        }
    }
}

*/
$conn->close();
registrar_log("Processo finalizado.");
/*


echo json_encode([
    'status' => 'ok',
    'removidos' => $removidos,
    'erros' => $erros
]);

*/
?>
