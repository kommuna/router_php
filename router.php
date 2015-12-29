<?php

if (!function_exists('apache_request_headers')) {
	function apache_request_headers() {
		foreach($_SERVER as $key=>$value) {
			if (substr($key,0,5)=="HTTP_") {
				$key=str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",substr($key,5)))));
				$out[$key]=$value;
			}
		}
		return $out;
	}
} 


class Router {
	private static $URI;
	private static $URIPath;
	private static $METHOD;
	private static $regexpMatches;
	private static $fileAccel;
	private static $proxyAccel;

	function matchExact($uri) {
		if ($uri === self::$URIPath) return true;
		return false;
	}

	function matchStartsWith($uri) {
		if (strlen($uri) > strlen(self::$URIPath)) return false;
		if ( substr(self::$URIPath, 0, strlen($uri)) === $uri ) return true;
		return false;
	}

	function matchComponent($uri) {
		if (self::matchExact($uri)) return true;
		if (strlen($uri) == strlen(self::$URIPath)) return false; //here if they have the same length, they can't match, because matchExact() didn't succeed.
		if (matchStartsWith($uri) && (substr(self::$URIPath, strlen($uri), 1) === '/')) return true;
		return false;
	}

	function matchRegex($uri) {
		if (preg_match($uri, self::$URIPath, self::$regexpMatches)) return true;
		return false;
	}

	function substituteRegex(&$out) {
		$out = preg_replace_callback('/{(\d+)}/', function($matches) {
			$match_index = intval($matches[1]);
			$ret = '';
			if (isset(self::$regexpMatches[$match_index])) {
				$ret = self::$regexpMatches[$match_index];
			}
			print("Replacing ".$matches[0]." with '".$ret."'\n");
			return $ret;
		}, $out);
	}

	private function updateURIPath() {
		$q = strpos(self::$URI, '?');
		if (false !== $q) {
			self::$URIPath = substr(self::$URI, 0, $q);
		} else {
			self::$URIPath = self::$URI;
		}
	}

	private function match($uri, &$out, $matchMethod, $flags) {
		switch ($matchMethod) {
			case 'EXACT':
				return self::matchExact($uri);
			case 'STARTS':
				if (self::matchStartsWith($uri)) {
					if (!isset($flags['DONT_APPEND_TAIL'])) {
						$out .= substr(self::$URIPath, strlen($uri));
					}
					return true;
				}
				return false;
			case 'COMPONENT':
				if (self::matchComponent($uri)) {
					if (!isset($flags['DONT_APPEND_TAIL'])) {
						$out .= substr(self::$URIPath, strlen($uri));
					}
					return true;
				}
				return false;
			case 'REGEX':
				if (self::matchRegex($uri)) {
					self::substituteRegex($out);
					print($out);
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

	private function getFileMimeType($path) {
		$file = basename($path);
		$pos = strrpos($file, '.');
		$extension = substr($file, $pos+1);
		return self::getMimeTypeByExtension($extension);
	}

	private function readFile($path, $flags) {
		if (!file_exists($path)) {
			error_log("Path $path doesn't exist");
			self::returnError('404', 'Not found');
		}
		if (!is_file($path)) {
			error_log("Path $path is not a file");
			self::returnError('405', 'Not a file');
		}
		if (!is_readable($path)) {
			error_log("Path $path is not readable");
			self::returnError('403', 'Forbidden');
		}
		header('Content-type: ' . self::getFileMimeType($path));
		readfile($path);
		exit();
	}

	private function readURL($url, $flags) {
		$ch = curl_init($url.'?'.$_SERVER['QUERY_STRING']);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, self::$METHOD);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if (self::$METHOD == 'POST' || self::$METHOD == 'PUT') {
			curl_setopt($ch, CURLOPT_INFILE, 'php://input');
		}
		$request_headers = apache_request_headers();
		if (isset($request_headers['Host'])) unset($request_headers['Host']);
		if (is_array($flags['PROXY_REQUEST_HEADERS'])) {
			$request_headers = $flags['PROXY_REQUEST_HEADERS'] + $request_headers;
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
		$out = curl_exec($ch);
		if ($out === false) {
			self::returnError('502', curl_error ($ch) . '('.$url.')');
		}
		curl_close($ch);
		$offset=0;
		while(true) {
			$h = strpos($out, "\r\n", $offset);
			if ($h === false) {
				break;
			}
			if ($h == $offset) {
				//end of headers
				$offset+=2;
				break;
			}
			header(substr($out, $offset, $h - $offset));
			$offset = $h + 2;
		}
		print(substr($out, $offset));
		exit();
	}

	function init() {
		self::$URI=$_SERVER['REQUEST_URI'];
		self::$METHOD=$_SERVER['REQUEST_METHOD'];
		self::$fileAccel=null;
		self::$proxyAccel=null;
		self::updateURIPath();
	}

	function setURI($uri) {
		self::$URI=$uri;
		self::updateURIPath();
	}

	function setMethod($method) {
		self::$METHOD=$method;
	}

	function setFileAccel($prefix) {
		self::$fileAccel = $prefix;
	}

	function setProxyAccel($prefix) {
		self::$proxyAccel = $prefix;
	}

	function returnFile($path, $flags) {
		if (is_null(self::$fileAccel)) self::readFile($path, $flags);
		header('X-Accel-Redirect: ' . self::$fileAccel . $path);
		exit();
	}

	function returnProxy($url, $flags) {
		if (is_null(self::$proxyAccel)) self::readURL($url, $flags);
		header('X-Accel-Redirect: ' . self::$proxyAccel . $url);
		exit();
	}

	function returnError($code, $explanation) {
		header('HTTP/1.1 ' . $code . ' ' . $explanation);
		header('Content-type: text/plain');
		print($code . ': ' . $explanation);
		exit();
	}

	function file($uri, $path, $flags=array()) {
		if (self::match($uri, $path, isset($flags['MATCH'])?$flags['MATCH']:'STARTS', $flags)) {
			if (self::checkMethod(array('GET'), $flags)) self::returnFile($path, $flags);
			return false;
		}
		return false;
	}

	function proxy($uri, $url, $flags=array()) {
		if (self::match($uri, $url, isset($flags['MATCH'])?$flags['MATCH']:'STARTS', $flags)) {
			if (self::checkMethod(array('GET', 'POST'), $flags)) self::returnProxy($url, $flags);
			return false;
		}
		return false;
	}


	private function getMimeTypeByExtension ($ext) {
		switch ($ext) {
			case 'html': case 'htm': case 'shtml': 		return 'text/html';
			case 'css': case 'scss': 			return 'text/css';
			case 'xml': 					return 'text/xml';
			case 'gif': 					return 'image/gif';
			case 'jpeg': case 'jpg': 			return 'image/jpeg';
			case 'js': 					return 'application/x-javascript';
			case 'atom': 					return 'application/atom+xml';
			case 'rss': 					return 'application/rss+xml';
			case 'mml': 					return 'text/mathml';
			case 'txt': 					return 'text/plain';
			case 'jad':	 				return 'text/vnd.sun.j2me.app-descriptor';
			case 'wml': 					return 'text/vnd.wap.wml';
			case 'htc': 					return 'text/x-component';
			case 'png': 					return 'image/png';
			case 'tif': case 'tiff': 			return 'image/tiff';
			case 'wbmp': 					return 'image/vnd.wap.wbmp';
			case 'ico':	 				return 'image/x-icon';
			case 'jng': 					return 'image/x-jng';
			case 'bmp': 					return 'image/x-ms-bmp';
			case 'svg': 					return 'image/svg+xml';
			case 'jar': case 'war': case 'ear': 		return 'application/java-archive';
			case 'hqx':	 				return 'application/mac-binhex40';
			case 'doc': 					return 'application/msword';
			case 'pdf': 					return 'application/pdf';
			case 'ps': case 'eps': case 'ai': 		return 'application/postscript';
			case 'rtf': 					return 'application/rtf';
			case 'xls': 					return 'application/vnd.ms-excel';
			case 'ppt': 					return 'application/vnd.ms-powerpoint';
			case 'wmlc': 					return 'application/vnd.wap.wmlc';
			case 'kml': 					return 'application/vnd.google-earth.kml+xml';
			case 'kmz':	 				return 'application/vnd.google-earth.kmz';
			case '7z': 					return 'application/x-7z-compressed';
			case 'cco': 					return 'application/x-cocoa';
			case 'jardiff': 				return 'application/x-java-archive-diff';
			case 'jnlp': 					return 'application/x-java-jnlp-file';
			case 'run': 					return 'application/x-makeself';
			case 'pl': case 'pm': 				return 'application/x-perl';
			case 'prc': case 'pdb': 			return 'application/x-pilot';
			case 'rar': 					return 'application/x-rar-compressed';
			case 'rpm': 					return 'application/x-redhat-package-manager';
			case 'sea': 					return 'application/x-sea';
			case 'swf': 					return 'application/x-shockwave-flash';
			case 'sit': 					return 'application/x-stuffit';
			case 'tcl': case 'tk': 				return 'application/x-tcl';
			case 'der': case 'pem': case 'crt': 		return 'application/x-x509-ca-cert';
			case 'xpi': 					return 'application/x-xpinstall';
			case 'xhtml': 					return 'application/xhtml+xml';
			case 'zip': 					return 'application/zip';
			case 'bin': case 'exe': case 'dll': 		return 'application/octet-stream';
			case 'deb':	 				return 'application/octet-stream';
			case 'dmg': 					return 'application/octet-stream';
			case 'eot': 					return 'application/octet-stream';
			case 'iso': case 'img': 			return 'application/octet-stream';
			case 'msi': case 'msp': case 'msm': 		return 'application/octet-stream';
			case 'mid': case 'midi': case 'kar':		return 'audio/midi';
			case 'mp3': 					return 'audio/mpeg';
			case 'ogg': 					return 'audio/ogg';
			case 'ra': 					return 'audio/x-realaudio';
			case '3gpp': case '3gp': 			return 'video/3gpp';
			case 'mpeg': case 'mpg': 			return 'video/mpeg';
			case 'mp4': 					return 'video/mp4';
			case 'mov': 					return 'video/quicktime';
			case 'flv': 					return 'video/x-flv';
			case 'mng': 					return 'video/x-mng';
			case 'asx': case 'asf': 			return 'video/x-ms-asf';
			case 'wmv': 					return 'video/x-ms-wmv';
			default:					return 'application/octet-stream';
		}
	}
}


Router::init();

?>
