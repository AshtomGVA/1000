<?php
//header("Access-Control-Allow-Origin: http://yourdomain.com");

require "db_params.php";

try {
	$conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
	// set the PDO error mode to exception
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	//echo "Connected successfully";
}
catch(PDOException $e) {
	//echo "Connection failed: " . $e->getMessage();
}

function checkAuthGetGame($conn,$email,$hash) {
	//get current game
	$sql = "SELECT p.idGame, p.idPlayer, g.created_by, g.nbPlayers, g.difficulty, g.distanceGoal, g.idDeck, g.activePlayerIndex, g.aborted_at, g.started, g.active, g.idWinner FROM participations p  INNER JOIN players p_self ON p_self.idPlayer = p.idPlayer AND p_self.emailPlayer = ? AND p_self.hashPlayer = ? INNER JOIN games g ON g.idGame = p.idGame WHERE p.isActive = 1";
	$stmt = $conn->prepare($sql);
	$stmt->bindParam(1,$email);
	$stmt->bindParam(2,$hash);
	$stmt->execute();
	$gameResult = $stmt->fetchObject();	
	return $gameResult;
}

function populateGameForPlayerRefresh($conn,$email,$hash) {
	$game = checkAuthGetGame($conn,$email,$hash);

	//GET ALL PLAYERS
	$sql = "SELECT pl.idPlayer, pl.nmPlayer, p.score, p.distance, p.playerIndex FROM players pl INNER JOIN participations p ON p.idPlayer=pl.idPlayer AND p.idGame=? ORDER BY p.playerIndex ASC";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->execute();
	$players = $stmt->fetchAll(PDO::FETCH_CLASS);
	for($i=0;$i<count($players);$i++){
		if($players[$i]->idPlayer == $game->idPlayer) {
			$players[$i]->current = true;
		}
		else {			
			$players[$i]->current = false;
		}
	}
	//print_r($players);
	$game->players = $players;

	//GET REQUESTING PLAYER HAND
	$sql = "SELECT hand FROM participations WHERE idGame=? AND idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$game->idPlayer);
	$stmt->execute();
	$hand = $stmt->fetchObject();
	$game->hand = json_decode($hand->hand);


	//GET ALL GAMEFIELDS
	$sql = "SELECT idPlayer,gameField,playerIndex FROM participations WHERE idGame=? ORDER BY playerIndex ASC";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->execute();
	$gameFields = $stmt->fetchAll(PDO::FETCH_CLASS);
	for($i=0;$i<count($gameFields);$i++) {
		$gameFields[$i]->gameField = json_decode($gameFields[$i]->gameField);
		//echo $gameFields[$i]->gameField;
	}
	$game->gameFields = $gameFields;

	return $game;
}


function completeGame($game,$conn) {
	if($game->created_by == $game->idPlayer) {
		$sql = "UPDATE games SET active=0, started=0, closed=1 WHERE idGame=?";
		$stmt = $conn->prepare($sql);
		$stmt->bindValue(1,$game->idGame);
		$stmt->execute();
		if($stmt->rowCount()==1) {
			return true;
		}
		else {
			return false;
		}		
	}
	else {
		return false;
	}
}

	switch($_POST['cmd']) {
		case 'createGame':
			//check basic auth
			if(array_key_exists('emailPlayer', $_POST) && array_key_exists('hashPlayer', $_POST)) {
				$sql = "SELECT idPlayer FROM players WHERE emailPlayer=? AND hashPlayer=?";
				$stmt = $conn->prepare($sql);
				$stmt->bindParam(1,$_POST['emailPlayer']);
				$stmt->bindParam(2,$_POST['hashPlayer']);
				$stmt->execute();
				$result = $stmt->fetch();
				if($result['idPlayer'] != '') {
					$idPlayer = $result['idPlayer'];
				}
				else {
					$idPlayer = -1;
				}
			}

			//check if user already in a game
			if($idPlayer >=0) {
				$sql = "SELECT idGame from participations WHERE idPlayer=? AND isActive=1";
				$stmt = $conn->prepare($sql);
				$stmt->bindParam(1,$idPlayer);
				$stmt->execute();
				if($stmt->rowCount() >0) {
					http_response_code(400);
					echo "You can't create a new game if you are already in an active game.";
					break;
				}

			}

			//if(array_key_exists('idPlayer', $result) && $result['idPlayer']>=0) {
			if($idPlayer >=0) {
				//create shuffled deck and store it
				$input = file_get_contents('1000lydeck.json');
				$deck = json_decode($input);
				shuffle($deck);
				$output = json_encode($deck);
				$stmt = $conn->prepare("INSERT INTO decks (cards) VALUES ( :deckCards )");
				$stmt->bindParam(':deckCards',$output);
				$stmt->execute();
				$idDeck = $conn->lastInsertId();

				//create the game entry
				$stmt = $conn->prepare("INSERT INTO games (nbPlayers,idDeck, created_by, difficulty, distanceGoal) VALUES ( :nbPlayers , :idDeck , :idPlayer , 'easy' , 500)");
				$stmt->bindParam(':nbPlayers',$nbPlayers);
				$stmt->bindParam(':idDeck',$idDeck);
				$stmt->bindParam(':idPlayer',$idPlayer);
				if(array_key_exists('nbPlayers', $_POST)) {
					$nbPlayers = $_POST['nbPlayers'];
				}
				else {
					$nbPlayers = 4;
				}
				$stmt->execute();
				$idGame = $conn->lastInsertId();

				//create the participation and link it to the game
				$stmt = $conn->prepare("INSERT INTO participations (idGame,idPlayer) VALUES ( :idGame , :idPlayer )");
				$stmt->bindParam(':idGame',$idGame);
				$stmt->bindParam(':idPlayer',$idPlayer);
				$stmt->execute();

				//return the id of the game created
				echo $idGame;
			}
			else {
				http_response_code(400);
				echo "You must be logged in to create a new game";
			}
		break;

		case 'refreshWaitingRoom':
			//$sql = "SELECT p.idGame, p.idPlayer, g.created_by, g.nbPlayers, g.difficulty, g.distanceGoal, g.idDeck, g.activePlayerIndex FROM participations p  INNER JOIN players p_self ON p_self.idPlayer = p.idPlayer AND p_self.emailPlayer = ? AND p_self.hashPlayer = ? INNER JOIN games g ON g.idGame = p.idGame WHERE p.isActive = 1";
			$sql = "SELECT p.idGame, g.startedAt, g.created_by, g.nbPlayers, g.difficulty, g.distanceGoal, g.started, g.active FROM participations p  INNER JOIN players p_self ON p_self.idPlayer = p.idPlayer AND p_self.emailPlayer = ? AND p_self.hashPlayer = ? INNER JOIN games g ON g.idGame = p.idGame and g.active=1 WHERE p.isActive = 1";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$_POST['emailPlayer']);
			$stmt->bindParam(2,$_POST['hashPlayer']);
			$stmt->execute();
			$gameResult = $stmt->fetchObject();

			//GET LIST OF PLAYERS
			$sql = "SELECT p.idPlayer,pl.nmPlayer,pl.victories,pl.totalPlayed,pl.totalDistance, p.playerIndex FROM participations p INNER JOIN players pl ON p.idPlayer = pl.idPlayer INNER JOIN games g on g.idGame =p.idGame WHERE p.idGame = ? AND p.isActive = 1";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$gameResult->idGame);
			$stmt->execute();
			$result = $stmt->fetchAll(PDO::FETCH_CLASS);
			
			$gameResult->players = $result;
			echo json_encode($gameResult);
		break;

		case 'refreshAvailableGames':
			$sql = "SELECT g.idGame, g.nbPlayers, g.dt_created, creator.nmPlayer FROM games g INNER JOIN players creator ON creator.idPlayer = g.created_by WHERE g.active = 1 and startedAt = 0";
			$stmt = $conn->prepare($sql);
			$stmt->execute();
			$gameResult = $stmt->fetchAll(PDO::FETCH_CLASS);

			$finalResult = array();

			foreach($gameResult as $game) {
				$sql = "SELECT p.idPlayer, pl.nmPlayer FROM participations p INNER JOIN players pl ON pl.idPlayer = p.idPlayer WHERE p.isActive = 1 and p.idGame=?";
				$stmt = $conn->prepare($sql);
				$stmt->bindParam(1,$game->idGame);
				$stmt->execute();
				$result = $stmt->fetchAll(PDO::FETCH_CLASS);
				$game->players = $result;
				array_push($finalResult, $game);
			}
			echo json_encode($finalResult);
		break;

		case 'leaveGame':
			$sql = "SELECT p.idGame, p.idPlayer, g.created_by, g.nbPlayers FROM participations p  INNER JOIN players p_self ON p_self.idPlayer = p.idPlayer AND p_self.emailPlayer = ? AND p_self.hashPlayer = ? INNER JOIN games g ON g.idGame = p.idGame WHERE p.isActive = 1";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$_POST['emailPlayer']);
			$stmt->bindParam(2,$_POST['hashPlayer']);
			$stmt->execute();
			$gameResult = $stmt->fetchObject();

			$sql = "UPDATE participations SET isActive = 0, left_at = NOW() WHERE idGame=? AND idPlayer=?";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$gameResult->idGame);
			$stmt->bindParam(2,$gameResult->idPlayer);
			$stmt->execute();
		break;

		case 'completeGame':
			$email = $_POST['emailPlayer'].$_GET['emailPlayer'];
			$hash = $_POST['hashPlayer'].$_GET['hashPlayer'];
			$game = checkAuthGetGame($conn,$email,$hash);

			if(!completeGame($game,$conn)) {
				http_response_code(400);
				echo "Game completion failed.";				
			}
		break;

		case 'joinGame':
			$sql = "SELECT p_self.idPlayer FROM players p_self WHERE p_self.emailPlayer = ? AND p_self.hashPlayer = ?";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$_POST['emailPlayer']);
			$stmt->bindParam(2,$_POST['hashPlayer']);
			$stmt->execute();
			$playerResult = $stmt->fetchObject();

			$sql = "SELECT p.idGame FROM participations p WHERE p.isActive = 1 AND p.idPlayer = ?";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$playerResult->idPlayer);
			$stmt->execute();
			$countResult = $stmt->rowCount();

			if($countResult == 0) {
				$sql = "SELECT idGame,idPlayer FROM participations WHERE idGame=? and idPlayer=?";
				$stmt = $conn->prepare($sql);
				$stmt->bindParam(1,$_POST['idGame']);
				$stmt->bindParam(2,$playerResult->idPlayer);
				$stmt->execute();

				if($stmt->rowCount() == 1) {
					$sql = "UPDATE participations SET isActive=1 WHERE idGame=? and idPlayer=?";
				}
				else {
					$sql = "INSERT INTO participations (idGame,idPlayer,joined_at) VALUES (?,?,NOW())";					
				}
				$stmt = $conn->prepare($sql);
				$stmt->bindParam(1,$_POST['idGame']);
				$stmt->bindParam(2,$playerResult->idPlayer);
				$stmt->execute();

				if($stmt->rowCount() == 1) {
					$sql = "SELECT p.idGame, g.startedAt, g.nbPlayers, g.created_by from participations p INNER JOIN games g ON g.idGame = p.idGame WHERE p.isActive = 1 and p.idPlayer=?";
					$stmt = $conn->prepare($sql);
					$stmt->bindParam(1,$playerResult->idPlayer);
					$stmt->execute();
					echo json_encode($stmt->fetchObject());
				}
			}
			else {
				http_response_code(400);
				echo "You are already active in a game.";				
			}
		break;

		case 'cancelGame':
			$email = $_POST['emailPlayer'].$_GET['emailPlayer'];
			$hash = $_POST['hashPlayer'].$_GET['hashPlayer'];
			$game = checkAuthGetGame($conn,$email,$hash);

			$sql = "UPDATE participations SET isActive=0, left_at = NOW() WHERE idGame=? AND idPlayer=?";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$game->idGame);
			$stmt->bindParam(2,$game->idPlayer);
			$stmt->execute();

			$sql = "UPDATE games SET active=0, started=0, closed=1, aborted_at = NOW() WHERE idGame=?";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$game->idGame);
			$stmt->execute();
		break;

		case 'startGame':
			$email = $_POST['emailPlayer'].$_GET['emailPlayer'];
			$hash = $_POST['hashPlayer'].$_GET['hashPlayer'];
			$game = checkAuthGetGame($conn,$email,$hash);

			$sql = "SELECT p.idPlayer,pl.nmPlayer,pl.victories,pl.totalPlayed,pl.totalDistance FROM participations p INNER JOIN players pl ON p.idPlayer = pl.idPlayer INNER JOIN games g on g.idGame =p.idGame WHERE p.idGame = ? AND p.isActive = 1";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$game->idGame);
			$stmt->execute();
			$result = $stmt->fetchAll();

			$game->players = $result;

			if(count($game->players) == $game->nbPlayers) {
				//echo "Enough players - ready to start the game";

				$sql = "UPDATE games SET started=1, startedAt = NOW(), activePlayerIndex=? WHERE idGame=?";
				$stmt = $conn->prepare($sql);
				$startingPlayer = rand(0,$game->nbPlayers-1);
				$stmt->bindParam(1,$startingPlayer);
				$stmt->bindParam(2,$game->idGame);
				$stmt->execute();

				if($stmt->rowCount() == 1) {				
					populateGameForPlayerRefresh($conn,$email,$hash);
				}
			}
			echo json_encode($game);
		break;
	}

$conn = null;
?> 