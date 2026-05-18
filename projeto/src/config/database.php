<?php
/**
 * Configuração de conexão com o banco de dados
 */
class Database {
    private $host;
    private $db_name = 'sistema_construtora';
    private $username;
    private $password;
    private $conn;

    public function __construct()
    {
        require_once __DIR__ . '/../../../utils/env.php';
        $this->host     = env('DB_HOST', 'localhost');
        $this->username = env('DB_USER', 'root');
        $this->password = env('DB_PASS', '');
    }

    /**
     * Obtém a conexão com o banco de dados
     * @return PDO
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8')
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo 'Erro de conexão: ' . $e->getMessage();
        }

        return $this->conn;
    }
}
?>
