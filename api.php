<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, X-Requested-With, enctype, Content-Description");
header("Content-Type: application/json; charset=utf-8");

$host = 'monorail.proxy.rlwy.net:52314'; // Endereço do servidor MySQL
$username = 'root'; // Nome de usuário do MySQL
$password = 'RFvpUdFgVNptamyhkfetrDzUyOzRDFms'; // Senha do MySQL
$database = 'base_de_dados'; // Nome do banco de dados

// Criando uma nova conexão mysqli
$con = new mysqli($host, $username, $password, $database);

if ($con->connect_error) {
    die("Falha na conexão: " . $con->connect_error);
}

function executarConsulta($con, $sql, $unico = false) {
    $resultado = $con->query($sql);
    if ($unico) {
        return $resultado->fetch_assoc();
    } else {
        $dados = array();
        while ($linha = $resultado->fetch_assoc()) {
            $dados[] = $linha;
        }
        return $dados;
    }
}

function construirClausulaWhere($con, $params) {
    $clausulasWhere = [];
    foreach ($params as $chave => $valor) {
        if ($chave !== 'resource' && $chave !== 'column' && $chave !== 'value') {
            $chaveEscapada = "`" . $con->real_escape_string($chave) . "`";
            $valorEscapado = $con->real_escape_string($valor);
            $clausulasWhere[] = "$chaveEscapada='$valorEscapado'";
        }
    }
    return $clausulasWhere ? 'WHERE ' . implode(' AND ', $clausulasWhere) : '';
}

function tratarRequisicao($con, $tabela) {
    $metodo = $_SERVER['REQUEST_METHOD'];
    $params = $_GET;
    $dados = json_decode(file_get_contents("php://input"), true);

    switch ($metodo) {
        case 'GET':
            if (isset($params['column']) && isset($params['value'])) {
                $coluna = $con->real_escape_string($params['column']);
                $valor = $con->real_escape_string($params['value']);
                $sql = "SELECT * FROM `$tabela` WHERE `$coluna`='$valor'";
            } else {
                $clausulaWhere = construirClausulaWhere($con, $params);
                $sql = "SELECT * FROM `$tabela` $clausulaWhere";
            }
            $resposta = executarConsulta($con, $sql);
            exit(json_encode($resposta));

        case 'PUT':
            if (isset($params['column']) && isset($params['value'])) {
                $coluna = $con->real_escape_string($params['column']);
                $valor = $con->real_escape_string($params['value']);
                $camposAtualizar = [];
                foreach ($dados as $chave => $valorAtualizar) {
                    $chaveEscapada = "`" . $con->real_escape_string($chave) . "`";
                    $valorEscapado = $con->real_escape_string($valorAtualizar);
                    $camposAtualizar[] = "$chaveEscapada = '$valorEscapado'";
                }
                
                $sql = "UPDATE `$tabela` SET " . implode(", ", $camposAtualizar) . " WHERE `$coluna` = '$valor'";
                
                if ($con->query($sql)) {
                    if ($con->affected_rows > 0) {
                        exit(json_encode(array('status' => 'Sucesso', 'updated_rows' => $con->affected_rows)));
                    } else {
                        exit(json_encode(array('status' => 'Nenhuma linha afetada')));
                    }
                } else {
                    exit(json_encode(array('status' => 'Não Funcionou', 'erro' => $con->error)));
                }
            }
            break;

        case 'POST':
            $colunas = implode(", ", array_map(fn($coluna) => "`" . $con->real_escape_string($coluna) . "`", array_keys($dados)));
            $valores = implode(", ", array_map(fn($v) => "'" . $con->real_escape_string($v) . "'", $dados));
            $sql = "INSERT INTO `$tabela` ($colunas) VALUES ($valores)";
            $status = $con->query($sql) ? ['ID' => $con->insert_id] + $dados : ['status' => 'Não Funcionou', 'erro' => $con->error];
            exit(json_encode($status));

        case 'DELETE':
            if (isset($params['column']) && isset($params['value'])) {
                $coluna = $con->real_escape_string($params['column']);
                $valor = $con->real_escape_string($params['value']);
                $sql = "DELETE FROM `$tabela` WHERE `$coluna`='$valor'";
                if ($con->query($sql)) {
                    if ($con->affected_rows > 0) {
                        exit(json_encode(array('status' => 'Sucesso', 'deleted_rows' => $con->affected_rows)));
                    } else {
                        exit(json_encode(array('status' => 'Nenhuma linha afetada')));
                    }
                } else {
                    exit(json_encode(array('status' => 'Não Funcionou', 'erro' => $con->error)));
                }
            }
            break;

        default:
            exit(json_encode(['status' => 'Método não suportado']));
    }
}

if (isset($_GET['resource'])) {
    $recurso = $_GET['resource'];

    switch ($recurso) { 
        case 'categorias':
            tratarRequisicao($con, 'categorias');
            break;
        case 'usuarios':
            tratarRequisicao($con, 'usuarios');
            break;
        case 'transacao':
            tratarRequisicao($con, 'transacao');
            break;
        case 'planejamento_mensal':
            tratarRequisicao($con, 'planejamento_mensal');
            break;
       
        
        default:
            exit(json_encode(['status' => 'Tabela não encontrada']));
    }
}
?>
