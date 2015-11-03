
# Static files php router

It is intented to be used to route URIs to static files or proxy. Mostly GET requests are routed, but it is possible to route other methods.

## API

Binds URI with a file, specified by *$path*. If current request URI matches provided *$uri* it will return file and exit.
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
*COMPONENT* is almost like *STARTS*, but it will match whole components divided by */*. For example *$uri* */foo/bar* matches the following URIs */foo/bar*, , */foo/bar/* and */foo/bar/boo*, but won't match */foo/barracks*.
*REGEX* will try to use *$uri* as a regular expression for current URI. Also, if you are using this match you may use {1}, {2}, etc in *$path* or *$url* and those will be substitued with corresponding matches from URI.

