<?php

require 'vendor/autoload.php';
// require_once './AttendanceLog.php';
require 'src/ZtecoWeb3Api.php';

use khad1r\webfingerprint\ZtecoWeb3Api;

$AttndLog = new ZtecoWeb3Api(getenv('URL_ZTECKO'), getenv('USER_ZTECKO'), getenv('PASS_ZTECKO'));
$startDate = date('Y-m-01');
$endDate = date('Y-m-t');
$uidList = [/* 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25 */];
function getAttendance()
{
  global $AttndLog;
  global $uidList;
  global $startDate;
  global $endDate;
  $resultLog = $AttndLog->getAttendance($startDate, $endDate, $uidList);
  $formatedResult = $AttndLog->formatAttendanceByDate($resultLog);
  $formatedResult2 = $AttndLog->formatAttendanceByID($resultLog);
  echo 'Group By Date';
  echo json_encode($formatedResult, JSON_PRETTY_PRINT);
  echo 'Group By ID';
  echo json_encode($formatedResult2, JSON_PRETTY_PRINT);
}
function searchSomeone()
{
  global $AttndLog;
  global $uidList;
  $ID15 = $AttndLog->getByID(15);
  echo json_encode($ID15, JSON_PRETTY_PRINT);
  $uidList = [$ID15['uid']];
  getAttendance();
}

function getAllUsers()
{
  global $AttndLog;
  global $uidList;
  $workers = $AttndLog->getUsers(100);
  echo json_encode($workers, JSON_PRETTY_PRINT);
  $uidList = array_column($workers, 'uid');
  getAttendance();
}
echo 'Search By ID ';
searchSomeone();
echo 'Get All Data';
getAllUsers();
