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
} catch (CampException $e) {
	echo "<font color=red>$e</red>";
	comment ($e->getDetails());
}
$displayer->ShowOptions ($game);
$displayer->endRight ();
// end of main script

function arraySerialize ($array, $delims = false, $showKey = false) {
	$string = '';
	if (!$delims) {
		$delims = ' []';
	}
	foreach ($array as $key => $value) {
		if (is_array ($value)) {
			$value = substr($delims, 1, 1) . arraySerialize ($value, $delims, $showKey) . substr($delims, 2, 1);
		}
		$string .= substr($delims, 0, 1) . ($showKey ? $key . '=' : '') . $value;
	}
	return substr ($string, 1);
}

function comment ($string) {
	if ($_GET['debug'] < 1) {return;}
	if (substr ($string, 0, 1) == "\n") {
		echo "\n";
		$string = substr ($string, 1);
	}
	echo "<!-- ";
	if (is_array ($string)) {
		var_dump ($string);
	} else {
		echo $string;
	}
	echo " -->";
}

function darker ($color) {
	$newColor = '#';
	foreach (colorSplit ($color) as $element) {
		$newColor .= dechex ($element * 2 - 256);
	}
	return $newColor;
}

function brighter ($color) {
	$newColor = '#';
	$split = colorSplit ($color);
	$min = min ($split);
	$max = max ($split);
	foreach ($split as $element) {
		$newColor .= str_pad (dechex (intdiv (($element - $min) * 255, $max - $min)), 2, '0', STR_PAD_LEFT);
	}
	return $newColor;
}

function colorSplit ($color) {
	foreach (array (0, 1, 2) as $pos) {
		$ret[$pos] = hexdec (substr ($color, $pos * 2 + 1, 2));
	}
	return $ret;
}

function debug ($let, $details = false) {
	if ($_GET['debug'] > 1) {
		echo " (($let))";
	} else {
		if (is_array ($details)) {
			$details['title'] = $let;
		} else {
			$details = "(($let)) $details";
		}
	}
	if ($details  &&  $_GET['debug'] > 0) {comment ($details);}
}

function debugMessage ($message, $highlight = false) {
	if ($highlight == 'bold') {
		$message = "<b>$message</b>";
	} else if ($highlight) {
		$message = "<font color='$highlight'>$message</font>";
	}
	$GLOBALS['debugmessage'][] = $message;
}

class CampException extends Exception {
	private $details;
	function __construct ($message, $_details) {
		$this->details = $details;
		parent::__construct($message);
	}

	public function getDetails () {
		return $details;
	}
}

function throwException ($message, $details) {
	throw new CampException ($message, $details);
}

?>
</BODY>
</HTML>
