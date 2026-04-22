<?php
exit();
// SSE headers
header("Access-Control-Allow-Origin: https://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("X-Accel-Buffering: no");
// Disable ALL output buffering (Apache, PHP, zlib)
while (ob_get_level() > 0) { ob_end_clean(); }
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);
$counter = 1;
while (true) {
    echo "id: {$counter}\n";
    echo "event: ping\n";
    echo 'data: {"counter": ' . $counter . "}\n\n";
    @ob_flush();
    flush();
    if (connection_aborted()) break;
    $counter++;
    sleep(1);
}