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
include "/usr/home/adf/credentials.php";

include 'strategy.php';
try {
	$s3 = new Aws\S3\S3Client([
    'version' => 'latest',
    'region' => 'us-east-1',
    'credentials' => array(
      'key' => AWS_KEY,
      'secret'  => AWS_SECRET
    )
  ]);
  $bucket = 'campagne.8wheels.org';

	if ($gameID = $_GET['id']) {
		$game = readGame ($gameID, $_GET['count']);
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
			$game = makePlay ($game, $_GET, 'user');
		}
	} else {
		$game = initialize ();
		$gameID = substr (hash ('sha512', time() . rand() . $_SERVER['REMOTE_ADDR']), 0, 12);
		$_GET['id'] = $gameID;
	}
	while ($game['user'][$game['who']]['type'] == 'B') {
		$game = makePlay ($game, selectPlay ($game), 'bot');
	}
	echo "<div class='left' id='tablesection'>";
	$playList = getPlayList ($game);
	drawBoard ($game, $playList);
	echo "</div>";

	echo "<div class='right'>";
	showMessages ($game);

	if (isset ($game['cards'])) {
		if ($_GET['debug'] > 1) {
			echo '<BR>';
		}
		drawPlayers ($game);
		echo count ($game['cards']) . " cards left<br>\n";
		foreach ($game['marked'] as $location => $dummy) {
			unset ($game['board'][explode ('-', $location)[0]][explode ('-', $location)[1]]['marked']);
		}
		unset ($game['marked']);
		echo '<P>';
		if ($suggest) {
			$suggestName = playName ($suggest, $playList);
			echo "Suggested play: $suggestName<P>";
		}
		drawChoices ($game, $playList);
	} else {
		Echo "Game over!<BR>";
		foreach (finalScore ($game) as $who => $score) {
			$game['user'][$who]['score'] = $score;
		}
		showMessages ($game);
		drawPlayers ($game);
	}
	saveGame ($game, $gameID);
} catch (CampException $e) {
	echo "<font color=red>$e</red>";
	comment ($e->getDetails());
}
echo "<p><a href='index.html'>Restart</a>&nbsp;&nbsp;";
$count = count ($game['cards']);
if ($_GET['debug'] > 0) {
	echo "<a href='campagne.php?id=$gameID&action=dump&count=$count' target='_blank'>Dump</a>&nbsp;&nbsp;";
	$debugParm = "&debug={$_GET['debug']}";
} else {
	$debugParm = '';
}
if ($count) {
	echo "<a href='campagne.php?id=$gameID&action=hint&count=$count{$debugParm}'>Hint</a>&nbsp;&nbsp;";
	$count += $game ['playercount'];
	echo "<a href='campagne.php?id=$gameID&action=undo&count=$count{$debugParm}'>Undo</a>&nbsp;&nbsp;";
}
echo "<a href='campagne_help.html' target='_blank'>Help</a>&nbsp;&nbsp;";
echo "</div>"; // split right
// end of main script

function initialize () {
	foreach (file ('cards.txt') as $cardDesc) {
		$len = strlen ($cardDesc);
		if (ord(substr($cardDesc, $len-1)) < 32) {
			$cardDesc = substr($cardDesc, 0, $len-1);
		}
		unset ($cardArray);
		$sides = explode ('|', ($cd1 = explode (',', $cardDesc))[0]);
		$cardArray[0][2] = cardPart ($sides, 0);
		$cardArray[2][4] = cardPart ($sides, 1);
		$cardArray[4][2] = cardPart ($sides, 2);
		$cardArray[2][0] = cardPart ($sides, 3);
		$cardArray[0][0] = cardPart ($sides, 3, 0);
		$cardArray[0][4] = cardPart ($sides, 0, 1);
		$cardArray[4][4] = cardPart ($sides, 1, 2);
		$cardArray[4][0] = cardPart ($sides, 2, 3);
		if ($cd1[1] == 'M') {
			$middle = 'M';
		} else if ($sides [0] == $sides [2]) {
			$middle = $cardArray[0][2];
		} else if ($sides [1] == $sides [3]) {
			$middle = $cardArray[2][0];
		} else if (preg_match ('/R([0-9]*)/', $cardDesc, $matched)) {
			$middle = 'R' . ($matched[1] ? 'x' : '1');
		} else {
			$middle = 'C1';
		}
		$cardArray[2][2] = $middle;

		if ($cd1[1] == 'S') {
			$cardArray['S'] = true;
		}
		for ($x = 0; $x < 5; $x+=2) {
			interpolate ($cardArray, $x, 1, $x, 0, $x, 2);
			interpolate ($cardArray, $x, 3, $x, 4, $x, 2);
		}
		for ($y = 0; $y < 5; $y++) {
			interpolate ($cardArray, 1, $y, 0, $y, 2, $y);
			interpolate ($cardArray, 3, $y, 4, $y, 2, $y);
		}

		// combine fields
		for ($x = 0; $x < 4; $x++) {
			for ($y = 0; $y < 4; $y++) {
				mergeFields ($cardArray, $x, $y, $x+1, $y);
				mergeFields ($cardArray, $x, $y, $x, $y+1);
			}
		}
		$game['cards'][] = $cardArray;
		if (strpos ($cardDesc, 'C|C') !== false) {
			comment ("\n$cardDesc -> " . arraySerialize ($cardArray));
		}
	}

	$midpoint = 50;
	$game['extent'] = array ("L" => $midpoint, "R" => $midpoint, "T" => $midpoint, "B" => $midpoint);

	$game['pending'] = 0;
	$game['playercount'] = 2;
	$game['who'] = rand (0, $game ['playercount']);
	$game = makePlay ($game, array ('x' => "$midpoint", 'y' => "$midpoint", 'orient' => 'N'), 'initialize');

	$game['user'][0] = array ('color' => 'red', 'score' => 0, 'type' => 'B', 'meeples' => 7, 'name' => 'Marius');
	$game['user'][1] = array ('color' => 'green', 'score' => 0, 'type' => 'H', 'meeples' => 7, 'name' => $_GET['name1'] ?? 'anonymous');

  foreach (array ('twocellscore' => 2, 'fieldcityscore' => 3) as $parm => $default) {
		$game['parms'][$parm] = $_GET[$parm] ?? $default;
	}

	foreach ($_GET as $key => $value) {
		if (substr ($key, 0, 2) == 's_') {
			$game ['strategy'][substr ($key, 2)] = $value;
		}
	}
	return $game;
}

function getPlayList ($game, $test = false, $card = false, $point = false) {
	$extent = $game['extent'];
	$board = $game['board'];
	if (!$card) {
		$card = $game['cards'][$game['pending']];
	}
	$counter = 0;

	if ($point) {
		$minx = $maxx = $point['x'];
		$miny = $maxy = $point['y'];
	} else {
		$miny = $extent ['T'] - 1;
		$maxy = $extent ['B'] + 1;
		$minx = $extent ['L'] - 1;
		$maxx = $extent ['R'] + 1;
	}

	for ($y = $miny; $y <= $maxy; $y++) {
		for ($x = $minx; $x <= $maxx; $x++) {
			$any = false;
			if (isset ($board[$x][$y])) {
				// already set
			} else {
				foreach (array ('N', 'E', 'W', 'S') as $direction) {
					$cardFix = rotate ($card, $direction);
					$valid = false;
					for ($side = 0; $side < 4; $side++)	{
						$subX = array (2, 0, 2, 4)[$side];
						$subY = array (0, 2, 4, 2)[$side];
						$thisEntity = $cardFix [$subX][$subY];
						if ($otherCard = $board[$x + ($subX - 2)/2][$y + ($subY - 2)/2]) {
							$otherEntity = $otherCard [4 - $subX][4 - $subY];
							if (substr ($otherEntity, 0, 1) == substr ($thisEntity, 0, 1)) {
								$valid = true;
								$ox = 4 - $subX;
								$oy = 4 - $subY;
							} else {
								$valid = false;
								break;
							}
						}
					}
					if ($valid) {
						if ($test) {
							return true;
						}
						$thisPlay = array ('x' => $x, 'y' => $y, 'orient' => $direction);
						if (!$any) {
							$countLetter = str_repeat (chr (ord ('A') + $counter % 26), $counter / 26 + 1);
							if ($countLetter == 'A') {
								$GLOBALS['cellA']="cell{$x}_$y";
							}
							$playList ['cell'][$x][$y] = $countLetter;
							$playList ['count'][$countLetter] = array ('x' => $x, 'y' => $y);
							$counter++;
							$any = true;
						}
						$thisPlay ['occupied'] = makePlay ($game, $thisPlay, 'test')['board'][$x][$y]['meeple'];
						$playList ['play'][] = $thisPlay;
						$playList ['count'][$countLetter]['o'] .= $direction;
					}
				}
			}
		}
	}
	return $playList;
}

function isLive ($game, $x, $y) {
	foreach ($game['cards'] as $card) {
		if (getPlayList ($game, true, $card, array ('x' => $x, 'y' => $y))) {
			return true;
		}
	}
	return false;
}

function drawPlayers ($game) {
	echo "<font face='Courier New'>";

	for ($userNum = 0; $userInfo = $game['user'][$userNum]; $userNum++) {
		echo "<font color='{$userInfo['color']}'>" . htmlPad ($userInfo['score'], 3, STR_PAD_LEFT) .
		 	'&nbsp;' . htmlPad (substr ($userInfo['name'], 0, 10), 10, STR_PAD_RIGHT) .
			'&nbsp;' . str_repeat ('&#x1F9CD;', $userInfo['meeples'] - 1);
		if ($userInfo['meeples']) {
			echo "<span id='lastmeeple$userNum'>&#x1F9CD</span>";
		}
		echo "</font><br>\n";
	}
	echo "</font>\n";
}

function drawBoard ($game, $playList) {
	// Show board as a table
	$extent = $game['extent'];
	$size = $game['size'];
	$sizepct = intval ($size * 100);
	$labelSize = intval (($sizepct * 3) / 2);
	// $bigSize = intval (($size * 5) / 2);
	echo "<font face='Courier New'><table id='boardtable'>";
	echo "<script>
	document.addEventListener('keydown',tableKey,false);

	function tableKey (event) {
		var orienter = document.getElementById('orient');
		if (event.keyCode == 190  &&  !orienter.disabled) {// dot
			orienter.value = orientList[(orientList.search(orienter.value) + 1) % orientList.length];
			orientChange ();
		} else if (event.keyCode == 191  &&  allowed) { // slash
			var meepleField = document.getElementById ('meeple');
			var oldMeeple = meepleField.value;
			newMeeple = oldMeeple; // no var, for visibility inside anonymous function
			allowedList = (' ' + allowed + ' !').split(' '); // ditto
			allowedList.forEach( function(option, index) {
				if (option == oldMeeple) {
					newMeeple = allowedList [index + 1];
				}
			});
			if (newMeeple == '!') { // Need to do this to avoid issues of finding both blanks;
							// can't bail from inside a forEach
				newMeeple = '';
			}
			meepleField.value = newMeeple;
			meepleChange();
		}
	}
	</script>";

	for ($row = $extent ['T'] - 1; $row <= $extent ['B'] + 1; $row++) {
		echo '<tr>';
		for ($column = $extent ['L'] - 1; $column <= $extent ['R'] + 1; $column++) {
			echo "<td id='cell{$column}_$row'";
			if ($cell = $game['board'][$column][$row]) {
				echo " style='font-size: $sizepct%'>";
				echo drawCell ($cell, false);
			} else if ($countLetter = $playList ['cell'][$column][$row]) {
				echo " onclick='clickToPlay(\"$column-$row\");' style='" . bigLetterStyle ($countLetter, $size) . "'>&nbsp;$countLetter&nbsp;";
			} else if (isset ($game['board']['dead'][$column][$row])  &&  count ($game['cards'])){
				echo " style='background-color:#E0E0E0'>";
			} else if ($_GET['debug'] > 0) {
				echo "><br><font color='#808080' face='serif' style='font-size: $labelSize%'>&nbsp;$column,$row</font><br>";
				// echo "><br>&nbsp;&nbsp;<br>";
			}
			echo "</td>";
		}
		echo "</tr>\n";
	}
	echo "</table></font>\n\n";
} // end drawBoard

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

function drawCell ($cell, $numbers, $bright = false) {
	$ret = '';
	$fw = $cell['marked']['new'] ? 'font-weight: bold;' : '';
	$fw .= $cell['marked']['complete'] ? 'font-style: italic;' : '';
	$special = $cell['S'];
	for ($y = 0; $y < 5; $y++) {
		for ($x = 0; $x < 5; $x++) {
			$subCell = $cell[$x][$y];
			$cellType = substr ($subCell, 0, 1);
			$sclen = strlen ($subCell);
			$who = $cell['meeple'][$subCell];
			if ($x % 4 == 0  &&  $y % 4 == 0) { // corners
				if (($sub1=$cell[$x/2+1][$y]) == $cell[$x][$y/2+1]  &&  $cell[$x/2+1][$y/2+1] != $sub1) {
					$grayCorner = false; // need to show actual city so user knows the sides are connected
				} else {
					$grayCorner = true;
				}
			} else {
				$grayCorner = substr ($subCell, 1, 1) == 'x';
			}
			if ($grayCorner) {
				$subCell = 'X0';
				$color = "#F0F0F0"; // light gray
				$fgcolor = $color;
			} else if (is_numeric ($who)) {
				$color = $GLOBALS['game']['user'][$who]['color'];
				$fgcolor = "#F0F0F0";
			} else {
				$color = array ("R" => "#DCDCFF", "C" => "#FAF2B8", "F" => "#DCFFDC", "M" => "#FAD0FA")[$cellType];
				$fgcolor = '#606060';
				if (strlen ($who)  &&  $who != 'X') {
					$color = darker ($color);
				} elseif ($bright) {
					$color = brighter ($color);
					$fgcolor = ($cellType == 'R') ? '#FFC0C0' : '#A02020';
				}
			}
			if (!$numbers) {
				$subCell = $cellType;
			}
			if ($previous != $color) {
				if ($previous) {
					$ret .= "</span>";
				}
				$subCell = "<span style='background-color:$color; color:$fgcolor;$fw'>$subCell";
				$previous = $color;
			}
			if ($x == 4) {
				$break = '&#x200C;'; // will end up as a no-op, but facilitates text processing
			} else if ($cellType == 'C'  &&  $special  &&  substr ($cell[$x+1][$y], 0, 1) == 'C') {
				$break = '&#x200C;*';
			} else {
				$break = '&nbsp;';
			}
			$ret .= (($sclen == 1  &&  $numbers) ? '&nbsp;&#x200C;' : '') . $subCell . $break;
		}
		$ret .= ($y == 4) ? '' : "<BR>\n";
	}
	$ret .= "</span>";
	return $ret;
}

function drawChoices ($game, $playList) {
	$thisCell = $game['cards'][$game['pending']];
	echo "<table><tr><td><font face='Courier New' color=blue id=current></font></td></tr></table><P>
		<script>
		cardWithNum = new Array(4);\n";
	for ($dirNumber = 0; $dirNumber  < 4; $dirNumber++) {
		echo "cardWithNum[$dirNumber] = \"" . str_replace ("\n", '', drawCell (rotate ($thisCell, substr ('NEWS', $dirNumber, 1)), true, true)) . "\";\n";
	}
	echo "</script>";

	$extent = $game['extent'];
	$xmin = $extent ['L'] - 1;
	$xmax = $extent ['R'] + 1;
	$ymin = $extent ['T'] - 1;
	$ymax = $extent ['B'] + 1;
	echo "<form action='campagne.php' id='turnform' onsubmit='submitMove()'>
	  Location:<select name='location' id='location' onchange='locChange()'>\n";
	foreach ($playList['count'] as $countLetter => $space) {
		$spaceString = "{$space['x']}-{$space['y']}";
		echo "<option value=$spaceString>$countLetter</option>\n";
	}
	echo "</select>";
	if ($_GET['debug']) {
		echo " <label>Debug level: <input type=number name=debug min=0 max=3 step=1 value={$_GET['debug']} /></label>";
	}

	echo "
		<span id=torient>Orientation:</span><select name=orient id='orient' onchange='orientChange()'>
			<option value=N>N</option>
			<option value=W>W</option>
			<option value=S>S</option>
			<option value=E>E</option>
		</select>
		<span id=tmeeple>Meeple:</span><select name=meeple id=meeple onchange='meepleChange()' />
		<input type=hidden name=id value='{$_GET['id']}' />
		<input type=hidden name=count value='" . count ($game['cards']) . "' />
		<input type='submit' value='Submit' id='submit'/>
	</form>\n";

	echo "<script>
	function locChange() {
		if (prevHTML.match (/;[A-Z][A-Z]/)) {
			prevCell.style = '" . bigLetterStyle ('XX', $game['size']) . "';
		} else {
			prevCell.style = '" . bigLetterStyle ('X', $game['size']) . "';
		}
		prevCell.innerHTML = prevHTML;
		var loc = document.getElementById ('location');
		var lvalue = loc.value;
		var cell = document.getElementById (('cell' + lvalue).replace('-', '_'));
		if (prevCell != cell) {
		  prevCell = cell;
		  prevHTML = prevCell.innerHTML;
	  }
		cell.style = '" . bigLetterStyle ('!', $game['size']) . "';

		switch (lvalue) {";
		foreach ($playList ['count'] as $cellInfo) {
			echo "case '{$cellInfo['x']}-{$cellInfo['y']}': orientList='{$cellInfo['o']}'; break;\n";
		}
		echo "default: alert ('bad lvalue: ' + lvalue);} // end switch
		var thisOption;
		var orienter = document.getElementById('orient');
		for (thisOption = orienter.options.length - 1; thisOption >= 0; thisOption--) {
		  orienter.remove (0);
		}

		orientList.match(/./g).forEach (function (orient) {
			 var newOption = document.createElement('option');
			 newOption.value = orient;
			 newOption.text = orient;
			 orienter.options.add(newOption);
	 })
	 orienter.value = orientList.substring(0,1);
	 var onlyOne = (orientList.length == 1);
	 orienter.disabled = onlyOne;
	 document.getElementById('torient').style = 'color: ' + (onlyOne ? 'gray' : 'black');
	orientChange ();
} // end locChange
	prevCell = document.getElementById('{$GLOBALS['cellA']}');
	prevHTML = prevCell.innerHTML;
	locChange();
	document.getElementById('location').focus();

	function orientChange() {
		orient = document.getElementById('orient').value;
		var playCode = document.getElementById ('location').value + orient;
	";
	$lbrace = '{';
	$rbrace = '}';
	for ($y = 0; $y < 5; $y++) {
		for ($x = 0; $x < 5; $x++) {
			if (!isset ($cellList [$subCell = $thisCell[$x][$y]])  &&  substr ($subCell, 1) <> 'x') {
				$cellList [$subCell] = true;
			}
		}
	}

  if ($game['user'][$game['who']]['meeples']) {
		foreach ($playList ['play'] as $play) {
			$allowed = '';
			foreach ($cellList as $type => $dummy) {
				if (!isset ($play ['occupied'][$type])) {
					$allowed .= ($allowed ? ' ' : '') . $type;
				}
			}
			echo "if (playCode == '{$play['x']}-{$play['y']}{$play['orient']}') {$lbrace}allowed = '$allowed';{$rbrace}\n";
		}
	} else {
		echo "allowed = '';";
	}
	echo "
	// remove options
	var meeple = document.getElementById('meeple');
	for (thisOption = meeple.options.length - 1; thisOption >= 0; thisOption--) {
		meeple.remove (0);
	}

	var newOption = document.createElement('option');
	newOption.value = '';
	newOption.text = 'none';
	meeple.options.add(newOption);

  var onlyOne;
  if (allowed) {
		allowed.match(/[A-Z][0-9]?/g).forEach (function (entity) {
			 var newOption = document.createElement('option');
			 newOption.value = entity;
			 newOption.text = entity;
			 meeple.options.add(newOption);
	 })
	 onlyOne = false;
 } else {
	 onlyOne = true;
 }
 meeple.value = '';
 meeple.disabled = onlyOne;
 document.getElementById('tmeeple').style = 'color: ' + (onlyOne ? 'gray' : 'black');

 meepleChange();
 	}

	function meepleChange () {
		var loc = document.getElementById ('location');
	  var lvalue = loc.value;
	  var cell = document.getElementById (('cell' + lvalue).replace('-', '_'));
		var value = document.getElementById ('meeple').value;
		var newCard = cardWithNum['NEWS'.search(orient)];
		if (value) {
			// Replace all instances of name of selected meeple with underlined one
			newCard = newCard.replace(new RegExp(value, 'g'), '<u>' + value + '&#x200C;</u>');
		}
		document.getElementById ('current').innerHTML = newCard;
		newCard = newCard.replace('&nbsp;&#x200C;', ''); // for monasteries
		cell.innerHTML = newCard.replace(/[A-Z][0-9x]&/g, function (entity) {
			return entity.substr (0, 1) + '&';
		});
		document.getElementById('submit').focus();
		document.getElementById('lastmeeple1').innerHTML = value ? '&#x1F9CE' : '&#x1F9CD';
	}

  function clickToPlay(newLocation) {
		if (document.getElementById ('location').value == newLocation) {
			var orientItem = document.getElementById('orient');
			newOrient = orientList[(orientList.search(orientItem.value) + 1) % orientList.length];
			orientItem.value = newOrient;
			orientChange ();
		} else {
			document.getElementById ('location').value = newLocation;
			locChange();
		}
	}

	function submitMove() {
		document.getElementById('orient').disabled = false;
	}
	</script>\n";
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

function cardPart ($sides, $s1, $s2 = 9) {
	$side1 = $sides[$s1];
	if (strlen ($side1) == 1) {
		$side1 = $side1 . '1';
	}
	if ($s2 == 9) {
		return $side1;
	} else {
		$side2 = $sides[$s2];
		if (strlen ($side2) == 1) {
			$side2 = $side2 . '1';
		}
		if ($side1 == $side2) {
			if (substr ($side1, 0, 1) == 'R') { // road bends around a field
				return 'F' . ($s1 + 4);
			} else {
				return $side1;
			}
		}
		// CC=Cx CF=Cn CR=>Cn FR=>Fn RR=>Fs1*2+s1+3
		switch (substr ($side1, 0, 1) . substr ($side2, 0, 1)) {
			case 'CC':
			return 'Cx';

			case 'CF':
			case 'CR':
			case 'FR';
			case 'FF';
			return $side1;

			case 'FC':
			case 'RC':
			case 'RF':
			return $side2;

			case 'RR':
			return 'F' . ($s1*2 + s1 + 3);
		}
	}
}

function interpolate (&$cardArray, $xnew, $ynew, $x1, $y1, $x2, $y2) {
	$sub1 = $cardArray[$x1][$y1];
	$t1 = substr ($sub1, 0, 1);
	$sub2 = $cardArray[$x2][$y2];
	$t2 = substr ($sub2, 0, 1);
	$edge = ($xnew * $ynew) % 4 == 0;
	if ($sub1 == $sub2  ||  $t1 == 'R') {
		$subNew = $sub1;
	} else if ($edge  &&  $t1 == 'C' && $t2 == 'C') {
		$subNew = min ($sub1, $sub2);
	} else if (preg_match ('/F./', "$sub1$sub2", $matched)) {
		$subNew = $matched[0];
	} else {
		$subNew = 'F' . substr ('02468', ($xnew + $ynew) % 5, 1);
	}
	$cardArray [$xnew][$ynew] = $subNew;
}

function mergeFields (&$cardArray, $xbase, $ybase, $xnew, $ynew) {
	$baseCard = $cardArray[$xbase][$ybase];
	$newCard = $cardArray[$xnew][$ynew];
	if (substr ($baseCard, 0, 1) == 'F'  &&  substr ($newCard, 0, 1) == 'F') {
		$cardArray [$xnew][$ynew] = $baseCard;
	}
}

function htmlPad ($string, $length, $type) {
	return str_replace (' ', '&nbsp;', str_pad($string, $length, ' ', $type));
}

function makePlay ($game, $play, $mode) {
	// identify the play
	if ($location = $play['location']) {
		$x = explode ('-', $location)[0];
		$y = explode ('-', $location)[1];
	} else {
		$x = $play['x'];
		$y = $play['y'];
	}
	if (!$x  &&  !$y) {
		throw new CampException ("Play has x=0 y=0", $play);
	}

	// play card
	$pending = $game['pending'];
	$game['board'][$x][$y] = rotate ($game['cards'][$pending], $play ['orient']);
	$game['board'][$x][$y]['marked']['new'] = true;
	$game['marked']["$x-$y"] = true;
	$pendingOriginal = $pending;
	do {
		if ($max = count ($game['cards']) - 1) {
			$game['cards'][$pending] = $game['cards'][$max];
			unset ($game['cards'][$max]);
			$game['pending'] = rand (0, $max - 1);
			if ($continue = !getPlayList ($game, true)) {
				$game['cards'][$max] = $game['cards'][$pending];
				$pending = ($pending + 1) % $max;
				if ($pending == $pendingOriginal) {
					unset ($game['cards']);
					$continue = false;
					$game['message'][] = 'All remaining cards are unplayable.';
				}
			}
		} else {
			unset ($game['cards']);
			$continue = false;
		}
	} while ($continue);

  // next player
	$who = $game['who'];
	$game['who'] = isset ($game['cards']) ? (($who + 1) % $game ['playercount']) : -1;

	// handle meeples
	if ($meeple = $play['meeple']) {
		$game['board'][$x][$y]['meeple'][$meeple] = $who;
		$game['user'][$who]['meeples']--;
		$game['user'][$who]['meeplecell'][$x][$y] = $meeple;
		$game['user'][$who]['meepletype'][substr ($play['meeple'], 0, 1)]++;
	}
	for ($side = 0; $side < 4; $side++) {
		getSideInfo ($side, $x, $y, 2, $dummy, $dummy, $nx, $ny, $dummy, $dummy);
		extendMeeples ($game, $nx, $ny);
		if (!isset ($game['board'][$nx][$ny])  &&  !isLive ($game, $nx, $ny)) {
			$game['board']['dead'][$nx][$ny] = true;
		}
	}
	extendMeeples ($game, $x, $y);

	foreach ($game['board'][$x][$y]['meeple'] as $entity => $who) {
		switch ($type = substr ($entity, 0, 1)) {
			case 'C':
			case 'R':
			$blankArray = array(); // So we can pass by reference
			$info = checkComplete ($game['board'], $x, $y, $entity, $blankArray, false);
			if ($info['complete']) {
				$factor = $type == 'R' ? 1 : ($info['count'] < 3 ? ($game['parms']['twocellscore'] / 2) : 2);
				complete ($game, $info['count'] * $factor, $info['location']);
			}
			break; // Later

			case 'F': //  never get the meeple back
			case 'M': // handled a different way
			break;
		} // end select
	} // end foreach

  // Monasteries
	for ($mx = $x -1; $mx < $x + 2; $mx++) {
		for ($my = $y -1; $my < $y + 2; $my++) {
			if (is_numeric ($monk = $game['board'][$mx][$my]['meeple']['M'])) {
				$basex = $mx;
				$basey = $my;
				$count = 0;
				for ($cx = $basex - 1; $cx < $basex + 2; $cx++) {
					for ($cy = $basey -1; $cy < $basey + 2; $cy++) {
						$count += isset ($game['board'][$cx][$cy]);
					}
				}
				if ($count == 9) {
					complete ($game, $count, array ($basex => array ($basey => array ('who' => $monk, 'entity' => 'M'))));
				}
			}
		}
	}

	// update extent
	$extent = $game['extent'];
	$extent ['L'] = min ($extent ['L'], $x);
	$extent ['R'] = max ($extent ['R'], $x);
	$extent ['T'] = min ($extent ['T'], $y);
	$extent ['B'] = max ($extent ['B'], $y);
	$game['extent'] = $extent;

	$height = $extent ['B'] - $extent ['T'] + 2;
	$width = $extent ['R'] - $extent ['L'] + 2;
	$game['size'] = max (min (7.0 / $height, 12.0 / $width, 1.0), 0.5);

	return $game;
}

function extendMeeples (&$game, $x, $y) {
	$clearMark = 'X';
	if (!$thisCell = $game['board'][$x][$y]) {
		return;
	}
	for ($side = 0; $side < 4; $side++) {
		$prevEntity = '';
		for ($pos = 1; $pos < 4; $pos++) {
			getSideInfo ($side, $x, $y, $pos, $sx, $sy, $nx, $ny, $vx, $vy);
			$thisEntity = $thisCell[$sx][$sy];
			if ($thisEntity == $prevEntity) {
				continue;
			}
			$prevEntity = $thisEntity;
			if (!isset ($thisCell['meeple'][$thisEntity])) {
				continue;
			}
			$neighbor = $game['board'][$nx][$ny];
			if ($touch = $neighbor[$vx][$vy]) {
				if ($thisCell['meeple'][$thisEntity] === $clearMark) {
					$new = $clearMark;
					$continue = isset ($neighbor['meeple'][$touch])  &&  $neighbor['meeple'][$touch] != $clearMark;
				} else {
					$new = '*';
					$continue = !isset ($neighbor['meeple'][$touch]);
				}
				if ($continue) {
					$game['board'][$nx][$ny]['meeple'][$touch] = $new;
					if (!isset ($visited [$nx][$ny])) {
						$visited [$nx][$ny] = true;
						extendMeeples ($game, $nx, $ny);
					}
				}
			}
		}
	}
} // end extendMeeples

function checkComplete ($board, $x, $y, $entity, &$visited, $gameOver) {
	$who = $board[$x][$y]['meeple'][$entity];
	if (isset ($visited[$x][$y])) {
		// if who on visited is not numeric, but who on entity is valid, update it before returning
		if (!is_numeric($visited[$x][$y]['who'])  &&  is_numeric($who)) {
			$visited[$x][$y] = array ('who' => $who, 'entity' => $entity);
		}
		if (substr ($entity, 0, 1) != 'F'  ||  isset($visited[$x][$y][$entity])) {
			return array ('complete' => true); // no-op on return
		}
	}
	if ($entity == 'M') {
		$count = 0;
		$dead = 0;
		for ($nx = $x - 1; $nx <= $x + 1; $nx++) {
			for ($ny = $y - 1; $ny <= $y + 1; $ny++) {
				if ($board [$nx][$ny]) {
					$count++;
				} else if ($board['dead'][$nx][$ny]) {
					$dead++;
				}
			}
		}
		return array ('count' => $count, 'location' => array ($x => array ($y => array ('who' => $who))),
				'dead' => $dead, 'complete' => $count == 9);
	}
	$cellType = substr ($entity, 0, 1);
	if ($cellType == 'C'  &&  $board[$x][$y]['S']) {
		$count = 2;
	} else if ($cellType != 'F') {
		$count = 1;
	} else if ($gameOver) {
		// Look for cities next to the field
		foreach (array (1, 3) as $sx) {
			foreach (array (1, 3) as $sy) {
				if ($board[$x][$y][$sx][$sy] == $entity) {
					$count += (fieldCount ($board, $x, $y, $sx, $sy, $visited)) * $GLOBALS['game']['parms']['fieldcityscore'];
				}
			}
		}
	} else {
		return false;
	}
	if (!isset ($visited [$x][$y])) { // could be a field visited twice on opposite sides of a road
		$visited [$x][$y] = array ('who' => $board[$x][$y]['meeple'][$entity], 'entity' => $entity);
	}
	$visited [$x][$y][$entity] = true;
	$complete = true;
	$dead = 0;
	for ($side = 0; $side < 4; $side++) {
		for ($pos = 1; $pos < 4; $pos++) {
			getSideInfo ($side, $x, $y, $pos, $sx, $sy, $nx, $ny, $vx, $vy);
			if ($board[$x][$y][$sx][$sy] == $entity) {
				if ($neighbor = $board[$nx][$ny]) {
					$recurse = checkComplete ($board, $nx, $ny, $neighbor[$vx][$vy], $visited, $gameOver);
					$complete = $complete && $recurse ['complete'];
					$count += $recurse ['count'];
					$open += $recurse ['open'];
					$dead += $recurse ['dead'];
				} else if (!isset($visited [$nx][$ny])) {
					$complete = false;
					$visited [$nx][$ny] = true;
					if (isset ($board['dead'][$nx][$ny])) {
						$dead++;
					} else {
						$open++;
					}
				}
			}
		}
	} // for $side
	return array ('count' => $count, 'location' => $visited, 'dead' => $dead, 'open' => $open, 'complete' => $complete);
}

function fieldCount ($board, $x, $y, $sx, $sy, &$visited) {
	$count = 0;
	foreach (array ($sx-1, $sx+1) as $nx) {
		foreach (array ($sy-1, $sy+1) as $ny) {
			if (substr ($entity = $board[$x][$y][$nx][$ny], 0, 1) == 'C') {
				if (!isset ($visited['f'][$x][$y][$entity])) {
					$visited['f'][$x][$y][$entity] = true;
					$blankArray = array ();
					$ccret = checkComplete ($board, $x, $y, $entity, $blankArray, false);
					foreach ($ccret['location'] as $vx => $data) {
						foreach ($data as $vy => $point) {
							$entity = $point['entity'];
							if (isset ($visited['fc'][$vx][$vy][$entity])) {
								$ccret['complete'] = false;
							} else {
								$visited['fc'][$vx][$vy][$entity] = true;
							}
						}
					}
					if ($ccret['complete']) {
						$count++;
					}
				}
			}
		}
	}
	return $count;
}

function rotate ($card, $direction) {
	$flagS = $card['S'];
	for ($iter = strpos ('NWSE', $direction); $iter; $iter--) {
		for ($newx = 0; $newx < 5; $newx++) {
			for ($newy = 0; $newy < 5; $newy++) {
				$new[$newx][$newy] = $card[4-$newy][$newx];
			}
		}
		$card = $new;
	}
	if ($flagS) {
		$card['S'] = $flagS;
	}
	return $card;
}

function complete (&$game, $points, $meeples) {
	if ($data = entityInfo ($game, $meeples, true)) {
		foreach ($meeples as $x => $mx) {
			foreach ($mx as $y => $info) {
				$game['board'][$x][$y]['meeple'][$info['entity']] = 'X';
				$game['board'][$x][$y]['marked']['complete'] = true;
				$game['marked']["$x-$y"] = true;
			}
		}
		foreach ($data['count'] as $who => $meepleCount) {
			if ($meepleCount == $data['most']) {
				$game['user'][$who]['score'] += $points;
				$best .= ' ' . $game['user'][$who]['name'];
			}
		}

		$game['message'][] = completeMessage ($info['entity'], true, $basex, $basey, $points, $best);
	} else if ($_GET['debug'] > 0) {
		$game['message'][] = "DEBUG: bogus completion around $x,$y.  Points=$points.  Details=$dbg.";
	}
}

function entityInfo (&$game, $meeples, $update) {
	$most = 0;
	foreach ($meeples as $x => $mx) {
		foreach ($mx as $y => $info) {
			if (is_numeric ($who = $info ['who'])) {
				$basex = $x;
				$basey = $y;
				$most = max ($most, ++$count[$who]);
				if (arraySerialize ($pos[$who]) < arraySerialize ($here = array ('x' => $x, 'y' => $y))) {
					$pos[$who] = $here;
				}
				if ($update) {
					$game['user'][$who]['meeples']++;
					unset ($game['user'][$who]['meeplecell'][$x][$y]);
					if ($game['user'][$who]['meeplecell'][$x] == array()) {
						unset ($game['user'][$who]['meeplecell'][$x]);
					}
					$game['user'][$who]['meepletype'][substr ($info['entity'], 0, 1)]--;
				}
			}
		}
	}
	if ($most) {
		return array ('most' => $most, 'count' => $count, 'pos' => $pos);
	}
}
function readGame ($gameID, $count) {
	return ($ret = json_decode ($GLOBALS['s3']->getObject([
		'Bucket' => $GLOBALS['bucket'],
		'Key' => $key = gameKeyID ($gameID, $count)
	])['Body'], true)) ? $ret :
			throwException ("Unable to access game data", "$gameID/$count -> $key");
}

function saveGame ($game, $gameID) {
	$GLOBALS['s3']->putObject([
    'Bucket' => $GLOBALS['bucket'],
    'Key' => gameKeyID ($gameID, count ($game['cards'])),
    'Body' => json_encode ($game)
  ]);
}

function gameKeyID ($gameID, $count) {
	$count = substr ($count + 100, 1); // add a leading zero if needed
	return "game.{$gameID}_$count.json";
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

function bigLetterStyle ($label, $base) {
	$fg = '#000000';
	$bg = '#E0E0E0';
	$size = intval (($label == '!' ? 100 : (strlen ($label) == 1 ? 250 : 200)) * $base);
	return "text-align:center; vertical-align:center; font-size: $size%; color:$fg; background-color:$bg";
}

function finalScore (&$game) {
	$extent = $game['extent'];
	$board = $game['board'];
	foreach ($game['user'] as $who => $info) {
		$score [$who] = $info['score'];
	}
	for ($y = $extent ['T']; $y <= $extent ['B']; $y++) {
		for ($x = $extent ['L']; $x <= $extent ['R']; $x++) {
			if ($cell = $board[$x][$y]) {
				foreach ($cell['meeple'] as $entity => $who) {
					if (is_numeric($who)) {
						$blankArray = array ();
						$info = checkComplete ($board, $x, $y, $entity, $blankArray, true);
						//@@ comment ("Processing $entity at $x,$y");
						//@@ comment ($info);
						if ($data = entityInfo ($game, $info['location'], false)) {
							//@@ comment ($data);
							// Make sure we have at least a tie for most meeples and we haven't already processed this entity elsewhere
							if ($data['most'] == $data['count'][$who]  &&  $x == $data['pos'][$who]['x']  &&  $y == $data['pos'][$who]['y']) {
								$score[$who] += $info ['count'];
								$game['message'][] = completeMessage ($entity, false, $x, $y, $info ['count'], ' ' . $game['user'][$who]['name']);
							}
						}
					}
				}
			}
		}
	}
	return $score;
}

function playName ($play, $playList = false) {
	if ($playList) {
		$space = $playList ['cell'][$play['x']][$play['y']];
	} else {
		$space = "{$play['x']},{$play['y']}";
	}
	return trim ("{$space}{$play['orient']} {$play['meeple']}");
}

function getSideInfo ($side, $x, $y, $pos, &$sx, &$sy, &$nx, &$ny, &$vx, &$vy) {
	switch ($side) {
	case 0:
		$sx = $pos;
		$sy = 0;
		$nx = $x;
		$ny = $y-1;
		$vx = $pos;
		$vy = 4;
		break;
	case 1:
		$sx = 0;
		$sy = $pos;
		$nx = $x-1;
		$ny = $y;
		$vx = 4;
		$vy = $pos;
		break;
	case 2:
		$sx = $pos;
		$sy = 4;
		$nx = $x;
		$ny = $y+1;
		$vx = $pos;
		$vy = 0;
		break;
	case 3:
		$sx = 4;
		$sy = $pos;
		$nx = $x+1;
		$ny = $y;
		$vx = 0;
		$vy = $pos;
		break;
	}
} // end getSideInfo

function entityList ($card, $blank) {
	$ret = array ();
	if ($blank) {
		$ret ['-'] = true;
	}
	for ($x = 0; $x < 5; $x++) {
		for ($y = 0; $y < 5; $y++) {
			$ret[$card[$x][$y]] = true;
		}
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

function completeMessage ($entity, $complete, $basex, $basey, $points, $who) {
	$where = ($_GET['debug'] > 0) ? (" at $basex,$basey ") : '';
	$status = $complete ? ' is complete' : '';
	return array ('M' => 'Monastery', 'C' => 'City', 'R' => 'Road', 'F' => 'Field')[substr ($entity, 0, 1)] .
			"{$where}$status for $points points for$who.";
}

function showMessages (&$game) {
	foreach ($game['message'] as $msg) {
		echo "$msg<BR>";
	}
	unset ($game['message']);
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