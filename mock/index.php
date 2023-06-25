<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$text = 'ChatGPT 是一个自然语言处理模型，它是由 OpenAI 发布的。数据集是从多个来源收集而来，包括维基百科、网上论坛、新闻文章、电子书等等。OpenAI 使用了一个基于变压器的深度学习模型进行训练，称为 GPT(Generative Pre-trained Transformer)，这个模型使用了大量的无监督学习方法，可以在大规模语料库上自动学习语言模式和语义信息。为了保护用户隐私和保密性，ChatGPT 中可能使用的真实数据经过了去除敏感信息和脱敏处理。';

$n = mb_strlen($text);
ob_start();

for ($i = 0; $i < $n; $i++) {
    $msg = '{"id":"chatcmpl-' . base64_encode(random_bytes(20)) . '",' .
        '"object":"chat.completion.chunk","created":' . time() .
        ',"model":"gpt-3.5-turbo","choices":[{"index":' . 0 .
        ',"delta":{"role":"assistant","content":"' . mb_substr($text, $i, 1) . '"},"finish_reason":' .
        (($i === ($n - 1)) ? '"stop"' : 'null')
        . '}]}';

    echo 'data: ' . $msg . PHP_EOL;
    echo PHP_EOL;
    ob_flush();
    usleep(random_int(10000, 100000));
}

echo 'data: [DONE]' . PHP_EOL;
echo PHP_EOL;
