<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatBotService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected int $timeout;
    protected string $chatBotInstructions = '';
    protected array $functionSchema = [];
    protected array $functionConfig = [];
    protected BookService $bookService;

    public function __construct(BookService $bookService)
    {
        $this->apiUrl = config('gemini.base_url');
        $this->apiKey = config('gemini.api_key');
        $this->timeout = (int) config('gemini.request_timeout', 30);
        $this->functionSchema = config('chatBot.function_schema');
        $this->functionConfig = config('chatBot.function_config');
        $this->chatBotInstructions = config('chatBot.instructions');
        $this->bookService = $bookService;
    }

    /**
     * Process the user query and delegate generation to Gemini.
     *
     * @param string $query
     * @param int $userId
     * @return string
     * @throws ConnectionException
     */
    public function processQuery(string $query, int $userId): string
    {
        Log::debug('User query: ' . $query);

        // Reset check - avoid further processing if a reset occurred
        if (session('conversation_reset', false)) {
            Log::debug('Skipping processing due to active conversation reset.');
            session()->forget('conversation_reset'); // Clear reset flag
            cache()->flush();  // Clear cache at the start
            return "Conversation history has been reset.";
        }

        // Retrieve conversation history
        $filePath = "conversations/{$userId}_conversation.json";
        $conversationHistory = $this->getConversationHistory($filePath);

        // Append the user's query to the conversation history
        $conversationHistory[] = [
            'role' => 'user',
            'parts' => [['text' => $query]],
        ];

        Log::debug('Updated Conversation History (after user input): ' . json_encode($conversationHistory));

        // Proceed with the Gemini response
        $geminiResponse = $this->getGeminiResponse($conversationHistory);

        // Decode Gemini's response to check metadata (e.g., saveToHistory flag)
        $responseParts = json_decode($geminiResponse, true);
        Log::debug('Decoded Gemini response: ' . json_encode($responseParts));
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Gemini response is not valid JSON: ' . $geminiResponse);
            // Fallback to basic response if JSON decoding fails
            $responseParts = ['text' => $geminiResponse, 'saveToHistory' => true];
        }

        // Extract response text and saveToHistory flag
        $responseText = $responseParts['text'] ?? $geminiResponse;
        $saveToHistory = $responseParts['saveToHistory'] ?? true; // Default to true if not explicitly provided

        Log::debug("Gemini response: Text = '{$responseText}', Save to History = " . ($saveToHistory ? 'true' : 'false'));

        // Conditionally append Gemini's response to the conversation history
        if ($saveToHistory) {
            $conversationHistory[] = [
                'role' => 'model',
                'parts' => [['text' => $responseText]],
            ];
            Log::debug('Updated Conversation History (after model response): ' . json_encode($conversationHistory));

            // Save updated conversation history
            $this->saveConversationHistory($filePath, $conversationHistory);
        } else {
            Log::debug('Skipping saving conversation history for this response.');
        }

        return $responseText;
    }

    /**
     * Process the query using CoreNLP to refine and extract key information (e.g., book title, author).
     *
     * @param array $conversationHistory
     * @return array
     */
    private function prepareGeminiRequest(array $conversationHistory): array
    {
        return [
            'contents' => $conversationHistory,
            'tools' => ['function_declarations' => $this->functionSchema],
            'tool_config' => $this->functionConfig,
        ];
    }

    /**
     * Communicate with Gemini API and handle function invocation if applicable.
     *
     * @param array $conversationHistory
     * @return string
     * @throws ConnectionException
     */
    private function getGeminiResponse(array $conversationHistory): string
    {
        $payload = $this->prepareGeminiRequest($conversationHistory);
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout($this->timeout)
            ->post("{$this->apiUrl}?key={$this->apiKey}", $payload);

        if ($response->successful()) {
            $responseData = $response->json();
            Log::debug('Gemini API response: ' . json_encode($responseData, JSON_PRETTY_PRINT));

            $candidates = $responseData['candidates'] ?? [];
            if (empty($candidates)) {
                return 'Sorry, I could not process your request.';
            }

            $contentParts = $candidates[0]['content']['parts'][0] ?? [];
            if (isset($contentParts['functionCall'])) {
                $functionCall = $contentParts['functionCall'];
                $query = $functionCall['args']['query'] ?? '';
                $saveToHistory = $functionCall['args']['saveToHistory'] ?? true; // Default to true

                // Handle reset conversation function
                if ($functionCall['name'] === 'resetConversation') {
                    return $this->resetConversation(auth()->id(), $query, $saveToHistory = false);
                }

                // Detect intent based on query keywords
                $intent = $this->bookService->detectBookIntent($query);

                if ($intent === 'find' && $functionCall['name'] === 'findBooks') {
                    // passing the bookService to the findBooks method to not save history
                    return $this->bookService->findBooks($query, false);
                } elseif ($intent === 'count') {
                    return $this->bookService->countBooks($query);
                } elseif ($intent === 'general_info') {
                    // Provide a generic response without calling findBooks
                    /*return "I have a dataset of books covering a variety of authors, languages, and years. You can ask me to list books by specific criteria, such as by author, title, or year.";*/
                    return $contentParts['text'] ?? 'I have a dataset of books covering a variety of authors, languages, and years. You can ask me to list books by specific criteria, such as by author, title, or year.';
                } else {
                    return 'Sorry, I could not process your request.';
                }
            }

            return $contentParts['text'] ?? 'Sorry, I could not process your request.';
        }

        Log::error('Gemini API error: ' . $response->status() . ' - ' . $response->body());
        return 'An error occurred while processing your query.';
    }

    /**
     * Retrieve conversation history from a JSON file.
     *
     * @param string $filePath
     * @return array
     */
    protected function getConversationHistory(string $filePath): array
    {
        $cacheKey = "conversation_history:{$filePath}";

        // If the reset flag is active, skip cache
        if (session('conversation_reset', false)) {
            Log::debug('Skipping cache due to conversation reset.');
            // Clear the cache explicitly when reset is triggered
            cache()->forget($cacheKey);
            // Reset the session flag to avoid this condition in future requests
            session()->forget('conversation_reset');
            return [['role' => 'model', 'parts' => [['text' => $this->chatBotInstructions]]]];
        }

        // Otherwise, retrieve from cache or file
        $cachedHistory = cache()->get($cacheKey);
        if ($cachedHistory) {
            Log::debug("Cache hit for conversation history.");
            return $cachedHistory;
        }

        return cache()->remember($cacheKey, 3600, function () use ($filePath) {
            if (Storage::exists($filePath)) {
                return json_decode(Storage::get($filePath), true);
            }
            return [['role' => 'model', 'parts' => [['text' => $this->chatBotInstructions]]]];
        });
    }

    /**
     * Save conversation history to a JSON file.
     *
     * @param string $filePath
     * @param array $conversationHistory
     * @return void
     */
    protected function saveConversationHistory(string $filePath, array $conversationHistory): void
    {
        $cacheKey = "conversation_history:{$filePath}";

        // Clear the cache before saving
        Log::debug("Clearing cache for key: {$cacheKey}");
        cache()->forget($cacheKey);
        // cache()->flush(); // Clear all cache for now

        // Debug log the history before saving
        Log::debug("Saving conversation history: " . json_encode($conversationHistory));

        // Save to file
        Storage::put($filePath, json_encode($conversationHistory, JSON_PRETTY_PRINT));
    }

    /**
     * Reset the conversation by deleting the conversation file and clearing cache.
     *
     * @param int $userId
     * @param string $query
     * @param bool $saveToHistory
     * @return string
     */
    protected function resetConversation(int $userId, string $query, bool $saveToHistory = true): string
    {
        Log::debug("Resetting conversation for user {$userId} with query: {$query}");

        $filePath = "conversations/{$userId}_conversation.json";
        $cacheKey = "conversation_history:{$filePath}";

        try {
            // Check if the file exists
            if (Storage::exists($filePath)) {
                Log::debug("File exists: {$filePath}");
                if (Storage::delete($filePath)) {
                    Log::info("File successfully deleted: {$filePath}");
                } else {
                    Log::warning("Failed to delete file: {$filePath}");
                }
            } else {
                Log::debug("File does not exist: {$filePath}");
            }

            // Clear the cache related to this conversation
            if (cache()->has($cacheKey)) {
                cache()->forget($cacheKey); // Clear the specific cache key
                Log::debug("Cache cleared for key: {$cacheKey}");
            }

            // Optional: flush the entire cache to ensure everything is reset (use with caution)
            // cache()->flush();  // You can use this if there are issues with specific cache keys persisting

            // Reset session flag to indicate conversation reset
            session(['conversation_reset' => true]);
            Log::debug("Session flag set for conversation reset.");

            // Return the response indicating reset was successful
            $response = "Conversation history has been reset.";
            Log::info("Response for query (history reset): {$response}");

            return json_encode(['text' => $response, 'saveToHistory' => false]);

        } catch (Exception $e) {
            Log::error("Error resetting conversation: " . $e->getMessage());
            return json_encode(['text' => 'An error occurred while resetting the conversation history.', 'error' => true]);
        }
    }

    /**
     * Move the conversation history to the trash folder.
     *
     * @param int $userId
     * @return void
     */
    public function trashConversation(int $userId): void
    {
        $filePath = "conversations/{$userId}_conversation.json";
        if (Storage::exists($filePath)) {
            $timestamp = now()->format('Y-m-d_H-i-s');
            Storage::move($filePath, "trash/{$userId}_conversation_{$timestamp}.json");
        }
    }
}
