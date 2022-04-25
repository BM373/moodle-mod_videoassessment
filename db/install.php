<?php
function xmldb_videoassessment_install() {
    global $OUTPUT;
    $cmdline = '/usr/local/bin/ffmpeg -version';
    ignore_user_abort(true);
    set_time_limit(0);
    $output = array();
    $retval = 0;
    putenv('PATH=');
    putenv('LD_LIBRARY_PATH=');
    putenv('DYLD_LIBRARY_PATH=');
    exec($cmdline, $output, $retval);
    if($retval == 1 || empty($output)){
        echo $OUTPUT->notification("The default installation path of ffmpeg does not exist!", 'notifyproblem');
    }else{
        $arr = explode("\n",$output[0]);
        $ffmpegversioninfo = $arr[0];
        echo $OUTPUT->notification($ffmpegversioninfo, 'notifysuccess');
    }
}
