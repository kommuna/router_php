
# Static files php router

It is intented to be used to route URIs to static files or proxy. Mostly GET requests are routed, but it is possible to route other methods.

## Usage

The idea is to have a file, lets say index.php, which should get all the requests to the site. 
It's a very simple php script with declarative(-ish) approach. 
There are two binding rules, which bind URI to an action. 
So far we have two actions -- return a static file or proxy the request to http URL. 
Rules are processed from top to bottom (it's php code after all), and you can add some if-s and computation and whatever (yeah, it's php code after all).
Here is a script example

```
<?php

        require_once("router.php");

        Router::file('/foo/bar', 'bar.html', array('MATCH'=>'EXACT'));
        Router::file('/boo/', 'boo.html');
        Router::proxy('/p/(\d+)', 'http://google.com/{1}', array('MATCH'=>'REGEX', 'ALL_METHODS_ACCEPTED'));

?>
```


## API

Binds URI with a file, specified by *$path* relative to your document root. If current request URI matches provided *$uri* it will return file and exit.
```
file($uri, $path, $flags)
```
By default it accepts only GET. If matching URL is hit with another method it will return *405 Method Not Allowed* and exit unless different behaviour is specified in *$flags*


Binds URI with an http URL , specified by *$url*. If current request URI matches provided *$uri* it will proxy the request and exit.
```
proxy($uri, $url, $flags)
```
This accepts GET and POST. Other methods can be used if you say so in *$flags*. 


*$uri* is something we match current request URI to. There are four maching mechanisms: *EXACT*, *STARTS*, *COMPONENT* and *REGEX*.

*EXACT* match is successfull if *$uri* equals to the current request URI.

*STARTS* match is successfull if the current request URI starts with *$uri* (or equals). For example *$uri* */foo/bar* matches the following URIs */foo/bar*, */foo/barracks*, */foo/bar/* and */foo/bar/boo*. 
Also, it appends the leftovers of request URI, which was beyond the *$uri* part to resulting *$path*, unless this behaviour is turned off with *DONT_APPEND_TAIL* flag.

*COMPONENT* is almost like *STARTS*, but it will match whole components divided by */*. For example *$uri* */foo/bar* matches the following URIs */foo/bar*, , */foo/bar/* and */foo/bar/boo*, but won't match */foo/barracks*.
It also appends the remaining part of request URI to *$path* as well as *STARTS* does.

*REGEX* will try to use *$uri* as a regular expression for current URI. Also, if you are using this match you may use {1}, {2}, etc in *$path* or *$url* and those will be substitued with corresponding matches from URI.

By default *STARTS* matching will be used. If you need to switch to another matching you can do this by adding a key *MATCH* to *$flags* with match name as a value. e.g.

```
file('/foo/bar', 'foos/bar.html', array('MATCH'=>'EXACT'));
```

*$flags* is an array and it contains a number of options. There are options, which just need to be in the array to be applied (switches), and there are options, which have values (like *MATCH* above).

Switches:

* *ALL_METHODS_ACCEPTED* -- *file()* and *proxy()* will match even if method is not GET for *file()* and neither GET nor POST for *proxy()*.
* *ALL_METHODS_ALLOWED* -- *$file()* and *proxy()* won't return *405 Method Not Allowed*, but simply won't match and you have a chance to match it later
* *DONT_APPEND_TAIL* -- don't append remaining part to *$path* for *STARTS* and *COMPONENT* matches.

Options with values:

* *MATCH* -- see above.
* *PROXY_REQUEST_HEADERS* => array() -- array of request headers appended when proxy http request is made. Useful for authentication.

Sets current request URI to something
```
setURI($uri)
```
By default $_SERVER['REQUEST_URI'] is taken, but if you prefer to have it from somewhere else you can use that before your rules.

Sets current request mothod to something
```
setMethod($method)
```
By default $_SERVER['REQUEST_METHOD'] is used, but agan you can override it.
