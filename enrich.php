<?php
/*
Plugin Name: Scriblio OpenAmazOogleThing Connector
Plugin URI: http://about.scriblio.net/
Description: Enriches library catalog content using a variety of bibliographic APIs
Version: 2.7 b01
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/
/* Copyright 2006 - 2009 Casey Bisson & Plymouth State University

	This program is free software; you can redistribute it and/or modify 
	it under the terms of the GNU General Public License as published by 
	the Free Software Foundation; either version 2 of the License, or 
	(at your option) any later version. 

	This program is distributed in the hope that it will be useful, 
	but WITHOUT ANY WARRANTY; without even the implied warranty of 
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
	GNU General Public License for more details. 

	You should have received a copy of the GNU General Public License 
	along with this program; if not, write to the Free Software 
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA 
*/ 

include_once('api_keys.php');


function scrib_rich_strippit( $input ){
	$return = array();

	if( is_object( $input )){
		$return[] = (string) $input;
	}else{
		foreach( (array) $input as $field )
			$return[] = (string) $field;
	}

	return $return;
}

function scrib_rich_passv(){
	global $wpdb, $scrib;

	// find records in the harvest table eligible for enrichment
	foreach( $wpdb->get_results('SELECT * FROM '. $scrib->harvest_table .' WHERE enriched = 0 ORDER BY RAND() LIMIT 5', ARRAY_A) as $harvest ){

		$post_id = $scrib->import_post_exists( array( array( 'id' => $harvest['source_id'], 'type' => 'sourceid' )));

		if( $post_id && ( $record = get_post_meta( $post_id, 'scrib_meditor_content', true )) && is_array( $record['marcish']['idnumbers'] )){
			$idnumbers = $record['marcish']['idnumbers'];
		}else{
//			$wpdb->get_var( 'UPDATE '. $scrib->harvest_table .' SET enriched = -1 WHERE source_id = "'. $harvest['source_id'] .'"' );
			continue;
		}

		$searchnumber = $record = array();
		foreach( $idnumbers as $idnumber ){
			switch( $idnumber['type'] ){
				case 'isbn':
					$searchnumber[] = $idnumber['id'];
			}
		}
		if( count( $searchnumber )){
			foreach( $searchnumber as $isbn ){
				if( $record = scrib_rich_amazon_fetchdetails( $isbn )){
					break;
				}
			}
			foreach( $searchnumber as $isbn ){
				if( $record = scrib_rich_librarything_fetchdetails( $isbn )){
					break;
				}
			}
			foreach( $searchnumber as $isbn ){
				if( $record = scrib_rich_googlebooks_fetchdetails( $isbn )){
					break;
				}
			}
		}

/*
		$searchnumber = array();
		foreach( $idnumbers as $idnumber ){
			switch( $idnumber['type'] ){
				case 'isbn':
				case 'lccn':
				case 'olid':
				case 'asin':
					$searchnumber[] = $idnumber['type'] .':'. $idnumber['id'];
			}
		}

		// get Amazon's work ASIN
		if( count( $searchnumber )){
			
				if( !count( $record )){
					if( $record = scrib_rich_amazon_fetchdetails( $asin )){	
						break;
					}
				}


			$xid = wp_remote_get( 'http://api.scriblio.net/v01b/xid/?idnumbers='. implode( ',', $searchnumber ) .'&output=php' );
			if( is_array( $xid )){
				$xid = unserialize( substr( $xid['body'], strpos( $xid['body'], 'a:' ) ));
	//print_r( $xid );
	
				if( is_array( $xid['asin'] ) && count( $xid['asin'] )){
					foreach( $xid['asin'] as $asin ){
						$idnumbers[] = array( 'type' => 'asin', 'id' => $asin, 'src' => 'asin:'. $asin );
	
						if( !count( $record )){
							if( $record = scrib_rich_amazon_fetchdetails( $asin )){	
								break;
							}
						}
					}

					$record['_idnumbers'] = $scrib->array_unique_deep( array_merge( $record['_idnumbers'] , $idnumbers ));
				
					if( isset( $record['_sourceid'] ) && strlen( $record['_sourceid'] ))
						$scrib->import_insert_harvest( $record , 1 );

					unset( $record );
				}
			}



		}
	
	
//print_r( $record );
*/	

		$wpdb->get_var( 'UPDATE '. $scrib->harvest_table .' SET enriched = 1 WHERE source_id = "'. $harvest['source_id'] .'"' );
	}
}
add_filter( 'bsuite_interval' , 'scrib_rich_passv' );

function scrib_rich_amazon_fetchdetails( $asin ){
	if( !SCRIB_KEY_AZPUBLIC || !SCRIB_KEY_AZPRIVATE )
		return FALSE;

	require_once 'aws_signed_request.php';
	global $scrib, $bsuite;

	$spare_keys = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g' );
	
	$pxml = aws_signed_request( 'com', array(
			'Operation' => 'ItemLookup',
			'ItemId' => $asin ,
			'ResponseGroup' => 'Images,EditorialReview,Subjects,Small,ItemAttributes'
		), SCRIB_KEY_AZPUBLIC, SCRIB_KEY_AZPRIVATE );

	$record = NULL;
	
	if( empty( $pxml->Items->Item->ASIN ))
		return FALSE;
	
	$src = 'asin:'. (string) $pxml->Items->Item->ASIN;
	$record['_sourceid'] = $src;
	
	foreach ( $pxml->Items->Item->ItemAttributes->Title as $temp )
		$record['marcish']['title'][] = array( 'a' => $scrib->make_utf8( (string) $temp ), 'src' => $src );
	
	/*
	foreach ( $pxml->Items->Item->ItemAttributes->Author as $temp )
		$record['marcish']['creator'][] = array( 'name' => $scrib->make_utf8( (string) $temp ), 'role' => 'Author', 'src' => $src );
	
	foreach ( $pxml->Items->Item->ItemAttributes->Creator as $temp )
		$record['marcish']['creator'][] = array( 'name' => $scrib->make_utf8( (string) $temp ), 'role' => $scrib->make_utf8( (string) $temp->attributes()->Role ), 'src' => $src );
	
	foreach ( $pxml->Items->Item->Subjects->Subject as $temp )
		$record['marcish']['subject'][] = array( 'a_type' => 'tag', 'a' => $scrib->make_utf8( (string) $temp ), 'dictionary' => 'Amazon', 'src' => $src );
	*/
	
	foreach ( $pxml->Items->Item->ItemAttributes->ReadingLevel as $temp )
		$record['marcish']['subject'][] = array( 'a_type' => 'readinglevel', 'a' => $scrib->make_utf8( (string) $temp ), 'dictionary' => 'Amazon', 'src' => $src );
	
	foreach ( $pxml->Items->Item->EditorialReviews->EditorialReview as $temp ){
		$record['marcish']['text'][] = array( 'type' => 'description', 'content' => $bsuite->autoksum_get_text( force_balance_tags( $scrib->make_utf8( (string) $temp->Content ))), 'src' => $src );
		// $scrib->make_utf8( (string) $temp->Source ) == the type/source of the description/review
	}
	
	$record['marcish']['published'][] = array( 
		'cy' => date( 'Y', strtotime( (string) $pxml->Items->Item->ItemAttributes->PublicationDate )),
		'cm' => date( 'm', strtotime( (string) $pxml->Items->Item->ItemAttributes->PublicationDate )),
		'cd' => date( 'd', strtotime( (string) $pxml->Items->Item->ItemAttributes->PublicationDate )),
		'cc' => 'exact',
	
		'edition' => $scrib->make_utf8( (string) $pxml->Items->Item->ItemAttributes->Edition ),
	
		'publisher' => $scrib->make_utf8( (string) $pxml->Items->Item->ItemAttributes->Manufacturer ),
		
		'src' => $src,
	);
	
	$record['marcish']['description_physical'][] = array( 
		'dw' => isset( $pxml->Items->Item->ItemAttributes->PackageDimensions->Height ) ? ( (int) $pxml->Items->Item->ItemAttributes->PackageDimensions->Height / 100 ) : '',
		'dh' => isset( $pxml->Items->Item->ItemAttributes->PackageDimensions->Length ) ? ( (int) $pxml->Items->Item->ItemAttributes->PackageDimensions->Length / 100 ) : '',
		'dd' => isset( $pxml->Items->Item->ItemAttributes->PackageDimensions->Width ) ? ( (int) $pxml->Items->Item->ItemAttributes->PackageDimensions->Width / 100 ) : '',
		'du' => 'inch',
	
		'wv' => isset( $pxml->Items->Item->ItemAttributes->PackageDimensions->Weight  ) ? ( (int) $pxml->Items->Item->ItemAttributes->PackageDimensions->Weight / 100 ) : '',
		'wu' => 'pound',
	
		'duration' => (int) $pxml->Items->Item->ItemAttributes->PackageDimensions->NumberOfPages,
		'duration_units' => 'pages',
	
		'cv' => isset( $pxml->Items->Item->ItemAttributes->ListPrice->Amount ) ? ( (int) $pxml->Items->Item->ItemAttributes->ListPrice->Amount / 100 ) : '',
		'cu' => (string) $pxml->Items->Item->ItemAttributes->ListPrice->CurrencyCode,
	
		'src' => $src,
	);
	
	$formats = $bindings = array();
	foreach( $pxml->Items->Item->ItemAttributes->ProductGroup as $temp)
		$formats[] = $scrib->make_utf8( (string) $temp );
	
	foreach ( $pxml->Items->Item->ItemAttributes->Binding as $temp)
		if( !stripos( (string) $temp, 'unknown' ))
			$bindings[] = $scrib->make_utf8( (string) $temp );
	
	foreach( $formats as $format )
		$record['marcish']['format'][] = array_filter( array( 'a' => $format, 'b' => array_shift( $bindings ), 'src' => $src ));
	
	if( count( $bindings ))
		foreach( $bindings as $binding )
			$record['marcish']['format'][] = array( 'a' => $binding, 'src' => $src );
	
	foreach ( $pxml->Items->Item->ItemAttributes->Format as $temp)
		$format[ array_shift( $spare_keys ) ] = $scrib->make_utf8( (string) $temp );
	
	$record['marcish']['format'][] = array_merge( $format, array( 'src' => $src ));
	$record['marcish']['format'] = array_filter( array_values( $record['marcish']['format'] ));
	
	foreach ( $pxml->Items->Item->ItemAttributes->ISBN as $temp)
		$record['marcish']['idnumbers'][] = array( 'type' => 'isbn', 'id' => (string) $temp, 'src' => $src );
	
	$record['marcish']['idnumbers'][] = array( 'type' => 'asin', 'id' => (string) $pxml->Items->Item->ASIN, 'src' => $src );
	
	$record['_icon'] = array( 
		's' => array( 
			'url' => (string) $pxml->Items->Item->MediumImage->URL,
			'w' => '100', //(int) $pxml->Items->Item->MediumImage->Width,
			'h' => '135', //(int) $pxml->Items->Item->MediumImage->Height,
			), 
		'l' => array( 
			'url' => (string) $pxml->Items->Item->LargeImage->URL,
			'w' => (int) $pxml->Items->Item->LargeImage->Width,
			'h' => (int) $pxml->Items->Item->LargeImage->Height,
		)
	);
	
	if( !$record['_icon']['s']['url'] )
		unset($record['_icon']['s']);
	
	if( !$record['_icon']['l']['url'] )
		unset($record['_icon']['l']);
	
	$record['_idnumbers'] = $record['marcish']['idnumbers'];
	$record['_title'] = $record['marcish']['title'][0]['a'];
	
	$record = array_filter( $record );

	if( isset( $record['_sourceid'] ) && strlen( $record['_sourceid'] ))
		$scrib->import_insert_harvest( $record , 1 );

	return TRUE;
}


function scrib_rich_librarything_fetchdetails( $isbn ){
	if( !SCRIB_KEY_AZNAPI )
		return FALSE;
	
	global $scrib, $bsuite;

	// do request
	$response = @file_get_contents( 'http://www.librarything.com/services/rest/1.0/?method=librarything.ck.getwork&isbn='. $isbn .'&apikey='. SCRIB_KEY_LIBTHING );
	
	$response = str_replace( '&acirc;', '', $scrib->make_utf8( $response ));
	
	if( $response === FALSE ){
		return FALSE;
	}else{
		// parse XML
		$pxml = simplexml_load_string( $response );
	
		if($pxml === FALSE)
			return FALSE; // no xml
	}
	
	$record = NULL;
	
	if( empty( $pxml->ltml->item->attributes()->id ))
		return FALSE;
	
	$spare_keys = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g' );

	$src = 'ltid:'. (string) $pxml->ltml->item->attributes()->id;
	$record['_sourceid'] = $src;

	$record['marcish']['idnumbers'][] = array( 'type' => 'ltid', 'id' => (string) $pxml->ltml->item->attributes()->id, 'src' => $src );
	
	$record['marcish']['idnumbers'][] = array( 'type' => 'isbn', 'id' => $isbn, 'src' => $src );


	foreach( $pxml->ltml->item->commonknowledge->fieldList->field as $ixml ){	
		switch( $ixml->attributes()->type ){
			case 2: // geographic places mentioned
				foreach( (array) $ixml->versionList->version->factList as $facts ){
					foreach( (array) $facts as $fact ){
						$fact_temp = (array) explode( ',', $scrib->strip_cdata( $fact ));
						$fact_temp = array_filter( array_reverse( array_map( 'trim', $fact_temp )));
	
						if( count( $fact_temp )){
							$temp = array();
							foreach( $fact_temp as $key => $val ){
								$temp[ $spare_keys[ $key ] .'_type' ] = 'place'; 
								$temp[ $spare_keys[ $key ] ] = $val; 
							}
							$record['marcish']['subject'][] = array_merge( $temp, array( 'src' => $src , 'dictionary' => 'LibraryThing' ));
						}
					}
				}
				break;
	
			case 3: // people / characters
				foreach( (array) $ixml->versionList->version->factList as $facts ){
					foreach( (array) $facts as $fact ){
						$fact_temp = (array) preg_split( '/[,\(\)\|]/', $scrib->strip_cdata( $fact ));
						$fact_temp = array_filter( array_map( 'trim', $fact_temp ));
	
						if( count( $fact_temp )){
							$temp = array();
							foreach( $fact_temp as $key => $val ){
								$temp[ $spare_keys[ $key ] .'_type' ] = 'person'; 
								$temp[ $spare_keys[ $key ] ] = $val; 
							}
							$record['marcish']['subject'][] = array_merge( $temp, array( 'src' => $src , 'dictionary' => 'LibraryThing' ));
						}
					}
				}
				break;
	
			case 4: // awards
				foreach( (array) $ixml->versionList->version->factList as $facts ){
					foreach( (array) $facts as $fact ){
						$fact_temp = (array) preg_split( '/[,\(\)\|]/', $scrib->strip_cdata( $fact ));
						$fact_temp = array_filter( array_map( 'trim', $fact_temp ));
	
						if( count( $fact_temp )){
							$temp = array();
							foreach( $fact_temp as $key => $val ){
								$temp[ $spare_keys[ $key ] .'_type' ] = 'award'; 
								$temp[ $spare_keys[ $key ] ] = $val; 
							}
							$record['marcish']['subject'][] = array_merge( $temp, array( 'src' => $src , 'dictionary' => 'LibraryThing' ));
						}
					}
				}
				break;
	
	/*
			case 16: // first publication date
				foreach( (array) $ixml->versionList->version->factList as $facts ){
					print_r( $facts );
					foreach( (array) $facts as $fact ){
						print_r( $fact );
					}
				}
				break;
	*/
	
			case 21: // title
				foreach( (array) $ixml->versionList->version->factList as $facts ){
					foreach( (array) $facts as $fact ){
						$record['marcish']['title'][] = array( 'a' => (string) $fact , 'src' => $src );
					}
				}
				break;
	
			case 25: // first words
				foreach( (array) $ixml->versionList->version->factList as $facts ){
					foreach( (array) $facts as $fact ){
						$record['marcish']['text'][] = array( 'type' => 'firstwords', 'content' => $scrib->strip_cdata( $fact ), 'src' => $src);
					}
				}
				break;
	
			case 26: // last words
				foreach( (array) $ixml->versionList->version->factList as $facts ){
					foreach( (array) $facts as $fact ){
						$record['marcish']['text'][] = array( 'type' => 'lastwords', 'content' => $scrib->strip_cdata( $fact ), 'src' => $src);
					}
				}
				break;
	
			case 30: // dedication
				foreach( (array) $ixml->versionList->version->factList as $facts ){
					foreach( (array) $facts as $fact ){
						$record['marcish']['text'][] = array( 'type' => 'dedication', 'content' => $scrib->strip_cdata( $fact ), 'src' => $src);
					}
				}
				break;
	
		}
	}

	$record['_idnumbers'] = $record['marcish']['idnumbers'];
	$record['_title'] = $record['marcish']['title'][0]['a'];
	
	$record = array_filter( $record );

	if( isset( $record['_sourceid'] ) && strlen( $record['_sourceid'] ))
		$scrib->import_insert_harvest( $record , 1 );

	return TRUE;
}

function scrib_rich_googlebooks_fetchdetails( $isbn ){
	global $scrib, $bsuite;

	require_once 'Zend/Loader.php' ;
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_Books');
	
	//$username = 'username@gmail.com';
	//$password = 'password';
	
	if( isset( $username ) && isset( $password ))
		$httpClient = Zend_Gdata_ClientLogin::getHttpClient( $username, $password, 'print' );
	
	$books = new Zend_Gdata_Books( $httpClient );
	
	$query = $books->newVolumeQuery();
	$query->setQuery( 'isbn:'. $isbn );
	//$query->setQuery( 'harry potter' );
	//$query->setMinViewability( 'partial_view' );
	$feed = $books->getVolumeFeed( $query );

	if( !array( $feed ) || !count( $feed ) )
		return;

	foreach( $feed as $entry ) {
		$src = 'gbid:'. (string) $entry->getVolumeId();
		$record['_sourceid'] = $src;
	
		// the gbid
		$record['marcish']['idnumbers'][] = array( 'type' => 'gbid', 'id' => (string) $entry->getVolumeId(), 'src' => $src );
	
		// other idnumbers
		foreach( (array) scrib_rich_strippit( $entry->getIdentifiers() ) as $temp ){
			$parts = explode( ':', $temp );
			switch( strtolower( $parts[0] ) ){
				case 'isbn':
					$record['marcish']['idnumbers'][] = array( 'type' => 'isbn', 'id' => (string) $parts[1], 'src' => $src );
					continue;
			}
		}
		
		// the title
		foreach( (array) scrib_rich_strippit( $entry->getTitles() ) as $temp )
			$record['marcish']['title'][] = array( 'a' => (string) $temp , 'src' => $src );
	
/*
		// the preview url
		if( !strrpos( $entry->getEmbeddability()->value , 'not_embeddable' ))
			$record['marcish']['linked_urls'][] = array( 'name' => 'View in Google Book Search', 'href' => 'http://books.google.com/books?id='. (string) $entry->getVolumeId() .'&printsec=frontcover', 'src' => $src );
	
		// subjects
		foreach( (array) scrib_rich_strippit( $entry->getSubjects() ) as $temp )
			$record['marcish']['subject'][] = array( 'a' => $temp , 'a_type' => 'temp', 'src' => $src , 'dictionary' => 'Google Book Search' );
*/
	
		// add item to the user's google book shelf
//		if( is_object( $httpClient ))
//			$books->insertVolume( $entry, Zend_Gdata_Books::MY_LIBRARY_FEED_URI );
	
	}

	foreach( $record['marcish'] as $k => $v )
		 $record['marcish'][ $k ] = $scrib->array_unique_deep( $v );


	$record['_idnumbers'] = $record['marcish']['idnumbers'];
	$record['_title'] = $record['marcish']['title'][0]['a'];
	
	$record = array_filter( $record );

	if( isset( $record['_sourceid'] ) && strlen( $record['_sourceid'] ))
		$scrib->import_insert_harvest( $record , 1 );

	return TRUE;
}