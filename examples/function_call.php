<?php

use SwooleAi\OpenAi\FunctionCall;
use SwooleAi\OpenAi\FunctionDef;
use SwooleAi\OpenAi\FunctionParameter;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

$tools = FunctionCall::create()->add(
    FunctionDef::create('turn_on_air_conditioning')
        ->withDescription('Turn on or off the air conditioning, set the mode and temperature')
        ->withParameter('on', FunctionParameter::create('boolean')->withDescription('turn on or off'))
        ->withParameter('mode', FunctionParameter::create('string')->withEnum('cooling', 'heating'))
        ->withParameter('temperature', FunctionParameter::create('number')->withDescription("Set the air temperature in degrees Celsius, for example 26"))
        ->withRequired('on', 'mode')
)->toArray();

$open_ai = new SwooleAi\OpenAi\OpenAi(getenv('OPENAI_API_KEY'));
$open_ai->setBaseURL('http://127.0.0.1:3080');

$messages[] = ["role" => "system", "content" => "You are a helpful assistant. "];
$messages[] = ["role" => "user", "content" => "今天的天气比较热，38摄氏度，请帮我调整空调设置"];

$opts = [
    'messages' => $messages,
    'model' => 'gpt-3.5-azure',
    'temperature' => 1.0,
    "tools" => $tools,
];

$complete = $open_ai->chat($opts);

if ($complete) {
    var_dump(json_decode($complete,));
} else {
    var_dump($open_ai->getError(), $open_ai->getErrno());
}

