<?php
$log = file('storage/logs/laravel.log');
$last = array_slice($log, -20);
echo implode("", $last);
