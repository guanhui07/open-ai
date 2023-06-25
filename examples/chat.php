<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use Swoole\OpenAi\OpenAi;

$open_ai = new OpenAi('test');
$open_ai->setBaseURL('127.0.0.1:9203/');
$messages[] = ["role" => "system", "content" => "You are a helpful assistant."];
$messages[] = ["role" => "user", "content" => "Who won the world series in 2020?"];
$messages[] = ["role" => "assistant", "content" => "The Los Angeles Dodgers won the World Series in 2020."];
$messages[] = ["role" => "user", "content" => "Where was it played?"];
$complete = $open_ai->chat([
    'model' => 'gpt-3.5-turbo',
    'messages' => $messages,
    'stream' => true,
], function ($curl_info, $data) use (&$txt) {
    if ($data !== '[DONE]') {
        $json = json_decode($data, true);
        $txt .= $json['choices'][0]['delta']['content'];
    }
});

if ($complete) {
    var_dump($txt);
} else {
    var_dump($open_ai->getError(), $open_ai->getErrno() === CURLE_COULDNT_CONNECT);
}

