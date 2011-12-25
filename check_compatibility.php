<?php
/*
	Helps checking compatibility with IP.Board and other scripts
	@author  NewEraCracker
	@version 0.9.4
	@date    2011/12/25
	@license Public Domain

	Inspired by all noobish hosting companies around the world

	Greetz to:
	 - ForumScriptz Team
	 - Matt Mecham
	 - Xenforo Developers
*/

/* -------------
   Configuration
   ------------- */

$mysqlEnabled  = true;
$mysqlHostname = '127.0.0.1';
$mysqlPortnum  = '3306';
$mysqlUsername = '';
$mysqlPassword = '';

/* ---------
   Functions
   --------- */
   
function intAbs($number)
{
	return (int)str_replace('-','',(string)$number);
}

function improvedIntVal($value)
{
	$value = (string)$value;
	$new   = '';
	$found = false;

	// Build
	for( $i=0; $i<strlen($value); $i++ )
	{
		// Found a number ?
		if( is_numeric($value[$i]) )
		{
			$found = true;
			$new .= $value[$i];
		}
		elseif($found)
		{
			// We already have numbers
			// and we don't like trash.
			break;
		}
	}
	$value = $new;

	// Return the result
	return (int)$value;
}

function mySqlVersionStringToInt($version)
{
	$version = explode('.',$version);
	$version = array_map('improvedIntVal',$version);
	$version = $version[0]*10000 + $version[1]*100 + $version[2];
	return $version;
}

function mySqlVersionIntToString($version)
{
	$version_remain = (int)($version);

	// Major  [?.x.x]
	$version_major  = (int)($version_remain/10000) ;
	$version_remain = (int)($version_remain-($version_major*10000));

	// Medium [x.?.x]
	$version_medium = (int)($version_remain/100);
	$version_remain = (int)($version_remain-($version_medium*100));

	// Lower  [x.x.?]
	$version_lower  = (int)($version_remain);

	return "{$version_major}.{$version_medium}.{$version_lower}";
}

/* -----------
   PHP Version
   ----------- */

// Check for lower than 5.2.9
if( version_compare(PHP_VERSION, '5.2.9', '<') )
{
	$errors[] = 'PHP 5.2.9 or newer is required. '.PHP_VERSION.' does not meet this requirement.';
}

// If 5.3, check for lower than 5.3.5
if( version_compare(PHP_VERSION, '5.3', '>=') && version_compare(PHP_VERSION, '5.3.5', '<') )
{
	$errors[] = 'PHP 5.3.5 or newer is required. '.PHP_VERSION.' does not meet this requirement.';
}

/* ------------
   PHP Settings
   ------------ */

// Functions to be enabled
$disabledFunctions    = array_map('trim', explode(',',@ini_get('disable_functions')) );
$disabledFunctions    = array_merge($disabledFunctions, array_map('trim', explode(',',@ini_get('suhosin.executor.func.blacklist'))) );
$functionsToBeEnabled = array('php_uname', 'base64_decode', 'fpassthru', 'ini_set', 'ini_get');
foreach( $functionsToBeEnabled as $test )
{
	if(!function_exists($test) || in_array($test, $disabledFunctions))
	{
		$errors[] = 'Function '.$test.' is required to be enabled in PHP!';
	}
}

// Do we have access to eval?
if( in_array('eval', $disabledFunctions) )
{
	$errors[] = 'Language construct eval is required to be enabled in PHP!';
}

// Magic Quotes
if( @ini_get('magic_quotes_gpc') || @get_magic_quotes_gpc() )
{
	$errors[] = 'magic_quotes_gpc is enabled in your php.ini! Please ask your host to disable it for better functionality.';
}

// Safe Mode
if( @ini_get('safe_mode') )
{
	$errors[] = 'PHP must not be running in safe_mode. Please ask your host to disable the PHP safe_mode setting.';
}

// Check PHP extensions
$required_extensions = array(
	array( 'ctype', 'Ctype' ),
	array( 'curl', 'cURL' ),
	array( 'dom', 'Document Object Model' ),
	array( 'iconv', 'Iconv' ),
	array( 'gd', 'GD Library' ),
	array( 'json', 'JSON' ),
	array( 'mbstring', 'Multibyte String' ),
	array( 'mysql', 'MySQL'  ),
	array( 'mysqli', 'MySQLi' ),
	array( 'openssl', 'OpenSSL'  ),
	array( 'pcre', 'Perl-Compatible Regular Expressions' ),
	array( 'reflection', 'Reflection Class' ),
	array( 'session', 'Session' ),
	array( 'spl', 'SPL' ),
	array( 'xml', 'XML Parser' ),
	array( 'zip', 'Zip' ),
	array( 'zlib', 'Zlib' ),
);

foreach( $required_extensions as $test )
{
	if( !extension_loaded($test[0]) )
	{
		$errors[] = 'The required PHP extension "'.$test[1].'" could not be found. Please ask your host to install this extension.';
	}
}

// Check cURL
if( extension_loaded('curl') )
{
	$curlFound = 'The required PHP extension "cURL" was found';

	// Some fail hosts have cURL but disable its functions. Lets check for that.
	$curlFuctions = array(
		'curl_close', 'curl_copy_handle', 'curl_errno', 'curl_error', 'curl_exec', 'curl_getinfo',
		'curl_init', 'curl_multi_add_handle', 'curl_multi_close', 'curl_multi_exec', 'curl_multi_getcontent',
		'curl_multi_info_read', 'curl_multi_init', 'curl_multi_remove_handle', 'curl_multi_select',
		'curl_setopt_array', 'curl_setopt', 'curl_version',
		);
	foreach( $curlFuctions as $test )
	{
		if(!function_exists($test) || in_array($test, $disabledFunctions))
		{
			$errors[] = $curlFound.', but function '.$test.' is disabled, please ask your host to enable it!';
		}
	}

	// We need SSL and ZLIB support
	if( $curlVersion = @curl_version() )
	{
		$curlBitFields = array( 'CURL_VERSION_SSL', 'CURL_VERSION_LIBZ' );
		$curlBitFriendly = array( 'SSL', 'ZLIB' );

		foreach($curlBitFields as $arr_key => $feature)
		{
			if( !($curlVersion['features'] && constant($feature)) )
			{
				$test = $curlBitFriendly[$arr_key];
				$errors[] = $curlFound.', but '.$test.' support is missing. Please ask your host to add support for '.$test.' in cURL.';
			}
		}
	}
}

// Check GD
if( function_exists('gd_info') )
{
	$gdFound = 'The required PHP extension "GD Library" was found';

	// We need GIF, JPEG and PNG support
	$required_gd = array(
		array( 'imagecreatefromgif', 'GIF' ),
		array( 'imagecreatefromjpeg', 'JPEG' ),
		array( 'imagecreatefrompng', 'PNG' ),
	);

	foreach( $required_gd as $test )
	{
		if( !function_exists($test[0]) )
		{
			$errors[] = $gdFound.', but '.$test[1].' support is missing. Please ask your host to add support for '.$test[1].' images.';
		}
	}

	// We need GD 2 and freetype support
	$gdInfo = @gd_info();
	if( @$gdInfo["GD Version"] && !strstr($gdInfo["GD Version"],'2.') )
	{
		$errors[] = $gdFound.', but GD Version is older than v2. Please ask your host to fix this issue.';
	}
	if( @$gdInfo['FreeType Support'] == false )
	{
		$errors[] = $gdFound.', but FreeType support is missing. Please ask your host to add support for this.';
	}
}

// Check RAM limits
if( $memLimit = @ini_get('memory_limit') )
{
	$memLimit = trim($memLimit);
	$last = strtolower($memLimit[strlen($memLimit)-1]);
	switch($last) {
		case 'g':
			$memLimit *= 1024;
		case 'm':
			$memLimit *= 1024;
		case 'k':
			$memLimit *= 1024;
	}

	$recLimit = (128*1024*1024);
	if($memLimit < $recLimit)
	{
		$errors[] = 'Memory Limit: '.(int)($recLimit/(1024*1024)).'M is required. Please ask your host to increase this setting.';
	}

}

/* ----------------
   Suhosin Settings
   ---------------- */
if( extension_loaded('suhosin') )
{
	// Value has to be the same or higher to pass tests
	$test_values = array(
		array( 'suhosin.get.max_name_length', 512 ),
		array( 'suhosin.get.max_totalname_length', 512 ), 
		array( 'suhosin.get.max_value_length', 1024 ), 
		array( 'suhosin.post.max_array_index_length', 256 ),
		array( 'suhosin.post.max_name_length', 512 ),
		array( 'suhosin.post.max_totalname_length', 8192 ),
		array( 'suhosin.post.max_vars', 4096 ),
		array( 'suhosin.post.max_value_length', 1000000 ),
		array( 'suhosin.request.max_array_index_length', 256 ),
		array( 'suhosin.request.max_totalname_length', 8192 ),
		array( 'suhosin.request.max_vars', 4096 ),
		array( 'suhosin.request.max_value_length', 1000000 ),
		array( 'suhosin.request.max_varname_length', 512 ),
	);

	// Value has to be false to pass tests
	$test_false = array(
		'suhosin.sql.bailout_on_error',
		'suhosin.cookie.encrypt',
		'suhosin.session.encrypt',
	);

	foreach($test_false as $test)
	{
		if( @ini_get($test) != false )
		{
			$errors[] = $test.' is required to be set to <b>off</b> in php.ini. Your host does not meet this requirement.';
		}
	}
	foreach($test_values as $test)
	{
		if( isset($test['0']) && isset($test['1']) )
		{
			if( @ini_get($test['0']) < $test['1'])
			{
				$errors[] = 'It is required that <b>'.$test['0'].'</b> is set to <b>'.$test['1'].'</b> or higher.';
			}
		}
	}
}

/* -------------
   MySQL Version
   ------------- */
if( $mysqlEnabled )
{
	// Just to be sure :)
	$mysqlPortnum = (int)$mysqlPortnum;

	if( function_exists('mysqli_connect') )
	{
		$mysqli = @mysqli_connect($mysqlHostname,$mysqlUsername,$mysqlPassword,'',$mysqlPortnum);

		if(!$mysqli)
		{
			$errors[] = 'Unable to connect to MySQLi: '.mysqli_connect_error();
		}
		else
		{
			$client_version = mySqlVersionStringToInt( mysqli_get_client_info() );
			$server_version = mySqlVersionStringToInt( mysqli_get_server_info($mysqli) );
			mysqli_close($mysqli);
		}
	}
	elseif( function_exists('mysql_connect') )
	{
		$mysqlHostname = $mysqlHostname.':'.$mysqlPortnum;
		$mysql = @mysql_connect($mysqlHostname,$mysqlUsername,$mysqlPassword);

		if(!$mysql)
		{
			$errors[] = 'Unable to connect to MySQL: '.mysql_error();
		}
		else
		{
			$client_version = mySqlVersionStringToInt( mysql_get_client_info() );
			$server_version = mySqlVersionStringToInt( mysql_get_server_info($mysql) );
			mysql_close($mysql);
		}
	}

	if( isset($server_version) && isset($client_version) )
	{
		if($server_version < 50000)
		{
			$errors[] = 'Your MySQL Version ('.mySqlVersionIntToString($server_version).') is end-of-life. Please ask your host to upgrade MySQL!';
		}
		elseif($server_version < 50100)
		{
			$errors[] = 'You are running MySQL '.mySqlVersionIntToString($server_version).', please ask your host to upgrade to MySQL 5.1!';
		}
		if( intAbs($server_version-$client_version) >= 1000 )
		{
			$errors[] = 'Your PHP MySQL library version ('.mySqlVersionIntToString($client_version).') does not match MySQL Server version ('.mySqlVersionIntToString($server_version).')! Please ask your host to fix this issue.';
		}
	}
}

/* ------
   Output
   ------ */

// Header
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>check_compatibility.php</title>
<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
</head>
<body>
<pre>';

// Body
if( isset($errors) && count($errors) )
{
	// Errors
	foreach($errors as $error)
	{
		echo $error.'<br />'."\r\n";
	}
}
else
{
	// Balls to you!
	echo 'Congratulations, no problems have been detected'.'<br />'."\r\n";
}

// Footer
echo '</pre>
</body>
</html>';