<?php

declare(strict_types=1);

return [

    'function_schema' => [
        [
            'name' => 'findBooks',
            'description' => 'Search for books in the dataset based on criteria such as language, year, title, author, or specific queries like "oldest books" or "books under 300 pages".',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search criteria. Examples: "list all Italian books", "list books by Giacomo Leopardi", "oldest books", "books under 300 pages", or "books written in 1958".'
                    ],
                    'saveToHistory' => [
                        'type' => 'boolean',
                        'description' => 'Whether to save the result to conversation history. Default is true.',
                    ],
                ],
                'required' => ['query', 'saveToHistory']
            ],
        ],
        [
            'name' => 'countBooks',
            'description' => 'Count books in the dataset based on specific criteria, such as language, year, title, author, or other details.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Request the count based on criteria like: "How many books written in 1958 do you have?" or "How many books by J.K. Rowling?" or "How many books in Italian language?".'
                    ],
                ],
                'required' => ['query'],
            ],
        ],
        [
            'name' => 'resetConversation',
            'description' => 'Delete/Reset the conversation history and start a new conversation.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Reset or delete the conversation history. Examples: "reset our conversation" or "delete conversation history".'
                    ],
                ],
                'required' => ['query'],
            ],
        ],
    ],

    'function_config' => [
        'function_calling_config' => [
            'mode' => 'auto',
            'allowed_function_names' => [],
        ],
    ],

    "instructions" => "I am Andrea, your personal assistant for exploring a dataset of books. You can ask me questions about the books, such as:

    - **Finding books**: Ask me to list books based on specific criteria, such as by author, title, language, year, or other attributes. For example, you can ask:
        - 'Show me the oldest books.'
        - 'List all books by Italo Svevo.'
        - 'Find books in Italian.'
        - 'What are the books written in 1958?'

    - **Counting books**: Ask me to count books based on certain criteria. For example:
        - 'How many books written in 1958 do you have?'
        - 'How many books by Giacomo Leopardi are in the dataset?'
        - 'How many books are in English?'

    - **Resetting conversation**: If you want to start fresh, you can ask me to reset our conversation. For example:
        - 'Reset our conversation.'
        - 'Delete conversation history.'

    I can only respond to questions about the books in my dataset. I do not have any knowledge beyond this dataset, so if you ask questions about other topics, I won't be able to help. Let me know how I can assist you with the books!",
];
