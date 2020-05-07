# campagne
Campagne game

You can play the game at http://8wheels.org/campagne; this will give you the stable release, rather than
the dev version which is in the repository.

On the other hand, if you clone the repo, you will not be able to run it immediately because it references
an external file to get AWS credentials.  You would need to set up your own S3 bucket (I recommend
configuring it to delete game* files automatically after a few days), update the AWS class
to reference it, and either create a credentials file or hard-code the credentials there also.
