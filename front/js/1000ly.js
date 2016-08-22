function Game(gameData) {
	var thisGame = this;
	thisGame.baseURL = 'http://www.ashtom.net/1000/';
	thisGame.idGame = ko.observable(gameData.idGame);
	thisGame.active = ko.observable(gameData.active);
	thisGame.closed = ko.observable(gameData.closed);
	thisGame.started = ko.observable(gameData.started);
	thisGame.players = ko.observableArray();
	thisGame.nbPlayers = ko.observable(gameData.nbPlayers);
	//thisGame.player = 0;
	thisGame.hand = ko.observableArray();
	thisGame.created_by = ko.observable(gameData.created_by);
	thisGame.startedAt = gameData.startedAt;
	thisGame.aborted_at = gameData.aborted_at;
	thisGame.gameFields = ko.observableArray();
	thisGame.activePlayerIndex = ko.observable(gameData.activePlayerIndex);
	thisGame.activeCard = 0;
	thisGame.ongoingCoup = -1;
	thisGame.difficulty = gameData.difficulty;
	thisGame.distanceGoal = gameData.distanceGoal;
	thisGame.refreshDelay = 1000;
	thisGame.gameTimeout = 0;
	thisGame.distanceCardPlayerStep = 25;
	thisGame.distanceCardOpponentStep = 15;
	thisGame.orderedDisplayGameFields = ko.observableArray();
	thisGame.idWinner = ko.observable();

	thisGame.init = function()  {
		var params = {
			'cmd':'initGame',
			'emailPlayer':localStorage.getItem("emailPlayer"),
			'hashPlayer':localStorage.getItem("hashPlayer")
		};
		
		$.ajax({
			url : thisGame.baseURL+'game.php',
			method : 'POST',
			data : params,
			success : function(data){
				data = JSON.parse(data);
				thisGame.refreshGame(data); 
				//could use self (top object) to ensure that refresh is notified to knockout observables?
				thisGame.active(true);
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	thisGame.refreshPlayerGame = function() {
		var params = {'cmd':'refreshPlayerGame','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : thisGame.baseURL+'game.php',
			method : 'POST',
			data : params,
			success : function(data){
				ajaxData = JSON.parse(data);		
				thisGame.refreshGame(ajaxData);
				if(thisGame.active()==true) {
					thisGame.gameTimeout = setTimeout(thisGame.refreshPlayerGame, thisGame.refreshDelay);
				}
				else {
					clearTimeout(thisGame.gameTimeout);
				}
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	thisGame.refreshGame = function(data){
		thisGame.startedAt = data.startedAt;
		thisGame.aborted_at = data.aborted_at;
		thisGame.idGame(data.idGame);
		thisGame.nbPlayers(data.nbPlayers);
		thisGame.created_by(data.created_by);
		thisGame.active(data.active);
		thisGame.started(data.started);
		thisGame.closed(data.closed);
		thisGame.activePlayerIndex(data.activePlayerIndex);
		thisGame.idWinner(data.idWinner);

		thisGame.players.removeAll();
		thisGame.gameFields.removeAll();

		//console.log(data);

		var playerOrder = new Array();
		var currentPlayerIndex = 0;
		var distanceCardStep = 0;

		for(player of data.players) {
			var newPlayer = new Player(player);
			thisGame.players.push(newPlayer);
			var newField = new playerTableau(newPlayer,thisGame,data.gameFields[newPlayer.playerIndex].gameField);

			if(newPlayer.current() == true){
				distanceCardStep = thisGame.distanceCardPlayerStep;
				currentPlayerIndex = newPlayer.playerIndex;
			}
			else {
				distanceCardStep = thisGame.distanceCardOpponentStep;				
			}
			playerOrder.push(newPlayer.playerIndex);

			for(index in newField.distances25()) {
				newField.distances25()[index].top = index*distanceCardStep;
			}
			for(index in newField.distances50()) {
				newField.distances50()[index].top = index*distanceCardStep;
			}
			for(index in newField.distances75()) {
				newField.distances75()[index].top = index*distanceCardStep;
			}
			for(index in newField.distances100()) {
				newField.distances100()[index].top = index*distanceCardStep;
			}
			for(index in newField.distances200()) {
				newField.distances200()[index].top = index*distanceCardStep;
			}
			thisGame.gameFields.push(newField);
		}

		while(playerOrder[playerOrder.length-1] != currentPlayerIndex) {
			var firstElement = playerOrder.shift();
			playerOrder.push(firstElement);
		}

		thisGame.orderedDisplayGameFields.removeAll();

		for(var i=0;i<playerOrder.length;i++) {
			var gamefieldIndex = playerOrder[i];
			thisGame.orderedDisplayGameFields.push(thisGame.gameFields()[gamefieldIndex]);
		}

		if(data.hand){
			//console.log('Received Hand :');
			//console.log(data.hand);
			receivedLength = data.hand.length;
			localLength = thisGame.hand().length;

			if(receivedLength > localLength) {
				for(var i=0;i<receivedLength-localLength;i++){
					var index = localLength+i;
					//console.log('Index: '+index);
					switch(data.hand[index].type){
						case 'distance':
							var newCard = new DistanceCard(data.hand[index]);
						break;
						case 'attack':
							var newCard = new AttackCard(data.hand[index]);
						break;
						case 'defense':
							var newCard = new DefenseCard(data.hand[index]);
						break;
						case 'protection':
							var newCard = new ProtectionCard(data.hand[index]);
						break;
					}
					//console.log(newCard);
					thisGame.hand.push(newCard);
				}
			}
			else if(receivedLength < localLength) {
				thisGame.hand.removeAll();
				for(card of data.hand) {
					var newCard = new Card(card);
					thisGame.hand.push(newCard);
				}
			}
			//important when playing a protection card and being able to play again
			else if(receivedLength == localLength) {
				for(i in data.hand) {
					//if hand has changed, reset it!
					if(data.hand[i].type != thisGame.hand()[i].type || data.hand[i].value != thisGame.hand()[i].value) {
						thisGame.hand.removeAll();
						for(card of data.hand) {
							var newCard = new Card(card);
							thisGame.hand.push(newCard);
						}
					}
					/*
					console.log('Comparing slot '+i);
					console.log(data.hand[i]);
					console.log(thisGame.hand()[i]);
					*/
				}
			}
			//console.log('Local Hand: ');
			//console.log(thisGame.hand());
			//console.log(data.hand.length);
			//console.log(thisGame.hand().length);
		}
		//console.log(thisGame.gameFields());
		//console.log(thisGame.players());
		//console.log(thisGame.hand());
	}

	thisGame.createDeck = function() {
		for(var i=0;i<6;i++){
			var card = new AttackCard('Asteroid collision','A',"You collide with an asteroid causing you to stop and repair your ship",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<3;i++){
			var card = new AttackCard('Uranium leak','B',"You're out of uranium and your nuclear propulsion engine doesn't work anymore.",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<3;i++){
			var card = new AttackCard('Black Hole','C',"You've been trapped in a black hole which prevents you from progressing further.",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<4;i++){
			var card = new AttackCard('Magnetic field','D',"Your navigation tools are heavily affected by the magnetic field causing you to move out of hyperspace.",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<5;i++){
			var card = new AttackCard('Space pirate attack','E',"You have been ambushed by space pirates and have been forced to stop.",'');
			thisGame.deck.push(card);
		}

		for(var i=0;i<6;i++){
			var card = new DefenseCard('Hull Repair','A',"Your ship is repaired",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<6;i++){
			var card = new DefenseCard('Uranium delivery','B',"New fuel for your engine",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<6;i++){
			var card = new DefenseCard('Out of the black','C',"You found your way out of a black hole",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<6;i++){
			var card = new DefenseCard('Clear space','D',"No more magnetic interference",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<14;i++){
			var card = new DefenseCard('Engage hyperspace engine','E',"You ignit back your light speed engine.",'');
			thisGame.deck.push(card);
		}

		var card = new ProtectionCard('Ace Pilot','A',"You have been trained in the best pilot school and no asteroid is too big for you.",'');
		thisGame.deck.push(card);
		var card = new ProtectionCard('Uranium cargo','B',"You have your own supply of energy.",'');
		thisGame.deck.push(card);
		var card = new ProtectionCard('Advanced Radar','C',"Thanks to self wonderful tool, you can see every black hole from far away.",'');
		thisGame.deck.push(card);
		var card = new ProtectionCard('Magnetic shield','D',"You are equipped with a magnetic shield that protects your navigation tools from magnetic interferences.",'');
		thisGame.deck.push(card);
		var card = new ProtectionCard('Stellar Forces Flag','E',"You're on an official mission for the Stellar Forces and the pirates won't dare to attack.",'');
		thisGame.deck.push(card);

		for(var i=0;i<10;i++){
			var card = new DistanceCard('25 light-years','25',"At least, you're a moving forward.",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<10;i++){
			var card = new DistanceCard('50 light-years','50',"Acceleration is in progress.",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<10;i++){
			var card = new DistanceCard('75 light-years','75',"You're going fast.",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<12;i++){
			var card = new DistanceCard('100 light-years','100',"You've got a nice cruising speed.",'');
			thisGame.deck.push(card);
		}
		for(var i=0;i<4;i++){
			var card = new DistanceCard('200 light-years','200',"You're at top speed!",'');
			thisGame.deck.push(card);
		}
	}

	thisGame.shuffle = function(array) {

		var currentIndex = array.length, temporaryValue, randomIndex ;

	  	// While there remain elements to shuffle...
	  	while (0 !== currentIndex) {

		    // Pick a remaining element...
		    randomIndex = Math.floor(Math.random() * currentIndex);
		    currentIndex -= 1;

		    // And swap it with the current element.
		    temporaryValue = array[currentIndex];
		    array[currentIndex] = array[randomIndex];
		    array[randomIndex] = temporaryValue;
		  }
	  	return array;
	}

	thisGame.distributeCard = function(playerIndex) {
		if(thisGame.deck.length>0) {
			var card = thisGame.deck.shift(); //get the first card from the deck
			thisGame.hand.push(card); //add it to the player's current hand;
		}
		/*if(thisGame.gameField[playerIndex].current) {
			thisGame.players[playerIndex].hand.push(card); //add it to the player's current hand;
		}
		else {
			thisGame.gameField[playerIndex].freeSpace.push(card); //add it to the player's free space;
		}*/
	}

	thisGame.itsYourTurn = function() {
		if(thisGame.activePlayerIndex() >= 0){
			//console.log(thisGame.activePlayerIndex());
			return thisGame.players()[thisGame.activePlayerIndex()].current();
		}
		else {
			return false;
		}
		
	}

	thisGame.startTurn = function() {
		thisGame.distributeCard(thisGame.activePlayerIndex());
		//IF THE PLAYER IS A BOT
		if(thisGame.players[thisGame.activePlayerIndex()].current != true) {
			thisGame.AIPlay(thisGame.gameField[thisGame.activePlayerIndex()]);
		}
	}

	thisGame.selectCard = function(clickedCard) {
		if(thisGame.itsYourTurn()) {
			for(card of thisGame.hand()) {
				card.isSelected(false);
			}
			clickedCard.isSelected(true);
			thisGame.activeCard = clickedCard;	
			//console.log(card);
		}
	}

	thisGame.selectPlayer = function(playerTableau) {
		//if its your turn (TODO: and you have selected a card)
		if(thisGame.itsYourTurn() == true) {
			if(thisGame.testMove(thisGame.gameFields()[thisGame.activePlayerIndex()],thisGame.activeCard,playerTableau)) {
				thisGame.playCard(thisGame.gameFields()[thisGame.activePlayerIndex()],thisGame.activeCard,playerTableau);
			}
		}
	}

	thisGame.testMove = function(playerTableau, card, targetTableau) {
		//check if a card has been selected
		if(card) {
			var canPlay = true;

			//1. check that the player has the card in its hand
			if(playerTableau.hasCard(card.type,card.value) == -1) {
				canPlay = false;
				//console.log("How can you play a card that you don't have?");
			}
			//2. if it's a attack, check that it's not played on itself (TODO: or a teammate)
			if(card.type == 'attack' && playerTableau.player.playerIndex == targetTableau.player.playerIndex) {
				canPlay = false;	
				//console.log('You cannot attack yourthisGame.');
			} 
			//3. if it's a attack, check that the target is moving (ie: roll card is present or the card is speed limit (D))
			if(card.type == 'attack' && (targetTableau.isMoving() != true && card.value != 'D')) {
				canPlay = false;
				//console.log('Target is not moving and the attack is not a speed limit.');
			} 
			//3b. if it's a attack, check that the target is not protected against this attack
			if(card.type == 'attack' && targetTableau.hasProtection(card.value) == true) {
				canPlay = false;
				//console.log('Target is protected against this attack.');
			}
			//4. if it's a defense, check that the target is the player itself (TODO: or a teammate)
			if(card.type == 'defense' && playerTableau.player.playerIndex != targetTableau.player.playerIndex) {
				canPlay = false;	
				//console.log('You cannot defend someone else');
			} 
			//5. if it's a defense, check that the target is indeed impacted by the corresponding attack
			//ie compare the value of the topmost card of the battle stack with the value of the defense card
			if(card.type == 'defense') {
				//if the defense card is removing a speed limit (ie value == D) but there is no speed limit
				if(card.value == 'D' && targetTableau.isRestricted() == false) { 
					canPlay = false;	
					//console.log('There is no speed limit to waive.');
				}
				//if there is no card in the battle stack and the card is not a Roll card and speed limit waiver
				else if(targetTableau.battles().length==0 && card.value != 'E' && card.value != 'D') { 
					canPlay = false;	
					//console.log('There is no hazard here.');				
				}
				//if there is at least one card on the battle stack and it's already a defense card and there is no speed limit
				else if (targetTableau.battles().length>0 && targetTableau.battles()[0].type == card.type && targetTableau.isRestricted() == false) { 
					//and the card to be played is NOT a Roll card
					if(card.value != 'E') {
						canPlay = false;	
						//console.log('There is no hazard here really.');
					}
				}
				//if there is at least one card on the battle stack but it has a different value than the card played and there is no speed limit
				else if (targetTableau.battles().length>0 && targetTableau.battles()[0].value != card.value && targetTableau.isRestricted() == false) {
					//and the player has NO Protection for this attack
					if(targetTableau.hasProtection(targetTableau.battles()[0].value) != true) {
						canPlay = false;	
						//console.log('This is not the right solution for this hazard.');					
					}
				}
				//if the attack is covered by the protection, there is no need to play the defense for this attack
				else if (targetTableau.battles().length>0 && targetTableau.battles()[0].value == card.value && targetTableau.hasProtection(targetTableau.battles()[0].value) == true) {
						canPlay = false;	
						//console.log('You are already protected against this attack.');
				}
			}
			//6. if it's a protection, check that the target is itself (TODO: or a teammate)
			if(card.type == 'protection' && playerTableau.player.playerIndex != targetTableau.player.playerIndex) {
				canPlay = false;	
				//console.log('You cannot protect someone else.');
			} 
			//7. if it's a distance, check that the target is itself (TODO: or a teammate)
			if(card.type == 'distance' && playerTableau.player.playerIndex != targetTableau.player.playerIndex) {
				canPlay = false;	
				//console.log('You cannot give distance to someone else.');
			} 
			//8. if it's a distance, check that the target is moving (ie: roll card is present)
			if(card.type == 'distance' && targetTableau.isMoving() != true) {
				canPlay = false;	
				//console.log('Target must be moving to travel distances.');
			} 
			//9. if it's a distance, check that the distance respects the speed limit in place (if any) 
			if(card.type == 'distance' && targetTableau.isRestricted() == true && card.value>50) {
				canPlay = false;	
				//console.log('Target cannot move faster than 50 due to speed restrictions.');
			} 
			//9. if it's a distance, check that the total distance won't be more than 1000 after the card is played  
			if(card.type == 'distance' && parseInt(targetTableau.player.distance()) + parseInt(card.value) > thisGame.distanceGoal) {
				canPlay = false;	
				//console.log('You cannot travel more than '+thisGame.distanceGoal+' light-years. Pick a lesser distance card.');
			} 
			return canPlay;			
		}
	}

	thisGame.playCard = function(playerTableau, card, targetTableau) {
		if(thisGame.testMove(playerTableau, card, targetTableau)) { //we test the move again?

			//console.log(targetTableau);

			var params = {
				'cmd':'playCard',
				'emailPlayer':localStorage.getItem("emailPlayer"),
				'hashPlayer':localStorage.getItem("hashPlayer"),
				'target':targetTableau.player.idPlayer,
				'card':{'name':card.name,'type':card.type,'value':card.value,'text':card.text}
			};

			$.ajax({
				url : thisGame.baseURL+'game.php',
				method : 'POST',
				data : params,
				success : function(data){
					data = JSON.parse(data);
					thisGame.refreshGame(data);
				},
				error : function(err){
					//console.log(err.responseText);
				}
			});
		}
	}

	thisGame.nextPlayer = function() {
		//First check if the previous player did reach 1000
		thisGame.checkForVictory(thisGame.activePlayerIndex());

		if(thisGame.activePlayerIndex()+1 < thisGame.players.length)	{
			thisGame.activePlayerIndex(thisGame.activePlayerIndex()+1);
		}	
		else {
			thisGame.activePlayerIndex(0);
		}
		//console.log(thisGame.players()[thisGame.activePlayerIndex()].playerName+"'s turn!");
		thisGame.distributeCard(thisGame.activePlayerIndex());

		//IF THE PLAYER IS A BOT
		if(thisGame.players[thisGame.activePlayerIndex()].current != true) {
			//thisGame.AIPlay(thisGame.gameFields()[thisGame.activePlayerIndex()]);
		}
	}

	thisGame.checkForVictory = function(playerIndex) {
		var distanceToEval = thisGame.gameFields()[playerIndex].reachedDistance();
		//console.log('Distance: '+distanceToEval);
		if(distanceToEval<thisGame.distanceGoal) {
			console.log('Not there yet...');
			return false;
		}
		else if(distanceToEval==thisGame.distanceGoal) {
			console.log('VICTORY!');
			return true;
		}
		else if(distanceToEval>thisGame.distanceGoal) {
			console.log('BUSTED!');
		}
	}

	//FUNCTION TO MAKE THE COMPUTER PLAYS
	thisGame.AIPlay = function(playerTableau) {
		console.log('Starting AI play for '+playerTableau.player.playerName);
		var hasPlayed = false;
		var card = -1;
		var targetTableau = -1;

		//1. if it's moving
		if(playerTableau.isMoving() == true) {
			//set the speed limit in order for the bot not to go beyond 1000
			var speed_limit = thisGame.distanceGoal - playerTableau.reachedDistance();
			console.log('Distance restriction: '+speed_limit);

			//if it is impacted by a speed limit card and it can travel more than 50 before reaching 1000
			if(playerTableau.isRestricted()==true && speed_limit > 50) {
				speed_limit = 50;
			}
			console.log('Speed limit: '+speed_limit);
			//check if it has a distance card that respects the speed limit if any
			if(playerTableau.hasCard('distance',speed_limit) !=-1) {
				card = playerTableau.hasCard('distance',speed_limit);
				//console.log(card);
			}
		}
		
		//if it still doesn't have a proper distance card to play
		if(card == -1) {
			console.log(playerTableau.player.playerName+" doesn't have a proper distance card to play.");
			//2. check if it's not moving
			if(playerTableau.isMoving() == false) {
				//if it is affected by a hazard and doesn't have a Protection for this hazard
				if(playerTableau.battles().length>0 && playerTableau.battles()[0].type == 'attack' && !playerTableau.hasProtection(playerTableau.battles()[0].value)) {
					console.log(playerTableau.player.playerName+' has a problem to solve before resuming its journey');
					//if it has the right card to defend
					if(playerTableau.hasCard('defense',playerTableau.battles()[0].value)!=-1) {
						console.log(playerTableau.player.playerName+' has the right card to fix this!');
						card = playerTableau.hasCard('defense',playerTableau.battles()[0].value);
					}
					//or better it has the protection card
					else if(playerTableau.hasCard('protection',playerTableau.battles()[0].value)!=-1) {
						card = playerTableau.hasCard('protection',playerTableau.battles()[0].value);
					}
				}
				//else if it has a Roll card
				else if(playerTableau.hasCard('defense','E')!=-1) {
					console.log(playerTableau.player.playerName+' has a Roll card!');
					card = playerTableau.hasCard('defense','E');
				}
			}
		}

		//if still not moving but has a speed limit that impacts it
		if(card == -1) {			
			//if it is speed restricted and has a defense or a protection against the speed limit
			if(playerTableau.isRestricted() == true) {
				if(playerTableau.hasProtection('D')) {
					card = playerTableau.hasCard('protection','D');
				}
				else if(playerTableau.hasCard('defense','D')) {
					card = playerTableau.hasCard('defense','D');
				}
			}
		}

		//if after that it doesn't have a card to play, let's try to attack!
		if(card == -1) {
			console.log(playerTableau.player.playerName+' tries to attack');
			if(playerTableau.hasCard('attack',-1)!=-1) {
				//check for possible target (using a counter to prevent infinite loop)
				var checkIndex = parseInt(Math.random()*thisGame.gameField.length);
				console.log("Checking target out of "+thisGame.players.length+" players - starting with player number "+checkIndex);
				for(var i=0; i<thisGame.players.length;i++) {
					//verify that the target is not itself and the target is moving
					if(checkIndex != playerTableau.player.playerIndex && thisGame.gameField[checkIndex].isMoving()==true) {
						targetTableau = thisGame.gameField[checkIndex];
						card = playerTableau.hasCard('attack',-1);
						console.log('Found a card and a target but need to check if the target is protected.');
						//if the moving target found doesn't have a protection then stop searching for targets
						if(targetTableau.hasProtection(card.value) == false) {
							console.log('Found moving target: '+targetTableau.player.playerName);
							i = thisGame.players.length;								
						}
						else {
							var card = -1;
							var targetTableau = -1;
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
				if(thisGame.testMove(playerTableau,card,targetTableau)) {
					thisGame.playCard(playerTableau,card,targetTableau);
				}
			}
			else {
				console.log('Playing '+card.name+' on itself');
				if(thisGame.testMove(playerTableau,card,playerTableau)) {
					thisGame.playCard(playerTableau,card,playerTableau);
				}
			}
			hasPlayed = true;
		} 
		//otherwise it has to discard
		else {
			thisGame.randomAIDiscard(playerTableau);
			hasPlayed = true;
		}
	}

	//RANDOM DISCARD BY THE BOT
	thisGame.randomAIDiscard = function(playerTableau) {
		var rndIndex = parseInt(Math.random()*playerTableau.player.hand().length)
		playerTableau.player.hand().splice(rndIndex, 1);
		console.log(playerTableau.player.playerName+' cannot play so discarding...');	
		console.log(playerTableau.player.hand());
		//go to next player
		thisGame.nextPlayer();
	}

	//OPEN TIMEFRAME FOR HUMAN PLAYER TO PLAY A COUP-FOURRE
	thisGame.openForCoupFourre = function(targetTableau,attackerPlayerIndex,value) {
		thisGame.activePlayerIndex(targetTableau.player.playerIndex);
		console.log('Giving the opportunity for a coup-fourré to player '+thisGame.activePlayerIndex());
		//if the player is a bot, it will play the coup-fourré automatically
		if(targetTableau.player.current != true)
		{
			thisGame.AIPlayCoupFourre(targetTableau,value);
		}
		//else, open the time window for coup-fourré for 15 seconds
		//then close it and resume to the attacker player if no coup-fourré is played
		else {
			console.log('Opening response time window for '+targetTableau.player.playerName+" to play a coup-fourré.");
			thisGame.ongoingCoup = setTimeout(function() {thisGame.closeForCoupFourre(attackerPlayerIndex);}, 15000);
		}
	}

	//CLOSE TIMEFRAME FOR COUP-FOURRE
	thisGame.closeForCoupFourre = function(attackerPlayerIndex) {
		//check that the coup is still ongoing
		if(thisGame.ongoingCoup != -1) {
			thisGame.ongoingCoup = -1;	//reset the flag			
			thisGame.activePlayerIndex(attackerPlayerIndex);//go back to the turn of the original attacker			
			thisGame.nextPlayer();	//go to next player	
		}
		//else acknowledge that the coup has been played
		else {
			console.log('Coup-fourré was played.');
		}
		console.log('Timeframe to play a coup-fourré is closed.');
	}

	//MAKE THE BOT PLAY A COU-FOURRE
	thisGame.AIPlayCoupFourre = function(playerTableau,value) {
		//check if the attack is a speed limit (value == 'D') or a standard attack
		if(value == 'D') {
			var card = playerTableau.hasCard('protection',playerTableau.speedLimit()[0].value);
		}
		else {
			var card = playerTableau.hasCard('protection',playerTableau.battles()[0].value);	
		}	
		card.isCoup = true;
		console.log('Playing '+card.name+' on itself');
		if(thisGame.testMove(playerTableau,card,playerTableau)) {
			thisGame.playCard(playerTableau,card,playerTableau);
		}
	}

}

//function Card(name,type,value,text,pictureURL) {
function Card(cardData) {
	this.name = cardData.name;
	this.type = cardData.type;
	this.value = cardData.value;
	this.text = cardData.text;
	this.faceUp = false;
	this.isSelected = ko.observable(false); //a card is selected before player confirm to play it
	this.tapped = false; //to show coup-fourrés
	this.isNew = true;
	this.isCoup = false;

	this.getClass = function() {
		var cssClass = this.type+' '+this.value;
		if(this.isSelected() == true) cssClass = cssClass+' selected';
		return cssClass;
	}

	this.zoomCard = function() {
		console.log('ZOOM '+this.name+" ("+this.type+"-"+this.value+")");
	}
}

function AttackCard(name,value,text,pictureURL) {
	this.type = 'attack';
	Card.call(this,name,this.type,value,text,pictureURL);
}

function DefenseCard(name,value,text,pictureURL) {
	this.type = 'defense';
	Card.call(this,name,this.type,value,text,pictureURL);
}

function ProtectionCard(name,value,text,pictureURL) {
	this.type = 'protection';
	Card.call(this,name,this.type,value,text,pictureURL);
}

function DistanceCard(name,value,text,pictureURL) {
	this.type = 'distance';
	Card.call(this,name,this.type,value,text,pictureURL);
	this.top = 0;

	this.getTop = function() {
		return this.top+'px';
	}
}

function Player(data) {
	this.playerName = data.nmPlayer;
	this.current = ko.observable(data.current); //is this the playing player facing the screen?
	this.distance = ko.observable(data.distance);
	this.playerIndex = data.playerIndex;
	this.idPlayer = data.idPlayer;
	this.isBot = false;
	this.lastPing = ko.observable(data.lastPing); //get the timestamp from the last ping received from player
}

function playerTableau(player, game, data) {
	var thisTableau = this;
	thisTableau.player = player;
	thisTableau.game = game;

	thisTableau.distances25 = ko.observableArray();  //contains all the distances played
	thisTableau.distances50 = ko.observableArray();  //contains all the distances played
	thisTableau.distances75 = ko.observableArray();  //contains all the distances played
	thisTableau.distances100 = ko.observableArray();  //contains all the distances played
	thisTableau.distances200 = ko.observableArray();  //contains all the distances played

	thisTableau.protections = ko.observableArray();  //store the protections in play
	thisTableau.battles = ko.observableArray();  //holds the battle stack
	thisTableau.speedLimit = ko.observableArray();  //contains the speed limit stack
	thisTableau.freeSpace = ko.observableArray();  //to receive cards before putting in hand


	if(data!=null) {
		for(card of data.distances25){
			var newCard = new DistanceCard(card);
			thisTableau.distances25.push(newCard);
		}
		for(card of data.distances50){
			var newCard = new DistanceCard(card);
			thisTableau.distances50.push(newCard);
		}
		for(card of data.distances75){
			var newCard = new DistanceCard(card);
			thisTableau.distances75.push(newCard);
		}
		for(card of data.distances100){
			var newCard = new DistanceCard(card);
			thisTableau.distances100.push(newCard);
		}
		for(card of data.distances200){
			var newCard = new DistanceCard(card);
			thisTableau.distances200.push(newCard);
		}


		for(card of data.protections){
			var newCard = new ProtectionCard(card);
			thisTableau.protections.push(newCard);
		}
		for(card of data.battles){
			if(card.type == 'attack') {
				var newCard = new AttackCard(card);
			}
			else if(card.type == 'defense'){
				var newCard = new DefenseCard(card);			
			}
			thisTableau.battles.push(newCard);
		}
		for(card of data.speedLimit){
			if(card.type == 'attack') {
				var newCard = new AttackCard(card);
			}
			else if(card.type == 'defense'){
				var newCard = new DefenseCard(card);			
			}
			thisTableau.speedLimit.push(newCard);
		}
		for(card of data.freeSpace){
			var newCard = new Card(card);
			thisTableau.freeSpace.push(newCard);
		}
	}	

	thisTableau.score = 0;
	thisTableau.reachedDistance = ko.observable(0);
	thisTableau.current = player.current(); //is this the currently playing player tableau?
	thisTableau.css = thisTableau.current ? 'player' : 'opponent';
	thisTableau.speedRestriction = false;

	thisTableau.renderOrder = 0;

	thisTableau.getDistributedCards = function() { //to retrieve card from the free space to the player hand
		if(thisTableau.freeSpace().length>0) {
			var distCard = thisTableau.freeSpace.shift();
			thisTableau.hand.push(distCard);
		}
		if(thisTableau.freeSpace().length>0) {
			setTimeout(thisTableau.getDistributedCards, 1000);
		}
	}

	thisTableau.getID = function() {
		return thisTableau.player.playerName+thisTableau.player.playerIndex;
	}

	thisTableau.getPlayerActivity = function() {
		var now = new Date();
		var playerLastPing = new Date(player.lastPing().replace(/ /g,"T")+'Z');
		playerLastPing.setHours(playerLastPing.getHours()-1);
		var latency = Math.abs(now - playerLastPing);

		if(latency > 10000) {
			return 'badLat';
		}
		else if(latency > 5000) {
			return 'notGoodLat';
		}
		else if(latency > 2000) {
			return 'okLat';
		}
		else if(latency <= 2000) {
			return 'goodLat';
		}
	}

	thisTableau.isMoving = function() { //check if the player is moving
		//check if the topmost card of the battle stack is a Roll card (E)
		var topBattleCard = thisTableau.battles()[0];
		//or if the player has the priority card (E) in its protection stack
		var priorityProtection = 0;
		for(i in thisTableau.protections()) {
			var protect = thisTableau.protections()[i];
			if(protect.value == 'E') { 
				priorityProtection = protect;
			}
		}
		//console.log('Top battle card: '+topBattleCard);
		//if the player has a Roll card or the Right Of Way card in play
		//or if it has the space pirate attack and the right of way card
		//or if it has any defense card and the right of way card
		//or if it has just played a protection against an attack (coup-fourré or not) and the right of way card
		if( (topBattleCard && topBattleCard.type == 'defense' && topBattleCard.value == 'E') 
			|| (topBattleCard && topBattleCard.type == 'attack' && topBattleCard.value == 'E' && priorityProtection != 0 && priorityProtection.value == 'E')
			|| (topBattleCard && topBattleCard.type == 'defense' && topBattleCard.value != 'E' && priorityProtection != 0 && priorityProtection.value == 'E')
			|| (topBattleCard && topBattleCard.type == 'attack' && thisTableau.hasProtection(topBattleCard.value) && priorityProtection != 0 && priorityProtection.value == 'E')
			) {
			//console.log('Target is moving');
			return true;
		}
		else {
			//console.log('Target is static');
			return false;
		}
	}

	thisTableau.isRestricted = function() { //check if the player is under a speed limit
		//Optional: check if the topmost card of the speed limit stack is an active speed limit
		var topSpeedLimitCard = thisTableau.speedLimit()[0];
		if(topSpeedLimitCard && topSpeedLimitCard.type == 'attack' && thisTableau.hasProtection('D') == false) {
			console.log('There is speed limit on player '+thisTableau.player.playerName+'.');
			return true
		}
		console.log(thisTableau.player.playerName+' is not speed limited.');
		return false;		
	}

	thisTableau.hasCard = function(type,value) {
		for(var i=0;i<thisTableau.game.hand().length;i++) {
			var card = thisTableau.game.hand()[i];
			//if the card search is a distance card, then check that the card is respecting the value limit
			if(type == 'distance' && card.type == type && card.value <= value) {
				return card;
			}
			//else just check if card with right type and right value is in the hand
			else if(card.type == type && card.value == value){
				return card;
			}
			//otherwise check that it's at least the right type (eg: to play a random attack)
			else if(card.type == type && value == -1) {
				return card;
			}
		}
		return -1;
	}

	thisTableau.hasProtection = function(value) {
		for(var i=0;i<thisTableau.protections().length;i++) {
			var card = thisTableau.protections()[i];
			if(card.value == value){
				console.log(thisTableau.player.playerName+' is protected.');
				return true;
			}
		}
		return false;		
	}

	thisTableau.discard = function(card,event) {
		var params = {
			'cmd':'discard',
			'emailPlayer':localStorage.getItem("emailPlayer"),
			'hashPlayer':localStorage.getItem("hashPlayer"),
			'card':{'name':card.name,'type':card.type,'value':card.value,'text':card.text}
		};

		$.ajax({
			url : thisGame.baseURL+'game.php',
			method : 'POST',
			data : params,
			success : function(data){
				data = JSON.parse(data);
				game.refreshGame(data);
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}
}



function evalEmail(email) {
	var exp = /^[a-z][a-z0-9._-]*@[a-z0-9._-]*\.[a-z]{2,3}/;

	if(exp.test(email)) {
		return true;
	} else {
		return false;
	}
}

function ThousandLightYears() {
	var self = this;
	self.baseURL = "http://www.ashtom.net/1000/";
	self.refreshDelay = 2000;
	self.playerProfile = ko.observable({'nmPlayer':localStorage.getItem("nmPlayer"),'emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")});
	self.playersList = ko.observableArray();
	self.availableGames = ko.observableArray();
	self.gamesList = ko.observableArray();
	self.waitingInterval = 0;

	self.playersListExpanded = ko.observable(false);
	self.registerExpanded = ko.observable(false);
	self.loginExpanded = ko.observable(false);

	var game = new Game({'idGame':localStorage.getItem("currentIdGame"),started:0});
	self.currentGame = ko.observable(game);


	self.init = function() {
		self.getProfile();
	}

	self.getProfile = function() {
		var params = {'cmd':'getProfile','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'community.php',
			method : 'GET',
			dataType: 'json',
			data : params,
			success : function(data){
				self.playerProfile(data);

				//check if player can log in and if it's already in a game
				if(self.playerProfile().hashPlayer != null) {
					if(self.playerProfile().idGame != null) {
						if(self.currentGame().active() == false) {
							self.refreshWaitingRoom();
						}
						else {
							self.loadStartedGame(self.currentGame());
						}				
					}
					else {
						initPlayerSlider();
						self.refreshAvailableGames();			
					}			
				}
			},
			error : function(err){
				console.log(err.responseText);
			}
		});		
	}

	self.createGame = function() {
		//var nbPlayers = $("input[name='nbPlayers']").val();
		var nbPlayers = parseInt($("#nbPlayers").text());
		var params = {'cmd':'createGame','nbPlayers':nbPlayers,'emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'hub.php',
			method : 'POST',
			data : params,
			success : function(data){
				self.currentGame().idGame = data;
				localStorage.setItem('currentIdGame',data);
				self.refreshWaitingRoom();
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	self.joinGame = function() {
		var params = {'cmd':'joinGame','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer"),'idGame':this.idGame};
		$.ajax({
			url : self.baseURL+'hub.php',
			method : 'POST',
			data : params,
			success : function(data){
				self.currentGame().idGame(data.idGame);
				localStorage.setItem('currentIdGame',data.idGame);
				self.refreshWaitingRoom();
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	self.resetHubPage = function(data) {
		var newGame = new Game({idGame:null,started:0});
		self.currentGame(newGame);
		localStorage.removeItem('currentIdGame');
		self.refreshAvailableGames();
		initPlayerSlider();
	}

	self.leaveGame = function() {
		var params = {'cmd':'leaveGame','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'hub.php',
			method : 'POST',
			data : params,
			success : function(data){
				self.resetHubPage(data);
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	self.leaveOnGoingGame = function() {
		var params = {'cmd':'leaveOnGoingGame','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'game.php',
			method : 'POST',
			data : params,
			success : function(data){
				self.resetHubPage(data);
			},
			error : function(err){
				console.log(err.responseText);
			}
		});		
	}

	self.CompleteAndReturnToHub = function(){
		clearTimeout(self.currentGame().gameTimeout);
		self.leaveGame();
		self.getProfile();
	}

	self.cancelGame = function() {
		var params = {'cmd':'cancelGame','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'hub.php',
			method : 'POST',
			data : params,
			success : function(data){
				self.resetHubPage(data);
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	self.startGame = function() {
		var params = {'cmd':'initGame','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'game.php',
			method : 'POST',
			data : params,
			success : function(data){
				data = JSON.parse(data);
				self.loadStartedGame(data);
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	self.loadStartedGame = function(game) {
		clearTimeout(self.waitingInterval);
		self.refreshGame(game);
	}

	self.refreshGame = function(ajaxData) {
		var params = {'cmd':'refreshPlayerGame','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'game.php',
			method : 'POST',
			data : params,
			success : function(data){
				ajaxData = JSON.parse(data);
				var game = new Game(ajaxData);
				self.currentGame(game);
				self.currentGame().refreshGame(ajaxData);
				self.currentGame().refreshPlayerGame();
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	self.refreshWaitingRoom = function() {
		var params = {'cmd':'refreshWaitingRoom','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'hub.php',
			method : 'POST',
			data : params,
			success : function(data){
				data = JSON.parse(data);
				clearTimeout(self.waitingInterval);
				self.refreshGame(data);
				console.log(data);
				console.log(self.currentGame());
				if(self.currentGame().idGame() != null) {
					self.waitingInterval = setTimeout(self.refreshWaitingRoom, self.refreshDelay);					
				}
				else {
					self.refreshAvailableGames();	
				}
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	self.refreshAvailableGames = function() {
		var params = {'cmd':'refreshAvailableGames','emailPlayer':localStorage.getItem("emailPlayer"),'hashPlayer':localStorage.getItem("hashPlayer")};
		$.ajax({
			url : self.baseURL+'hub.php',
			method : 'POST',
			data : params,
			success : function(data){
				data = JSON.parse(data);
				self.availableGames(data);
				clearTimeout(self.waitingInterval);
				self.waitingInterval = setTimeout(self.refreshAvailableGames, self.refreshDelay);
			},
			error : function(err){
				console.log(err.responseText);
			}
		});
	}

	self.register = function() {
		var name = $("input[name='playerName']").val();
		var email = $("input[name='playerEmail']").val();
		var pwd = $("input[name='playerPwd']").val();
		var confirm = $("input[name='playerConfPwd']").val();
		$("input[name='playerPwd']").val('');
		$("input[name='playerConfPwd']").val('');
		var error = 0;
		var errorText = "";
		if(name=='') {
			error++;
			errorText += "Please provide a name.<br />";
		}
		if(pwd=='') {
			error++;
			errorText += "Please provide a password.<br />";
		}
		else if(pwd != confirm) {
			error++;
			errorText += "Passwords don't match.<br />";
		}
		if(!evalEmail(email)) {
			error++;
			errorText += "Please provide a valid email address.<br />";
		}

		if(error==0) {
			var params = {'cmd':'register','nmPlayer':name,'emailPlayer':email,'playerPwd':pwd};
			$.ajax({
				url : self.baseURL+'community.php',
				method : 'POST',
				data : params,
				success : function(data){
					localStorage.setItem('nmPlayer',name);
					localStorage.setItem('hashPlayer',data);
					localStorage.setItem('emailPlayer',email);
					$("input[name='playerName']").val('');
					$("input[name='playerEmail']").val('');
					$("input[name='playerPwd']").val('');
					$("input[name='playerConfPwd']").val('');
					$('#registerMessage').text('');
					self.getProfile();
				},
				error : function(err){
					console.log(err.responseText);
				}
			});			
		}
		else{			
			$('#registerMessage').html(errorText);
		}
	}

	self.login = function() {
		var email = $("input[name='loginEmail']").val();
		var pwd = $("input[name='loginPwd']").val();

		var error = 0;
		var errorText = "";
		if(!evalEmail(email)) {
			error++;
			errorText += "Please provide a valid email address.<br />";
		}
		if(pwd=='') {
			error++;
			errorText += "Please provide a password.<br />";
		}

		if(error==0) {
			var params = {'cmd':'login','emailPlayer':email,'password':pwd};
			$.ajax({
				url : self.baseURL+'community.php',
				method : 'POST',
				data : params,
				success : function(data){
					data = JSON.parse(data);
					localStorage.setItem('nmPlayer',data.nmPlayer);
					localStorage.setItem('emailPlayer',data.emailPlayer);
					localStorage.setItem('hashPlayer',data.hashPlayer);
					self.getProfile();
					$("input[name='loginName']").val('');
					$("input[name='loginPwd']").val('');
				},
				error : function(err){
					//console.log(err.responseText);
					$('#loginMessage').html(err.responseText);					
				}
			});
		}
		else{			
			$('#loginMessage').html(errorText);
		}
	}

	self.logout = function() {
		localStorage.removeItem('currentIdGame');
		localStorage.removeItem('nmPlayer');
		localStorage.removeItem('hashPlayer');
		self.playerProfile({});
		clearTimeout(self.waitingInterval);
	}

	self.getPlayersList = function() {
		self.expandPlayersList();
		var params = {'cmd':'playersList','page':0};
		$.ajax({
			url : self.baseURL+'community.php',
			method : 'GET',
			data : params,
			success : function(data){
				data = JSON.parse(data);
				//console.log(data);
				self.playersList.removeAll();
				for(player of data){
					player.expanded = ko.observable(false);
					self.playersList.push(player);
				}
			},
			error : function(err){
				console.log(err.responseText);
			}
		});		
	}

	self.expandPlayersList = function() {
		self.playersListExpanded(!self.playersListExpanded());
	}
	self.expandLogin = function() {
		self.loginExpanded(!self.loginExpanded());
	}
	self.expandRegister = function() {
		self.registerExpanded(!self.registerExpanded());
	}

	self.expandPlayerProfile = function() {
		this.expanded(!this.expanded());
		console.log(this.expanded());
	}
}

var mainApp = new ThousandLightYears();
mainApp.init();
ko.applyBindings(mainApp,document.getElementById('wrapper'));


function initPlayerSlider() {
    initNbPlayers = 2;  
    $( "#nbPlayers" ).text( initNbPlayers );
    $( "#nbPlayersSlider" ).slider({
      min: 2,
      max: 4,
      value: initNbPlayers,
      step: 1,
      slide: function( event, ui ) {
        $( "#nbPlayers" ).text( ui.value );
        console.log($("#nbPlayers").text());
      }
    });
}  

$(function() {
    $( document ).tooltip();
    initPlayerSlider();
});