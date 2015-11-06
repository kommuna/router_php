<?php

	require_once("router.php");

	Router::file('/foo/bar', 'bar.html');
	Router::proxy('/boo/', 'http://google.com/');

	Router::returnError('404', 'Not found');

?>
