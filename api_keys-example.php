<?php

// Amazon public and private keys
// http://aws.amazon.com/
define( 'SCRIB_KEY_AZPUBLIC', 'key' );
define( 'SCRIB_KEY_AZPRIVATE', 'key' );

// LibraryThing
// http://www.librarything.com/api
define( 'SCRIB_KEY_LIBTHING', 'key' );

// Google Book Search
// Set the path to the Zend GData Library
// http://code.google.com/apis/gdata/articles/php_client_lib.html
set_include_path( get_include_path() . PATH_SEPARATOR . '/path/to/ZendGdata/library' );
