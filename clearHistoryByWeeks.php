<?php

/*
    echo "<pre>";print_r(["#TS"]);die;

    ALTER TABLE inforpark.client_units ADD limpar_historico_por_semana int NULL;

*/


require_once  "ExceptionLogs.php";
require_once  "Database.php";

$WEEK = 7;
$LIMIT_MAX_ROWS = 20000;





function dateBasedOnDays($days){
  return  date('Y-m-d', strtotime(sprintf('-%s days',$days)));
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
        throw new ErrorException($e->getMessage(), 500);
    }
    return false;
}





function fetchBankContext($context,$customer)
{
   try {
        $accessDataBase = getClientAccess();
        $db=str_replace("i","I",str_replace("p","P",$accessDataBase["bd"]));
        $accessDataBase["bd"] = sprintf("%s_%s_%s", $db,
            str_pad($customer["clientId"], '4', '0', STR_PAD_LEFT), str_pad($customer["clientId"], '4', '0', STR_PAD_LEFT));
        return $accessDataBase;
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }
    return null;
}


function recoverMovementCam($context,$dateToPerformCleaning,$limitRows)
{
    $movements = [];
    try {
        $sql="
            SELECT * FROM movimentoscameras  where  
                  DATE_FORMAT(created_at,\"%x-%m-%d\")  <= '".$dateToPerformCleaning."' 
                  ORDER by created_at desc  limit 0,".$limitRows." 
             ";
        $stmt = $context->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

  
    return $movements;

}



function recoverMovementSensor($context,$dateToPerformCleaning,$limitRows)
{

    
    $movements = [];
    try {
        $sql="SELECT *  from movimentossensores where  
                  DATE_FORMAT(created_at,\"%x-%m-%d\")  <= '".$dateToPerformCleaning."' 
                  ORDER by created_at desc   limit 0,".$limitRows." ";
        $stmt = $context->prepare($sql);

        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

  
    return $movements;

}




function recoverMovementSlotCollects($context,$dateToPerformCleaning,$limitRows)
{
    $movements = [];
    try {
        $sql="SELECT *  from register_slot_collects where  
                  DATE_FORMAT(created_at,\"%x-%m-%d\")  <= '".$dateToPerformCleaning."' 
                  ORDER by created_at desc   limit 0,".$limitRows." ";
        $stmt = $context->prepare($sql);

        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }
    return $movements;
}




function slotCollectsByPairs($context,$codePairs)
{
    $movements = [];
    try {
        $sql="SELECT *  from register_slot_collects where   register_slot_pair_id=?";
        $stmt = $context->prepare($sql);
        $stmt->bindParam(1,$codePairs);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }
    return $movements;
}







function clearCustomerDataWithWeeksGreaterThanZero($context)
{
    $customers = [];
    try {
        $where = " where  cu.limpar_historico_por_semana > 0";
        $stmt = $context->prepare(sprintf("select cu.client_id as clientId , limpar_historico_por_semana as clearHistWeeks from client_units cu  %s ",$where));
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $customers;
}




function deleteMovementSensor($context,$codeSensorId)
{
    try {
        $sql="delete  from movimentossensores where  codigo=?";
        $stmt = $context->prepare($sql);
        $stmt->bindParam(1,$codeSensorId);
        if($stmt->execute()){
          return true;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

  
    return false;

}


function deleteMovementSensorCam($context,$codeSensorId)
{
    try {
        $sql="delete  from movimentoscameras where  codigo=?";
        $stmt = $context->prepare($sql);
        $stmt->bindParam(1,$codeSensorId);
        if($stmt->execute()){
          return true;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

  
    return false;

}




function deleteMovementSlotCollects($context,$codeSensorId)
{
    try {
        $sql="delete  from register_slot_collects where  register_slot_pair_id=?";
        $stmt = $context->prepare($sql);
        $stmt->bindParam(1,$codeSensorId);
        if($stmt->execute()){
          return true;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }
    return false;

}


function deleteMovementSlotPairs($context,$codeSensorId)
{
    try {
        $sql="delete  from register_slot_pairs where  id=?";
        $stmt = $context->prepare($sql);
        $stmt->bindParam(1,$codeSensorId);
        if($stmt->execute()){
          return true;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

  
    return false;

}

function beginTransaction($context){
  $context->beginTransaction();
}


function commit($context){
  $context->commit();
}

function rollBack($context){
  $context->rollBack();
}



print("Iniciando Processo de Limpeza ....\n");
$handler = ConectDataBasecontext();
if (!is_null($handler)) {
    $customers = clearCustomerDataWithWeeksGreaterThanZero($handler);
    foreach ($customers as $key => $customer) {
      $searchForDays=intval($customer['clearHistWeeks']) * $WEEK; 
      $baseContext=fetchBankContext($handler,$customer);
      $dynamicContextOfTheSelectedBank = ConectDataBasecontext($baseContext);
      $dateToPerformCleaning =dateBasedOnDays($searchForDays);
      $sensorHistorys=recoverMovementSensor($dynamicContextOfTheSelectedBank,$dateToPerformCleaning,$LIMIT_MAX_ROWS);
      print_r("---------Data  < ".$dateToPerformCleaning."-----------\n\n");
      foreach($sensorHistorys as $index=>$sensor){
        print_r("---------Data  < ".$dateToPerformCleaning."-----------\n\n");
        print_r(sprintf("{Senores [%s]} - Cliente [%s] semanas [%s] dias [%s] criação [%s] (%s)\n ",$index,$customer['clientId'],$customer['clearHistWeeks'],
                 $searchForDays,  $sensor['created_at'],$sensor['codigo']));
        deleteMovementSensor($dynamicContextOfTheSelectedBank,$sensor['codigo']);
       }
       $sensorCamHistorys=recoverMovementCam($dynamicContextOfTheSelectedBank,$dateToPerformCleaning,$LIMIT_MAX_ROWS);
       foreach($sensorCamHistorys as $index=>$sensor){
        print_r("---------Data  < ".$dateToPerformCleaning."-----------\n\n");
         print_r(sprintf("{Senores Camera [%s]} -  Cliente [%s] semanas [%s] dias [%s] criação [%s] (%s)\n ",$index,$customer['clientId'],$customer['clearHistWeeks'],
                  $searchForDays,  $sensor['created_at'],$sensor['codigo']));
         deleteMovementSensorCam($dynamicContextOfTheSelectedBank,$sensor['codigo']);
        }
        $slotColletsHistory = recoverMovementSlotCollects($dynamicContextOfTheSelectedBank,$dateToPerformCleaning,$LIMIT_MAX_ROWS);
        foreach($slotColletsHistory as $index=>$slot){
          print_r("---------Data  < ".$dateToPerformCleaning."-----------\n\n");
          print_r(sprintf("{Slot Collects [%s]} -  Cliente [%s] semanas [%s] dias [%s] criação [%s] (%s)\n ",$index,$customer['clientId'],$customer['clearHistWeeks'],
                   $searchForDays,  $slot['created_at'],$slot['id']));

            beginTransaction($dynamicContextOfTheSelectedBank);
            try {
    beginTransaction($dynamicContextOfTheSelectedBank);

    // Desabilita verificação de chave estrangeira
    $dynamicContextOfTheSelectedBank->exec("SET FOREIGN_KEY_CHECKS=0");

    if (deleteMovementSlotCollects($dynamicContextOfTheSelectedBank, $slot["register_slot_pair_id"])) {
        if (deleteMovementSlotPairs($dynamicContextOfTheSelectedBank, $slot["register_slot_pair_id"])) {
            commit($dynamicContextOfTheSelectedBank);
            print_r("Par e Slot Deletado OK \n\n");
        } else {
            rollBack($dynamicContextOfTheSelectedBank);
        }
    } else {
        rollBack($dynamicContextOfTheSelectedBank);
    }

    // Reabilita verificação de chave estrangeira
    $dynamicContextOfTheSelectedBank->exec("SET FOREIGN_KEY_CHECKS=1");

} catch (Exception $e) {
    rollBack($dynamicContextOfTheSelectedBank);
    ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "FOREIGN_KEY_CLEANUP_ERROR");
}

         }
    }





}












?>
