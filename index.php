<?php

use classes\Parser;

define('URL', 'https://www.yell.ru/company/reviews/?id=1942069&sort=recent');
define('FILENAME', 'reviews.json');

include 'config/Autoloader.php';

try {
    $content = (new Parser(URL))->run();

    $content->saveDataJson(FILENAME);
} catch (Exception $e) {
    echo $e->getMessage();
}
