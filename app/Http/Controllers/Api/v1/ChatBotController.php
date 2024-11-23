<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ChatBotService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatBotController extends Controller
{
    protected ChatBotService $chatBotService;

    public function __construct(ChatBotService $chatBotService)
    {
        $this->chatBotService = $chatBotService;
    }

    /**
     * Handle the incoming chat query.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleQuery(Request $request): JsonResponse
    {
        try {
            $query = $request->input('query'); // Query from user
            $response = $this->chatBotService->processQuery($query, auth()->id());

            return response()->json([
                'response' => [
                    'text' => $response,
                    'source' => 'Laravel LLM ChatBot',
                    'timestamp' => now(),
                ],
            ]);
        } catch (ConnectionException $e) {
            return response()->json([
                'error' => 'Failed to connect to the chat service.',
                'details' => $e->getMessage(),
            ], 503); // Service Unavailable
        } catch (GuzzleException $e) {
            return response()->json([
                'error' => 'An error occurred while communicating with the chat service.',
                'details' => $e->getMessage(),
            ], 500); // Internal Server Error
        } catch (\Exception $e) {
            // Catch unexpected exceptions to prevent unhandled errors
            return response()->json([
                'error' => 'An unexpected error occurred.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
