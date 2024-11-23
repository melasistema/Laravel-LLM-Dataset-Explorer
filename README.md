
# 🚀 **Laravel LLM Dataset Explorer with Gemini AI**

## 🌟 Project Overview

Welcome to the **Laravel LLM Dataset Explorer with Gemini AI**! This project demonstrates how you can leverage the power of **Large Language Models (LLMs)**, specifically **Gemini AI**, to **interact with datasets in a natural, conversational way**. By combining **Laravel**, **Gemini AI**, and **CoreNLP**, we enable intelligent querying and dynamic responses from structured data (such as a book dataset).

This project showcases how LLMs can **transform structured datasets** into interactive experiences where users can **query** and **explore** data via **natural language**—just like chatting with a human. Whether you're interacting with books, movies, or any other dataset, this is an example of how **AI** and **data** converge to create powerful conversational tools.

## 📌 Features

-   **Gemini AI-Powered Chatbot**: Uses **Gemini AI** to process and respond to natural language queries about the dataset.
-   **Dynamic Dataset Interaction**: Query your dataset with plain text and get intelligent, context-aware responses.
-   **Context-Aware Conversations**: AI understands the context and intent of your queries to retrieve the most relevant data.
-   **CoreNLP Integration**: Enhances the model’s ability to understand complex user queries and improve data extraction.
-   **Conversation Management**: Save and reset conversation history with the chatbot to refine your testing and interactions.
-   **Docker & Laravel Sail**: Built on **Laravel Sail** for a seamless local development environment using Docker.

## 🔧 Installation

### 1. Clone the Repository

Start by cloning this repository to your local machine:

`git clone https://github.com/your-repo/laravel-llm-dataset-explorer.git
cd laravel-llm-dataset-explorer`

### 2. Install Dependencies

-   **PHP & Composer**: Install the required PHP packages.

`composer install`

### 3. Configure Environment Variables

Copy `.env.example` to `.env` and update the necessary configuration values, such as your **Gemini AI API key** and **dataset settings**.

`cp .env.example .env`

Make sure to set the following variables in your `.env` file:

-   `GEMINI_API_KEY`: Your **Gemini AI** API key.
-   `GEMINI_BASE_URL`: The base URL for the **Gemini API**.
-   `GEMINI_REQUEST_TIMEOUT`: Request timeout for the Gemini API (in seconds).

### 4. Run Migrations

Set up your database by running Laravel migrations:

`php artisan migrate`

If you want to seed the database with sample user for the login, you can run:

`php artisan db:seed`

### 5. Start the Development Environment with Laravel Sail

Ensure that **Docker Desktop** is running, and then start the Laravel Sail development environment:

`vendor/sail up`

This will start all necessary Docker containers for your application, and you can access it locally.

----------

## 🧠 Usage

### API Endpoints

-   **Handle Query**: `POST /api/chat`

    -   **Request Body**:

    `{
    "query": "Hi, there! What are you designed to do?"
    }`

    -   **Response**:

    `{
    "response": {
    "text": "I am designed to help you explore and query a dataset of books.  I can perform the following tasks:\n\n* **Find books** based on criteria like author, title, language, year, or other details.  For example, you could ask me \"Find books written by Giacomo Leopardi\" or \"List all books published in 1922\".\n* **Count books** that match specific criteria.  You might ask \"How many books are in the dataset written in French?\" or \"How many books were published before 1900?\".\n* **Reset our conversation** if you want to start over.\n\nEssentially, I'm your personal book dataset assistant! Let me know what you'd like to explore.\n",
    "source": "Laravel LLM ChatBot",
    "timestamp": "2015-10-26T12:00:00Z"
    }
    }`


----------

### Example Conversations

#### Example 1: Ask for books counting

-   **User**: "How many books do you have in your dataset?"
-   **ChatBot**:

    `There are 100 books matching your criteria.`

-   **User**: "And how many of those are written in italian?"
-   **ChatBot**:

    `There are 5 books matching your criteria.`


#### Example 2: Ask for book listing

-   **User**: "Show me italian books under the 500 pages"
-   **ChatBot**:

    `Here are some books that match your criteria:\nTitle: Poems, Author: Giacomo Leopardi, Country: Italy, Language: Italian, Year: 1818, Pages: 184, ImageLink: images/poems-giacomo-leopardi.jpg, Link: \n\nTitle: Confessions of Zeno, Author: Italo Svevo, Country: Italy, Language: Italian, Year: 1923, Pages: 412, ImageLink: images/confessions-of-zeno.jpg, Link: https://en.wikipedia.org/wiki/Zeno%27s_Conscience\n`


#### Example 3: Reset the Conversation

-   **User**: "Please delete our conversation history."
-   **ChatBot**: "Conversation history has been reset."

----------

## 🛠 Code Structure

### `app/Services/ChatBotService.php`

Handles user queries and interacts with **Gemini AI**. It processes requests and uses the **CoreNLP** and **BookService** to return meaningful responses.

### `app/Services/BookService.php`

Manages the dataset of books and handles the filtering and retrieval of data based on user queries, such as "Italian books" or "books from the 14th century".

### `app/Http/Controllers/Api/ChatBotController.php`

Handles incoming queries via the API endpoint. It uses the **ChatBotService** to interpret queries and provide relevant responses based on the dataset.

----------

## ⚙️ Configuration

### Environment Variables

Make sure to set these environment variables in your `.env` file:

-   `CORE_NLP_SERVER_URL`: The endpoint for the **CoreNLP** server.
-   `CORE_NLP_SERVER_PORT`: The port for the **CoreNLP** server.
-   `GEMINI_API_KEY`: Your Gemini API key for LLM-powered responses.
-   `GEMINI_BASE_URL`: The endpoint for accessing the **Gemini AI** services.
-   `GEMINI_REQUEST_TIMEOUT`: Timeout for API requests (in seconds).

### Configuration Files

-   **`config/chatBot.php`**: Contains chatbot instructions and function schemas.
-   **`config/gemini.php`**: Configuration for **Gemini AI** API integration.

----------


## 📘 How Does This Work?

This project demonstrates the use of **Gemini AI** to interact with a **structured dataset** (books, in this case). The system leverages advanced technologies to interpret **natural language queries** and deliver relevant, human-like responses.

### Key Features:

-   **CoreNLP**: Analyzes and extracts meaning from user inputs, enabling precise filtering and querying of the dataset.
-   **Gemini AI with Function Calling**: Dynamically invokes predefined functions when specific intents are detected in the user's query.

#### 🧠 Why Function Calling?

We implemented **Gemini AI's function calling** to handle structured queries. When a specific function is triggered (e.g., retrieving book details), the response is **fixed and deterministic**, ensuring:

-   **Cost-Efficiency**: By using concise, predefined responses, we optimize token usage and reduce API costs.
-   **Predictability**: The returned information is precise and contextually relevant, minimizing unexpected results.

This setup makes **Gemini AI** an effective bridge between human-like conversation and structured datasets, maintaining balance between **dynamic interactivity** and **practical cost management**.

----------


### ⚠️ Important: Not for Production Use

This project is a **proof of concept** and a **study prototype**. While it showcases the potential of **LLMs** in dataset interaction, it is not optimized for production use.

#### Key Considerations:

1.  **Database Integration**: Replace the JSON-based dataset with Laravel's database system and **ElasticSearch** for robust and scalable querying.
2.  **Caching Mechanisms**: Use caching to optimize response times and reduce computational costs.
3.  **Optimized Context Management**: Currently, the results from `findBooks()` are **not saved** in the conversation context history. This design avoids rapid consumption of tokens under the **Gemini AI free tier**. However, for production:
    -   **Result Optimization**: Responses can be re-prompted to **Gemini AI** for a more natural language refinement.
    -   **Efficient Context Management**: Proper history context handling should be implemented to balance conversational continuity with cost efficiency.
4.  **Security Measures**: Implement strong authentication, request validation, and data sanitization mechanisms.

> **🚀 This project serves as a valuable starting point** for exploring how conversational AI can interact with structured datasets. Use it as a foundation to build a production-ready solution tailored to your needs.

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](https://opensource.org/licenses/MIT) link for details.

----------

### 🌟 Thank you for checking out this project! Feel free to contribute, raise issues, or improve the code. Let’s make data exploration more engaging with **AI**! 🚀

----------
