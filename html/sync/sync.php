<?php
/*

Update today

*/

require_once('includes/synccommon.php');

echo_c("Sync hombu web schedule now ... ");

syncTheseDays(0, 30);

?>