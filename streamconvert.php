<?php

define('SOX_PATH', '/opt/sox/bin/sox');

if ($_SERVER['PATH_INFO'] != '/stream'){
    header('Content-Type: audio/x-mpegurl');
    echo 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'/stream';
    exit;
}

$opts = array(
  'http'=>array(
    'method'=>"GET",
    'header'=>"User-Agent: ogg2mp3 (gnegg.ch/ogg2mp3)\r\n"
  )
);

$context = stream_context_create($opts);

$streams = file('http://ormgas.rainwave.cc/ormgas.m3u', false, $context);
foreach($streams as $stream){
    // try next stream
    if ( ($fh = fopen(trim($stream), 'r')) === false) continue;
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
    break; // leave if stream goes down.
}
// we'll never get down here I guess
die("No Valid Stream found")

?>
