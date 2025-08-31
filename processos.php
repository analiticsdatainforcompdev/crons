<?php


$crons = ["CameraInterpletion.php","SensorInterpletion.php"];
foreach($crons as $key => $cron){
    print_r(sprintf("Iniciando Executando com de [ %s ]\n",$cron));
    require_once($cron);
    print_r(sprintf("Finalizando com de [ %s ]\n\n",$cron));
}






?>