<?php

    function emptyDir ($src){
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    delDir($full);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
    }

    function delDir ($src) {
        if (file_exists($src)) {
            emptyDir ($src);
            rmdir($src);
        }
    }

    