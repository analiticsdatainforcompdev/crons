<?php

class ControleCrons
{

    private $host = "162.214.76.190";
    private $user = "inforparkuser";
    private $password = "ASD7N#!a)k6a";
    private $db = "endpointinforcomp";
    private $port = 3306;
    private $charset = 'utf8mb4';
    private $pdo =  null;
    private $command = 'RUNCRONS' ;








    public function __construct()
    {
        try {
            $this->pdo = new PDO(sprintf("mysql:host=%s;port=%s;dbname=%s",
                                 $this->host, $this->port, $this->db),  $this->user, $this->password,array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $this->pdo->exec(sprintf("set names  %s", $this->charset));
        } catch (Exception $e) {
            throw new Exception("Erro ao Connectar com Base", 1);
        }
    }



    private function modificarConfigurations($state)
    {
        try {
            $sql = "UPDATE infor_config_app SET infor_jsonconfig=? , update_at=now() WHERE `infor_config`=?";
            $this->pdo->prepare($sql)->execute([$state,$this->command]);
        } catch (Exception $e) {
            throw new Exception("Erro ao Connectar com Base", 1);
        }
    }

    private function diffData($dataStart,$dataFim){
            $date1 = strtotime($dataStart); 
            $date2 = strtotime($dataFim); 
            $diff = abs($date2 - $date1); 
            $years = floor($diff / (365*60*60*24)); 
            $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24)); 
            $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24)/ (60*60*24));
            $hours = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24 - $days*60*60*24) / (60*60));
            $minutes = floor(($diff - $years * 365*60*60*24  - $months*30*60*60*24 - $days*60*60*24 - $hours*60*60)/ 60); 
            $seconds = floor(($diff - $years * 365*60*60*24  - $months*30*60*60*24 - $days*60*60*24 - $hours*60*60 - $minutes*60)); 
        return $minutes;

    }




    public function excuteCron()
    {
        try {
            $data = $this->pdo->query("SELECT * FROM `infor_config_app` WHERE `infor_config`='RUNCRONS' and `infor_jsonconfig`='0'")->fetchAll(PDO::FETCH_ASSOC);
            if (count($data) > 0) {
                if($this->diffData(strtotime(date('Y/m/d H:i:s')), strtotime($data[0]['update_at'])) > 30){
                    $this->modificarConfigurations('0');
                }
                
                $this->modificarConfigurations('1');
                return true;
            }
            return false;
        } catch (Exception $e) {
            throw new Exception("Erro ao Connectar com Base", 1);
        }
        return false;
    }

    public function liberarCron()
    {
        try {
            $this->modificarConfigurations('0');
        } catch (Exception $e) {
            throw new Exception("Erro ao Connectar com Base", 1);
        }
    }
}
