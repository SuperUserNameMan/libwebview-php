<?php
//TAB=4

if (
	! function_exists( 'popup_info' )
	&&
	! function_exists( 'popup_warning' )
	&&
	! function_exists( 'popup_error' )
	&&
	! function_exists( 'popup_question' )
)
{
	define( 'POPUP_INFO'     , 0 );
	define( 'POPUP_WARNING'  , 1 );
	define( 'POPUP_ERROR'    , 2 );
	define( 'POPUP_QUESTION' , 3 );

	function popup_warning ( string $MESSAGE , string $TITLE = NULL ) : bool { return popup_info( $MESSAGE , $TITLE , POPUP_WARNING  ); }
	function popup_error   ( string $MESSAGE , string $TITLE = NULL ) : bool { return popup_info( $MESSAGE , $TITLE , POPUP_ERROR    ); }
	function popup_question( string $MESSAGE , string $TITLE = NULL ) : bool { return popup_info( $MESSAGE , $TITLE , POPUP_QUESTION ); }

	switch( PHP_OS_FAMILY )
	{
		case 'Windows':
			if ( ! extension_loaded( 'FFI' ) )
			{
				ini_set( 'extension_dir' , dirname( PHP_BINARY ).'\\ext' );
				dl( dirname( PHP_BINARY ).'\\ext\\php_ffi.dll');
			}
			if ( extension_loaded( 'FFI' ) )
			{
				function popup_info( string $MESSAGE , string $TITLE = NULL , string $TYPE = POPUP_INFO ) : bool
				{
					static $FFI = null ;

					if ( is_null( $FFI ) )
					{
						$CDEF = <<<CDEF
							enum{
								MB_OK = 0x00000000L,
								MB_ABORTRETRYIGNORE = 0x00000002L ,
								MB_CANCELTRYCONTINUE = 0x00000006L ,

								MB_OKCANCEL = 0x00000001L ,
								MB_RETRYCANCEL = 0x00000005L ,
								MB_YESNO = 0x00000004L ,
								MB_YESNOCANCEL = 0x00000003L ,

								MB_HELP = 0x00004000L ,


								MB_ICONEXCLAMATION = 0x00000030L ,
								MB_ICONWARNING     = 0x00000030L ,

								MB_ICONINFORMATION = 0x00000040L ,
								MB_ICONASTERISK    = 0x00000040L ,

								MB_ICONQUESTION    = 0x00000020L ,

								MB_ICONSTOP        = 0x00000010L ,
								MB_ICONERROR       = 0x00000010L ,
								MB_ICONHAND        = 0x00000010L ,
							};

							typedef void* HANDLE;
							typedef HANDLE HWND;
							typedef const char* LPCSTR;
							typedef unsigned UINT;

							int MessageBoxW(void*, void*, void*, unsigned);
						CDEF;

						$FFI = FFI::cdef( $CDEF , 'user32.dll' );
					}

					$TITLE ??= $_SERVER['PHP_SELF'] ;

					switch( $TYPE )
					{
						case POPUP_ERROR   : $FLAGS = 0x10 ; break ; // OK
						case POPUP_WARNING : $FLAGS = 0x30 ; break ; // OK
						case POPUP_QUESTION: $FLAGS = 0x24 ; break ; // YES/NO
						default:             $FLAGS = 0x40 ; break ; // OK
					}

					if ( function_exists( 'iconv' ) )
					{
						$TITLE = iconv( "UTF-8", "UTF-16LE//TRANSLIT", $TITLE."\0\0\0" );
						$MESSAGE = iconv( "UTF-8", "UTF-16LE//TRANSLIT", $MESSAGE."\0\0\0" );
					}

					$PRESSED = $FFI->MessageBoxW( NULL , $MESSAGE , $TITLE , $FLAGS );

					return $PRESSED != 7;
				}
				return;
			}
			else
			if ( function_exists( 'readline' ) )
			{
				goto popup_fallback_readline;
			}
		break;


		case 'Linux' :
		case 'BSD':
		default:
			if ( ! empty( exec( "which zenity" ) ) ) // GNOME
			{
				function popup_info( string $MESSAGE , string $TITLE = NULL , int $TYPE = POPUP_INFO ) : bool
				{
					$TITLE ??= $_SERVER['PHP_SELF'] ;

					switch( $TYPE )
					{
						case POPUP_ERROR:    $TYPE = 'error' ; break ;
						case POPUP_QUESTION: $TYPE = 'question' ; break ;
						case POPUP_WARNING:  $TYPE = 'warning' ; break ;
						default:              $TYPE = 'info' ; break ;
					}

					$PRESSED = exec( "zenity --$TYPE --title=\"$TITLE\" --text=\"$MESSAGE\" ; echo $?" );

					return $PRESSED == 0 ;
				}
				return;
			}
			else
			if ( ! empty( exec( "which kdialog" ) ) ) // KDE
			{
				function popup_info( string $MESSAGE , string $TITLE = NULL , int $TYPE = POPUP_INFO ) : bool
				{
					$TITLE ??= $_SERVER['PHP_SELF'] ;

					switch( $TYPE )
					{
						case POPUP_ERROR:    $TYPE = 'error' ; break ;
						case POPUP_QUESTION: $TYPE = 'yesno' ; break ;
						case POPUP_WARNING:  $TYPE = 'sorry' ; break ;
						default:              $TYPE = 'msgbox' ; break ;
					}

					$PRESSED = exec( "kdialog --title \"$TITLE\" --$TYPE \"$MESSAGE\" ; echo $?" );

					return $PRESSED == 0 ;
				}
				return;
			}
			else
			if ( ! empty( exec( "which xmessage" ) ) ) // X11
			{
				function popup_info( string $MESSAGE , string $TITLE = NULL , int $TYPE = POPUP_INFO ) : bool
				{
					$TITLE ??= $_SERVER['PHP_SELF'] ;

					$BTNS = $TYPE == POPUP_QUESTION ? 'NO,YES' : 'OK' ;

					switch( $TYPE )
					{
						case POPUP_ERROR : $ICO = 'X' ; break ;
						case POPUP_QUESTION : $ICO = '?' ; break ;
						case POPUP_WARNING  : $ICO = '!' ; break ;
						default: $ICO = 'i' ; break ;
					}

					if ( function_exists( 'iconv' ) )
					{
						$TITLE = iconv( "UTF-8", "ASCII//TRANSLIT", $TITLE );
						$MESSAGE = iconv( "UTF-8", "ASCII//TRANSLIT", $MESSAGE );
					}

					$TITLE = addslashes( $TITLE );
					$MESSAGE = addslashes( $MESSAGE );

					$PRESSED = exec( "echo \"[$ICO] ___ $TITLE ___ [$ICO]\n\n$MESSAGE\n\" | xmessage -buttons $BTNS -center -print -file -" );

					return $PRESSED != 'NO' ;
				}
				return;
			}
			else
			if ( function_exists( 'readline' ) )
			{
				popup_fallback_readline:
				function popup_info( string $MESSAGE , string $TITLE = NULL , int $TYPE = POPUP_INFO ) : bool
				{
					$TITLE ??= $_SERVER['PHP_SELF'] ;

					switch( $TYPE )
					{
						case POPUP_ERROR : $ICO = '[ ERROR ]' ; break ;
						case POPUP_QUESTION : $ICO = '[ QUESTION ]' ; break ;
						case POPUP_WARNING  : $ICO = '[ WARNING ]' ; break ;
						default: $ICO = '[ INFO ]' ; break ;
					}

					echo "$ICO $TITLE : $MESSAGE" ;

					if ( $TYPE == POPUP_QUESTION )
					{
						$PRESSED = strtolower( readline( " ( Yes / No ) : " ) )[0] ?? 'y' ;
						return $PRESSED != 'n' ;
					}

					readline( "( Press Enter to continue ... )" );

					return true ;
				}
				return;
			}
			else // read
			{
				function popup_info( string $MESSAGE , string $TITLE = NULL , int $TYPE = POPUP_INFO ) : bool
				{
					$TITLE ??= $_SERVER['PHP_SELF'] ;

					switch( $TYPE )
					{
						case POPUP_ERROR : $ICO = '[ ERROR ]' ; break ;
						case POPUP_QUESTION : $ICO = '[ QUESTION ]' ; break ;
						case POPUP_WARNING  : $ICO = '[ WARNING ]' ; break ;
						default: $ICO = '[ INFO ]' ; break ;
					}

					echo "$ICO $TITLE : $MESSAGE" ;

					if ( $TYPE == POPUP_QUESTION )
					{
						$PRESSED = strtolower( exec( 'read -p " ( Yes / No ) : " answer ; echo $answer' ) )[0] ?? 'y' ;
						return $PRESSED != 'n' ;
					}

					readline( "( Press Enter to continue ... )" );

					return true ;
				}
				return;
			}
		break;
	} //endswitch
}//endif function_exists()

// -------------------------------------------

class WebView
{
	const HINT_NONE  = 0 ; // Width and height are default size
	const HINT_MIN   = 1 ; // Width and height are minimum bounds
	const HINT_MAX   = 2 ; // Width and height are maximum bounds
	const HINT_FIXED = 3 ; // Window size can not be changed by a user

	static object $FFI ;
	static object $FFI_null ;

	static function _init() : bool
	{
		if ( PHP_OS_FAMILY != 'Windows' )
		{
			// TODO : check if required libs are installed, and display messagebox if not
			// Debian : "apt install libgtk-3-0 libwebkit2gtk-4.0-37"
			// Fedora : "dnf install gtk3 webkit2gtk4.0"
		}

		if ( isset( self::$FFI ) ) return true ;

		self::$FFI_null = FFI::cast('void*',null);

		$header_dirs = [ './' , './webview/' , './lib/' , './include/' ];
		$header_file = 'libwebview.h' ;

		foreach( $header_dirs as $dir )
		{
			if ( file_exists( $dir.$header_file ) )
			{
				$header_file = $dir.$header_file ;
				break ;
			}
		}

		if ( ! file_exists( $header_file ) )
		{
			popup_error( "`libwebview.h` not found." );
			return false ;
		}

		$lib_dirs = [ './' , './webview/' , './lib/' , './include/' ];
		$lib_ext  = PHP_OS_FAMILY == 'Windows' ? '.dll' : '.so' ;
		$lib_file = 'libwebview'.$lib_ext ;

		foreach( $lib_dirs as $dir )
		{
			if ( file_exists( $dir.$lib_file ) )
			{
				$lib_file = $dir.$lib_file ;
				break ;
			}
		}

		if ( ! file_exists( $lib_file ) )
		{
			popup_error( "`libwebview$lib_ext` not found." );
			return false ;
		}

		try
		{
			self::$FFI = FFI::cdef( file_get_contents( $header_file ) , $lib_file );
		}
		catch( Error $E )
		{
			popup_error( $E->getMessage() , 'Exception' );
			return false ;
		}

		return true ;
	}

	// ---------------------------

	private object $webview ;

	function __construct( bool $debug = false )
	{
		self::_init();

		$this->webview = $this->webview = self::$FFI->webview_create( $debug , null );
	}

	function __destruct()
	{
		self::$FFI->webview_destroy( $this->webview );
	}

	function run() { self::$FFI->webview_run( $this->webview ); }

	function terminate() { self::$FFI->webview_terminate( $this->webview ); }

	function dispatch( callable $fn ) { self::$FFI->webview_dispatch( $this->webview , $fn , self::$FFI_null ); }

	function set_title( string $title ) { self::$FFI->webview_set_title( $this->webview , $title ); }

	function set_size( int $width , int $height , int $hints = 0 ) { self::$FFI->webview_set_size( $this->webview , $width , $height , $hints ); }

	function navigate( string $url ) { self::$FFI->webview_navigate( $this->webview , $url ); }

	function set_html( string $html ) { self::$FFI->webview_set_html( $this->webview , $html ); }

	function init( string $js ) { self::$FFI->webview_init( $this->webview , $js ); } // executed before every window.onload

	function eval( string $js ) { self::$FFI->webview_eval( $this->webview , $js ); }

	function bind( string $name , callable $fn )
	{
		$cb = function( $seq , $req ) use ( $fn )
		{
			$args = json_decode( $req );

			$results = $fn( $args );

			$results = is_null( $results ) ? '' : json_encode( $results ) ;

			self::$FFI->webview_return( $this->webview , $seq , 0 , $results );
		};

		self::$FFI->webview_bind( $this->webview , $name , $cb , self::$FFI_null );
	}

	function unbind( string $name ) { self::$FFI->webview_unbind( $this->webview , $name ); }
}

//EOF
