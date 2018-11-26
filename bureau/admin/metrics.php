<?php
header('Content-type: text/plain');
require_once("../class/config_nochk.php");

$stats->export_stats();
