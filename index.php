<?php

use classes\Parser;

require 'classes/Autoloader.php';

$config = require 'config/config.php';

try {
    $content = ( new Parser($config) )->run();

    $content->saveDataJson();
    echo '<pre>'.print_r($content->getReviews(),1).'</pre>';
} catch (Exception $e) {
    echo $e->getMessage();
}
