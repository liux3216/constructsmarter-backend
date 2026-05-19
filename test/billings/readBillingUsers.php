<?php
require_once __DIR__ . "/_billings.php";

$rows = $db->all(
    "SELECT
        `u`.`id`,
        COALESCE(`u`.`email`, '') AS `email`,
        COALESCE(CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`), '') AS `userName`
     FROM `users` `u`
     WHERE `u`.`void` = 'no'
       AND COALESCE(`u`.`email`, '') <> ''
       AND (`u`.`quitDate` IS NULL OR `u`.`quitDate` > CURDATE())
     ORDER BY `u`.`firstName`, `u`.`lastName`, `u`.`email`;",
    [],
    __FILE__,
    __LINE__
);

exit(json_encode($rows));
