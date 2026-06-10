<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");
$weekStart = requireDate($_POST, "weekStart", true);
$weekEnd = requireDate($_POST, "weekEnd", true);
exit(json_encode(readCalendarAssignments($db, $userId, $weekStart, $weekEnd, __FILE__, __LINE__)));
