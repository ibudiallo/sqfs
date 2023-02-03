<?php

function show_manual() {
    echo file_get_contents(ROOT_DIR."/src/lib/manual.txt")."\n";
    
}