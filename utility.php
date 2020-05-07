<?php
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

class DetailedException extends Exception {
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
	throw new DetailedException ($message, $details);
}
?>
