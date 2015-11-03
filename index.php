<?php

	require_once("router.php");

	Router::setURI('/foo/bar');
	Router::setMethod('GET');

	Router::file('/foo/bar', 'bar.html');
	Router::file('/boo/:id', 'boo.html');

?>
