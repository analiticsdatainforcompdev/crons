<?php

require_once  "ExceptionLogs.php";
require_once  "Database.php";
require_once("ControleCrons.php");


define("TABLE_CAMERAS_MOVIMENTOS","movimentoscameras");
define("ENTRADA",1);
define("PLACA_INDEFINIDA","99999999");
define("PLACA_AJUSTE_SAIDA","66666666");
define("PLACA_DESCONHECIDA","Unknown");
/*Funcoes de Apoio*/

function dd($o){
    print("================================Debug==========================\n\n");
    print_r($o);
    print("================================Debug==========================\n\n");
    die();
}
function printIf($display, $message)
{
    if ($display) {
        echo $message . "\n";
    }
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

function getInterpletacaoCustomersOnLine($context)
{
    $customers = [];
    try {

        $stmt = $context->prepare("select cu.client_id as clientId  from client_units cu   where   cu.tipo_interpretacao=1");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $customers;
}



function buscarContextoDoBanco($context,$customer)
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







function recuperarMovimentoNaoProcessadosEntrada($context)
{
    $movimentos = [];
    try {

        $sql="
            SELECT codigo, nsr, `data`, hora, nuvem, codigosensor, portatirasensor, 
            placa, created_at, update_at FROM movimentoscameras  where nuvem='N'  and portatirasensor=1 ORDER by codigo
        ";
        $stmt = $context->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $movimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

  
    return $movimentos;

}



function recuperarMovimentoNaoProcessados($context)
{
    $movimentos = [];
    try {

        $sql="
            SELECT codigo, nsr, `data`, hora, nuvem, codigosensor, portatirasensor, 
            placa, created_at, update_at FROM movimentoscameras  where nuvem='N' ORDER by codigo
        ";
        $stmt = $context->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $movimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

  
    return $movimentos;

}



function recuperarPortariaECamera($context)
{
    $portariaCameras = [];
    try {

        $sql="
            SELECT p.id  as portariaId,cam.id as camId,cam.sentido,p.vagas 
            FROM cameras cam  left join portarias p 
            on p.id=cam.portaria_id
        ";
        $stmt = $context->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $portariaCameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

    return $portariaCameras;

}



function recuperarPortariaVagas($context,$idPortaria)
{


      

    $portariaCameras = [];
    try {
        $sql="
                 SELECT id,description ,detalhes ,vagas,univdid,univdtreeid  FROM portarias  where id=:portariaId
        ";
        $stmt = $context->prepare($sql);
        $stmt->bindValue(":portariaId",$idPortaria);

        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $portariaCameras = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
        throw new ErrorException($e->getMessage(), 500);
    }

    return $portariaCameras;

}




function criarContextoPortariaCamera($sensorCam){
    return array(
        "uuid"=>sprintf("%s%s",$sensorCam["portariaId"],$sensorCam["camId"]),
        "portariaId"=>$sensorCam["portariaId"],
        "cameraId"=>$sensorCam["camId"],
        "sentido"=>$sensorCam["sentido"],
        "vagas"=>$sensorCam["vagas"]
    );
}



function getRegisterSlotPairCamPlaca($context,$sensorCam,$camPlaca)
{
    $registerslotpair = null;

  
 
    try {

        $sql="
            SELECT  id, portaria_id, placa, occupation, occupation_cam_id, `release`, release_cam_id, created_at, update_at 
            FROM register_slot_pairs_cam   where
            portaria_id=:portaria_id and  placa=:placa and `release` IS NULL 
        ";

        $stmt = $context->prepare($sql);

         
        $stmt->bindValue(":portaria_id",$sensorCam["portariaId"]);
        $stmt->bindValue(":placa",$camPlaca);



        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {

        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotpair;
}





function getRegisterSlotPairCamDataMinina($context)
{
    $registerslotpair = null;
    try {
        $sql="
                 SELECT * FROM `register_slot_pairs_cam`
                  WHERE `release` IS  NUll ORDER by occupation asc   limit 0,1
        ";

        $stmt = $context->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {

        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return (is_array( $registerslotpair) && count( $registerslotpair)!=0) ?  $registerslotpair[0]:[];
}



function getRegisterSlotPairCam($context,$sensorCam)
{
    $registerslotpair = null;
    try {

        $sql="
            SELECT  id, portaria_id, placa, occupation, occupation_cam_id, `release`, release_cam_id, created_at, update_at 
            FROM register_slot_pairs_cam   where
            occupation_cam_id =:sensorCam and placa=:placa and `release` IS NULL 
        ";

        $stmt = $context->prepare($sql);
        $stmt->bindValue(":sensorCam", $sensorCam["uuid"]);
        $stmt->bindValue(":placa", $sensorCam["placa"]);


        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $registerslotpair = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {

        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }

    return $registerslotpair;
}


function createdRegisterSlotPairCam($context, $sensorCam,$movimentoLido)
{
    print("=========================Criando Slot   2    \n\n");
    print("=========================Criando Slot   2     2    \n\n");
    $newpairSersor = null;
    try {

        $sql="
            INSERT INTO register_slot_pairs_cam (portaria_id, placa, occupation, occupation_cam_id,created_at)
                VALUES(:portaria_id,:placa,:occupation,:occupation_cam_id,now());

        ";
        $stmt = $context->prepare($sql);

        //2020-09-19 20:33:14.000
        $dataAcesso=sprintf("%s%s",$movimentoLido["data"],$movimentoLido["hora"]);
        $ano=substr($dataAcesso,0,4);
        $mes=substr($dataAcesso,4,2);
        $dia=substr($dataAcesso,6,2);
        $hora=substr($dataAcesso,8,2);
        $minuto=substr($dataAcesso,10,2);
        $segundos=substr($dataAcesso,12,2);
        $data=sprintf("%s-%s-%s %s:%s:%s",$ano,$mes,$dia,$hora,$minuto,$segundos);
        $stmt->bindValue(":portaria_id", $sensorCam["portariaId"]);
        $stmt->bindValue(":placa", $movimentoLido["placa"]);
        $stmt->bindValue(":occupation", $data);
        $stmt->bindValue(":occupation_cam_id", $sensorCam["uuid"]);
        
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



function alterarRegisterSlotPairCam($context, $sensorCamLocalizado,$sensorCam,$movimentoLido)
{
    printIf(true, "-----Iniciando--Interpletação.");

    $newpairSersor = null;
    try {

        $sql="
            update  register_slot_pairs_cam set placarelease=:placarelease,  `release`=:release,release_cam_id=:release_cam_id
                ,update_at=now() where id=:id

        ";
        $stmt = $context->prepare($sql);
        //2020-09-19 20:33:14.000
        $dataAcesso=sprintf("%s%s",$movimentoLido["data"],$movimentoLido["hora"]);
       
        $ano=substr($dataAcesso,0,4);
        $mes=substr($dataAcesso,4,2);
        $dia=substr($dataAcesso,6,2);
        $hora=substr($dataAcesso,8,2);
        $minuto=substr($dataAcesso,10,2);
        $segundos=substr($dataAcesso,12,2);
        $data=sprintf("%s-%s-%s %s:%s:%s",$ano,$mes,$dia,$hora,$minuto,$segundos);
        
        if(!isset($sensorCamLocalizado["id"])){
            return false;
        }

        $stmt->bindValue(":id", $sensorCamLocalizado["id"]);
        $stmt->bindValue(":placarelease", $movimentoLido["placa"]);
        $stmt->bindValue(":release", $data);
        $stmt->bindValue(":release_cam_id", $sensorCam["uuid"]);
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








function alterarMovimentoParaProcessados($context, $idMovimento)
{

    print("alterarMovimentoParaProcessados\n");
    try {
        $stmt = $context->prepare("update movimentoscameras set  nuvem='S', update_at=now()  where codigo=:idMoviment");
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


function recuperarVagasUnidsTv($context,$unvdId){
    $univd =null;
    try {

       
        $stmt = $context->prepare("SELECT * FROM `register_univd_trees` WHERE `id`=".$unvdId);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $univd = $stmt->fetch(PDO::FETCH_ASSOC);
        }

       
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return $univd;
}



function alterarNumerosDeVagasDaPortariaMenosEntada($context, $sensorCam)
{
    printIf(true,"---Alterando Vagas Entradas ------\n");
    try {
        $portaria=recuperarPortariaVagas($context,$sensorCam["portatirasensor"]);
        $undTree=recuperarVagasUnidsTv($context,$portaria["univdtreeid"]);
        $vagasDisponiveis=intval($undTree["available"]);
        $vagasPortariaTotais=intval($portaria["vagas"]);
        $vagasDisponiveis--;
        $stmt = $context->prepare("update register_univd_trees set  available=:vagas, updated_at=now()  where id=:id");
        $stmt->bindValue(":vagas", $vagasDisponiveis);
        $stmt->bindValue(":id", $undTree["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (Exception $e) {
        dd($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
}








function alterarNumerosDeVagasDaPortariaMaisSaida($context, $sensorCam)
{
    printIf(true,"---Alterando Vagas Entradas ------\n");
    try {

       
        $portaria=recuperarPortariaVagas($context,$sensorCam["codigosensor"]);
        $undTree=recuperarVagasUnidsTv($context,$portaria["univdtreeid"]);
        $vagasDisponiveis=intval($undTree["available"]);
        $vagasDisponiveis++;
        $stmt = $context->prepare("update register_univd_trees set  available=:vagas, updated_at=now()  where id=:id");
        $stmt->bindValue(":vagas", $vagasDisponiveis);
        $stmt->bindValue(":id", $undTree["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (Exception $e) {
        dd($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
}




function alterarNumerosDeVagasDaPortariaEntada($context, $sensorCam)
{
    printIf(true,"---Alterando Vagas Entradas ------\n");
    try {

        $portaria=recuperarPortariaVagas($context,$sensorCam["portariaId"]);
        $undTree=recuperarVagasUnidsTv($context,$portaria["univdtreeid"]);
        $vagasDisponiveis=intval($undTree["available"]);
        $vagasPortariaTotais=intval($portaria["vagas"]);
        $vagasDisponiveis--;
    
        $stmt = $context->prepare("update register_univd_trees set  available=:vagas, updated_at=now()  where id=:id");
        $stmt->bindValue(":vagas", $vagasDisponiveis);
        $stmt->bindValue(":id", $undTree["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (Exception $e) {
        dd($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
}

/*
function altearNumerosDeVagasDaPortariaSaida($context,$sensorCamLocalizado, $sensorCam)
{
    printIf(true,"---Alterando Vagas Saida ------\n");
    $portaria=recuperarPortariaVagas($context,$sensorCamLocalizado["portaria_id"]);
    $vagasDisponiveis=intval($portaria["vagas"]);
    $vagasDisponiveis++;
    try {
        $stmt = $context->prepare("update portarias set  vagas=:vagas, updated_at=now()  where id=:id");
        $stmt->bindValue(":vagas", $vagasDisponiveis);
        $stmt->bindValue(":id", $sensorCam["portariaId"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
}
*/


function altearNumerosDeVagasDaPortariaSaida($context,$sensorCamLocalizado, $sensorCam)
{
    printIf(true,"---Alterando Vagas Saida ------\n");
    $portaria=recuperarPortariaVagas($context,$sensorCamLocalizado["portaria_id"]);
    $undTree=recuperarVagasUnidsTv($context,$portaria["univdtreeid"]);
    $vagasDisponiveis=intval($undTree["available"]);
    $vagasPortariaTotais=intval($portaria["vagas"]);
    $vagasDisponiveis++;
   
   // if(($vagasDisponiveis+1) <=  $vagasPortariaTotais){
    //    $vagasDisponiveis++;
   // }
    
    try {
        $stmt = $context->prepare("update register_univd_trees set  available=:vagas, updated_at=now()  where id=:id");
        $stmt->bindValue(":vagas", $vagasDisponiveis);
        $stmt->bindValue(":id", $undTree["id"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
}


/*
function altearNumerosDeVagasDaPortariaSaida($context,$sensorCamLocalizado, $sensorCam)
{
    printIf(true,"---Alterando Vagas Saida ------\n");
    $portaria=recuperarPortariaVagas($context,$sensorCamLocalizado["portaria_id"]);
    $vagasDisponiveis=intval($portaria["vagas"]);
    $vagasDisponiveis++;
    try {
        $stmt = $context->prepare("update portarias set  vagas=:vagas, updated_at=now()  where id=:id");
        $stmt->bindValue(":vagas", $vagasDisponiveis);
        $stmt->bindValue(":id", $sensorCam["portariaId"]);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
}
*/





function removeMovimento($context,$id)
{

    
    printIf(true,"---Removendo Movimentos------\n");
    try {
        $stmt = $context->prepare("delete  from  movimentoscameras    where codigo=:id");
        $stmt->bindValue(":id", $id);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (Exception $e) {
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;
}


function ajustarSaidaComLimit($context,$sensorCam){
    printIf(true,"---Ajustando Saidas Vagas Entradas ------\n");
    try {
        $idCamera=0;
        $ajusteContador=0;
        $diferencia=0;
        foreach($sensorCam as $key=>$cam){
            if($cam['codigosensor'] !=$idCamera){
                $idCamera=$cam['codigosensor'];
                $portaria=recuperarPortariaVagas($context,$cam["codigosensor"]);
                $undTree=recuperarVagasUnidsTv($context,$portaria["univdtreeid"]);
                $vagasDisponiveis=intval($undTree["available"]);
                $diferencia=$portaria['vagas']-$vagasDisponiveis;
                print_r("===== ".$diferencia);
                if($diferencia < 0   ){
                    $diferencia=abs($diferencia);
                  
                    for($next=0;$next < $diferencia;$next++){
                        $placa=PLACA_AJUSTE_SAIDA;
                        $hora=(date('Hmi')+(20*$next));
                        $sql="
                            insert into movimentoscameras(nsr,`data`, hora, nuvem, codigosensor, portatirasensor, placa, created_at) 
                            value(?,?,?,?,?,?,?,now()) 
                        ";
                        $stmt = $context->prepare($sql);
                        $stmt->execute(array(0,date('Ymd'), $hora,'N',$idCamera,ENTRADA,$placa));
                        if ($stmt->rowCount() > 0) {
                            $ajusteContador++;
                        }
                    }
                }
                return ($diferencia==$ajusteContador)?true:false;
            }
        }
    } catch (Exception $e) {
        dd($e);
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "BASE_ERROR_");
    }
    return false;


}



function InitInterpretacao(){
    
$ControleCrons =  new ControleCrons();
/*Lógica de Execução*/

$echos = true;
try {

        if(!$ControleCrons->excuteCron()) return ;
        print("Iniciando Processo de Interpletação ....\n");
        $handler = ConectDataBasecontext();
    

        if (!is_null($handler)) {
            $customers = getInterpletacaoCustomersOnLine($handler);
            $entradas=0;
            $saidas=0;
            $portariaCameras=null;
            $placaAnterior=null;
            $portaria=null;
            
            foreach ($customers as $key => $customer) {
                $portariaCameras=[];
                $idClient = $customer["clientId"];
                $baseSelecionada=buscarContextoDoBanco($handler,$customer);
                $contextDinamicoDoBancoSelecionado = ConectDataBasecontext($baseSelecionada);
                printIf($echos, "-----Acessando   Banco [" . $baseSelecionada["bd"] . "].");
                if (!isTableInBase($contextDinamicoDoBancoSelecionado,TABLE_CAMERAS_MOVIMENTOS)) {
                    printIf($echos, "----- Error  Acessando Banco Tabela de [".TABLE_CAMERAS_MOVIMENTOS."] [" . $baseSelecionada["bd"] . "].\n\n\n");
                    
                }else{
                    printIf($echos, "-----Procesando  Banco [" . $baseSelecionada["bd"] . "].");
                    $movimentosPreProcessados = [];
                    $movimentosPreProcessadosEntrada = recuperarMovimentoNaoProcessados($contextDinamicoDoBancoSelecionado);
                    $entradas=0;
                    $saidas=0;
                    $time=0;
                    foreach($movimentosPreProcessadosEntrada as $i=>$v){
                        if($v["placa"]!=PLACA_AJUSTE_SAIDA && $v['portatirasensor']+strtotime($v['created_at']) - $time < 20){
                             removeMovimento($contextDinamicoDoBancoSelecionado,$v["codigo"]);
                        }else{
                            $movimentosPreProcessados[]=$v;
                        }
                        $time=$v['portatirasensor']+strtotime($v['created_at']);
                    }
                    printIf($echos, "-----Recuperando Dados Não Processados (Sensor PRE- PROCESSADOS) [" . $baseSelecionada["bd"] . "].  Count(".count($movimentosPreProcessados).")");
                    foreach ($movimentosPreProcessados as $mvp) {
                        $idPortaria = $mvp["codigosensor"];
                        $portaria=recuperarPortariaVagas($contextDinamicoDoBancoSelecionado,$idPortaria);
                        $offset = strpos($mvp["placa"],PLACA_DESCONHECIDA);
                        if($offset === false) {
                            if($mvp['portatirasensor']==ENTRADA){
                            if(alterarNumerosDeVagasDaPortariaMenosEntada($contextDinamicoDoBancoSelecionado,$mvp)){
                                alterarMovimentoParaProcessados($contextDinamicoDoBancoSelecionado, $mvp["codigo"]);
                                $entradas++;
                            }
                            }else{
                                if(alterarNumerosDeVagasDaPortariaMaisSaida($contextDinamicoDoBancoSelecionado,$mvp)){
                                    alterarMovimentoParaProcessados($contextDinamicoDoBancoSelecionado, $mvp["codigo"]);
                                    $saidas++;
                                }
                            }
                        }else{
                            removeMovimento($contextDinamicoDoBancoSelecionado, $mvp["codigo"]);
                        }
                    }
                    if(count($movimentosPreProcessados)>0){
                        ajustarSaidaComLimit($contextDinamicoDoBancoSelecionado,$movimentosPreProcessados);
                    }
                }
            }
        }

        $ControleCrons->liberarCron();

    }catch(Exception  $e){
        $ControleCrons->liberarCron();
        ExceptionLog(["File" => $e->getFile(), "line" => $e->getLine(), "msn" => $e->getMessage()], "ERROR_PROCESS");
    }
}


InitInterpretacao();





?>