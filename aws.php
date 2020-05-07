<?php
class AWS {
  protected $bucket;
	protected $s3;

	function __construct () {
		include "/usr/home/adf/credentials.php";
		$this->s3 = new Aws\S3\S3Client([
	    'version' => 'latest',
	    'region' => 'us-east-1',
	    'credentials' => array(
	      'key' => AWS_KEY,
	      'secret'  => AWS_SECRET
	    )
	  ]);
	  $this->bucket = 'campagne.8wheels.org';
	}

	public function readGame ($gameID, $count) {
		return ($ret = json_decode ($this->s3->getObject([
			'Bucket' => $this->bucket,
			'Key' => $key = $this->gameKeyID ($gameID, $count)
		])['Body'], true)) ? $ret :
				throwException ("Unable to access game data", "$gameID/$count -> $key");
	}

	public function saveGame ($game, $gameID) {
		$this->s3->putObject([
	    'Bucket' => $this->bucket,
	    'Key' => $this->gameKeyID ($gameID, count ($game['cards'])),
	    'Body' => json_encode ($game)
	  ]);
	}

	protected function gameKeyID ($gameID, $count) {
		$count = substr ($count + 100, 1); // add a leading zero if needed
		return "game.{$gameID}_$count.json";
	}
} // end class AWS
?>
