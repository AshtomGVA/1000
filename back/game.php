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
	$sql = "SELECT p.idGame, p.idPlayer, g.created_by, g.nbPlayers, g.difficulty, g.distanceGoal, g.idDeck, g.activePlayerIndex, g.aborted_at, g.started, g.active, g.closed, g.idWinner FROM participations p  INNER JOIN players p_self ON p_self.idPlayer = p.idPlayer AND p_self.emailPlayer = ? AND p_self.hashPlayer = ? INNER JOIN games g ON g.idGame = p.idGame WHERE p.isActive = 1";
	$stmt = $conn->prepare($sql);
	$stmt->bindParam(1,$email);
	$stmt->bindParam(2,$hash);
	$stmt->execute();
	$game = $stmt->fetchObject();	

	return $game;
}

function populateGameForPlayerRefresh($conn,$email,$hash) {
	$game = checkAuthGetGame($conn,$email,$hash);

	//UPDATE LAST PING
	if($game->idGame >= 0) {
		$sql = "UPDATE participations SET lastPing=NOW() WHERE idGame=? and idPlayer=?";
		$stmt = $conn->prepare($sql);
		$stmt->bindValue(1,$game->idGame);
		$stmt->bindValue(2,$game->idPlayer);
		$stmt->execute();
	}

	//print_r($game);

	//GET ALL PLAYERS
	$sql = "SELECT pl.idPlayer, pl.nmPlayer, p.score, p.distance, p.playerIndex, p.lastPing FROM players pl INNER JOIN participations p ON p.idPlayer=pl.idPlayer AND p.idGame=? ORDER BY p.playerIndex ASC";
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

function refreshGame($game,$conn) {
	$sql = "SELECT g.idGame, p.idPlayer, g.created_by, g.nbPlayers, g.difficulty, g.distanceGoal, g.idDeck, g.activePlayerIndex, g.aborted_at, g.started, g.active, g.idWinner FROM participations p  INNER JOIN players p_self ON p_self.idPlayer = p.idPlayer AND p_self.idPlayer=? INNER JOIN games g ON g.idGame = p.idGame WHERE p.isActive = 1";
	$stmt = $conn->prepare($sql);
	$stmt->bindParam(1,$game->idPlayer);
	$stmt->execute();
	$game = $stmt->fetchObject();		

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

function testMove($playerId, $card, $targetId, $game, $conn) {
		$distanceGoal = $game->distanceGoal;
		$battleTop = battleTop($targetId,$game,$conn);
		//check if a card has been selected
		if($card) {
			$canPlay = true;

			//1. check that the player has the card in its hand
			if(hasCard($playerId,$card,$game,$conn) == -1) {
				$canPlay = false;
				return "How can you play a card that you don't have?";
			}
			//2. if it's a attack, check that it's not played on itself (TODO: or a teammate)
			if($card->type == 'attack' && $playerId == $targetId) {
				$canPlay = false;	
				return 'You cannot attack yourself.';
			} 
			//3. if it's a attack, check that the target is moving (ie: roll card is present or the card is speed limit (D))
			if($card->type == 'attack' && (isMoving($targetId,$game,$conn) != true && $card->value != 'D')) {
				$canPlay = false;
				return 'Target is not moving and the attack is not a speed limit.';
			} 
			//3b. if it's a attack, check that the target is not protected against this attack
			if($card->type == 'attack' && hasProtection($targetId,$card->value,$game,$conn) == true) {
				$canPlay = false;
				return 'Target is protected against this attack.';
			}
			//4. if it's a defense, check that the target is the player itself (TODO: or a teammate)
			if($card->type == 'defense' && $playerId != $targetId) {
				$canPlay = false;	
				return 'You cannot defend someone else';
			} 
			//5. if it's a defense, check that the target is indeed impacted by the corresponding attack
			//ie compare the value of the topmost card of the battle stack with the value of the defense card
			if($card->type == 'defense') {
				//if the defense card is removing a speed limit (ie value == D) but there is no speed limit
				if($card->value == 'D' && isRestricted($targetId,$game,$conn) == false) { 
					$canPlay = false;	
					return 'There is no speed limit to waive.';
				}
				//if there is no card in the battle stack and the card is not a Roll card and speed limit waiver
				else if($battleTop==null && $card->value != 'E' && $card->value != 'D') { 
					$canPlay = false;	
					return 'There is no hazard here.';
				}
				//if there is at least one card on the battle stack and it's already a defense card and there is no speed limit
				else if ($battleTop!=null && $battleTop->type == $card->type && isRestricted($targetId,$game,$conn) == false) { 
					//and the card to be played is NOT a Roll card
					if($card->value != 'E') {
						$canPlay = false;	
						return 'There is no hazard here really.';
					}
				}
				//if there is at least one card on the battle stack but it has a different value than the card played and there is no speed limit
				else if ($battleTop!=null && $battleTop->value != $card->value && isRestricted($targetId,$game,$conn) == false) {
					//and the player has NO Protection for this attack
					if(hasProtection($targetId,$battleTop->value) != true) {
						$canPlay = false;	
						return 'This is not the right solution for this hazard.';
					}
				}
				//if the attack is covered by the protection, there is no need to play the defense for this attack
				else if ($battleTop!=null && $battleTop->value == $card->value && hasProtection($targetId,$battleTop->value,$game,$conn) == true) {
						$canPlay = false;	
						return 'You are already protected against this attack.';
				}
			}
			//6. if it's a protection, check that the target is itself (TODO: or a teammate)
			if($card->type == 'protection' && $playerId != $targetId) {
				$canPlay = false;	
				return 'You cannot protect someone else.';
			} 
			//7. if it's a distance, check that the target is itself (TODO: or a teammate)
			if($card->type == 'distance' && $playerId != $targetId) {
				$canPlay = false;	
				return 'You cannot give distance to someone else.';
			} 
			//8. if it's a distance, check that the target is moving (ie: roll card is present)
			if($card->type == 'distance' && isMoving($targetId,$game,$conn) != true) {
				$canPlay = false;	
				return 'Target must be moving to travel distances.';
			} 
			//9. if it's a distance, check that the distance respects the speed limit in place (if any) 
			if($card->type == 'distance' && isRestricted($targetId,$game,$conn) == true && $card->value>50) {
				$canPlay = false;	
				return 'Target cannot move faster than 50 due to speed restrictions.';
			} 
			//9. if it's a distance, check that the total distance won't be more than 1000 after the card is played  
			if($card->type == 'distance' && reachedDistance($targetId,$game,$conn) + $card->value > $distanceGoal) {
				$canPlay = false;	
				return 'You cannot travel more than $distanceGoal light-years. Pick a lesser distance card.';
			} 
			return $canPlay;			
		}
	}

function hasCard($playerId,$cardToCheck,$game,$conn){
	$sql = "SELECT hand FROM participations WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$playerId);
	$stmt->execute();
	$hand = $stmt->fetchAll(PDO::FETCH_CLASS);
	$hand = json_decode($hand[0]->hand);

	for($i=0;$i<count($hand);$i++) {
		$card = $hand[$i];
		//if the card search is a distance card, then check that the card is respecting the value limit
		if($cardToCheck->type == 'distance' && $card->type == $cardToCheck->type && $card->value == $cardToCheck->value) {
			$card->cardIndex = $i;
			return $card;
		}
		//if the card search is a distance card, then check that the card is respecting the value limit
		/*else if($cardToCheck->type == 'distance' && $card->type == $cardToCheck->type && $card->value < $cardToCheck->value) {
			$card->cardIndex = $i;
			return $card;
		}*/
		//else just check if card with right type and right value is in the hand
		else if($card->type == $cardToCheck->type && $card->value == $cardToCheck->value){
			$card->cardIndex = $i;
			return $card;
		}
		//otherwise check that it's at least the right type (eg: to play a random attack)
		else if($card->type == $cardToCheck->type && $cardToCheck->value == -1) {
			$card->cardIndex = $i;
			return $card;
		}
	}
	return -1;
}

function getPlayerId($playerIndex,$game,$conn){
	$sql = "SELECT idPlayer FROM participations WHERE idGame=? and playerIndex=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$playerIndex);
	$stmt->execute();
	$result = $stmt->fetchColumn(0);
	return $result;
}

function getPlayerHand($playerId,$game,$conn){
	$sql = "SELECT hand FROM participations WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$playerId);
	$stmt->execute();
	$result = $stmt->fetchAll();
	//print_r(json_decode($result[0]['hand']));
	return json_decode($result[0]['hand']);
}

function savePlayerHand($playerId,$handArray,$game,$conn){
	$sql = "UPDATE participations SET hand=? WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,json_encode($handArray));
	$stmt->bindValue(2,$game->idGame);
	$stmt->bindValue(3,$playerId);
	$stmt->execute();
	if($stmt->rowCount()==1) {
		return 'playerHand save OK'.$playerId;//true;
	}
	else {
		return 'playerHand save KO';//false;
	}
}

function getGameField($playerId,$game,$conn){
	$sql = "SELECT gameField FROM participations WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$playerId);
	$stmt->execute();
	$result = $stmt->fetchAll(PDO::FETCH_CLASS);
	return json_decode($result[0]->gameField);
}

function saveGameField($playerId,$gamefield,$game,$conn){
	$sql = "UPDATE participations SET gameField=? WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,json_encode($gamefield));
	$stmt->bindValue(2,$game->idGame);
	$stmt->bindValue(3,$playerId);
	$stmt->execute();
	if($stmt->rowCount()==1) {
		return true;
	}
	else {
		return false;
	}
}

function getDeck($game,$conn){
	$sql = "SELECT d.idDeck,cards FROM decks d INNER JOIN games g ON g.idDeck = d.idDeck WHERE g.idGame=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->execute();
	$result = $stmt->fetchAll();
	return $result[0];
}

function saveDeck($deckObject,$game,$conn){
	$sql = "UPDATE decks SET cards=? WHERE idDeck=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$deckObject->cards);
	$stmt->bindValue(2,$deckObject->idDeck);
	$result = $stmt->execute();
	//print_r($result);
	if($stmt->rowCount()==1) {
		return 'OK';//true;
	}
	else {
		return 'KO';//false;
	}
}

function setCoupFourreFlag($coupFourreFlag,$game,$conn){
	$sql = "UPDATE games SET ongoingCoup=? WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$coupFourreFlag);
	$stmt->bindValue(2,$game->idGame);
	$stmt->bindValue(3,$playerId);
	$stmt->execute();
	if($stmt->rowCount()==1) {
		return true;
	}
	else {
		return false;
	}
}


function battleTop($playerId,$game,$conn){
	$sql = "SELECT gameField FROM participations WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$playerId);
	$stmt->execute();
	$result = $stmt->fetchAll(PDO::FETCH_CLASS);
	//$battleStack = $stmt->fetchAll(PDO::FETCH_CLASS);
	return json_decode($result[0]->gameField->battles[0]);
}

function speedLimitTop($playerId,$game,$conn){
	$sql = "SELECT gameField FROM participations WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$playerId);
	$stmt->execute();
	$result = $stmt->fetchAll(PDO::FETCH_CLASS);
	$battleStack = $stmt->fetchAll(PDO::FETCH_CLASS);
	return json_decode($result[0]->gameField->speedLimit[0]);
}

function hasProtection($playerId,$value,$game,$conn) {
	$sql = "SELECT gameField FROM participations WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$playerId);
	$stmt->execute();
	$result = $stmt->fetchAll(PDO::FETCH_CLASS);
	$protectionStack = json_decode($result[0]->gameField->protections);

	for($i=0;$i<count($protectionStack);$i) {
		$card = $protectionStack[$i];
		if($card->value == $value){
			return $card;
		}
	}
	return false;		
}

function reachedDistance($playerId,$game,$conn) {
	$sql = "SELECT gameField FROM participations WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$playerId);
	$stmt->execute();
	$result = $stmt->fetchAll(PDO::FETCH_CLASS);
	$distance25 = json_decode($result[0]->gameField)->distances25;
	$distance50 = json_decode($result[0]->gameField)->distances50;
	$distance75 = json_decode($result[0]->gameField)->distances75;
	$distance100 = json_decode($result[0]->gameField)->distances100;
	$distance200 = json_decode($result[0]->gameField)->distances200;

	$reachedDistance = count($distance25)*25 + count($distance50)*50 + count($distance75)*75 + count($distance100)*100 + count($distance200)*200;

	return $reachedDistance;
}

function getPlayerScore($idPlayer,$game,$conn) {
	$sql = "SELECT gameField FROM participations WHERE idGame=? and idPlayer=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->bindValue(2,$idPlayer);
	$stmt->execute();
	$result = $stmt->fetchAll(PDO::FETCH_CLASS);

	//refresh game to ensure if there is a winner
	$game = refreshGame($game,$conn);


	//Reference https://en.wikibooks.org/wiki/Card_Games/Mille_Bornes
	//scored by each side
	//1 per km traveled
	$score = reachedDistance($idPlayer,$game,$conn);
	$protections = json_decode($result[0]->gameField)->protections;
	//100 per safety
	$score += count($protections)*100;

	//300 for each coup-fourré
	for($i=0;$i<count($protections);$i++) {
		$card = (object) $protections[$i];
		if($card->isCoup == true){
			$score += 300;
		}
	}
	//if all 4 safeties
	if(count($protections)==4) {
		$score += 700;
	}

	//if player is the winner
	if($idPlayer == $game->idWinner) {
		//400 for being the winner
		$score += 400;

		//Delayed action 	300 	for completing the trip after the draw pile is exhausted
		//Safe trip 		300 	for completing the trip without playing any 200 km cards
		//Extension 		200 	for completing the trip after calling for an Extension
		//Shutout 			500 	for completing a trip before the opponent has played any Distance cards
	}

	return $score;	
}

function isRestricted($playerId,$game,$conn) { //check if the player is under a speed limit
	//Optional: check if the topmost card of the speed limit stack is an active speed limit
	$topSpeedLimitCard = speedLimitTop($playerId,$game,$conn);
	if($topSpeedLimitCard && $topSpeedLimitCard->type == 'attack') {
		//console.log('There is speed limit on player '+self.player.playerName+'.');
		return true;
	}
	//console.log(self.player.playerName+' is not speed limited.');
	return false;		
}

function isMoving($playerId,$game,$conn) { //check if the player is moving
	//check if the topmost card of the battle stack is a Roll card (E)
	$topBattleCard = battleTop($targetId,$game,$conn);
	//or if the player has the priority card (E) in its protection stack
	$priorityProtection = hasProtection($playerId,'E',$game,$conn);

	//if the player has a Roll card or the Right Of Way card in play
	//or if it has the space pirate attack and the right of way card
	//or if it has any defense card and the right of way card
	//or if it has just played a protection against an attack (coup-fourré or not) and the right of way card
	if( ($topBattleCard && $topBattleCard->type == 'defense' && $topBattleCard->value == 'E') 
		|| ($topBattleCard && $topBattleCard->type == 'attack' && $topBattleCard->value == 'E' && $priorityProtection != 0 && $priorityProtection->value == 'E')
		|| ($topBattleCard && $topBattleCard->type == 'defense' && $topBattleCard->value != 'E' && $priorityProtection != 0 && $priorityProtection->value == 'E')
		|| ($topBattleCard && $topBattleCard->type == 'attack' && hasProtection($topBattleCard->value) && $priorityProtection != 0 && $priorityProtection->value == 'E')
		) {
		return true;
	}
	else {
		return false;
	}
}

function playCard($playerId, $card, $targetId, $game, $conn) {
		$gameField = getGameField($targetId,$game,$conn);
		$playerHand = getPlayerHand($playerId,$game,$conn);
		switch($card->type) {
			case 'attack':
				//if it's a blocking attack (ie: not a speed limit with value D)
				if($card->value != 'D') {
					array_unshift($gameField->battles,$card);
				}
				else {
					array_unshift($gameField->speedLimit,$card);
				}
				break;
			case 'defense':
				//if it's a speed limit waiver, put it in the speed limit stack
				if($card->value == 'D') {
					array_unshift($gameField->speedLimit,$card);
				}
				else {
					array_unshift($gameField->battles,$card);					
				}
				break;
			case 'protection':
				//if player is playing a coup-fourré
				if($game->ongoingCoup == true ) {
					$card->isCoup = true;
					//add 300 to the distance as a bonus for the coup fourré 
					//WRONG coup fourré only adds to score not distance
					//$gameField.reachedDistance(parseInt($gameField.reachedDistance())+300);

					//disable ongoingCoup to make sure that closeForCoupFourre won't catch up the flow of the game incorrectly
					$game->ongoingCoup = false;
				}
				array_push($gameField->protections,$card);
				break;
			case 'distance':
				switch($card->value) {
					case '25':
						array_push($gameField->distances25,$card);
						break;
					case '50':
						array_push($gameField->distances50,$card);
						break;
					case '75':
						array_push($gameField->distances75,$card);
						break;
					case '100':
						array_push($gameField->distances100,$card);
						break;
					case '200':
						array_push($gameField->distances200,$card);
						break;
				}
				break;
		}		

		$cardPlayedIndex = array_search($card,$playerHand);
		array_splice($playerHand,$cardPlayedIndex,1);

		//SAVE TARGET GAMEFIELD
		saveGameField($targetId,$gameField,$game,$conn);

		//SAVE PLAYER HAND
		savePlayerHand($playerId,$playerHand,$game,$conn);

		//UPDATE DISTANCE
		updateDistance($targetId,$game,$conn);

		//console.log(playerTableau.player.playerName+ ' plays '+$card->name+' ('+$card->type+ ' '+$card->value+') on '+$gameField.player.playerName);
		//if the card played is an attack, then check for possible coup-fourré
		$possibleCoup = (object) array('type'=>'protection','value'=>$card->value);
		if($card->type == 'attack' && hasCard($targetId,$possibleCoup,$game,$conn) != -1) {
			//console.log($gameField.player.playerName+' can choose to play a coup-fourré!');
			//$game->ongoingCoup = true;
			//SAVE GAME COUP FOURRE FLAG
			setCoupFourreFlag(true,$game,$conn);
		}
		//else if the player played a protection card, it can play another turn
		else if($card->type == 'protection') {
			//console.log(self.players[self.activePlayerIndex()].playerName+" can play again!");
			//startTurn($game,$conn);
			distributeCard($game->activePlayerIndex,$game,$conn);
		}
		//otherwise finish the turn and goes to next player
		else {
			nextPlayer($game,$conn);
		}
}

function nextPlayer($game,$conn) {
	$activePlayerIndex = $game->activePlayerIndex;
	//First check if the previous player did reach the goal
	if(checkForVictory($activePlayerIndex,$game,$conn) == false) {
		if($activePlayerIndex+1 <= $game->nbPlayers-1){
			$activePlayerIndex++;
		}	
		else {
			$activePlayerIndex = 0;
		}
		setActivePlayerIndex($activePlayerIndex,$game,$conn);
		distributeCard($activePlayerIndex,$game,$conn);

		//IF THE PLAYER IS A BOT
		/*if(self.players[self.activePlayerIndex()].current != true) {
			//self.AIPlay(self.gameFields()[self.activePlayerIndex()]);
		}*/	
	}
}

function checkForVictory($playerIndex,$game,$conn) {
	$idPlayer = getPlayerId($playerIndex,$game,$conn);
	$distanceToEval = reachedDistance($idPlayer,$game,$conn);
	//console.log('Distance: '+distanceToEval);
	if($distanceToEval<$game->distanceGoal) {
		//console.log('Not there yet...');
		return false;
	}
	else if($distanceToEval==$game->distanceGoal) {
		//console.log('VICTORY!');
		if(wrapUpGame($idPlayer,$game,$conn)) {
			recordResults($game,$conn);
		}
		return true;
	}
	else if($distanceToEval>$game->distanceGoal) {
		//console.log('BUSTED!');
	}
}

function startTurn($game,$conn) {
	distributeCard($game->activePlayerIndex,$game,$conn);
	//IF THE PLAYER IS A BOT
	/*if(thisGame.players[thisGame.activePlayerIndex()].current != true) {
		thisGame.AIPlay(thisGame.gameField[thisGame.activePlayerIndex()]);
	}*/
}


/*
//FUNCTION TO MAKE THE COMPUTER PLAYS
function AIPlay($playerId) {
	//console.log('Starting AI play for '+playerTableau.player.playerName);
	$hasPlayed = false;
	$card = -1;
	$targetTableau = -1;

	//1. if it's moving
	if($playerId.isMoving() == true) {
		//set the speed limit in order for the bot not to go beyond 1000
		$speed_limit = thisGame.distanceGoal - $playerId.reachedDistance();
		console.log('Distance restriction: '+speed_limit);

		//if it is impacted by a speed limit card and it can travel more than 50 before reaching 1000
		if($playerId.isRestricted()==true && speed_limit > 50) {
			speed_limit = 50;
		}
		console.log('Speed limit: '+speed_limit);
		//check if it has a distance card that respects the speed limit if any
		if($playerId.hasCard('distance',speed_limit) !=-1) {
			card = $playerId.hasCard('distance',speed_limit);
			//console.log(card);
		}
	}
	
	//if it still doesn't have a proper distance card to play
	if(card == -1) {
		console.log($playerId.player.playerName+" doesn't have a proper distance card to play.");
		//2. check if it's not moving
		if($playerId.isMoving() == false) {
			//if it is affected by a hazard and doesn't have a Protection for this hazard
			if($playerId.battles().length>0 && $playerId.battles()[0].type == 'attack' && !$playerId.hasProtection($playerId.battles()[0].value)) {
				console.log($playerId.player.playerName+' has a problem to solve before resuming its journey');
				//if it has the right card to defend
				if($playerId.hasCard('defense',$playerId.battles()[0].value)!=-1) {
					console.log($playerId.player.playerName+' has the right card to fix this!');
					card = $playerId.hasCard('defense',$playerId.battles()[0].value);
				}
				//or better it has the protection card
				else if($playerId.hasCard('protection',$playerId.battles()[0].value)!=-1) {
					card = $playerId.hasCard('protection',$playerId.battles()[0].value);
				}
			}
			//else if it has a Roll card
			else if($playerId.hasCard('defense','E')!=-1) {
				console.log($playerId.player.playerName+' has a Roll card!');
				card = $playerId.hasCard('defense','E');
			}
		}
	}

	//if still not moving but has a speed limit that impacts it
	if(card == -1) {			
		//if it is speed restricted and has a defense or a protection against the speed limit
		if($playerId.isRestricted() == true) {
			if($playerId.hasProtection('D')) {
				card = $playerId.hasCard('protection','D');
			}
			else if($playerId.hasCard('defense','D')) {
				card = $playerId.hasCard('defense','D');
			}
		}
	}

	//if after that it doesn't have a card to play, let's try to attack!
	if(card == -1) {
		console.log($playerId.player.playerName+' tries to attack');
		if($playerId.hasCard('attack',-1)!=-1) {
			//check for possible target (using a counter to prevent infinite loop)
			$checkIndex = parseInt(Math.random()*thisGame.gameField.length);
			console.log("Checking target out of "+thisGame.players.length+" players - starting with player number "+checkIndex);
			for($i=0; i<thisGame.players.length;i++) {
				//verify that the target is not itself and the target is moving
				if(checkIndex != $playerId.player.playerIndex && thisGame.gameField[checkIndex].isMoving()==true) {
					targetTableau = thisGame.gameField[checkIndex];
					card = $playerId.hasCard('attack',-1);
					console.log('Found a card and a target but need to check if the target is protected.');
					//if the moving target found doesn't have a protection then stop searching for targets
					if(targetTableau.hasProtection(card.value) == false) {
						console.log('Found moving target: '+targetTableau.player.playerName);
						i = thisGame.players.length;								
					}
					else {
						$card = -1;
						$targetTableau = -1;
					}
				}
				if(checkIndex<thisGame.gameField.length-1) {
					checkIndex++;
				}
				else {
					checkIndex = 0;
				}
			}
		}
	}

	//If the bot has a card, it can try to play it
	if(card != -1) {
		console.log(card);
		//if the target is different than itself
		if(targetTableau != -1) {
			console.log('Attacking '+targetTableau.player.playerName+' with '+card.name);
			if(thisGame.testMove($playerId,card,targetTableau)) {
				thisGame.playCard($playerId,card,targetTableau);
			}
		}
		else {
			console.log('Playing '+card.name+' on itself');
			if(thisGame.testMove($playerId,card,$playerId)) {
				thisGame.playCard($playerId,card,$playerId);
			}
		}
		hasPlayed = true;
	} 
	//otherwise it has to discard
	else {
		thisGame.randomAIDiscard($playerId);
		hasPlayed = true;
	}
}
*/

//RANDOM DISCARD BY THE BOT
function randomAIDiscard($playerId,$game,$conn) {
	$playerHand = getPlayerHand($playerId,$game,$conn);
	$rndIndex = random(0,count($playerHand)-1);
	//console.log($playerId.player.playerName+' cannot play so discarding...');	
	//console.log($playerId.player.hand());
	if($cardToDiscard != -1) {
		array_splice($playerHand,$rndIndex,1);
	}
	savePlayerHand($playerId,$playerHand,$game,$conn);
	nextPlayer($game,$conn);
}

function wrapUpGame($idPlayer,$game,$conn) {
	$sql = "UPDATE games SET idWinner=?, finishedAt=NOW(), activePlayerIndex=-1 WHERE idGame=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$idPlayer);
	$stmt->bindValue(2,$game->idGame);
	$stmt->execute();
	if($stmt->rowCount()==1) {
		return true;
	}
	else {
		return false;
	}
}

function recordResults($game,$conn) {
	//refresh game to ensure if there is a winner
	$game = refreshGame($game,$conn);

	$sql = "SELECT idPlayer FROM participations WHERE idGame=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$game->idGame);
	$stmt->execute();
	$result = $stmt->fetchAll(PDO::FETCH_CLASS);

	//print_r($result);

	foreach($result as $player) {
		$sql = "UPDATE participations SET score=? WHERE idGame=? and idPlayer=?";
		$stmt = $conn->prepare($sql);
		$score = getPlayerScore($player->idPlayer,$game,$conn);
		$stmt->bindValue(1,$score);
		$stmt->bindValue(2,$game->idGame);
		$stmt->bindValue(3,$player->idPlayer);
		$stmt->execute();

		$sql = "UPDATE players SET totalScore=totalScore+?, totalPlayed=totalPlayed+1, victories=victories+?, totalDistance=totalDistance+? WHERE idPlayer=?";
		$stmt = $conn->prepare($sql);
		$stmt->bindValue(1,$score);
		if($player->idPlayer == $game->idWinner) {
			$victory = 1;
		}
		else {
			$victory = 0;
		}
		$stmt->bindValue(2,$victory);
		$distance = reachedDistance($player->idPlayer,$game,$conn);
		$stmt->bindValue(3,$distance);
		$stmt->bindValue(4,$player->idPlayer);
		$stmt->execute();
	}
	if($stmt->rowCount()==1) {
		return true;
	}
	else {
		return false;
	}
}

function setActivePlayerIndex($activePlayerIndex,$game,$conn){
	$sql = "UPDATE games SET activePlayerIndex=? WHERE idGame=?";
	$stmt = $conn->prepare($sql);
	$stmt->bindValue(1,$activePlayerIndex);
	$stmt->bindValue(2,$game->idGame);
	$stmt->execute();
	if($stmt->rowCount()==1) {
		return true;
	}
	else {
		return false;
	}
}

function updateDistance($idPlayer,$game,$conn){
	$sql = "UPDATE participations SET distance=? WHERE idGame=? AND idPlayer=?";
	$stmt = $conn->prepare($sql);
	$distance = reachedDistance($idPlayer,$game,$conn);
	$stmt->bindValue(1,$distance);
	$stmt->bindValue(2,$game->idGame);
	$stmt->bindValue(3,$idPlayer);
	$stmt->execute();
	if($stmt->rowCount()==1) {
		return true;
	}
	else {
		return false;
	}
}

function distributeCard($playerIndex,$game,$conn) {
	$idPlayer = getPlayerId($playerIndex,$game,$conn);
	$hand = getPlayerHand($idPlayer,$game,$conn);
	$deck = getDeck($game,$conn);
	$cards = json_decode($deck['cards']);
	
	if(count($deck)>0) {
		$card = array_shift($cards); //get the first card from the deck
		array_push($hand,$card); //add it to the player's hand;
	}


	//SAVE DECK
	$deckObject = (object) $deck;
	$deckObject->cards = json_encode($cards);
	saveDeck($deckObject,$game,$conn);

	//SAVE HAND
	savePlayerHand($idPlayer,$hand,$game,$conn);

	//return $game->idGame;

	//return refreshGame($game,$conn);
}

function discard($cardArray,$game,$conn){
	$hand = getPlayerHand($game->idPlayer,$game,$conn);
	$card = (object) $cardArray;
	$cardToDiscard = hasCard($game->idPlayer,$card,$game,$conn);

	if($cardToDiscard != -1) {
		array_splice($hand,$cardToDiscard->cardIndex,1);
	}

	//echo $hand;

	savePlayerHand($game->idPlayer,$hand,$game,$conn);
	nextPlayer($game,$conn);
}

function initGame($conn, $email, $hash) {	
	$game = checkAuthGetGame($conn,$email,$hash);

	//recreate shuffled deck and store it
	$input = file_get_contents('1000lydeck.json');
	$deck = json_decode($input);
	shuffle($deck);
	$output = json_encode($deck);
	$stmt = $conn->prepare("UPDATE decks SET cards = :deckCards WHERE idDeck = :deckId");
	$stmt->bindParam(':deckCards',$output);
	$stmt->bindParam(':deckId',$game->idDeck);
	$stmt->execute();

	//constitute hands with 6 cards
	$hands = array();
	for($i=0; $i<$game->nbPlayers;$i++) {
		$hands[$i] = array();
	}

	for($k=0; $k<6; $k++) {
		for($i=0; $i<$game->nbPlayers;$i++) {
			$card = array_shift($deck);
			array_push($hands[$i], $card);
		}
	}

	$gamefield = (object) array();

	//create gamefields for all players
	$gamefield->distances25 = array();  //contains all the distances played
	$gamefield->distances50 = array();  //contains all the distances played
	$gamefield->distances75 = array();  //contains all the distances played
	$gamefield->distances100 = array();  //contains all the distances played
	$gamefield->distances200 = array();  //contains all the distances played

	$gamefield->protections = array();  //store the protections in play
	$gamefield->battles = array();  //holds the battle stack
	$gamefield->speedLimit = array();  //contains the speed limit stack
	$gamefield->freeSpace = array();  //to receive cards before putting in hand

	//update deck
	$sql = "UPDATE decks SET cards=? WHERE idDeck = ?";
	$stmt = $conn->prepare($sql);
	$newDeck = json_encode($deck);
	$stmt->bindValue(1,$newDeck);
	$stmt->bindValue(2,$game->idDeck);
	$stmt->execute();

	//order players by joined datetime and distribute all hands and init gamefields
	for($i=0; $i<$game->nbPlayers;$i++) {

		$sql = "SELECT idPlayer FROM participations WHERE idGame=? ORDER BY joined_at ASC LIMIT ?,1";
		$stmt = $conn->prepare($sql);
		$stmt->bindValue(1,$game->idGame);
		$stmt->bindValue(2,$i,PDO::PARAM_INT);
		$stmt->execute();
		$player = $stmt->fetchObject();

		$sql = "UPDATE participations SET playerIndex=?, hand=?, gameField=?, distance=0 WHERE idGame=? AND idPlayer=?";
		$stmt = $conn->prepare($sql);
		$stmt->bindValue(1,$i);
		$json_hand = json_encode($hands[$i]);
		$stmt->bindValue(2,$json_hand);
		$json_gamefield = json_encode($gamefield);
		$stmt->bindValue(3,$json_gamefield);
		$stmt->bindValue(4,$game->idGame);
		$stmt->bindValue(5,$player->idPlayer);
		$stmt->execute();
	}

	//update game started
	$sql = "UPDATE games SET idWinner=-1, startedAt=NOW(), finishedAt=0, started=1, activePlayerIndex=? WHERE idGame = ?";
	$stmt = $conn->prepare($sql);
	$activePlayerIndex = rand(0,$game->nbPlayers-1);
	$stmt->bindValue(1,$activePlayerIndex);
	$stmt->bindValue(2,$game->idGame);
	$stmt->execute();

	distributeCard($activePlayerIndex,$game,$conn);

}

	switch($_GET['cmd'].$_POST['cmd']) {
		case 'initGame':
			$email = $_POST['emailPlayer'].$_GET['emailPlayer'];
			$hash = $_POST['hashPlayer'].$_GET['hashPlayer'];
			initGame($conn,$email,$hash);

			//echo count(json_decode($newDeck));
			$game = populateGameForPlayerRefresh($conn,$_POST['emailPlayer'].$_GET['emailPlayer'],$_POST['hashPlayer'].$_GET['hashPlayer']);
			echo json_encode($game);

		break;

		case 'leaveOnGoingGame':
			$sql = "SELECT p.idGame, p.idPlayer, g.created_by, g.nbPlayers FROM participations p  INNER JOIN players p_self ON p_self.idPlayer = p.idPlayer AND p_self.emailPlayer = ? AND p_self.hashPlayer = ? INNER JOIN games g ON g.idGame = p.idGame WHERE p.isActive = 1";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$_POST['emailPlayer']);
			$stmt->bindParam(2,$_POST['hashPlayer']);
			$stmt->execute();
			$gameResult = $stmt->fetchObject();

			$sql = "UPDATE participations SET isActive = 0, isBot=1, left_at = NOW() WHERE idGame=? AND idPlayer=?";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$gameResult->idGame);
			$stmt->bindParam(2,$gameResult->idPlayer);
			$stmt->execute();

			//check if remaining human players
			$sql = "SELECT idPlayer FROM participations WHERE idGame=? AND isActive=1 AND isBot=0";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$gameResult->idGame);
			$stmt->execute();
			//if there is no more human playing, then close the game
			if($stmt->rowCount()<=0) {
				$sql = "UPDATE games SET active=0, started=0, closed=1, aborted_at = NOW() WHERE idGame=?";
				$stmt = $conn->prepare($sql);
				$stmt->bindParam(1,$game->idGame);
				$stmt->execute();				
			}
		break;

		case 'distributeCard':	
			$game = checkAuthGetGame($conn,$_POST['emailPlayer'].$_GET['emailPlayer'],$_POST['hashPlayer'].$_GET['hashPlayer']);	

			//get deck
			$sql = "SELECT cards FROM decks d WHERE d.idDeck = ?";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$game->idDeck);
			$stmt->execute();
			$deck = $stmt->fetchObject();
			$deck = json_decode($deck->cards);

			//get activePlayer hand
			$sql = "SELECT hand FROM participations p WHERE p.idGame=? AND p.idPlayer=?";
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(1,$game->idGame);
			$stmt->bindParam(2,$game->idPlayer);
			$stmt->execute();
			$hand = json_decode($stmt->fetchObject()->hand);

			$card = array_shift($deck);
			array_push($hand, $card);


			//update deck
			$sql = "UPDATE decks SET cards=? WHERE idDeck = ?";
			$stmt = $conn->prepare($sql);
			$newDeck = json_encode($deck);
			$stmt->bindParam(1,$newDeck);
			$stmt->bindParam(2,$game->idDeck);
			$stmt->execute();

			//update hand
			$sql = "UPDATE participations SET hand=? WHERE idGame=? AND idPlayer=?";
			$stmt = $conn->prepare($sql);
			$json_hand = json_encode($hand);
			$stmt->bindParam(1,$json_hand);
			$stmt->bindParam(2,$game->idGame);
			$stmt->bindParam(3,$game->idPlayer);
			$stmt->execute();

			//return GAME or WHAT?
		break;

		case "refreshPlayerGame":
			//echo "refreshPlayerGame";
			$game = populateGameForPlayerRefresh($conn,$_POST['emailPlayer'].$_GET['emailPlayer'],$_POST['hashPlayer'].$_GET['hashPlayer']);
			//print_r($game);
			$json_game = json_encode($game);
			echo $json_game;
		break;

		case "discard":
			$email = $_POST['emailPlayer'].$_GET['emailPlayer'];
			$hash = $_POST['hashPlayer'].$_GET['hashPlayer'];
			$game = checkAuthGetGame($conn,$email,$hash);

			//print_r($_GET['card']);

			discard($_POST['card'],$game,$conn);

			$game = populateGameForPlayerRefresh($conn,$_POST['emailPlayer'].$_GET['emailPlayer'],$_POST['hashPlayer'].$_GET['hashPlayer']);
			$json_game = json_encode($game);
			echo $json_game;
		break;

		case "playCard":
			$email = $_POST['emailPlayer'].$_GET['emailPlayer'];
			$hash = $_POST['hashPlayer'].$_GET['hashPlayer'];
			$game = checkAuthGetGame($conn,$email,$hash);	

			$card = (object) $_POST['card'];

			if(testMove($game->idPlayer,$card,$_POST['target'],$game,$conn)) {
				playCard($game->idPlayer,$card,$_POST['target'],$game,$conn);
			}
			echo json_encode(populateGameForPlayerRefresh($conn,$email,$hash));
		break;

		case "test":
			$email = $_POST['emailPlayer'].$_GET['emailPlayer'];
			$hash = $_POST['hashPlayer'].$_GET['hashPlayer'];

			//echo "refreshPlayerGame";
			$game = populateGameForPlayerRefresh($conn,$_POST['emailPlayer'].$_GET['emailPlayer'],$_POST['hashPlayer'].$_GET['hashPlayer']);
			//print_r($game);
			echo '<p>'.json_encode($game).'</p>';

			//$result = updateDistance($game->idPlayer,$game,$conn);
			//$result = updateDistance($game->idPlayer,$game,$conn);
			//$result = getPlayerScore($game->idPlayer,$game,$conn);
			//$result = getPlayerScore(993,$game,$conn);
			//$result = json_encode(recordResults($game,$conn));
			//$result = checkForVictory(1,$game,$conn);
			//initGame($conn,$email,$hash);
			//$result = initGame(1,$game,$conn);
			//echo '<p>'.$result.'</p>';

			//echo json_encode(getPlayerHand($game->idPlayer,$game,$conn));
			//print_r(getPlayerHand($game->idPlayer,$game,$conn));
			//echo json_encode(getDeck($game,$conn));

			/*
			$deck = getDeck($game,$conn);
			$cards = json_decode($deck['cards']);
			$card = array_shift($cards);

			$deckObject = (object) $deck;
			$deckObject->cards = json_encode($cards);

			//print_r($deckObject);

			echo saveDeck($deckObject,$game,$conn);

			$deck = (object) getDeck($game,$conn);

			echo count(json_decode($deck->cards));
			*/

			/*
			$card = (object) ["type"=>"distance","name"=>"200 light-years","value"=>"200","text"=>"You're at top speed!"];
			//$card = ["type"=>"distance","name"=>"200 light-years","value"=>"200","text"=>"You're at top speed!"];
			echo '<p>';
			echo json_encode(discard($card,$game,$conn));
			echo '</p>';
			*/

			//print_r($card);

			//distributeCard(0,$game,$conn);

			/*
			$hand = getPlayerHand($game->idPlayer,$game,$conn);

			print_r($hand);
			savePlayerHand($game->idPlayer,$hand,$game,$conn);

			distributeCard(0,$game,$conn);

			$hand = getPlayerHand($game->idPlayer,$game,$conn);

			print_r($hand);
			*/
			//print_r(getPlayerHand($game->idPlayer,$game,$conn));
			//print_r(getPlayerHand($game->idPlayer,$game,$conn));

			//echo nextPlayer($game,$conn);

			$game = populateGameForPlayerRefresh($conn,$_POST['emailPlayer'].$_GET['emailPlayer'],$_POST['hashPlayer'].$_GET['hashPlayer']);
			//print_r($game);
			echo '<p>'.json_encode($game).'</p>';
		break;

	}

$conn = null;
?> 