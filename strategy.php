<?php
function selectPlay ($game) {
	$who = $game['who'];
	$topScore = -999;
	$card = $game['cards'][$game['pending']];
	if ($game['user'][$who]['meeples']) {
		$entityList = entityList ($card, true);
	} else {
		$entityList = array ('-' => true);
	}
	foreach (getPlayList ($game)['play'] as $play) {
		foreach ($entityList as $entity => $dummy) {
			$playWith = $play;
			if ($entity != '-'  &&  !strpos ($entity, 'x')  &&  !$play['occupied'][$entity]) {
				$playWith ['meeple'] = $entity;
			}
			$score = evaluate (makePlay ($game, $playWith, 'score'), $who);
			$msg = playName ($playWith) . '=' . number_format ($score, 2);
			$debug = (rand(0, 20) == 13);
			if ($score > $topScore) {
				$msg .= ' $TOP$ ';
				$debug = true;
				$topScore = $score;
				$myPlay = $playWith;
			}
			if ($debug) {comment ($GLOBALS['debugmsg']);}
			comment ("$msg ****\n");
		}
	}
	return $myPlay;
}

function evaluate ($game, $who) {
	$ret = 0;
	$worker = new Strategizer ();
	$worker->init();
	$ret = $worker->evaluatePlayer ($game, $who);
	// move new object  to initialize()
	$ret += rand (0, 100) / 10000.0; // take random action in case of ties, instead of always at the top
	$GLOBALS['debugmsg']=$worker->getMsg();
	return $ret;
} // end function evaluate

class Strategizer {
  private $msg;
	private $simpleStrategy;

  public function __construct () {
		$this->simpleStrategy = array (
			1 => array ('weight' => 5.0, 'power' => .5), // meeples
			2 => array ('weight' => 0, 'power' => 1), // rounds; only here for combining
			3 => array ('weight' => 3, 'power' => 1), // field meeples
			4 => array ('weight' => 3, 'power' => .5), // road meeples
			5 => array ('weight' => 3, 'power' => .5), // city meeples
			6 => array ('weight' => -1, 'power' => 1.6), // dead meeples
			7 => array ('weight' => 1, 'power' => 1), // city expectation
			8 => array ('weight' => 1, 'power' => 1) // meeple expectation
		);
	}

	public function init() {
		$this->msg = '';
	}

  public function evaluatePlayer ($game, $who) {
		foreach (finalScore ($game) as $scoreWho => $score) {
			$this->addMsg ("\n$scoreWho raw=$score");
			$value = array_fill (1, 8, 0);
			$value[1] = $game['user'][$scoreWho]['meeples'];
			$value[2] = count ($game['cards']) / $game ['playercount'];
			foreach ($game['user'][$scoreWho]['meepletype'] as $type => $count) {
				$value[strpos ('...FRC', $type)] = $count;
			}
			$value[6] = $value[3]; // all field meeples are dead
			foreach ($game['user'][$scoreWho]['meeplecell'] as $x => $column) {
				foreach ($column as $y => $entity) {
					$this->processEntity($game, $scoreWho, $x, $y, $entity, $value, $visited);
				}
			}
			$score += $this->finalEval ($value);
			if ($scoreWho == $who) {
				$ret += $score * ($game ['playercount'] - 1);
			} else {
				$ret -= $score;
			}
			$this->addMsg (' (ret=' . number_format ($ret, 2) . ')');
		} // end foreach $scoreWho
		return $ret;
	} // end function evaluate

	protected function processEntity ($game, $scoreWho, $x, $y, $entity, &$value, &$visited) {
		$blankArray = array();
		$info = checkComplete ($game['board'], $x, $y, $entity, $blankArray, false); //!! don't do same one twice
		$already = false;
		$most = 0;
		unset ($count);
		foreach ($info['location'] as $meeplex => $columnInfo) {
			foreach ($columnInfo as $meepley => $cellInfo) {
				if (is_numeric($cellWho = $cellInfo['who'])) {
					$most = max ($most, ++$count[$cellWho]);
				}
				if ($cellWho === $scoreWho) {
					if (isset ($visited[$meeplex][$meepley])) {
						$already = "$meeplex,$meepley";
					} else {
						$visited[$meeplex][$meepley] = true;
					}
				}
			}
		}
		$this->addMsg ("\n$scoreWho $x $y $entity $already -> " . arraySerialize ($info, false, true));
		if ($info['dead'] > 0) {
			$value[6]++;
		} else if ($already) {
			$this->addMsg (" already visited $already");
		} else if ($count[$scoreWho] < $most) {
			$this->addMsg (" outvoted");
		} else {
			$potential = 0;
			switch (substr ($entity, 0, 1)) {
			case 'M':
				$potential = pow (.5, 7 - $info['count']); // move base to $sS
				$sub = 8;
				break;
			case 'C':
				$potential = pow (.5, $info['open'] - 1) * $info['count'];
				$sub = 7;
				break;
			}
			if ($potential) {
				$this->addMsg (" potential=$potential");
				$value[$sub] += $potential;
			}
		}
	} // end function processEntity

	public function finalEval ($value) {
		$ret = 0;
		foreach ($this->simpleStrategy as $seq => $parms) {
			$score += ($itemScore = $parms['weight'] * pow ($value[$seq], $parms['power']));
			$temp = number_format ($itemScore, 2); $tscore = number_format ($score, 2);
			$this->addMsg ("\n#$seq: {$parms['weight']}*{$value[$seq]}^{$parms['power']}=$temp -> $tscore");
		}
		if ($value[1] > $value[2]) {
			// enough meeples; no dead penalty
		} else if ($value[2] > 5) {
			$score -= ($itemScore = pow ($value[2] - 5, .5) * pow ($value[6], 1.2)); // dead meeples are even worse early in the game
			$temp = number_format ($itemScore, 2); $tscore = number_format ($score, 2);
			$this->addMsg ("\n#*: $value[2]^.5*$value[6]^1.2=$temp -> $tscore");
		}
		return $score;
	} // end function finalEval

	protected function addMsg ($text) {
		$this->msg .= $text;
	}

	public function getMsg () {
		$copy = $this->msg;
		$this->msg = '';
		return $copy;
	}
} // end class Strategizer
?>
