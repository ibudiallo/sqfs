<?php

use Parser\SQLParser;

include __DIR__."/../init.php";


$input = "SELECT * FROM ~/, .";

$parser = new SQLParser($input);

$parser->parse();