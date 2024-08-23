<?php

namespace SwooleAi\OpenAi;

abstract class Client
{
    protected array $headers = [];
    protected array $responseHeaders = [];
    protected  $timeout = 0;
    private  $streamCallback;
    protected string $proxy = "";
    protected  $curlInfo = [];
    protected  $error = '';
    protected  $errno = 0;
    protected  $httpVersion = CURL_HTTP_VERSION_1_1;
    protected  $baseUrl;
    protected  $debug = false;
    protected  $chunkBuffer = '';

    public const CONTENT_TYPE_JSON = 1;
    public const CONTENT_TYPE_FORM_DATA = 2;
    public const CONTENT_TYPE_FORM_URLENCODED = 3;
    private  $streamParser = null;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return array
     * Remove this method from your code before deploying
     */
    public function getCURLInfo()
    {
        return $this->curlInfo;
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
    }

    protected function setContentType(int $type)
    {
        switch ($type) {
            case self::CONTENT_TYPE_JSON:
                $this->withHeader("Content-Type: application/json");
                break;
            case self::CONTENT_TYPE_FORM_DATA:
                $this->withHeader("Content-Type: multipart/form-data");
                break;
            case self::CONTENT_TYPE_FORM_URLENCODED:
                $this->withHeader("Content-Type: application/x-www-form-urlencoded");
                break;
        }
    }

    protected function post(string $url, mixed $data)
    {
        return $this->request($url, 'POST', $data);
    }

    protected function setStreamCallback(callable $cb): void
    {
        $this->streamCallback = $cb;
    }

    protected function setStreamParser(callable $parser): void
    {
        $this->streamParser = $parser;
    }

    /**
     * @param string $proxy
     */
    public function setProxy(string $proxy)
    {
        if ($proxy && strpos($proxy, '://') === false) {
            $proxy = 'https://' . $proxy;
        }
        $this->proxy = $proxy;
    }

    /**
     * @param string $baseUrl
     * @return void
     */
    public function setBaseURL(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, " /\ \t\n\r\0\x0B");
    }

    /**
     * @param array $header
     * @return void
     */
    public function setHeaders(array $header): void
    {
        if ($header) {
            foreach ($header as $key => $value) {
                $this->headers[$key] = $value;
            }
        } else {
            $this->headers = [];
        }
    }

    public function withHeader(string $header)
    {
        $this->headers[] = $header;
        return $this;
    }

    public function clearHeaders(...$headers): void
    {
        foreach ($headers as $header) {
            foreach ($this->headers as $key => $value) {
                if (str_starts_with($value, $header)) {
                    unset($this->headers[$key]);
                }
            }
        }
    }

    /**
     * @param int $version
     */
    public function setHttpVersion(int $version): void
    {
        $this->httpVersion = match ($version) {
            2 => CURL_HTTP_VERSION_2,
            default => CURL_HTTP_VERSION_1_1,
        };
    }

    protected function dumpVars(string $name, mixed $value)
    {
        echo "[$name]\n" . str_repeat('=', 80) . "\n";
        var_dump($value);
    }

    protected function getContentType(): string
    {
        return $this->curlInfo['content_type'] ?? '';
    }

    protected function getStatusCode(): int
    {
        return intval($this->curlInfo['http_code']) ?? 0;
    }

    protected function getHeaders(): array
    {
        return $this->responseHeaders;
    }

    /**
     * @param string $url
     * @param string $method
     * @param mixed $post_fields
     * @param bool $stream
     * @return bool|string
     */
    protected function request(string $url, string $method, mixed $post_fields = null, bool $stream = false)
    {
        $curl_opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => $this->httpVersion,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) {
                if ($pos = strpos($header, ':')) {
                    $key = strtolower(trim(substr($header, 0, $pos)));
                    $value = strtolower(trim(substr(strstr($header, ':'), 1)));
                    $this->responseHeaders[$key] = $value;
                }
                return strlen($header);
            },
        ];

        $this->curlInfo = [];

        if (!empty($post_fields)) {
            $curl_opts[CURLOPT_POSTFIELDS] = $post_fields;
        }

        if (!empty($this->proxy)) {
            $curl_opts[CURLOPT_PROXY] = $this->proxy;
        }

        if ($this->debug) {
            $this->dumpVars('CURL Options', $curl_opts);
            $curl_opts[CURLOPT_VERBOSE] = 1;
        }

        $errorResponse = '';
        if ($stream) {
            /**
             * @var $stream_method callable
             */
            $stream_method = $this->streamCallback;
            $isEventStreamResponse = true;

            $eventStreamParser = function ($curl, $stream_chunk_data) use ($stream_method, &$isEventStreamResponse, &$errorResponse) {
                $len = strlen($stream_chunk_data);
                if ($this->debug) {
                    $this->dumpVars('Stream Chunk', $stream_chunk_data);
                }
                if (empty($this->curlInfo)) {
                    $this->curlInfo = curl_getinfo($curl);
                    if (!str_starts_with($this->getContentType(), 'text/event-stream')) {
                        if ($this->debug) {
                            $this->dumpVars('Stream Error', "ContentType: " . $this->curlInfo['content_type']);
                        }
                        $isEventStreamResponse = false;
                    }
                }
                if (!$isEventStreamResponse) {
                    $errorResponse .= $stream_chunk_data;
                    return $len;
                }

                if ($this->chunkBuffer) {
                    $stream_chunk_data = $this->chunkBuffer . $stream_chunk_data;
                    $this->chunkBuffer = '';
                }
                // 兼容处理，有些服务器使用 "\r\n\r\n" 作为结束符
                $stream_chunk_data = str_replace("\r\n\r\n", "\n\n", $stream_chunk_data);
                $list = explode("\n\n", $stream_chunk_data);
                if (count($list) === 1) {
                    $this->chunkBuffer = $stream_chunk_data;
                    return $len;
                }

                // 未结束的 event-stream message
                $endBlock = $list[count($list) - 1];
                if (!empty($endBlock)) {
                    $this->chunkBuffer = $endBlock;
                }
                // 去掉最后一个元素
                $list = array_slice($list, 0, count($list) - 1);
                if ($this->debug) {
                    $this->dumpVars('Message List', $list);
                }
                foreach ($list as $msg) {
                    $fields = explode("\n", $msg);
                    $info = [];
                    foreach ($fields as $_tmp) {
                        [$k, $v] = explode(':', $_tmp, 2);
                        if (empty($k)) {
                            $k = '_';
                        }
                        $info[$k] = trim($v);
                    }
                    if ($this->debug) {
                        $this->dumpVars('Message Info', $info);
                    }
                    if (!isset($info['data'])) {
                        continue;
                    }
                    $argv = count($info) > 1 ? $info : $info['data'];
                    if ($stream_method($curl, $argv) === false) {
                        if ($this->debug) {
                            echo "stream callback return 0";
                        }
                        return 0;
                    }
                }
                return $len;
            };

            $curl_opts[CURLOPT_WRITEFUNCTION] = $this->streamParser ?: $eventStreamParser;
        }

        $curl = curl_init();

        curl_setopt_array($curl, $curl_opts);
        $response = curl_exec($curl);

        if (empty($response)) {
            $this->error = curl_error($curl);
            $this->errno = curl_errno($curl);
        } else {
            $this->error = '';
            $this->errno = 0;
        }

        if (empty($this->curlInfo)) {
            $this->curlInfo = curl_getinfo($curl);
        }

        if ($errorResponse and $stream) {
            if (is_array($errorResponse)) {
                $errorResponse = json_encode($errorResponse);
            }
            ($this->streamCallback)($curl, $errorResponse);
        }

        curl_close($curl);
        return $response;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getErrno(): int
    {
        return $this->errno;
    }
}
