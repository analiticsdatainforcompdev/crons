<?php





function getClientAccess(){
    return array(
        "host" => "localhost",
        "user" => "",
        "pass" => "",
        "bd" => "",
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
        ExceptionLog($e,"BASE_ERROR_");
    }

    return $conexao;

}
