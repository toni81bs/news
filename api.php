<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$cache_file = 'cache_news.json';

if (file_exists($cache_file)) {
    echo file_get_contents($cache_file);
} else {
    echo json_encode(["news_results" => []]);
}
exit;