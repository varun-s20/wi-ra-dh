<?php
/** Parses every PHP file in the theme and plugins with PHP's own parser (a syntax check that also runs on old PHP versions). */
$root = $argv[1] ?? '/w';
$bad  = 0;
$n    = 0;
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	if ( substr( $f, -4 ) !== '.php' || strpos( $f, '/tests/' ) !== false ) {
		continue;
	}
	$n++;
	try {
		token_get_all( file_get_contents( $f ), TOKEN_PARSE );
	} catch ( ParseError $e ) {
		$bad++;
		echo 'FAIL ' . $f . ':' . $e->getLine() . ' ' . $e->getMessage() . "\n";
	}
}
echo 'PHP ' . PHP_VERSION . ": $n files, " . ( $bad ? "$bad with syntax errors" : 'no syntax errors' ) . "\n";
