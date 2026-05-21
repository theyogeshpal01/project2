<?php
// ping.php

// Return a clean 200 OK status code
http_response_code(200);

// Clear any caching headers just in case
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Send a simple plain text response
header("Content-Type: text/plain");
echo "Server is alive!";
exit;