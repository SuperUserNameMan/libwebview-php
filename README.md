# libwebview-php
PHP-8.1 FFI wrapper for [webview](https://github.com/webview/webview)

# How to :

Compile your own `libwebview.so` or `libwebview.dll` following instructions at [webview](https://github.com/webview/webview).

Copy `libwebview.h` and `libwebview.php` with the compiled library.

```PHP
<?php

require_once( 'libwebview.php' );

$webview = new WebView( debug: true );

$webview->set_title( 'My PHP webview' );
$webview->set_size( 640 , 480 , WebView::HINT_NONE );

$webview->set_html
(
	'<h1>My title</h1>'
	.'<p>My paragraph</p>'
	.'<button onclick="echo(\'my click\');">My button</button>'
);

$webview->bind( "echo" , function( $args )
{
  echo $args[0].PHP_EOL ;
});

$webview->bind( "loop" , function( $args ) use ( $webview )
{
	static $frame = 0 ;
	echo "frame = $frame".PHP_EOL;
	$frame++;

	$webview->eval( "setTimeout( loop , 1000 );" );
});

$webview->init( "setTimeout( loop , 3000 );" );

$webview->run();

//EOF
```
