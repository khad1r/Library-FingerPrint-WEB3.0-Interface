<?php


require_once './AttendanceLog.php';

$ip = "36.95.106.105";
$port = "45179";
$startDate = '2023-03-30';
$endDate = '2023-04-04';
$uidList = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25];
$AttndLog = new AttendanceLog($ip, $port);
$AttndLog->prepare($startDate, $endDate, $uidList);
$AttndLog->execute();
$resultLog = $AttndLog->getResult();
var_dump($resultLog);
