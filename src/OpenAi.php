<?php

namespace SwooleAi\OpenAi;

use Exception;
use RuntimeException;

class OpenAi extends Client
{
    private  $model = "text-davinci-002";
    private  $chatModel = "gpt-3.5-turbo";
    private  $apiType = 'openai';
    private  $apiVersion = 'v1';
    private  $apiKey;
    private array $apiParams = [];
    private const MAX_AUDIO_SPEED = 4;
    private const MIN_AUDIO_SPEED = 0.25;
    public const MSG_DONE = '[DONE]';

    public function __construct($OPENAI_API_KEY)
    {
        parent::__construct('https://api.openai.com');
        if (empty($OPENAI_API_KEY)) {
            throw new RuntimeException('OPENAI_API_KEY must be not empty');
        }
        $this->setApiKey($OPENAI_API_KEY);
    }

    protected function setApiKey(string $api_key): void
    {
        $this->apiKey = $api_key;
    }

    public function setApiVersion(string $version): void
    {
        $this->apiVersion = $version;
    }

    public function setApiType(string $type, array $params = []): void
    {
        $this->apiType = $type;
        $this->apiParams = $params;
    }

    protected function sendRequest(string $url, string $method, array $opts = []): bool|string
    {
        if (array_key_exists('file', $opts) || array_key_exists('image', $opts)) {
            $this->setContentType(self::CONTENT_TYPE_FORM_DATA);
            $post_fields = $opts;
        } else {
            $this->setContentType(self::CONTENT_TYPE_JSON);
            $post_fields = json_encode($opts);
        }
        if ($this->apiType === 'azure') {
            $this->withHeader('api-key: ' . $this->apiKey);
        } else {
            $this->withHeader("Authorization: Bearer {$this->apiKey}");
            if ($this->apiType === 'Cloudflare-AI-Gateway') {
                $url = $this->apiParams['base-url'] . '/' . substr($url, strlen($this->baseUrl . '/v1/'));
            }
        }
        $eventStream = array_key_exists('stream', $opts) && $opts['stream'];
        return $this->request($url, $method, $post_fields, $eventStream);
    }

    protected function getApiUrl(string $api): string
    {
        $parts = [
            rtrim($this->baseUrl, '/'),
            rtrim($this->apiVersion, '/'),
            ltrim($api, '/'),
        ];
        return implode('/', $parts);
    }

    /**
     * @return bool|string
     */
    public function listModels(): bool|string
    {
        return $this->sendRequest($this->getApiUrl('models'), 'GET');
    }

    /**
     * @param $model
     * @return bool|string
     */
    public function retrieveModel($model): bool|string
    {
        return $this->sendRequest($this->getApiUrl('models/' . $model), 'GET');
    }

    /**
     * @param array $opts
     * @param callable|null $stream
     * @return bool|string
     * @throws Exception
     */
    public function completion(array $opts, ?callable $stream = null): bool|string
    {
        if ($stream != null and array_key_exists('stream', $opts)) {
            if (!$opts['stream']) {
                throw new Exception(
                    'Please provide a stream function. Check https://github.com/orhanerday/open-ai#stream-example for an example.'
                );
            }
            $this->setStreamCallback($stream);
        }

        $opts['model'] = $opts['model'] ?? $this->model;

        return $this->sendRequest($this->getApiUrl('completions'), 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function image($opts): bool|string
    {
        return $this->sendRequest($this->getApiUrl('images/generations'), 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function imageEdit($opts)
    {
        return $this->sendRequest($this->getApiUrl('images/edits'), 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function createImageVariation($opts)
    {
        return $this->sendRequest($this->getApiUrl('images/variations'), 'POST', $opts);
    }

    /**
     * @param array $opts
     * @param callable|null $streamFn
     * @return bool|string
     * @throws Exception
     */
    public function chat(array $opts, callable $streamFn = null)
    {
        if ($streamFn != null && array_key_exists('stream', $opts)) {
            if (!$opts['stream']) {
                throw new Exception(
                    'Please provide a stream function.'
                );
            }
            $this->setStreamCallback(function ($curl, $data) use ($streamFn) {
                return $streamFn($curl, $data === self::MSG_DONE ? $data : json_decode($data, true));
            });
        }

        if ($this->apiType == 'azure') {
            $url = $this->baseUrl . '/openai/deployments/' . $this->apiParams['deployment-id'] .
                '/chat/completions?api-version=' . urlencode($this->apiParams['api-version']);
        } else {
            $url = $this->getApiUrl('chat/completions');
            $opts['model'] = $opts['model'] ?? $this->chatModel;
        }

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function speech($opts)
    {
        $url = $this->getApiUrl('audio/speech');
        if (isset($opts['speed'])) {
            if ($opts['speed'] > self::MAX_AUDIO_SPEED && $opts['speed'] < self::MIN_AUDIO_SPEED) {
                throw new RuntimeException('speed error', -1);
            }
        }
        $res = $this->sendRequest($url, 'POST', $opts);
        if (str_starts_with($this->curlInfo['content_type'], 'application/json')) {
            $errorArray = json_decode($res, true);
            throw new RuntimeException($errorArray['error']['message']);
        }
        return $res;
    }

    /**
     * @param $opts
     * @return array
     */
    public function transcribe($opts)
    {
        $url = $this->getApiUrl('audio/transcriptions');
        $resp = $this->sendRequest($url, 'POST', $opts);
        if ($resp and str_starts_with($this->curlInfo['content_type'], 'application/json')) {
            return json_decode($resp, true);
        }
        throw new RuntimeException($resp['error']['message']);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function translate($opts)
    {
        return $this->sendRequest($this->getApiUrl('audio/translations'), 'POST', $opts);
    }

    /**
     * @param string $file
     * @param string $purpose
     * @param string $filename
     * @param string $mimetype
     * @return bool|string
     */
    public function uploadFile(string $file, string $purpose, string $filename = '', string $mimetype = '')
    {
        $filename = empty($filename) ? basename($file) : $filename;
        $opts = [
            'file' => new \CURLFile($file, $mimetype, $filename),
            'purpose' => $purpose,
        ];
        $this->withHeader('Expect:');
        $this->setDebug(false);
        return $this->sendRequest($this->getApiUrl('files'), 'POST', $opts);
    }

    /**
     * @param string $purpose
     * @return bool|string
     */
    public function listFiles(string $purpose = ''): bool|string
    {
        $url = $this->getApiUrl('files');
        if ($purpose) {
            $url .= "?purpose=$purpose";
        }
        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $file_id
     * @return bool|string
     */
    public function retrieveFile($file_id): bool|string
    {
        $url = $this->getApiUrl("files/$file_id");
        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $file_id
     * @return bool|string
     */
    public function retrieveFileContent($file_id): bool|string
    {
        $url = $this->getApiUrl("files/$file_id/content");
        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $file_id
     * @return bool|string
     */
    public function deleteFile($file_id): bool|string
    {
        $url = $this->getApiUrl("files/$file_id");
        return $this->sendRequest($url, 'DELETE');
    }


    /**
     * @param $opts
     * @return bool|string
     */
    public function embeddings($opts): bool|string
    {
        return $this->sendRequest($this->getApiUrl('embeddings'), 'POST', $opts);
    }
}
