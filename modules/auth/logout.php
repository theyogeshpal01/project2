<?php
session_start();
session_unset();
session_destroy();

// Redirect to login page (or index for now since login isn't fully built)
header("Location: ../../index.php");
exit();
?>
