<?php
class HTMLDisplay {
	private $meepleUnicode = '&#x1F9CD;';
	private $meepleUnicodeSitting = '&#x1F9CE;';

	public function drawPlayers ($game) {
		echo "<font face='Courier New'>";

		for ($userNum = 0; $userInfo = $game['user'][$userNum]; $userNum++) {
			echo "<font color='{$userInfo['color']}'>" . htmlPad ($userInfo['score'], 3, STR_PAD_LEFT) .
			 	'&nbsp;' . htmlPad (substr ($userInfo['name'], 0, 10), 10, STR_PAD_RIGHT) .
				'&nbsp;' . str_repeat ($this->meepleUnicode, $userInfo['meeples'] - 1);
			if ($userInfo['meeples']) {
				echo "<span id='lastmeeple$userNum'>{$this->meepleUnicode}</span>"; // last meeple sometimes sits down
			}
			echo "</font><br>\n";
		}
		echo "</font>\n";
	}

	public function drawBoard ($game, $playList) {
		// Show board as a table
		echo "<div class='left' id='tablesection'>";
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
					echo " style='font-size: $sizepct%;" . (is_numeric($who = $cell['marked']['new']) ?
						" border:4px solid {$GLOBALS['game']['user'][$who]['color']};" : '') . "'>";
					echo $this->drawCell ($cell, false);
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
		echo "</table></font></div>\n";
	} // end drawBoard

	function drawChoices ($game, $playList) {
		$thisCell = $game['cards'][$game['pending']];
		echo "<table><tr><td><font face='Courier New' color=blue id=current></font></td></tr></table><P>
			<script>
			cardWithNum = new Array(4);\n";
		for ($dirNumber = 0; $dirNumber  < 4; $dirNumber++) {
			echo "cardWithNum[$dirNumber] = \"" . str_replace ("\n", '', $this->drawCell (rotate ($thisCell, substr ('NEWS', $dirNumber, 1)), true, true)) . "\";\n";
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
		$sizepct = intval ($game['size'] * 100);
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

			cell.style = 'font-size:$sizepct%; border:4px solid #A0A0A0'; // lightish gray
			document.getElementById('submit').focus();
			document.getElementById('lastmeeple1').innerHTML = value ? '{$this->meepleUnicodeSitting}' : '{$this->meepleUnicode}'; // Meeple standing and sitting emoji
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

	function showMessages (&$game) {
		foreach ($game['message'] as $msg) {
			echo "$msg<BR>";
		}
		unset ($game['message']);
	}

	function ShowOptions ($game) {
		echo "<p><a href='index.html'>Restart</a>&nbsp;&nbsp;";
		$count = count ($game['cards']);
		$gameID = $GLOBALS['gameID'];
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
	}

	public function startRight () {
		echo "<div class='right'>";
	}

	public function endRight () {
 		echo "</div>";
	}

	public function drawCell ($cell, $numbers, $bright = false) {
		$ret = '';
		$fw = $cell['marked']['complete'] ? 'font-style: italic;' : '';
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
} // end class HTMLDisplay
?>
