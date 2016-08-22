<?php
//header("Access-Control-Allow-Origin: http://yourdomain.com");

require 'db_params.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Connected successfully";
}
catch(PDOException $e) {
    echo $sql. "<br />" . $e->getMessage();
}

switch($_GET['cmd'].$_POST['cmd']) {
    case 'playersList':
        $recordPerPage = 30;
        if(array_key_exists('page', $_GET)) {
            $startRecord = $_GET['page'] * $recordPerPage;
        } 
        else {
            $startRecord = 0;
        }
        $sql = "SELECT idPlayer,nmPlayer,victories,totalPlayed,totalDistance,totalScore FROM `players` ORDER BY totalScore DESC LIMIT :startRecord, :recordPerPage ";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':recordPerPage', $recordPerPage,PDO::PARAM_INT);
        $stmt->bindParam(':startRecord', $startRecord,PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_CLASS);
        echo json_encode($result);
    break;
    case 'register':
        if(array_key_exists('nmPlayer', $_POST) 
            && array_key_exists('emailPlayer', $_POST) 
            && filter_var($_POST['emailPlayer'], FILTER_VALIDATE_EMAIL) 
            && preg_match("/^[a-zA-Z ]*$/",$_POST['nmPlayer'])) {
            //check if email address already exists
            $sql = "SELECT idPlayer from players where emailPlayer=?";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(1, $_POST['emailPlayer']);
            $stmt->execute();
            if($stmt->rowCount()>0) {
                http_response_code(400);
                echo "Email address already used.";
            }
            else {
                //else insert the new player
                $sql = "INSERT INTO players (nmPlayer,emailPlayer,hashPlayer,password) VALUES (?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(1, $_POST['nmPlayer']);
                $stmt->bindParam(2, $_POST['emailPlayer']);
                $hashPlayer = md5(str_shuffle(emailPlayer));
                $stmt->bindParam(3, $hashPlayer);
                $password = md5($_POST['playerPwd']);
                $stmt->bindParam(4, $password);
                $stmt->execute();
                echo $hashPlayer;                
            }
        }
        else {
            http_response_code(400);
            echo "Please provide a name and a valid e-mail address.";
        }
    break;
    case 'getProfile':
        //check basic auth
        if(array_key_exists('emailPlayer', $_GET) && array_key_exists('hashPlayer', $_GET)) {
            $sql = "SELECT pl.idPlayer, pl.nmPlayer, pl.hashPlayer, pl.victories, pl.totalPlayed, pl.totalDistance, pl.totalScore, p.idGame FROM players pl LEFT OUTER JOIN participations p ON p.idPlayer = pl.idPlayer AND p.isActive = 1 WHERE pl.emailPlayer=? AND pl.hashPlayer=?";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(1,$_GET['emailPlayer']);
            $stmt->bindParam(2,$_GET['hashPlayer']);
            $stmt->execute();
            $result = $stmt->fetchObject();
            echo json_encode($result);
        }
    break;
    case 'login':
        //check basic auth
        if(array_key_exists('emailPlayer', $_POST) && array_key_exists('password', $_POST)) {
            $sql = "SELECT nmPlayer, hashPlayer, emailPlayer FROM players WHERE emailPlayer=? AND password=?";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(1,$_POST['emailPlayer']);
            $password = md5($_POST['password']);
            $stmt->bindParam(2,$password);
            $stmt->execute();
            if($stmt->rowCount()==1){
                $result = $stmt->fetchObject();
                echo json_encode($result);
            }
            else {
                http_response_code(400);
                echo "Could not log you in, please check your e-mail address or your password.";
            }
        }
    break;
}

$conn = null;

?>