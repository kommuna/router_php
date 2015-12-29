<?php

	require_once("router.php");

	Router::proxy('/api/', 'http://google.com/api/', array(
		'PROXY_REQUEST_HEADERS' => array('Authorization: Basic Zsad23rfQ=')
	));

	Router::file('/', 'static/index.html', array('MATCH'=>'EXACT'));
	Router::file('/css/', 'static/css/');
	Router::file('/fonts/', 'static/fonts/');
	Router::file('/img/', 'static/img/');
	Router::file('/js/', 'static/js/');
	Router::file('/common/', 'static/common/');
	Router::file('/layout/', 'static/layout/');
	Router::file('/\/(.+\.html)/', 'static/pages/{1}', array('MATCH'=>'REGEX'));
	Router::file('/', 'static/index.html', array('DONT_APPEND_TAIL'=>1));
	Router::returnError('404', 'Not found');

?>
