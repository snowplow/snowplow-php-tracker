<?php
$file = dirname(__DIR__).'/vendor/autoload.php';
if (file_exists($file)) {
    require $file;
}
else {
    die("Dependencies must be installed using composer:\n\nphp composer.phar install\n\n"
        . "See http://getcomposer.org for help with installing composer\n");
}
