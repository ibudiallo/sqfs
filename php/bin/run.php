<?php

use Parser\SQLParser;

include __DIR__."/../init.php";

// $input = "SELECT name, extension, group, filesize, owner FROM ~/ WHERE extension = 'sh'";

if ($argc !== 2) {
    show_manual();
    exit(1);
}

$input = trim($argv[1]);

$parser = new SQLParser($input);

$query = $parser->parse();

$query->execute();