<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");
$day = requireDate($_POST, "day", true);
exit(json_encode(readCalendarAssignments($db, $userId, $day, $day, __FILE__, __LINE__)));
