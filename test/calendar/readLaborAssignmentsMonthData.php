<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");
$month = requireDate($_POST, "month", true);
$monthStart = date("Y-m-01", strtotime($month));
$monthEnd = date("Y-m-t", strtotime($month));
exit(json_encode(readCalendarAssignments($db, $userId, $monthStart, $monthEnd, __FILE__, __LINE__)));
