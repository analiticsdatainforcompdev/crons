<?php


set_error_handler('exceptions_error_handler');

function exceptions_error_handler($severity, $message, $filename, $lineno) {
  if (error_reporting() == 0) {
    return;
  }
  if (error_reporting() & $severity) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
  }
}



/*Gerar o Logos de Erros Das Crons* */
function ExceptionLog($error,$info=null){
    $inforError=array(
        "info"=>(!is_null($info) && strlen($info) > 0) ? $info : "GENERICS_",
        "datahora"=>date("d-m-Y h:m:s"),
        "error"=>$error
    );
    $pathLogsError=__DIR__."./erros_logs/";
    if(!file_exists($pathLogsError)){
      mkdir($pathLogsError, 0777, true);
    }
    $log=fopen(sprintf("%s%s_%s.txt", $pathLogsError,$inforError["info"],date("d_m_Y")),"a+");
    fwrite($log,sprintf("%s\n",json_encode($inforError)));
    fclose($log);

}



?>