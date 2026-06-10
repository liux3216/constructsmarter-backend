<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$curUserId = $_POST["curUserId"];
$user = $db->one(
    "SELECT 
    CONCAT_WS(\" \", `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`, 
    `u`.`id`, 
    `allOffice`, 
    `background`, 
    `birthDay`, 
    `invoiceNumber`, 
    `calendar`, 
    `u`.`creatorId`, 
    `u`.`createdAt`, 
    `u`.`updatedAt`, 
    `timeOffs`, 
    `department`, 
    `dispatch`, 
    `driverLicense`, 
    `email`, 
    `extension`, 
    `firstName`, 
    `fleets`, 
    `forms`, 
    `hireDate`, 
    `lanId`, 
    `lastName`, 
    `middleName`, 
    `newspaper`, 
    `trainings`,
    `office`, 
    `outside`, 
    `outsideStatus`, 
    `perDiem`, 
    `personel`, 
    `phaseLevel`, 
    `phoneNumber`,
    `workPhone`,  
    `projects`, 
    `assignments`, 
    `metrics`, 
    `purchases`, 
    `quitDate`, 
    `region`, 
    `reports`, 
    `residence`, 
    `role`, 
    `ssn`, 
    `profileId`, 
    IF(`profileId` IS NOT NULL, CONCAT(\"https:\/\/$publicBucket.s3.us-west-1.amazonaws.com\/\", `profileId`), \"\") AS `profileUrl`,
    `unionName`, 
    `u`.`updaterId`, 
    `workPhone`,
    `version`,
    `address`,
    `verificationCode`, 
    `void`, 
    `voidReason`,
    `validateReason`,
    CASE WHEN `mvrId` <> \"\" THEN \"yes\" ELSE \"\" END AS `hasMVR`,
    `f`.`name` AS `profileFileName`
    FROM `users` `u` LEFT JOIN `fileInfo` `f` ON `u`.`profileId` = `f`.`id` WHERE `u`.`id` = ?;", [$curUserId], __FILE__, __LINE__
);
if(!$user) exit("");
$user["competencyServices"] = $db->all(
    "SELECT `s`.`id` AS `value`, CONCAT_WS(\" - \", `s`.`code`, `s`.`name`) AS `label`
    FROM `users_competency` `uc`
    INNER JOIN `services` `s` ON `s`.`id` = `uc`.`serviceId`
    WHERE `uc`.`userId` = ? AND `s`.`void` = 'no'
    ORDER BY `s`.`name` ASC;",
    [$curUserId],
    __FILE__,
    __LINE__
);
exit(json_encode($user));
