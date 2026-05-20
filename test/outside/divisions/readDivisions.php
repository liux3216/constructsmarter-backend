<?php
require_once __DIR__ . '/helpers.php';
$divisions = outsideDivisionsSort(outsideDivisionsEnsureSeed($db, $userId));
exit(json_encode($divisions));
