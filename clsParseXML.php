<?php
/* 

XML Parser Class
by Eric Rosebrock
http://www.phpfreaks.com

Class originated from: kris@h3x.com AT: http://www.devdump.com/phpxml.php

Usage:

<?php
include 'clsParseXML.php';

$xmlparse = &new ParseXML;
$xml = $xmlparse->GetXMLTree('/path/to/xmlfile.xml');

echo "<pre>";
print_r($xml[IMAGEURLMEDIUM]);
echo "</pre>";
?>

The path to the XML file may be a local file or a URL.
Returns the elements of the XML file into an array with
it's subelements as keys and subarrays.

*/

class ParseXML{
      function GetChildren($vals, &$i) { 
         $children = array(); // Contains node data
         if (isset($vals[$i]['value'])){
            $children['VALUE'] = $vals[$i]['value'];
         } 
         
         while (++$i < count($vals)){ 
            switch ($vals[$i]['type']){
               
            case 'cdata': 
               if (isset($children['VALUE'])){
                  $children['VALUE'] .= $vals[$i]['value'];
               } else {
                  $children['VALUE'] = $vals[$i]['value'];
               } 
            break;
            
            case 'complete':
               if (isset($vals[$i]['attributes'])) {
                  $children[$vals[$i]['tag']][]['ATTRIBUTES'] = $vals[$i]['attributes'];
                  $index = count($children[$vals[$i]['tag']])-1;
         
                  if (isset($vals[$i]['value'])){ 
                     $children[$vals[$i]['tag']][$index]['VALUE'] = $vals[$i]['value']; 
                  } else {
                     $children[$vals[$i]['tag']][$index]['VALUE'] = '';
                  }
               } else {
                  if (isset($vals[$i]['value'])){
                     $children[$vals[$i]['tag']][]['VALUE'] = $vals[$i]['value']; 
                  } else {
                     $children[$vals[$i]['tag']][]['VALUE'] = '';
                  } 
               }
            break;
            
            case 'open': 
               if (isset($vals[$i]['attributes'])) {
                  $children[$vals[$i]['tag']][]['ATTRIBUTES'] = $vals[$i]['attributes'];
                  $index = count($children[$vals[$i]['tag']])-1;
                  $children[$vals[$i]['tag']][$index] = array_merge($children[$vals[$i]['tag']][$index],$this->GetChildren($vals, $i));
               } else {
                  $children[$vals[$i]['tag']][] = $this->GetChildren($vals, $i);
               }
            break; 
         
            case 'close': 
               return $children; 
         } 
      }
   }
     
      function GetXMLTree($xmlloc){ 
         if (file_exists($xmlloc)){
            $data = mb_convert_encoding(implode('', file($xmlloc)), 'UTF-8'); 
         } else {
            $fp = fopen($xmlloc,'r');
            while(!feof($fp)){
               $data = $data . mb_convert_encoding(fread($fp, 1024), 'UTF-8'); 
            }
      
            fclose($fp);
         }
      
         $parser = xml_parser_create('ISO-8859-1');
         xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1); 
         xml_parse_into_struct($parser, $data, $vals, $index); 
         xml_parser_free($parser); 
      
         $tree = array(); 
         $i = 0; 
      
         if (isset($vals[$i]['attributes'])) {
            $tree[$vals[$i]['tag']][]['ATTRIBUTES'] = $vals[$i]['attributes']; 
            $index = count($tree[$vals[$i]['tag']])-1;
            $tree[$vals[$i]['tag']][$index] =  array_merge($tree[$vals[$i]['tag']][$index], $this->GetChildren($vals, $i));
         } else {
            $tree[$vals[$i]['tag']][] = $this->GetChildren($vals, $i); 
         }
      return $tree; 
      }
}