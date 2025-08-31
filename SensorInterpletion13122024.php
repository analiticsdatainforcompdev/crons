<?php

require_once  "ExceptionLogs.php";
require_once  "Database.php";
require_once("ControleCrons.php");




$echos = true;
define("VAGA_LIVRE", "L");
define("VAGA_OCUPADA", "O");

/*Funcoes de Apoio*/

function printIf($display, $message)
{
    if ($display) {
        echo $message . "\n";
    }
}





function dd($o){
    print("================================Debug==========================\n\n");
    print_r($o);
    print("================================Debug==========================\n\n");
    die();
}



function isTableInBase($context, $tableName)
{
    try {
        $stmt = $context->prepare("SHOW TABLES like :table");
        $stmt->bindValue(":table", $tableName);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (Exception $e) {

        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
}

function validarUnidadeSensor($context, $idUnidade)
{
    $sensor = null;
    try {
        $stmt = $context->prepare("SELECT id, register_sector_id, sensor_id, slot_code, `status`,
                                   univd_code, color, x, y, angle, created_at, updated_at FROM  
                                   register_slots where sensor_id=:idUnidade");
        $stmt->bindValue(":idUnidade", $idUnidade);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $sensor = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $sensor;
}

function validarInformacaoDoCliente($context, $idClient, $document)
{
    try {
        $stmt = $context->prepare("SELECT count(id) as rows_ FROM clients  where  id=:id_client and trim(document)=trim(:doc_client)");
        $stmt->bindValue(":id_client", $idClient);
        $stmt->bindValue(":doc_client", $document);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (intval($result["rows_"]) === 1) ? true : false;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
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

function recuperarMovimentoNaoProcessados($context)
{
    $movimentos = [];
    try {

        $stmt = $context->prepare("select codigo, nsr, status, `data`, hora, nuvem, codigosensor,controladora,
        created_at, updated_at ,temperatura  from movimentossensores where (isnull(nuvem) or nuvem='N')  order by nsr");


        /*

        $stmt = $context->prepare("select codigo, nsr, status, `data`, hora, nuvem, codigosensor,
                                  created_at, updated_at  from movimentossensores where (isnull(nuvem) or nuvem='N')  order by `data`,`hora` asc");
        
        
        */
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $movimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $movimentos;

}

function deletarMovimentoParaProcessados($context, $idMovimento)
{

    print("remover movimento\n");
    try {
        $stmt = $context->prepare("delete from  movimentossensores where  codigo=:idMoviment");
        $stmt->bindValue(":idMoviment", $idMovimento);
        $stmt->execute();
        if ($stmt->execute()) {
             print("remover movimento OK\n");
            return true;
        }

    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;

}


function alterMovimentoParaProcessados($context, $idMovimento)
{

    print("alterMovimentoParaProcessados\n");
    try {

        $stmt = $context->prepare("update movimentossensores set  nuvem='S', updated_at=now()  where codigo=:idMoviment");
        $stmt->bindValue(":idMoviment", $idMovimento);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {

            return true;
        }

    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;

}

function atualizarStatusdoSensorLido($context, $idSensor, $status)
{
    print("(Atualizando) atualizarStatusdoSensorLido   update register_slots set  status='{$status}'  where id={$idSensor} \n");

    try {
        $stmt = $context->prepare("update register_slots set  status='{$status}'  where id={$idSensor}");

        print_r("update register_slots set  status='{$status}'  where id={$idSensor}\n\n");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            print("(Atualizando)  atualizarStatusdoSensorLido  -  OK\n");
            return true;
        }

        print(" (Atualizando)  atualizarStatusdoSensorLido  -  Update Não Processado\n");

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;
}

function getRegisterSlotPair($context, $idSensor)
{

    $registerslotpair = null;
    try {

        $stmt = $context->prepare("select id, register_slot_id, occupation, `release`, created_at, updated_at  from register_slot_pairs
                                   where (register_slot_id=:sensor_id and   `release` IS NULL )  order by id desc ;");

        $stmt->bindValue(":sensor_id", $idSensor);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {

        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotpair;
}



function setRegisterSlotPairRelease($context,$sensor,$registerSlotPairs)
{
   try {


      
        print("Ok - 2021 0 1 ----> update register_slot_pairs  set  `release`='".$sensor["release"]."',updated_at=now()  where id=".$registerSlotPairs[0]["id"]."   ");
        
        $stmt=null;
        $stmt = $context->prepare("update register_slot_pairs  set  `release`='{$sensor["release"]}' ,updated_at=now()  where id={$registerSlotPairs[0]["id"]}");
     
     
     
        //$stmt->bindValue(":release", $sensor["release"]);
        //$stmt->bindValue(":id", $registerSlotPairs[0]["id"]);
     
     
        $stmt->execute();
        if ($stmt->rowCount() > 0) {

            print("OK --- - -- - update register_slot_pairs  set  `release`='".$sensor["release"]."',updated_at=now()  where id=".$registerSlotPairs[0]["id"]."   ");
       

            return true;
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;

}




function setRegisterSlotPairOccupation($context,$sensor,$registerSlotPairs)
{
   try {


        print("update register_slot_pairs  set  `occupation`=".$sensor["occupation"].",updated_at=now()  where id=".$registerSlotPairs[0]["id"]."   ");
        
        $stmt=null;
        $stmt = $context->prepare("update register_slot_pairs  set  `occupation`=':release' ,updated_at=now()  where id=:id");
        $stmt->bindValue(":occupation", $sensor["occupation"]);
        $stmt->bindValue(":id", $registerSlotPairs[0]["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;

}



/*temperatura*/

//#2023




function setRegisterSlotTemperatura($context,$sensor,$ultimaTemperatura)
{
    try {

        $stmt = $context->prepare("update register_slots  set  temperatura=:temperatura ,updated_at=now()  where id=:sensor");
        $stmt->bindValue(":temperatura", $ultimaTemperatura );
        $stmt->bindValue(":sensor", $sensor);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;

}





function getMaxTemperaturaSensorBy($context,$sensor)
{
    $registerslotpair = null;
    try {
        $stmt = $context->prepare("SELECT MAX(created_at) as created_at,temperatura  FROM movimentossensores  where codigosensor =:sensor GROUP  by 	temperatura  ORDER  by created_at desc   limit 0,1");
        $stmt->bindValue(":sensor", $sensor);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $registerslotpair;
}



function getTemperaturaSensorBy($context,$sensor)
{
    $registerslotpair = null;
    try {
        $stmt = $context->prepare("select temperatura from register_slots where id=:sensor;");
        $stmt->bindValue(":sensor", $sensor);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $registerslotpair;
}



function criarEAtualizarHistoricoDoSensor($context,$movimentoTemperatura){
   $newHistorico = null;

  
    try {
        $stmt = $context->prepare("INSERT INTO historicoTemperaturas(sensor, `data`, hora, temperatura, `status`,sensor_id,controladora_id, created_at, interpretacao_at) ".
                                  " values(:sensor,:data,:hora,:temperatura,:status,:sensor_id,:controladora_id,:created_at,now()) ");

        $stmt->bindValue(":sensor", $movimentoTemperatura["sensor"]);
        $stmt->bindValue(":data", $movimentoTemperatura["data"]);
        $stmt->bindValue(":hora", $movimentoTemperatura["hora"]);
        $stmt->bindValue(":temperatura", $movimentoTemperatura["temperatura"]);
        $stmt->bindValue(":status", $movimentoTemperatura["status"]);
        $stmt->bindValue(":sensor_id", $movimentoTemperatura["sensor"]);
        $stmt->bindValue(":controladora_id", $movimentoTemperatura["controladora"]);
        $stmt->bindValue(":created_at", $movimentoTemperatura["created_at"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $newHistorico = $context->lastInsertId();
        }

    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $newHistorico;
}


function haSensorTemTemperatura($sensorId,$listaDeSensoresAtivoComTemperatura){

    // print_r([$sensorId,$listaDeSensoresAtivoComTemperatura,is_array($listaDeSensoresAtivoComTemperatura)]);exit;
     if(is_null($listaDeSensoresAtivoComTemperatura)) return false;
     $ids = array_column($listaDeSensoresAtivoComTemperatura, 'id');
 
     return in_array($sensorId,$ids);
}



function getTemperaturaDefalt($context)
{
    $clientesTemTemperaturaDefault = null;
    try {

        $stmt = $context->prepare("SELECT  * FROM client_units x  where x.temp_alarme  is not null ");
        if($stmt->execute()){
           
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as $index =>  $row ){
                $clientesTemTemperaturaDefault[]=$row;
            }
        }
     

    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $clientesTemTemperaturaDefault;
}

function getSensorTemTemperatura($context)
{
    $sensors = null;
    try {

        $stmt = $context->prepare("SELECT * FROM register_slots rlst   where rlst.sensor_temp ='S'");
        if($stmt->execute()){
           
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
           

            foreach($rows as $index =>  $row ){
                $sensors[]=array("id"=>$row['id']);
            }
        }
     

    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $sensors;
}

/*temperatura*/





function createdRegisterSlotPair($context, $sensorLido)
{




    print("=========================Criando Slot   2    \n\n");
    print("insert into  register_slot_pairs(register_slot_id, occupation,created_at) values(". $sensorLido["register_slot_id"].",". $sensorLido["occupation"].",now()) ");
    print("=========================Criando Slot   2     2    \n\n");
    

   
   

    $newpairSersor = null;
    try {
        $stmt = $context->prepare("insert into  register_slot_pairs(register_slot_id, occupation,created_at) values(:register_slot_id,:occupation,now()) ");
        $stmt->bindValue(":register_slot_id", $sensorLido["register_slot_id"]);
        $stmt->bindValue(":occupation", $sensorLido["occupation"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $newpairSersor = $context->lastInsertId();
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $newpairSersor;
}

function registerSlotCollects($context, $collets)
{

    $newpairSersor = null;
    try {
        $stmt = $context->prepare("insert into  register_slot_collects
                                    (register_slot_id, register_slot_pair_id, nsr, `type`, sensor_id, version, created_at)
                                    values(:register_slot_id,:register_slot_pair_id,:nsr,:type,:sensor_id,:version,now()) ");



        $stmt->bindValue(":register_slot_id", $collets["register_slot_id"]);
        $stmt->bindValue(":register_slot_pair_id", $collets["register_slot_pair_id"]);
        $stmt->bindValue(":nsr", $collets["nsr"]);
        $stmt->bindValue(":type", $collets["type"]);
        $stmt->bindValue(":sensor_id", $collets["sensor_id"]);
        $stmt->bindValue(":version", $collets["version"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $newpairSersor = $context->lastInsertId();
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $newpairSersor;

}

function setRegisterSlotPairId($context, $colletsId, $registerSlotPairId)
{
    try {

        $stmt = $context->prepare("update register_slot_collects  set  register_slot_pair_id=:register_slot_pair_id ,updated_at=now()  where id=:id");
        $stmt->bindValue(":register_slot_pair_id", $registerSlotPairId);
        $stmt->bindValue(":id", $colletsId);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;

}

function getRegisterSector($context, $registerSectorId)
{
    $registerslotpair = null;
    try {

        $stmt = $context->prepare("select *   from register_sectors
                                   where (id=:registerSectorId) limit 0,1;");

        $stmt->bindValue(":registerSectorId", $registerSectorId);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotpair;
}

function getRowsCountsRegisterSlots($context, $registerSectorId, $state)
{
    $registerslotStatus = null;
    try {

        $stmt = $context->prepare("SELECT count(*) as rows_  FROM register_slots  where  `status`=:status and register_sector_id=:register_sector_id ");
        $stmt->bindValue(":status", $state);
        $stmt->bindValue(":register_sector_id", $registerSectorId);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotStatus["rows_"];

}



function getRowsCountsRegisterSlotsSummary($context, $registerCode, $state)
{
    $registerslotStatus = null;
    try {

        $stmt = $context->prepare("SELECT count(*) as rows_  FROM register_slots  where  `status`=:status and univd_code=:univd_code ");
        $stmt->bindValue(":status", $state);
        $stmt->bindValue(":univd_code", $registerCode);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotStatus["rows_"];

}







function getRowsCountsRegisterSlotsFloor($context, $registerSectorIdFloor)
{
    $registerslotpair = null;
    try {

        $stmt = $context->prepare("select   id, code, description, width, `length`, slot_width, 
                                   slot_length, image_width, image_length, slots, available, occupied, `map`, created_at, updated_at
                                   from register_floors where (id=:id) limit 0,1;");


        $stmt->bindValue(":id", $registerSectorIdFloor);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotpair;
}


 




function setRegisterSectorScore($context, $sectorSelected)
{

    print("\n\n"); 
    print("update register_sectors  set 
    available=".$sectorSelected["available"].",occupied=".$sectorSelected["occupied"]." ,updated_at=now()  where id=".$sectorSelected["id"]."");
    print("\n\n"); 


     try {
        $stmt = $context->prepare("update register_sectors  set 
                 available=:available,occupied=:occupied ,updated_at=now()  where id=:id");
        $stmt->bindValue(":available", $sectorSelected["available"]);
        $stmt->bindValue(":occupied", $sectorSelected["occupied"]);
        $stmt->bindValue(":id", $sectorSelected["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {

            
            return true;
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;

}






function setRegisterFloor($context, $floorSelected)
{
    try {
        $stmt = $context->prepare("update register_floors  set  available=:available,occupied=:occupied ,updated_at=now()  where id=:id");
        $stmt->bindValue(":available", $floorSelected["available"]);
        $stmt->bindValue(":occupied", $floorSelected["occupied"]);
        $stmt->bindValue(":id", $floorSelected["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;

}







function getRegisterTrees($context)
{
    $registerslotTrees = [];
    try {

        $stmt = $context->prepare("SELECT id, code, description, available, color, legend, created_at, updated_at  from register_univd_trees");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotTrees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotTrees;
}



function eSensorDeCamera($context,$sensor)
{

    
    $sensorPortaria =[];
    try {
        $id=$sensor["code"];
        $stmt = $context->prepare("SELECT id,univdid,univdtreeid from portarias where id=".$id);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $sensorPortaria = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $sensorPortaria;
}





function getRegisterUnivds($context)
{
    $registerslotpair = [];
    try {

        $stmt = $context->prepare("select  id, code, description, available, created_at, updated_at   from register_univds");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotpair;
}







function setRegisterUnivds($context, $univdSelected)
{


     
    $eSensorDeCamera=eSensorDeCamera($context,$univdSelected);
    if(count($eSensorDeCamera)>0) return false;
    

  
    print("Undvs Base ==========================\n");
    print(   "update register_univds  set  available={$univdSelected["available"]},updated_at=now()  where id={$univdSelected["id"]}");
    print("=====================================\n");


    try {
        $stmt = $context->prepare("update register_univds  set  available=:available,updated_at=now()  where id=:id");
        $stmt->bindValue(":available", $univdSelected["available"]);
        $stmt->bindValue(":id", $univdSelected["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;

}





function setRegisterTrees($context, $treesSelected)
{
  try {


        $eSensorDeCamera=eSensorDeCamera($context,$treesSelected);

        

        if(count($eSensorDeCamera)>0) return false;

        print("Undvs Trees==========================\n");
        print("update register_univd_trees  set  available=".$treesSelected["available"].",updated_at=now()  where id=".$treesSelected["id"]."");
        print("=====================================\n");
      

        $stmt = $context->prepare("update register_univd_trees  set  available=:available,updated_at=now()  where id=:id");
        $stmt->bindValue(":available", $treesSelected["available"]);
        $stmt->bindValue(":id", $treesSelected["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }

    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return false;

}






function getAllUndvTrees($context,$treesSelected)
{

    print("getAllUndvTrees Trees==========================\n");
        print("SELECT * FROM register_univd_trees_x_univds rutxs  join register_univds ru on ru.id=rutxs.register_univd_tree_id 
        where rutxs.register_univd_id={$treesSelected["id"]}");
        print("=======================getAllUndvTrees======\n");

        print_r($treesSelected);



    $registerslotTrees = [];
    try {

        $stmt = $context->prepare("SELECT * FROM register_univd_trees_x_univds rutxs  left  join register_univds ru on
                                 ru.id=rutxs.register_univd_tree_id 
                                  where rutxs.register_univd_id=:id");


        $stmt->bindValue(":id", $treesSelected["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotTrees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        print_r($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotTrees;
}


/*Funcoes de Apoio*/
/*Lógica de Execução*/

$ControleCrons =  new ControleCrons();
$clientesComtemperaturaDefault = null;
$sensoresComTemperaturaAtiva =null;


try {

    $_filiais_no_table=[
        10=>14
    ];
   


   

    //if(!$ControleCrons->excuteCron()) return;

    
    print("Iniciando Base....\n");
    $handler = ConectDataBasecontext();
    print("Iniciando....\n");
    if (!is_null($handler)) {
        $customers = getInterpletacaoCustomersOnLine($handler);
        $clientesComtemperaturaDefault =getTemperaturaDefalt($handler);
     
        foreach ($customers as $key => $customer) {

            $idClient = $customer["clientId"];
            $idClientFilial = $customer["filial"];
            printIf($echos, "-----Iniciando--Interpletação.");

            $accessDataBase = getClientAccess();
            $db=str_replace("i","I",str_replace("p","P",$accessDataBase["bd"]));

            /*

            $accessDataBase["bd"] = sprintf("%s_%s_%s", $db,
                str_pad($idClient, '4', '0', STR_PAD_LEFT), str_pad($idClient, '4', '0', STR_PAD_LEFT));

                */

                $accessDataBase["bd"] = sprintf("%s_%s_%s", $db,
                str_pad($idClient, '4', '0', STR_PAD_LEFT), str_pad($idClientFilial, '4', '0', STR_PAD_LEFT));
                




            printIf($echos, "-----  Acessando Banco [" . $db . "].\n\n\n");
            $contextDinamico = ConectDataBasecontext($accessDataBase);
            $sensoresComTemperaturaAtiva =getSensorTemTemperatura($contextDinamico);

        
            
            if (!isTableInBase($contextDinamico, "movimentossensores")) {
                printIf($echos, "----- Error  Acessando Banco [" . $accessDataBase["bd"] . "].\n\n\n");
            } else {

                /*Se tem a Tabela de Movimentos na Base*/
                printIf($echos, "-----Procesando  Banco [" . $accessDataBase["bd"] . "].");
                printIf($echos, "-----Recuperando Dados Não Processados (Sensor) [" . $accessDataBase["bd"] . "].");
                $movimentos = recuperarMovimentoNaoProcessados($contextDinamico);
                foreach ($movimentos as $movimento) {
                            $registerslotpair_id = '';
                            printIf($echos, "-----  Acessando Banco [" . $db . "].\n\n\n");
                            printIf($echos, "-----Movimento [".$movimento["status"]."] - Recuperando Dados Não Processados (Sensor) [" . $accessDataBase["bd"] . "].");
                            $IfDatavalida = explode("-", $movimento["data"]);


                            // print_r(["<pre>",$movimento["codigosensor"],$sensoresComTemperaturaAtiva]);die;



                            

                            if(haSensorTemTemperatura($movimento["codigosensor"],$sensoresComTemperaturaAtiva)){

                              
                                $movimentoTemperatura =  array(
                                    "sensor"=>$movimento["codigosensor"],
                                    "data"=>$movimento["data"],
                                    "hora"=>$movimento["hora"],
                                    "temperatura"=>$movimento["temperatura"],
                                    "controladora"=>$movimento["controladora"],
                                    "status"=>$movimento["status"],
                                    "created_at"=>$movimento["created_at"]
                                );
                                

                                                                                                             
                                printIf($echos, "-----Historico gravado Temperatura (Sensor [" . $movimento["codigosensor"] . "] ) [" . $accessDataBase["bd"] . "].");
                                if(criarEAtualizarHistoricoDoSensor($contextDinamico,$movimentoTemperatura)){
                                    $sensorInfTemperatura=getTemperaturaSensorBy($contextDinamico,$movimento["codigosensor"]);
                                    $sensorDetalhes=getMaxTemperaturaSensorBy($contextDinamico,$movimento["codigosensor"]);
                                    if(intval($sensorInfTemperatura["temperatura"])  != intval($sensorDetalhes["temperatura"])){
                                        setRegisterSlotTemperatura($contextDinamico,$movimento["codigosensor"],$sensorDetalhes["temperatura"]);
                                    }
                                }                                                                                                                                                                                                                                                                                                   
                          
                            }

                            if (strlen($IfDatavalida[0]) === 4 && strlen($IfDatavalida[1]) === 2 && strlen($IfDatavalida[2]) === 2) {
                                $dataHora = sprintf("%s %s", $movimento["data"], $movimento["hora"]);
                                printIf($echos, "-----Validando Unidade (Sensor [" . $movimento["codigosensor"] . "] ) [" . $accessDataBase["bd"] . "].");
                                $sensorLido = validarUnidadeSensor($contextDinamico, $movimento["codigosensor"]);
                                if (!is_null($sensorLido)) {
                                    printIf($echos, "\n-----Realizar Atualizaçoes  [" . $accessDataBase["bd"] . "].\n");
                                    $idSensor = $sensorLido["id"];
                                    $registerslotpair = getRegisterSlotPair($contextDinamico, $idSensor);
                                    switch ($movimento["status"]) {
                                        case "1":
                                            print("Liberação....\n\n");
                                            printIf($echos, "-----------------------//ATUALIZAR STATUS DA VAGA (Sensor [" . $movimento["codigosensor"] . "] ) [" . $accessDataBase["bd"] . "].");
                                            print_r( $sensorLido["status"] );
                                            if ($sensorLido["status"] !== VAGA_LIVRE) {
                                                if (atualizarStatusdoSensorLido($contextDinamico, $idSensor, VAGA_LIVRE)) {
                                                    if (is_null($registerslotpair) || empty($registerslotpair)) {
                                                        printIf($echos, "------'ERRO - VAGA LIBERADA, MAS NÃO FOI POSSÍVEL GERAR O PAR - NÃO HÁ REGISTRO AGUARDANDO LIBERAÇÃO\n'--------//Par (Sensor [" . $movimento["codigosensor"] . "] ) [" . $accessDataBase["bd"] . "].");
                                                    }else{
                                                        $sensorLido["release"] = $dataHora;
                                                        if (setRegisterSlotPairRelease($contextDinamico, $sensorLido,$registerslotpair)) {
                                                            //Atualizaçoes Gerais
                                                            //ATUALIZAR CONTAGEM DE VAGAS DO SETOR
                                                            //CONTAR TODAS AS VAGAS LIVRES NO SETOR:
                                                            $registerSectorFirst = (getRegisterSector($contextDinamico, $sensorLido["register_sector_id"]))[0];
                                                            $registerSectorFirst["available"] = getRowsCountsRegisterSlots($contextDinamico, $registerSectorFirst["id"], VAGA_LIVRE);
                                                            $registerSectorFirst["occupied"] = getRowsCountsRegisterSlots($contextDinamico, $registerSectorFirst["id"], VAGA_OCUPADA);
                                                            
                                                            setRegisterSectorScore($contextDinamico, $registerSectorFirst);
                                                            //ATUALIZAR CONTAGEM DE VAGAS DO PISO
                                                            //CONTAR TODAS AS VAGAS LIVRES NO PISO:
                                                            $registerfloorFirst = getRowsCountsRegisterSlotsFloor($contextDinamico,$registerSectorFirst["register_floor_id"]);
                                                            $available = 0;
                                                            $occupied = 0;
                                                            foreach($registerfloorFirst as $index=>$registersectorforeach){
                                                                if (!empty($registersectorforeach["available"])) {
                                                                    $available += $registersectorforeach["available"];
                                                                }
                                                                if (!empty($registersectorforeach["occupied"])) {
                                                                    $occupied += $registersectorforeach["occupied"];
                                                                }
                                                            }
                                                            $registerfloorFirs[0]["available"]=$available;
                                                            $registerfloorFirst[0]["occupied"]=$occupied;
                                                    
                                                            setRegisterFloor($contextDinamico,$registerfloorFirst[0]);
                                                            //ATUALIZAR UNIVDS DO CLIENTE
                                                            $univdsGets=getRegisterUnivds($contextDinamico);
                                                            foreach ($univdsGets as $index=>$univd) {
                                                                $univd["available"]=getRowsCountsRegisterSlotsSummary($contextDinamico,$univd["id"],VAGA_LIVRE);
                                                                setRegisterUnivds($contextDinamico,$univd);
                                                            }
                                                            //ATUALIZAR ARVORES
                                                            $univdtrees=getRegisterTrees($contextDinamico);
                                                            foreach ($univdtrees as $index=>$univdtree) {
                                                            

                                                                $available = 0;
                                                                $treesUnvds=getAllUndvTrees($contextDinamico,$univdtree);
                                                                foreach($treesUnvds as $treesUnvd){
                                                                        $available += $treesUnvd["available"];
                                                                    }
                                                                $univdtree["available"]= $available;
                                                                setRegisterTrees($contextDinamico,$univdtree);
                                                            }
                                                            
                                                        //Atualizaçoes Gerais
                                                            deletarMovimentoParaProcessados($contextDinamico, $movimento["codigo"]);
                                                            printIf($echos, "------'OK -PAR GERADO COM SUCESSO\n' [" . $movimento["codigosensor"] . "] ) [" . $accessDataBase["bd"] . "].");
                                                        }
                                                    }
                                                }
                                            }else{
                                                deletarMovimentoParaProcessados($contextDinamico, $movimento["codigo"]);
                                                printIf($echos, "------'OK - Fechado\n' [" . $movimento["codigosensor"] . "] ) [" . $accessDataBase["bd"] . "].");
                                            }
                                        break;
                                        case "2":
                                            $slotAtualizado = atualizarStatusdoSensorLido($contextDinamico, $idSensor, VAGA_OCUPADA);
                                            //if (atualizarStatusdoSensorLido($contextDinamico, $idSensor, VAGA_OCUPADA)) {
                                                if (is_null($registerslotpair) || empty($registerslotpair)) {
                                                        $registerslotpairNew = null; 
                                                        if($slotAtualizado){
                                                            $registerslotpairNew = array(
                                                                "register_slot_id" => $idSensor,
                                                                "occupation" => $dataHora,
                                                            );
                                                        }else{
                                                            $registerslotpairNew = array(
                                                                "register_slot_id" => $idSensor,
                                                                "occupation" => $dataHora,
                                                                "release"    => $dataHora
                                                            );
                                                        }
                                                        $saveNewPair = createdRegisterSlotPair($contextDinamico, $registerslotpairNew);
                                                        //INCLUIR COLLECTS
                                                        /*Valida o Status*/
                                                        $collets = array(
                                                            "register_slot_id" => $sensorLido["id"],
                                                            "register_slot_pair_id" =>$saveNewPair,
                                                            "nsr" => $movimento["codigo"],
                                                            "type" => $movimento["status"],
                                                            "sensor_id" => $movimento["codigosensor"],
                                                            "version" => 0,
                                                        );
                                                        $createdCollets = registerSlotCollects($contextDinamico, $collets);
                                                        $registerslotpair_id=$createdCollets ;

                                                    }else{
                                                        if(empty($registerslotpair["release"])){
                                                            $sensorLido["release"] = $dataHora;
                                                            print("Estou \n\n");
                                                            if(setRegisterSlotPairRelease($contextDinamico, $sensorLido,$registerslotpair)){
                                                                $registerslotpairNew = array(
                                                                    "register_slot_id" => $idSensor,
                                                                    "occupation" => $dataHora,
                                                                );
                
                                                                $saveNewPair = createdRegisterSlotPair($contextDinamico, $registerslotpairNew);
                                                                //INCLUIR COLLECTS
                                                                /*Valida o Status*/
                                                            
                                                                $collets = array(
                                                                    "register_slot_id" => $sensorLido["id"],
                                                                    "register_slot_pair_id" =>$saveNewPair,
                                                                    "nsr" => $movimento["codigo"],
                                                                    "type" => $movimento["status"],
                                                                    "sensor_id" => $movimento["codigosensor"],
                                                                    "version" => 0,
                                                                );
                                                                $createdCollets = registerSlotCollects($contextDinamico, $collets);
                                                                $registerslotpair_id=$createdCollets ;



                                                            }
                                                        }
                                                    }

                                                    /*Valida o Status*/
                                                
                                                    ///TEste
                                                    //ATUALIZAR CONTAGEM DE VAGAS DO SETOR
                                                    //CONTAR TODAS AS VAGAS LIVRES NO SETOR:
                                                    $registerSectorFirst = (getRegisterSector($contextDinamico, $sensorLido["register_sector_id"]))[0];
                                                    $registerSectorFirst["available"] = getRowsCountsRegisterSlots($contextDinamico, $registerSectorFirst["id"], VAGA_LIVRE);
                                                    $registerSectorFirst["occupied"] = getRowsCountsRegisterSlots($contextDinamico, $registerSectorFirst["id"], VAGA_OCUPADA);
                                                    
                                                    setRegisterSectorScore($contextDinamico, $registerSectorFirst);
                                                    //ATUALIZAR CONTAGEM DE VAGAS DO PISO
                                                    //CONTAR TODAS AS VAGAS LIVRES NO PISO:
                                                    $registerfloorFirst = getRowsCountsRegisterSlotsFloor($contextDinamico,$registerSectorFirst["register_floor_id"]);
                                                    $available = 0;
                                                    $occupied = 0;
                                                    foreach($registerfloorFirst as $index=>$registersectorforeach){
                                                        if (!empty($registersectorforeach["available"])) {
                                                            $available += $registersectorforeach["available"];
                                                        }
                                                        if (!empty($registersectorforeach["occupied"])) {
                                                            $occupied += $registersectorforeach["occupied"];
                                                        }
                                                    }
                                                    $registerfloorFirs[0]["available"]=$available;
                                                    $registerfloorFirst[0]["occupied"]=$occupied;
                                            
                                                    setRegisterFloor($contextDinamico,$registerfloorFirst[0]);
                                                    //ATUALIZAR UNIVDS DO CLIENTE
                                                    $univdsGets=getRegisterUnivds($contextDinamico);

                                                    foreach ($univdsGets as $index=>$univd) {
                                                        $univd["available"]=getRowsCountsRegisterSlotsSummary($contextDinamico,$univd["code"],VAGA_LIVRE);
                                                        setRegisterUnivds($contextDinamico,$univd);
                                                    }
                                                    //ATUALIZAR ARVORES
                                                    $univdtrees=getRegisterTrees($contextDinamico);
                                                    foreach ($univdtrees as $index=>$univdtree) {
                                                            $available = 0;
                                                            $treesUnvds=getAllUndvTrees($contextDinamico,$univdtree);
                                                            foreach($treesUnvds as $treesUnvd){
                                                                    $available += $treesUnvd["available"];
                                                                }
                                                            $univdtree["available"]= $available;
                                                            setRegisterTrees($contextDinamico,$univdtree);


                                                    
                                                    }

                                                    deletarMovimentoParaProcessados($contextDinamico, $movimento["codigo"]);
                                                    printIf($echos, "\n-----  //OCUPAÇÃO DA VAGA  Completa 2 [" . $accessDataBase["bd"] . "].\n");
                                                    ///teste
                                             
                                            
                                           // }

                                        break;


                                        case "5":
                                            deletarMovimentoParaProcessados($contextDinamico, $movimento["codigo"]);
                                            printIf($echos, "\n\n\n\n\n('ETemperatura')\n\n\n\n\n");
                                        break;

                                        default:
                                        printIf($echos, "\n\n\n\n\n('ERRO - STATUS INFORMADO INCORRETAMENTE')\n\n\n\n\n");
                                    }



                            } else {
                                $info = array(
                                    "msn" => "ERRO -(DAta INVALIDA) - [0000-06-00 H:M:S] VAGA NÃO ENCONTRADA PELO ID DO SENSOR ",
                                    "data_base" => $accessDataBase["bd"],
                                    "sensor_id" => $movimento["codigosensor"],
                                );
                                ExceptionLog($info, "ERROR_DATA");
                            }
                    }



                
                /*Se tem a Tabela de Movimentos na Base*/
            }
        }
        $info = array(
            "msn" => "OK -(OPERAÇAO FINALIZADO) -  ",           
        );
       // ExceptionLog($info, "OK_DATA");
    }
    $ControleCrons->liberarCron();
}
} catch (Exception $e) {
    $ControleCrons->liberarCron();
    ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "ERROR_PROCESS");
}