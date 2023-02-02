<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('SynacoreComputer.php');

$file = file_get_contents('challenge.bin');
$program = [];

$bytes = str_split($file, 2);
foreach ($bytes as $byte) {
    $unpacked = unpack('v', $byte);
    $program[] = $unpacked[1];
}

$computer = new SynacoreComputer($program);
$computer->run();