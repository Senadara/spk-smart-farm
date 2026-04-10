<?php
$lines = file("storage/logs/laravel.log");
$lines = array_reverse($lines);
$count = 0;
foreach($lines as $line) {
    if(strpos($line, "local.ERROR") !== false || strpos($line, "Exception") !== false) {
        echo $line;
        $count++;
    }
    if ($count >= 10) break;
}
