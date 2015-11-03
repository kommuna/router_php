<?php

class Router {
	private static $URI;
	private static $METHOD;
	private static $regexpMatches;

	function matchExact($uri) {
		if ($uri === self::$URI) return true;
		return false;
	}

	function matchStartsWith($uri) {
		if (strlen($uri) > strlen(self::$URI)) return false;
		if ( substr(self::$URI, 0, strlen($uri)) === $uri ) return true;
		return false;
	}

	function matchComponent($uri) {
		if (self::matchExact($uri)) return true;
		if (strlen($uri) == strlen(self::$URI)) return false; //here if they have the same length, they can't match, because matchExact() didn't succeed.
		if (matchStartsWith($uri) && (substr(self::$URI, strlen($uri), 1) === '/')) return true;
		return false;
	}

	function matchRegex($uri) {
		if (preg_match($uri, self::$URI, self::$regexpMatches)) return true;
		return false;
	}

	function substituteRegex(&$out) {
		preg_replace_callback('{\d+}', function($matches) {
			var_dump($matches);
		}, $out);
	}

	private function match($uri, &$out, $matchMethod) {
		switch ($matchMethod) {
			case 'EXACT':
				return self::matchExact($uri);
			case 'STARTS':
				return self::matchStartsWith($uri);
			case 'COMPONENT':
				return self::matchComponent($uri);
			case 'REGEX':
				if (self::matchRegex($uri)) {
					self::substituteRegex($out);
					return true;
				}
				return false;
			default:
				self::returnError('500', 'Unknown match: ' . $matchMethod);
		}
	}

	private function checkMethod($allowedMethods, $flags) {
		if (!is_array($allowedMethods)) self::returnError('500', 'Bad allowed methods');
		if (count($allowedMethods) == 0) self::returnError('500', 'No allowed methods');
		if (in_array(self::$METHOD, $allowedMethods)) return true;
		if (isset($flags['ALL_METHODS_ACCEPTED'])) return true;
		if (isset($flags['ALL_METHODS_ALLOWED'])) return false;
		self::returnError('405', 'Method Not Allowed');
	}

	function init() {
		self::$URI=$_SERVER['REQUEST_URI'];
		self::$METHOD=$_SERVER['REQUEST_METHOD'];
	}

	function setURI($uri) {
		self::$URI=$uri;
	}

	function setMethod($method) {
		self::$METHOD=$method;
	}

	function returnFile($path) {
		print('X-Accel-Redirect: ' . $path);
		exit();
	}

	//TODO add real proxy mechanism
	function returnProxy($url) {
		print('X-Accel-Redirect: http://' . $url);
		exit();
	}

	//TODO add real file mechanism
	function returnError($code, $explanation) {
		print('HTTP/1.1 ' . $code . ' ' . $explanation);
		exit();
	}


	function file($uri, $path, $flags=array()) {
		if (self::match($uri, $path, isset($flags['MATCH'])?$flags['MATCH']:'STARTS')) {
			if (self::checkMethod(array('GET'), $flags)) self::returnFile($path);
			return false;
		}
		return false;
	}

	function proxy($uri, $url, $flags=array()) {
		if (self::match($uri, $url, isset($flags['MATCH'])?$flags['MATCH']:'STARTS')) {
			if (self::checkMethod(array('GET', 'POST'), $flags)) self::returnProxy($url);
			return false;
		}
		return false;
	}
}

?>
