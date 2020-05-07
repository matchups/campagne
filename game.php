<?php
class Campagne {
	function initialize () {
		foreach (file ('cards.txt') as $cardDesc) {
			$len = strlen ($cardDesc);
			if (ord(substr($cardDesc, $len-1)) < 32) {
				$cardDesc = substr($cardDesc, 0, $len-1);
			}
			unset ($cardArray);
			$sides = explode ('|', ($cd1 = explode (',', $cardDesc))[0]);
			$cardArray[0][2] = $this->cardPart ($sides, 0);
			$cardArray[2][4] = $this->cardPart ($sides, 1);
			$cardArray[4][2] = $this->cardPart ($sides, 2);
			$cardArray[2][0] = $this->cardPart ($sides, 3);
			$cardArray[0][0] = $this->cardPart ($sides, 3, 0);
			$cardArray[0][4] = $this->cardPart ($sides, 0, 1);
			$cardArray[4][4] = $this->cardPart ($sides, 1, 2);
			$cardArray[4][0] = $this->cardPart ($sides, 2, 3);
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
				$this->interpolate ($cardArray, $x, 1, $x, 0, $x, 2);
				$this->interpolate ($cardArray, $x, 3, $x, 4, $x, 2);
			}
			for ($y = 0; $y < 5; $y++) {
				$this->interpolate ($cardArray, 1, $y, 0, $y, 2, $y);
				$this->interpolate ($cardArray, 3, $y, 4, $y, 2, $y);
			}

			// combine fields
			for ($x = 0; $x < 4; $x++) {
				for ($y = 0; $y < 4; $y++) {
					$this->mergeFields ($cardArray, $x, $y, $x+1, $y);
					$this->mergeFields ($cardArray, $x, $y, $x, $y+1);
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
		$game = $this->makePlay ($game, array ('x' => "$midpoint", 'y' => "$midpoint", 'orient' => 'N'), 'initialize');

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
						$cardFix = $this->rotate ($card, $direction);
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
							$thisPlay ['occupied'] = $this->makePlay ($game, $thisPlay, 'test')['board'][$x][$y]['meeple'];
							$playList ['play'][] = $thisPlay;
							$playList ['count'][$countLetter]['o'] .= $direction;
						}
					}
				}
			}
		}
		return $playList;
	}

	protected function isLive ($game, $x, $y) {
		foreach ($game['cards'] as $card) {
			if ($this->getPlayList ($game, true, $card, array ('x' => $x, 'y' => $y))) {
				return true;
			}
		}
		return false;
	}

	protected function cardPart ($sides, $s1, $s2 = 9) {
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

	protected function interpolate (&$cardArray, $xnew, $ynew, $x1, $y1, $x2, $y2) {
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

	protected function mergeFields (&$cardArray, $xbase, $ybase, $xnew, $ynew) {
		$baseCard = $cardArray[$xbase][$ybase];
		$newCard = $cardArray[$xnew][$ynew];
		if (substr ($baseCard, 0, 1) == 'F'  &&  substr ($newCard, 0, 1) == 'F') {
			$cardArray [$xnew][$ynew] = $baseCard;
		}
	}

	public function makePlay ($game, $play, $mode) {

		$who = $game['who'];

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
		$game['board'][$x][$y] = $this->rotate ($game['cards'][$pending], $play ['orient']);
		$game['board'][$x][$y]['marked']['new'] = $who;
		$game['marked']["$x-$y"] = true;
		$pendingOriginal = $pending;

		do {
			if ($max = count ($game['cards']) - 1) {
				$game['cards'][$pending] = $game['cards'][$max];
				unset ($game['cards'][$max]);
				$game['pending'] = rand (0, $max - 1);
				if ($continue = !$this->getPlayList ($game, true)) {
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
		$game['who'] = isset ($game['cards']) ? (($who + 1) % $game ['playercount']) : -1;

		// handle meeples
		if ($meeple = $play['meeple']) {
			$game['board'][$x][$y]['meeple'][$meeple] = $who;
			$game['user'][$who]['meeples']--;
			$game['user'][$who]['meeplecell'][$x][$y] = $meeple;
			$game['user'][$who]['meepletype'][substr ($play['meeple'], 0, 1)]++;
		}

		for ($side = 0; $side < 4; $side++) {
			$this->getSideInfo ($side, $x, $y, 2, $dummy, $dummy, $nx, $ny, $dummy, $dummy);
			$this->extendMeeples ($game, $nx, $ny);
			if (!isset ($game['board'][$nx][$ny])  &&  !$this->isLive ($game, $nx, $ny)) {
				$game['board']['dead'][$nx][$ny] = true;
			}
		}
		$this->extendMeeples ($game, $x, $y);


		foreach ($game['board'][$x][$y]['meeple'] as $entity => $who) {
			switch ($type = substr ($entity, 0, 1)) {
				case 'C':
				case 'R':
				$blankArray = array(); // So we can pass by reference
				$info = $this->checkComplete ($game['board'], $x, $y, $entity, $blankArray, false);
				if ($info['complete']) {
					$factor = $type == 'R' ? 1 : ($info['count'] < 3 ? ($game['parms']['twocellscore'] / 2) : 2);
					$this->complete ($game, $info['count'] * $factor, $info['location']);
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
						$this->complete ($game, $count, array ($basex => array ($basey => array ('who' => $monk, 'entity' => 'M'))));
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

	protected function extendMeeples (&$game, $x, $y) {
		$clearMark = 'X';
		if (!$thisCell = $game['board'][$x][$y]) {
			return;
		}
		for ($side = 0; $side < 4; $side++) {
			$prevEntity = '';
			for ($pos = 1; $pos < 4; $pos++) {
				$this->getSideInfo ($side, $x, $y, $pos, $sx, $sy, $nx, $ny, $vx, $vy);
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
							$this->extendMeeples ($game, $nx, $ny);
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
						$count += ($this->fieldCount ($board, $x, $y, $sx, $sy, $visited)) * $GLOBALS['game']['parms']['fieldcityscore'];
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
				$this->getSideInfo ($side, $x, $y, $pos, $sx, $sy, $nx, $ny, $vx, $vy);
				if ($board[$x][$y][$sx][$sy] == $entity) {
					if ($neighbor = $board[$nx][$ny]) {
						$recurse = $this->checkComplete ($board, $nx, $ny, $neighbor[$vx][$vy], $visited, $gameOver);
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

	protected function fieldCount ($board, $x, $y, $sx, $sy, &$visited) {
		$count = 0;
		foreach (array ($sx-1, $sx+1) as $nx) {
			foreach (array ($sy-1, $sy+1) as $ny) {
				if (substr ($entity = $board[$x][$y][$nx][$ny], 0, 1) == 'C') {
					if (!isset ($visited['f'][$x][$y][$entity])) {
						$visited['f'][$x][$y][$entity] = true;
						$blankArray = array ();
						$ccret = $this->checkComplete ($board, $x, $y, $entity, $blankArray, false);
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

	public function rotate ($card, $direction) {
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

	protected function complete (&$game, $points, $meeples) {
		if ($data = $this->entityInfo ($game, $meeples, true)) {
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

			$game['message'][] = $this->completeMessage ($info['entity'], true, $basex, $basey, $points, $best);
		}
	}

	protected function entityInfo (&$game, $meeples, $update) {
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

	public function finalScore (&$game) {
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
							$info = $this->checkComplete ($board, $x, $y, $entity, $blankArray, true);
							if ($data = $this->entityInfo ($game, $info['location'], false)) {
								// Make sure we have at least a tie for most meeples and we haven't already processed this entity elsewhere
								if ($data['most'] == $data['count'][$who]  &&  $x == $data['pos'][$who]['x']  &&  $y == $data['pos'][$who]['y']) {
									$score[$who] += $info ['count'];
									$game['message'][] = $this->completeMessage ($entity, false, $x, $y, $info ['count'], ' ' . $game['user'][$who]['name']);
								}
							}
						}
					}
				}
			}
		}
		return $score;
	}

	public function playName ($play, $playList = false) {
		if ($playList) {
			$space = $playList ['cell'][$play['x']][$play['y']];
		} else {
			$space = "{$play['x']},{$play['y']}";
		}
		return trim ("{$space}{$play['orient']} {$play['meeple']}");
	}

	protected function getSideInfo ($side, $x, $y, $pos, &$sx, &$sy, &$nx, &$ny, &$vx, &$vy) {
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

	public function entityList ($card, $blank) {
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

	protected function completeMessage ($entity, $complete, $basex, $basey, $points, $who) {
		$where = ($_GET['debug'] > 0) ? (" at $basex,$basey ") : '';
		$status = $complete ? ' is complete' : '';
		return array ('M' => 'Monastery', 'C' => 'City', 'R' => 'Road', 'F' => 'Field')[substr ($entity, 0, 1)] .
				"{$where}$status for $points points for$who.";
	}
}
?>
