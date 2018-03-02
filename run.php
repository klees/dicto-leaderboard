<?php

function read_stop() {
	$dirs = scandir(__DIR__);
	return in_array("stop", $dirs);
}

function read_next_commit() {
	$head = file_get_contents(__DIR__."/commits", FALSE, null, 0, 120);
	$line = explode("\n", $head)[0];	
	return explode(";", $line);
}

function read_result($hash) {
	$contents = file_get_contents(__DIR__."/results/$hash.report", FALSE, null, 0, 600);
	$matches = [];
	preg_match("%.*commit\s*:\s*(\w+).*violations\s*:\s*(\d+)%s", $contents, $matches);
	//preg_match("%.*commit\s*:\s*(\w+).*violations\s*:\s*(\d+)%", $contents, $matches);
	return [$matches[1], $matches[2]];
}

function write_result($hash, $date, $author, $violations) {
	file_put_contents(__DIR__."/results/collection.csv", "$hash;$date;$author;$violations\n", FILE_APPEND);
}

function purge_first_commit($hash) {
	$contents = explode("\n", file_get_contents(__DIR__."/commits"));
	$line = array_shift($contents);
	list($hash2, , ) = explode(";", $line);
	assert($hash == $hash2, "Hash to purge and hash from scan did not match.");
	file_put_contents(__DIR__."/commits", implode("\n", $contents));
}

while(true) {
	if (read_stop()) {
		echo "Stopping...\n";
		break;
	}
	list($hash, $author, $date) = read_next_commit();
	echo "##################################################################################################\n";
	echo "#\n";
	echo "# Scanning $hash of $author on $date\n";
	echo "#\n";
	echo "##################################################################################################\n";
	chdir(__DIR__."/ILIAS");
	system("git checkout -f $hash");
	chdir(__DIR__);
	system("php ./dicto.phar analyze ./config.yaml");
	system("php ./dicto.phar report total ./config.yaml > ./results/$hash.report");
	list($hash2, $violations) = read_result($hash);
	assert($hash == $hash2, "Hashes from result and scan did not match.");
	write_result($hash, $date, $author, $violations);
	purge_first_commit($hash);
}
