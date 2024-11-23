<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class BookService
{
    protected array $fields;
    protected array $books;
    protected CoreNlpService $coreNlpService;

    /**
     * Initialize the BookService.
     *
     * @param CoreNlpService $coreNlpService
     * @throws Exception
     */
    public function __construct(CoreNlpService $coreNlpService)
    {
        // Path to books.json using base_path
        $jsonContent = base_path('database/data/books.json');
        $data = json_decode(file_get_contents($jsonContent), true);
        if ($data === null) {
            throw new Exception('Error decoding books.json: ' . json_last_error_msg());
        }
        $this->fields = $data['fields'] ?? [];
        $this->books = $data['books'] ?? [];
        $this->coreNlpService = $coreNlpService;
    }

    /**
     * Detect intent of the query: 'find', 'count', or 'general_info'.
     *
     * @param string $query
     * @return string
     */
    public function detectBookIntent(string $query): string
    {
        $queryLower = strtolower(trim($query));

        // Check for "general information" queries
        if (preg_match('/\b(which books do you have|what books are available|tell me about your books)\b/', $queryLower)) {
            return 'general_info';
        }

        // Check for counting intent
        if (preg_match('/\bhow many\b|\bcount\b/', $queryLower)) {
            return 'count';
        }

        // Check for finding intent
        if (preg_match('/\blist\b|\bshow\b|\bfind\b|\bwhat are\b|\bwhich\b/', $queryLower)) {
            return 'find';
        }

        // Default to 'general_info' if no specific keywords are detected
        return 'find';
    }

    /**
     * Apply filters to the dataset of books.
     *
     * @param array $books
     * @param array $filters
     * @return array Filtered books
     */
    private function applyFilters(array $books, array $filters): array
    {
        return array_filter($books, function ($book) use ($filters) {
            $bookNormalized = array_change_key_case($book, CASE_LOWER);

            foreach ($filters as $key => $value) {
                $key = strtolower($key); // Normalize key
                if (!isset($bookNormalized[$key])) {
                    return false;
                }

                $bookValue = $bookNormalized[$key];

                // Handle numeric and exact match filters
                if (is_array($value)) {
                    foreach ($value as $operator => $filterValue) {
                        if (is_numeric($bookValue)) {
                            $bookValue = (int) $bookValue;
                            $filterValue = (int) $filterValue;
                            switch ($operator) {
                                case '<':
                                    if ($bookValue >= $filterValue) return false;
                                    break;
                                case '>':
                                    if ($bookValue <= $filterValue) return false;
                                    break;
                                case '=':
                                    if ($bookValue != $filterValue) return false;
                                    break;
                                case '<=':
                                    if ($bookValue > $filterValue) return false;
                                    break;
                                case '>=':
                                    if ($bookValue < $filterValue) return false;
                                    break;
                                default:
                                    return false;
                            }
                        } else {
                            return false; // Non-numeric field used for numeric comparison
                        }
                    }
                } else {
                    // Handle exact string match
                    if (!is_string($bookValue) || !is_string($value)) {
                        return false; // Ensure both are strings for comparison
                    }
                    if (strtolower($bookValue) !== strtolower($value)) {
                        return false;
                    }
                }
            }

            return true; // Book passed all filters
        });
    }

    /**
     * Find books matching the query.
     *
     * @param string $query
     * @param bool $saveToHistory
     * @return string
     */
    public function findBooks(string $query, bool $saveToHistory = true): string
    {
        try {
            // Normalize query for consistency
            $queryLower = strtolower($query);
            $filters = $this->extractFiltersFromQuery($queryLower);
            Log::debug('Extracted filters:', $filters);

            // Apply filters using the reusable method
            $filteredBooks = $this->applyFilters($this->books, $filters);

            // Log filtered books for debugging
            Log::debug('Books matching filters:', $filteredBooks);

            // Prepare response
            $response = $filteredBooks
                ? $this->formatBookResponse(array_slice($filteredBooks, 0, 5))
                : 'No books found matching your criteria.';

            // If history is not to be saved, log and return directly
            if (!$saveToHistory) {
                Log::info("Response for query (not saved to history): {$response}");
                return json_encode(['text' => $response, 'saveToHistory' => false]);
            }

            return json_encode(['text' => $response, 'saveToHistory' => true]);
        } catch (Exception $e) {
            Log::error('Error searching for books: ' . $e->getMessage());
            return 'An error occurred while searching for books.';
        }
    }

    /**
     * Count books based on the query criteria.
     *
     * @param string $query
     * @return string
     */
    public function countBooks(string $query): string
    {
        try {
            // Extract filters using NLP-based analysis
            $filters = $this->extractFiltersFromQuery($query);
            Log::debug('Extracted filters for counting:', $filters);

            // Apply filters using the reusable method
            $filteredBooks = $this->applyFilters($this->books, $filters);

            // Return the count result
            $count = count($filteredBooks);
            return $count > 0
                ? "There are $count books matching your criteria."
                : 'No books found matching your criteria.';
        } catch (Exception $e) {
            Log::error('Error counting books: ' . $e->getMessage());
            return 'An error occurred while counting books.';
        }
    }

    /**
     * Extract filters from the query string based on searchable fields in books.json.
     *
     * @param string $query
     * @return array
     */
    private function extractFiltersFromQuery(string $query): array
    {
        $filters = [];
        $text_analysis = $this->coreNlpService->analyzeText($query);
        Log::debug('Text analysis output:', $text_analysis);

        foreach ($text_analysis['entities'] as $entity) {
            if (isset($entity['text'])) {
                $text = strtolower($entity['text']);
                $number = (int) $text;

                // Handle Author
                if (strtolower($entity['type']) === 'person') {
                    $filters['Author'] = ucfirst($text); // Match dataset case
                }

                // Handle Title
                if (strtolower($entity['type']) === 'work_of_art') {
                    $filters['Title'] = ucfirst($text); // Match dataset case
                }


                // Handle Language
                if (strtolower($entity['type']) === 'language' || strtolower($entity['type']) === 'nationality') {
                    $filters['Language'] = ucfirst($text); // e.g., "Italian"
                }

                // Handle Country
                if (strtolower($entity['type']) === 'location') {
                    $filters['Country'] = ucfirst($text); // e.g., "United States"
                }

                // Handle "oldest" or "old" logic for Year (including BC years)
                if (str_contains($query, 'oldest') || str_contains($query, 'old')) {
                    $filters['Year']['<'] = 0;  // This captures all books before year 0 (BC books)
                }

                // Handle Year (e.g., "before 1950" or "approximately 1950")
                if (strtolower($entity['type']) === 'date') {
                    if (str_contains($query, 'before') || str_contains($query, 'older than')) {
                        $filters['Year']['<'] = $number;
                    } elseif (str_contains($query, 'after') || str_contains($query, 'newer than')) {
                        $filters['Year']['>'] = $number;
                    } else {
                        $filters['Year']['='] = $number; // Default to exact match
                    }
                }


            }
        }

        // Handle Pages (e.g., "under 300 pages", "more than 200 pages")
        if (preg_match('/under\s(\d+)\s*pages/i', $query, $matches)) {
            $filters['Pages']['<'] = (int) $matches[1];
        } elseif (preg_match('/more than\s(\d+)\s*pages/i', $query, $matches)) {
            $filters['Pages']['>'] = (int) $matches[1];
        } elseif (preg_match('/approximately\s(\d+)\s*pages/i', $query, $matches)) {
            $filters['Pages']['='] = (int) $matches[1];
        }

        // Check for explicit patterns (e.g., "books by Dante Alighieri")
        if (preg_match('/books\s+by\s+([a-zA-Z\s]+)/i', $query, $matches)) {
            $filters['Author'] = trim($matches[1]);
        }

        // Debug the extracted filters
        Log::debug('Extracted filters:', $filters);
        return $filters;
    }

    /**
     * Format the book response.
     *
     * @param array $books
     * @return string
     */
    private function formatBookResponse(array $books): string
    {
        if (empty($books)) {
            return 'No books found matching your criteria.';
        }

        // Format book details with fields dynamically
        $response = "Here are some books that match your criteria:\n";
        $response .= implode("\n", array_map(function ($book) {
            $details = array_map(function ($field) use ($book) {
                return isset($book[$field]) ? ucfirst($field) . ': ' . $book[$field] : '';
            }, array_keys($this->fields));

            return implode(', ', array_filter($details));  // Only include non-empty details
        }, $books));

        return $response;
    }
}
