# OpenAI API Client in PHP

为何 `fork` 此库而不是向原项目发起 `PR` ，主要是是作者的响应时间太长，一个非常简单的 `PR` 经常 `1-2` 周的时间没有任何回复。
我们需要快速验证迭代，后续会抽时间提交给 `orhanerday/open-ai`

## 改进
对 `orhanerday/open-ai` 库做了一些改进，包括如下内容：

1. 支持了获取 `curl` 底层错误码，在出现网络底层的问题后可以根据错误信息排查问题
2. 支持设置 `HTTP` 版本，使用方法 `$openai->setHttpVersion(2)` ，通过设置 `HTTP2` 协议可以绕过 Nginx 的 `proxy cache`
3. 改进了 `setBaseUrl()` 方法，原库是硬编码写死了 `api.openai.com`，发送请求前通过字符串替换设置 `BaseUrl`，不是很优雅
4. 改进了 `chat stream` 的实现，原库直接使用了 `WRITE_FUNCTION`，应用层需要分割 `Chunks`并手工解析 `Event-Stream` 消息

## 使用
```shell
composer require swoole-inc/open-ai
```

## 实例

```php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use SwooleAi\OpenAi\OpenAi;

$open_ai = new OpenAi('test');
$open_ai->setBaseURL('https://chat.swoole.com/');
$messages[] = ["role" => "system", "content" => "You are a helpful assistant."];
$messages[] = ["role" => "user", "content" => "Who won the world series in 2020?"];
$messages[] = ["role" => "assistant", "content" => "The Los Angeles Dodgers won the World Series in 2020."];
$messages[] = ["role" => "user", "content" => "Where was it played?"];
$complete = $open_ai->chat([
    'model' => 'gpt-3.5-turbo',
    'messages' => $messages
], function ($curl_info, $data) use (&$txt) {
    if ($data !== '[DONE]') {
        $json = json_decode($data, true);
        $txt .= $json['choices'][0]['delta']['content'];
    }
});
var_dump($complete);

if ($complete) {
    var_dump($txt);
} else {
    var_dump($open_ai->getError(), $open_ai->getErrno() === CURLE_COULDNT_CONNECT);
}
```

## 代理
在中国无法直接访问 `OpenAI` 服务器，可以设置代理。

```php
$open_ai = new OpenAi('test');
$open_ai->setProxy('socks5h://127.0.0.1:1080');
```

- `socks5h://`：`socks5` 代理并且在对端进行 `DNS` 解析（推荐使用）
- `socks5://`：`socks5` 代理并且在本地进行 `DNS` 解析
- `http://`：`HTTP` 代理
