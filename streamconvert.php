<?php

/*
   This script, when installed on a webserver provides a means to convert
   ogg internet radio streams to mp3.
   
   One reason for doing a thing like this is hardware without ogg support,
   like my Sonos solution.

   This script depends on sox (http://sox.sourceforge.net) which must be
   compiled with ogg and mp3 write support (LAME).

   When installed, you can launch it either as

   http://yourserver/oggconvert.php?url=http://ormgas.rainwave.cc/ormgas.m3u

   to make the script output a m3u playlist pointing to itself or as

   http://yourserver/oggconvert.php?url=http://ormgas.rainwave.cc/ormgas.m3u&play=1
  
   to make the script start do the conversion.

   If the url= parameter ends in .m3u or .pls, the script will assume the
   url to be a playlist itself and will follow any url contained therein.

   The script is (c) 2008 by Philip Hofstetter and licensed under the MIT license.
   Any questions can be directed to pilif@gnegg.ch
*/

// the path to the MP3-Write-Enabled SOX
define('SOX_PATH', '/opt/sox/bin/sox');

if (empty($_GET['url'])){
    die("no url to play provided. use ?url=url_of_stream to select the stream");
}
if ($_GET['play'] != '1'){
    header('Content-Type: audio/x-mpegurl');
    
    echo 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'?url='.rawurlencode($_GET['url'])."&play=1\n";
    exit;
}

$opts = array(
  'http'=>array(
    'method'=>"GET",
    'header'=>"User-Agent: ogg2mp3 (gnegg.ch/ogg2mp3)\r\n"
  )
);

$context = stream_context_create($opts);

$fh = fopen($_GET['url'], 'r', false, $context);
$header = array();
foreach($http_response_header as $line){
    list($name, $content) = explode(': ', $line, 2);
    $header[strtolower($name)] = $content;
}

switch($header['content-type']){
    case 'audio/x-mpegurl':
        $streams = fetchStreams($fh);
        fclose($fh);
        foreach($streams as $url) convertStreamUrl($url, $context);
        break;
    case 'application/ogg': 
        convertStream($fh);
        break;
    default: 
        header('Content-Type: text/plain');
        die("Server returned unknown Content-Type: ".$header['content-type']);
}

function fetchStreams($fh){
    $streams = array();
    while (!feof($fh)){
        $line = trim(fgets($fh));
        if (empty($line)) continue; // skip empty lines
        if (preg_match('%^\s*#%', $line)) continue; // skip comments
        $streams[] = $line;
    }
    return $streams;
}

function convertStreamUrl($url, $context){
    if ( ($fh = fopen(trim($url), 'r', false, $context)) === false) return false;
    convertStream($fh); // never returns
    return true;
}

function convertStream($fh){
    $descspec = array( 0 => array('pipe', 'r'),
                       1 => array('pipe', 'w'),
                       2 => array('file', '/tmp/oggerr.log', 'a')
                     );
    $pipes = array();
    $cmd = SOX_PATH.' -t ogg - -t mp3 -';
    $p = proc_open($cmd, $descspec, $pipes, '/tmp', $_ENV);
    if (!$p) die("cannot open sox\n");
    header('Content-Type: audio/mpeg');
    stream_set_blocking($pipes[1], 0);
    while (!feof($fh)){
        fwrite($pipes[0], fread($fh, 8192));
        echo fread($pipes[1], 8192);
    }
    fclose($pipes[0]);
    fclose($pipes[1]);
    proc_close($p);
    return true;
}
?>
