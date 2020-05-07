<HTML>
<HEAD>
<TITLE>
Campagne v1.0
</TITLE>
<link rel='stylesheet' href='styles.css'>
</HEAD>
<BODY>
<?php

// Pull in Amazon Web Services
require '/usr/home/adf/public_html/campagne/vendor/autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

include 'utility.php';
include 'strategy.php';
include 'htmldisplay.php';
include 'game.php';
include 'aws.php';

try {
	$displayer = new HTMLDisplay;
	$gamer = new Campagne;
	$saver = new AWS;

	if ($gameID = $_GET['id']) {
		$game = $saver->readGame ($gameID, $_GET['count']);
		$game['objects']['gamer'] = $gamer;
		switch ($_GET['action']) {
		case 'dump':
			var_dump ($game);
			return;
		case 'hint':
			$suggest = selectPlay ($game);
			break;
		case 'undo':
			break;
		default:
			$game = $gamer->makePlay ($game, $_GET, 'user');
		}
	} else {
		$game = $gamer->initialize ();
		$game['objects']['gamer'] = $gamer;
		$gameID = substr (hash ('sha512', time() . rand() . $_SERVER['REMOTE_ADDR']), 0, 12);
		$_GET['id'] = $gameID;
	}
	while ($game['user'][$game['who']]['type'] == 'B') {
		$game = $gamer->makePlay ($game, selectPlay ($game), 'bot');
	}

	$playList = $gamer->getPlayList ($game);
	$displayer->drawBoard ($game, $playList);
	$displayer->startRight ();
	$displayer->showMessages ($game);

	if (isset ($game['cards'])) {
		$displayer->drawPlayers ($game);
		$game['message'][] = count ($game['cards']) . " cards left<br>";
		foreach ($game['marked'] as $location => $dummy) {
			unset ($game['board'][explode ('-', $location)[0]][explode ('-', $location)[1]]['marked']);
		}
		unset ($game['marked']);
		if ($suggest) {
			$game['message'][] = "Suggested play: " . $gamer->playName ($suggest, $playList);
		}
		$displayer->showMessages ($game);
		$displayer->drawChoices ($game, $playList);
	} else {
		$game['message'][] = "Game over!";
		foreach ($gamer->finalScore ($game) as $who => $score) {
			$game['user'][$who]['score'] = $score;
		}
		$displayer->showMessages ($game);
		$displayer->drawPlayers ($game);
	}
	$saver->saveGame ($game, $gameID);
} catch (DetailedException $e) {
	echo "<font color=red>$e</red>";
	comment ($e->getDetails());
}
$displayer->ShowOptions ($game);
$displayer->endRight ();
// end of main script

?>
</BODY>
</HTML>
