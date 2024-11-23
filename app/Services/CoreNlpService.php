<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class CoreNlpService
{
    protected Client $client;
    protected string $coreNlpUrl;
    protected int $coreNlpPort;
    protected int $timeout;

    /**
     * Initialize the CoreNLP service.
     */
    public function __construct()
    {
        $this->coreNlpUrl = config('coreNLP.core_nlp_server.url', 'http://corenlp');
        $this->coreNlpPort = (int) config('coreNLP.core_nlp_server.port', 9000);
        $this->timeout = (int) config('coreNLP.request_timeout', 30);

        $this->client = new Client([
            'base_uri' => "{$this->coreNlpUrl}:{$this->coreNlpPort}",
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Send the query to the CoreNLP server and get analysis.
     *
     * @param string $text
     * @param array $properties
     * @return array
     */
    public function sendRequest(string $text, array $properties): array
    {
        try {
            $response = $this->client->post('annotate', [
                'query' => [
                    'properties' => json_encode(array_merge($properties, [
                        'outputFormat' => 'json',
                        'pipelineLanguage' => 'en'
                    ])),
                ],
                'body' => $text,
                'headers' => [
                    'Content-Type' => 'text/plain'
                ]
            ]);

            // Check for successful response
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            } else {
                Log::error("CoreNLP request failed with status code: " . $response->getStatusCode());
                return [];
            }
        } catch (GuzzleException $e) {
            Log::error('CoreNLP request failed', ['error' => $e->getMessage(), 'request' => $text, 'properties' => $properties]);
            return [];
        }
    }

    /**
     * Analyze the sentiment of the given text.
     *
     * @param string $text
     * @return string|null
     */
    public function analyzeSentiment(string $text): ?string
    {
        $properties = ['annotators' => 'sentiment'];
        $response = $this->sendRequest($text, $properties);

        // Check if response is valid and contains the sentiment
        return $response['sentences'][0]['sentiment'] ?? null;
    }

    /**
     * Recognize entities in the given text.
     *
     * @param string $text
     * @return array
     */
    public function recognizeEntities(string $text): array
    {
        $properties = ['annotators' => 'ner'];
        $response = $this->sendRequest($text, $properties);

        $entities = [];
        foreach ($response['sentences'] ?? [] as $sentence) {
            foreach ($sentence['tokens'] ?? [] as $token) {
                if (isset($token['ner']) && $token['ner'] !== 'O') {
                    $entities[] = [
                        'text' => $token['word'],
                        'type' => $token['ner']
                    ];
                }
            }
        }

        return $entities;
    }

    /**
     * Detect the intent of the given text.
     *
     * @param string $text
     * @return string
     */
    public function detectIntent(string $text): string
    {
        // This method can be expanded in the future with additional logic
        return 'unknown';
    }

    /**
     * Extract key phrases from the given text.
     *
     * @param string $text
     * @return array
     */
    public function extractKeyPhrases(string $text): array
    {
        $properties = ['annotators' => 'pos'];
        $response = $this->sendRequest($text, $properties);

        $keyPhrases = [];
        foreach ($response['sentences'] ?? [] as $sentence) {
            $phrase = '';
            foreach ($sentence['tokens'] ?? [] as $token) {
                if (in_array($token['pos'], ['NN', 'NNS', 'JJ'])) {
                    $phrase .= $token['word'] . ' ';
                } elseif ($phrase) {
                    $keyPhrases[] = trim($phrase);
                    $phrase = '';
                }
            }
            if ($phrase) {
                $keyPhrases[] = trim($phrase);
            }
        }

        return $keyPhrases;
    }

    /**
     * Analyze the given text for sentiment, entities, intent, and key phrases.
     *
     * @param string $text
     * @return array
     */
    public function analyzeText(string $text): array
    {
        return [
            'sentiment' => $this->analyzeSentiment($text),
            'entities' => $this->recognizeEntities($text),
            'intent' => $this->detectIntent($text),
            'key_phrases' => $this->extractKeyPhrases($text),
        ];
    }

    /**
     * Retry the request if the connection fails due to server unavailability.
     *
     * @param string $text
     * @param array $properties
     * @param int $retries
     * @return array
     */
    public function sendRequestWithRetry(string $text, array $properties, int $retries = 3): array
    {
        $attempt = 0;
        $response = [];

        while ($attempt < $retries) {
            $response = $this->sendRequest($text, $properties);
            if (!empty($response)) {
                return $response;
            }

            Log::warning("CoreNLP request failed, retrying... Attempt {$attempt}.");
            $attempt++;
            sleep(2); // Sleep for 2 seconds before retrying
        }

        Log::error("CoreNLP request failed after {$retries} attempts.");
        return [];
    }
}
