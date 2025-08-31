<?php
date_default_timezone_set('America/Sao_Paulo');
require_once  "ExceptionLogs.php";
function getClientAccess(){
    return array(
        "host" => "",
        "user" => "",
        "pass" => "",
        "bd" => "inforpark",
        "port"=>3306
    );
}

function ConectDataBaseContext($context = null)
{

    $conexao = null;
    try {
        if (is_null($context)) {
            $context=getClientAccess();
            $host =$context["host"];
            $user = $context["user"];
            $pass =$context["pass"];
            $bd = $context["bd"];
            $port =$context["port"];
            $dsn = sprintf("mysql:host=%s;dbname=%s;port:%s", $host, $bd, $port);
        }else{
            $host =$context["host"];
            $user = $context["user"];
            $pass =$context["pass"];
            $bd = $context["bd"];
            $port =$context["port"];
            $dsn = sprintf("mysql:host=%s;dbname=%s;port:%s", $host, $bd, $port);
        }
        $conexao = new PDO($dsn, $user, $pass);
        $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) {
        print_r($e);
    }

    return $conexao;

}


function getInterpletacaoCustomersOnLine($context)
{
    $customers = [];
    try {
        $sql="select cu.client_id as clientId , cu.id as filial from client_units cu   where   "; 
        $sql.="cu.tipo_interpretacao=1 ORDER BY cu.client_id ASC";
        $stmt = $context->prepare($sql);

        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {

        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $customers;
}


// Funções
function abrirEstabelecimento($context,$sensores=[]) {
    echo "🔒 Abertos estabelecimento\n";
    $sensoresList =  explode(",",$sensores);
    $abertos=0;
    foreach($sensoresList as $row){
         try {
            $stmt = $context->prepare("update register_slots set  status='L', updated_at=now()  where sensor_id=:sensorID");
            $stmt->bindValue(":sensorID", $row);
                $stmt->execute();
            if ($stmt->rowCount() > 0) {
               $abertos++;
            }
        }catch (Exception $e) {}
    }

    return $abertos;

}

function fecharEstabelecimento($context,$sensores=[]) {
    echo "🔒 Fechando estabelecimento\n";
    $sensoresList =  explode(",",$sensores);
    $fechados=0;
    foreach($sensoresList as $row){
         try {
            $stmt = $context->prepare("update register_slots set  status='O', updated_at=now()  where sensor_id=:sensorID");
            $stmt->bindValue(":sensorID", $row);
                $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $fechados++;
            }
        }catch (Exception $e) {}
        
    }

    return $fechados;
}
print("Iniciando Base....\n");
$handler = ConectDataBasecontext();
$funcionamentos=[
    ["clientId" => 20 ,"filial" =>21,"sensores"=>"1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16"]
];
$now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
$dayOfWeek = (int)$now->format('w'); // 0 (Dom) a 6 (Sáb)
$hour = (int)$now->format('H');
$minute = (int)$now->format('i');
print("Iniciando....\n");
if (!is_null($handler)) {
        $customers = getInterpletacaoCustomersOnLine($handler);
        foreach($customers as $key => $customer){
            $idClient = $customer["clientId"];
            $idClientFilial = $customer["filial"];
            foreach($funcionamentos as $keyfuncionamento => $funcionamento){
                if($customer['clientId']==$funcionamento['clientId'] && $customer['filial']==$funcionamento['filial']){
                    $accessDataBase = getClientAccess();
                    $db=str_replace("i","I",str_replace("p","P",$accessDataBase["bd"]));
                    $accessDataBase["bd"] = sprintf("%s_%s_%s", $db,
                        str_pad($idClient, '4', '0', STR_PAD_LEFT), str_pad($idClientFilial, '4', '0', STR_PAD_LEFT));
                    $context = ConectDataBasecontext($accessDataBase);
                    // Verifica se está no horário de funcionamento
                    $estaAberto = false;
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 6) {
                        // Segunda a sábado: 06h00 às 22h00
                        if ($hour >= 6 && $hour < 22) {
                            $estaAberto = true;
                        }
                    } elseif ($dayOfWeek == 0) {
                        // Domingo: 07h00 às 20h00
                        if ($hour >= 7 && $hour < 20) {
                            $estaAberto = true;
                        }
                    }
                  
                    // Aqui você decide o que fazer com base no estado
                    if ($estaAberto) {
                       print_r(abrirEstabelecimento($context,$funcionamento['sensores']));
                    } else {
                       print_r(fecharEstabelecimento($context,$funcionamento['sensores']));
                    }


                }
            }
        }


}

 

