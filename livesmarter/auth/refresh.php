<?php
require_once __DIR__.'/internalAuth.php';liveRespond(['user'=>livePublicUser($liveUser),'token'=>liveSendToken($liveUser,$config)]);
