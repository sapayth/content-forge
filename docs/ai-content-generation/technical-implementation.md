# AI Content Generation - Technical Implementation Plan

**Document Type:** Technical Specification  
**Feature:** AI Settings & Content Generation  
**Target Audience:** Developers  
**Last Updated:** December 2024  
**Related Documents:** [User Flow](./user-flow.md), [v1.2.0 Plan](../planning/v1.2.0-plan.md)

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Backend Components](#backend-components)
3. [Frontend Components](#frontend-components)
4. [REST API Endpoints](#rest-api-endpoints)
5. [Data Structures & Storage](#data-structures--storage)
6. [Security Implementation](#security-implementation)
7. [Provider Implementations](#provider-implementations)
8. [Error Handling](#error-handling)
9. [Hooks and Filters](#hooks-and-filters)
10. [Integration Points](#integration-points)
11. [File Structure](#file-structure)
12. [Testing Strategy](#testing-strategy)

---

## Architecture Overview

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    WordPress Admin UI                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Settings   │  │ Pages/Posts  │  │  AI Generate │      │
│  │   Page       │  │   Page      │  │     Tab      │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
└─────────┼─────────────────┼─────────────────┼──────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    REST API Layer                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ AI Settings  │  │   Posts     │  │  AI Generate │      │
│  │  Endpoints   │  │  Endpoints  │  │   Endpoints  │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
└─────────┼─────────────────┼─────────────────┼──────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    Business Logic Layer                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ AI Settings  │  │   Post       │  │  AI Content  │      │
│  │   Manager    │  │  Generator   │  │  Generator   │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
└─────────┼─────────────────┼─────────────────┼──────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    Provider Layer                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   OpenAI     │  │  Anthropic  │  │    Google    │      │
│  │   Adapter    │  │   Adapter   │  │   Adapter    │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
└─────────┼─────────────────┼─────────────────┼──────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────┐
│              External AI Provider APIs                      │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Settings Configuration:**
   - User → Settings UI → REST API → AI_Settings_Manager → WordPress Options (encrypted)

2. **Content Generation:**
   - User → AI Generate Tab → REST API → Post Generator → AI_Content_Generator → Provider Adapter → External API → Response Processing → Editor Formatting → WordPress Post

---

## Backend Components

### 1. AI_Settings_Manager

**File:** `includes/Settings/AI_Settings_Manager.php`

**Purpose:** Manages AI provider settings, API key storage, and configuration.

**Class Structure:**
```php
namespace ContentForge\Settings;

class AI_Settings_Manager {
    // Constants
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_ANTHROPIC = 'anthropic';
    const PROVIDER_GOOGLE = 'google';
    
    const OPTION_PROVIDER = 'cforge_ai_provider';
    const OPTION_MODEL = 'cforge_ai_model';
    const OPTION_KEY_PREFIX = 'cforge_ai_';
    
    // Methods
    public static function get_providers(): array;
    public static function get_models( string $provider ): array;
    public static function get_active_provider(): string;
    public static function get_active_model(): string;
    public static function get_api_key( string $provider ): string|false;
    public static function save_api_key( string $provider, string $key ): bool;
    public static function save_settings( array $settings ): bool;
    public static function get_settings(): array;
    public static function is_configured(): bool;
    public static function encrypt_key( string $key ): string;
    public static function decrypt_key( string $encrypted_key ): string|false;
}
```

**Key Methods:**

- `get_providers()`: Returns array of available providers with labels
  - Applies filter: `cforge_ai_providers`
  - Allows third-party plugins to add/modify providers
  ```php
  $providers = [
      'openai' => 'OpenAI',
      'anthropic' => 'Anthropic',
      'google' => 'Google',
  ];
  
  /**
   * Filter AI providers.
   *
   * @since 1.2.0
   *
   * @param array $providers Array of provider slugs => labels.
   * @return array Filtered providers.
   */
  return apply_filters( 'cforge_ai_providers', $providers );
  ```

- `get_models( $provider )`: Returns available models for provider
  - Applies filter: `cforge_ai_models`
  - Allows third-party plugins to add/modify models for a provider
  ```php
  // Example for OpenAI
  $models = [
      'gpt-4' => 'GPT-4',
      'gpt-4-turbo' => 'GPT-4 Turbo',
      'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
  ];
  
  /**
   * Filter AI models for a specific provider.
   *
   * @since 1.2.0
   *
   * @param array  $models   Array of model slugs => labels.
   * @param string $provider Provider slug.
   * @return array Filtered models.
   */
  return apply_filters( 'cforge_ai_models', $models, $provider );
  ```

- `save_api_key( $provider, $key )`: Encrypts and stores API key
  - Uses `update_option()` with encrypted value
  - Option name: `cforge_ai_{provider}_key`
  - Returns boolean success

- `get_api_key( $provider )`: Retrieves and decrypts API key
  - Returns decrypted key or `false` if not found

- `is_configured()`: Checks if at least one provider has API key
  - Returns `true` if active provider has valid key

**Storage:**
- Provider: `cforge_ai_provider` (string)
- Model: `cforge_ai_model` (string)
- API Keys: `cforge_ai_openai_key`, `cforge_ai_anthropic_key`, `cforge_ai_google_key` (encrypted strings)

---

### 2. AI_Content_Generator

**File:** `includes/Generator/AI_Content_Generator.php`

**Purpose:** Handles AI API communication and content generation.

**Class Structure:**
```php
namespace ContentForge\Generator;

class AI_Content_Generator {
    protected string $provider;
    protected string $model;
    protected string $api_key;
    protected string $editor_type;
    
    public function __construct( string $provider, string $model, string $api_key, string $editor_type = 'block' );
    public function generate( string $content_type, string $custom_prompt = '' ): array|WP_Error;
    public function test_connection(): array;
    protected function make_api_request( array $payload ): array|WP_Error;
    protected function format_content( string $raw_content, string $editor_type ): string;
    protected function build_prompt( string $content_type, string $custom_prompt = '' ): string;
    protected function parse_response( array $response ): array;
}
```

**Key Methods:**

- `generate( $content_type, $custom_prompt )`: Generates both title and content in a single API call
  - Builds comprehensive prompt requesting both title and content
  - Makes single API request to provider
  - Parses response to extract title and content
  - Formats content for editor type (block/classic)
  - Returns array with `title` and `content` keys
  ```php
  public function generate( string $content_type, string $custom_prompt = '' ): array|WP_Error {
      $prompt = $this->build_prompt( $content_type, $custom_prompt );
      
      $payload = [
          'prompt' => $prompt,
          'content_type' => $content_type,
          'editor_type' => $this->editor_type,
      ];
      
      $response = $this->make_api_request( $payload );
      
      if ( is_wp_error( $response ) ) {
          return $response;
      }
      
      $parsed = $this->parse_response( $response );
      
      return [
          'title' => sanitize_text_field( $parsed['title'] ),
          'content' => $this->format_content( $parsed['content'], $this->editor_type ),
      ];
  }
  ```
  
  **Response Format:**
  ```php
  [
      'title' => 'Generated Post Title',
      'content' => 'Formatted content for editor...',
  ]
  ```

- `test_connection()`: Validates API key and connection
  - Makes minimal API call
  - Returns `['success' => true/false, 'message' => '...']`

- `build_prompt( $content_type, $custom_prompt )`: Constructs AI prompt for both title and content
  - Incorporates content type context
  - Adds custom prompt if provided
  - Includes instructions to generate both title and content
  - Includes editor format instructions
  ```php
  protected function build_prompt( string $content_type, string $custom_prompt = '' ): string {
      $type_data = Content_Type_Data::get_type_context( $content_type );
      $type_keywords = Content_Type_Data::get_type_keywords( $content_type );
      
      $prompt = sprintf(
          "Generate a WordPress blog post with the following requirements:\n\n" .
          "1. Create an engaging, SEO-friendly title (maximum 60 characters)\n" .
          "2. Write comprehensive content (minimum 500 words)\n\n" .
          "Content Type: %s\n" .
          "Context: %s\n" .
          "Keywords to consider: %s\n",
          Content_Type_Data::get_type_label( $content_type ),
          $type_data,
          implode( ', ', $type_keywords )
      );
      
      if ( ! empty( $custom_prompt ) ) {
          $prompt .= "\nAdditional Instructions: " . $custom_prompt . "\n";
      }
      
      $prompt .= "\nFormat the response as JSON with 'title' and 'content' keys. " .
                 "Format the content for " . ( $this->editor_type === 'block' ? 'WordPress Block Editor' : 'Classic HTML Editor' ) . ".";
      
      /**
       * Filter AI generation prompt.
       *
       * @since 1.2.0
       *
       * @param string $prompt        The generated prompt.
       * @param string $content_type  Content type slug.
       * @param string $custom_prompt User-provided custom prompt.
       * @param string $editor_type   Editor type (block/classic).
       * @return string Filtered prompt.
       */
      return apply_filters( 'cforge_ai_generation_prompt', $prompt, $content_type, $custom_prompt, $this->editor_type );
  }
  ```

- `parse_response( $response )`: Parses provider response to extract title and content
  - Handles JSON response format
  - Extracts title and content fields
  - Falls back to text parsing if JSON not available
  ```php
  protected function parse_response( array $response ): array {
      // Provider-specific parsing logic
      // Should extract both title and content from response
      // Returns ['title' => '...', 'content' => '...']
  }
  ```

**Provider Adapters:**

Each provider requires a specific adapter class:

- `AI_Provider_OpenAI` (extends base provider class)
- `AI_Provider_Anthropic` (extends base provider class)
- `AI_Provider_Google` (extends base provider class)

**Base Provider Class:**
```php
namespace ContentForge\Generator\Providers;

abstract class AI_Provider_Base {
    protected string $api_key;
    protected string $model;
    
    /**
     * Generate both title and content in a single API call.
     *
     * @param array $params Generation parameters.
     * @return array|WP_Error Array with 'title' and 'content' keys, or WP_Error on failure.
     */
    abstract public function generate( array $params ): array|WP_Error;
    
    abstract public function test_connection(): bool;
    abstract protected function get_api_endpoint(): string;
    abstract protected function build_request_payload( array $params ): array;
    
    /**
     * Parse response to extract title and content.
     *
     * @param array $response Raw API response.
     * @return array Array with 'title' and 'content' keys.
     */
    abstract protected function parse_response( array $response ): array;
}
```

---

### 3. Content_Type_Data

**File:** `includes/Content/Content_Type_Data.php`

**Purpose:** Provides content-type-specific data for prompts and generation.

**Class Structure:**
```php
namespace ContentForge\Content;

class Content_Type_Data {
    const TYPE_GENERAL = 'general';
    const TYPE_ECOMMERCE = 'e-commerce';
    const TYPE_PORTFOLIO = 'portfolio';
    const TYPE_BUSINESS = 'business';
    const TYPE_EDUCATION = 'education';
    const TYPE_HEALTH = 'health';
    const TYPE_TECHNOLOGY = 'technology';
    const TYPE_FOOD = 'food';
    const TYPE_TRAVEL = 'travel';
    const TYPE_FASHION = 'fashion';
    
    public static function get_types(): array;
    public static function get_type_label( string $type ): string;
    public static function get_type_context( string $type ): string;
    public static function get_type_keywords( string $type ): array;
    public static function get_type_examples( string $type ): array;
}
```

**Key Methods with Filters:**

- `get_types()`: Returns all available content types
  - Applies filter: `cforge_content_types`
  ```php
  public static function get_types(): array {
      $types = [
          self::TYPE_GENERAL => [
              'label' => 'General/Blog',
              'context' => 'General blog posts and articles',
              'keywords' => ['article', 'blog', 'news', 'post'],
              'examples' => ['Blog post', 'News article', 'Opinion piece'],
          ],
          self::TYPE_ECOMMERCE => [
              'label' => 'E-commerce',
              'context' => 'Product descriptions, reviews, shopping guides, and e-commerce content',
              'keywords' => ['product', 'review', 'shopping', 'buy', 'purchase', 'customer'],
              'examples' => ['Product review', 'Shopping guide', 'Buyer\'s guide'],
          ],
          // ... other types
      ];
      
      /**
       * Filter content types.
       *
       * @since 1.2.0
       *
       * @param array $types Array of content type slugs => data arrays.
       * @return array Filtered content types.
       */
      return apply_filters( 'cforge_content_types', $types );
  }
  ```

**Data Structure:**
```php
// Example for e-commerce
[
    'label' => 'E-commerce',
    'context' => 'Product descriptions, reviews, shopping guides, and e-commerce content',
    'keywords' => ['product', 'review', 'shopping', 'buy', 'purchase', 'customer'],
    'examples' => ['Product review', 'Shopping guide', 'Buyer\'s guide'],
]
```

---

### 4. Post Generator Integration

**File:** `includes/Generator/Post.php` (modifications)

**Changes:**
- Add `use_ai` parameter to `generate()` method
- Add `content_type` parameter
- Add `ai_prompt` parameter (optional)
- Integrate `AI_Content_Generator` when `use_ai` is true
- Fallback to regular generation if AI fails

**Modified Method Signature:**
```php
public function generate( $count = 1, $args = [] ) {
    // Existing code...
    
    // Check if AI generation is requested
    $use_ai = isset( $args['use_ai'] ) && $args['use_ai'];
    $content_type = isset( $args['content_type'] ) ? $args['content_type'] : 'general';
    $ai_prompt = isset( $args['ai_prompt'] ) ? $args['ai_prompt'] : '';
    
    if ( $use_ai && AI_Settings_Manager::is_configured() ) {
        // Use AI generation - single call for both title and content
        $ai_generator = new AI_Content_Generator(
            AI_Settings_Manager::get_active_provider(),
            AI_Settings_Manager::get_active_model(),
            AI_Settings_Manager::get_api_key( AI_Settings_Manager::get_active_provider() ),
            cforge_detect_editor_type( $post_type )
        );
        
        // Generate both title and content in a single API call
        $ai_result = $ai_generator->generate( $content_type, $ai_prompt );
        
        if ( is_wp_error( $ai_result ) ) {
            // Fallback to regular generation on error
            $title = $this->randomize_title();
            $content = $this->randomize_content( $post_type );
        } else {
            // Use generated title and content
            $title = isset( $ai_result['title'] ) ? $ai_result['title'] : $this->randomize_title();
            $content = isset( $ai_result['content'] ) ? $ai_result['content'] : $this->randomize_content( $post_type );
        }
        
        /**
         * Filter AI-generated title and content before saving.
         *
         * @since 1.2.0
         *
         * @param array  $result      Array with 'title' and 'content' keys.
         * @param string $content_type Content type slug.
         * @param string $ai_prompt   Custom AI prompt.
         * @param string $post_type   WordPress post type.
         * @return array Filtered result.
         */
        $ai_result = apply_filters( 'cforge_ai_generated_content', $ai_result, $content_type, $ai_prompt, $post_type );
        $title = $ai_result['title'] ?? $title;
        $content = $ai_result['content'] ?? $content;
    } else {
        // Regular generation (existing logic)
        // ...
    }
}
```

---

## Frontend Components

### 1. AISettings Component

**File:** `src/js/components/AISettings.jsx`

**Purpose:** Settings page for AI configuration.

**Component Structure:**
```jsx
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, Button, SelectControl, TextControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

function AISettings() {
    const [provider, setProvider] = useState('openai');
    const [model, setModel] = useState('');
    const [apiKey, setApiKey] = useState('');
    const [testing, setTesting] = useState(false);
    const [notice, setNotice] = useState(null);
    const [saving, setSaving] = useState(false);
    
    // Provider and model options
    const providers = [
        { label: 'OpenAI', value: 'openai' },
        { label: 'Anthropic', value: 'anthropic' },
        { label: 'Google', value: 'google' },
    ];
    
    const [models, setModels] = useState([]);
    
    // Load settings on mount
    useEffect(() => {
        loadSettings();
    }, []);
    
    // Load models when provider changes
    useEffect(() => {
        loadModels(provider);
        loadApiKey(provider);
    }, [provider]);
    
    const loadSettings = async () => {
        // Fetch current settings
    };
    
    const loadModels = async (providerName) => {
        // Fetch models for provider
    };
    
    const loadApiKey = async (providerName) => {
        // Load saved API key for provider (masked)
    };
    
    const handleTestConnection = async () => {
        // Test API connection
    };
    
    const handleSave = async () => {
        // Save settings
    };
    
    return (
        <div className="cforge-ai-settings">
            {/* Provider Selection */}
            {/* Model Selection */}
            {/* API Key Input */}
            {/* Test Connection Button */}
            {/* Save Button */}
            {/* Notices */}
        </div>
    );
}
```

**State Management:**
- `provider`: Currently selected provider
- `model`: Currently selected model
- `apiKey`: API key for current provider (masked when loaded)
- `models`: Available models for current provider
- `testing`: Test connection in progress
- `saving`: Save operation in progress
- `notice`: Success/error notices

**Key Functions:**
- `loadSettings()`: Fetches current provider/model from API
- `loadModels(provider)`: Fetches available models for provider
- `loadApiKey(provider)`: Loads saved API key (returns masked value)
- `handleTestConnection()`: Tests API connection with current values
- `handleSave()`: Saves provider, model, and API key

---

### 2. AIGenerateTab Component

**File:** `src/js/components/AIGenerateTab.jsx`

**Purpose:** AI Generate tab content in Pages/Posts page.

**Component Structure:**
```jsx
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SelectControl, TextareaControl, Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

function AIGenerateTab({ onSuccess }) {
    const [isConfigured, setIsConfigured] = useState(false);
    const [contentType, setContentType] = useState('general');
    const [customPrompt, setCustomPrompt] = useState('');
    const [post, setPost] = useState({
        post_type: 'post',
        post_status: 'publish',
        // ... other post fields
    });
    const [generating, setGenerating] = useState(false);
    const [notice, setNotice] = useState(null);
    
    // Content types
    const contentTypes = [
        { label: 'General/Blog', value: 'general' },
        { label: 'E-commerce', value: 'e-commerce' },
        // ... other types
    ];
    
    useEffect(() => {
        checkConfiguration();
    }, []);
    
    const checkConfiguration = async () => {
        // Check if AI is configured
    };
    
    const handleGenerate = async () => {
        // Generate content using AI
    };
    
    if (!isConfigured) {
        return (
            <div className="cforge-ai-not-configured">
                <p>{__('Configure AI model to use AI content generation', 'content-forge')}</p>
                <Button href={settingsUrl}>
                    {__('Go to Settings', 'content-forge')}
                </Button>
            </div>
        );
    }
    
    return (
        <div className="cforge-ai-generate-tab">
            {/* Content Type Selector */}
            {/* Custom Prompt Field */}
            {/* Post Settings */}
            {/* Generate Button */}
            {/* Notices */}
        </div>
    );
}
```

**State Management:**
- `isConfigured`: Whether AI is configured
- `contentType`: Selected content type
- `customPrompt`: Optional user prompt
- `post`: Post settings (type, status, etc.)
- `generating`: Generation in progress
- `notice`: Success/error notices

---

### 3. Pages/Posts Component Updates

**File:** `src/js/pages-posts.jsx` (modifications)

**Changes:**
- Add third tab: "AI Generate"
- Import `AIGenerateTab` component
- Add tab state management
- Update tab rendering

**Modified Structure:**
```jsx
const [tab, setTab] = useState('auto'); // 'auto', 'manual', 'ai'

// In render:
<div className="cforge-tabs">
    {/* Auto Generate Tab */}
    <input
        type="radio"
        id="tab-auto"
        checked={tab === 'auto'}
        onChange={() => setTab('auto')}
    />
    
    {/* Manual Tab */}
    <input
        type="radio"
        id="tab-manual"
        checked={tab === 'manual'}
        onChange={() => setTab('manual')}
    />
    
    {/* AI Generate Tab */}
    <input
        type="radio"
        id="tab-ai"
        checked={tab === 'ai'}
        onChange={() => setTab('ai')}
    />
</div>

{tab === 'ai' && <AIGenerateTab onSuccess={handleSuccess} />}
```

---

## REST API Endpoints

### 1. AI Settings Endpoints

**Base Route:** `/cforge/v1/ai/`

#### GET `/cforge/v1/ai/settings`

**Purpose:** Retrieve current AI settings.

**Response:**
```json
{
    "provider": "openai",
    "model": "gpt-4",
    "is_configured": true,
    "providers": {
        "openai": "OpenAI",
        "anthropic": "Anthropic",
        "google": "Google"
    },
    "models": {
        "gpt-4": "GPT-4",
        "gpt-4-turbo": "GPT-4 Turbo"
    }
}
```

**Implementation:**
```php
// includes/Api/AI.php
public function get_settings( $request ) {
    return new \WP_REST_Response([
        'provider' => AI_Settings_Manager::get_active_provider(),
        'model' => AI_Settings_Manager::get_active_model(),
        'is_configured' => AI_Settings_Manager::is_configured(),
        'providers' => AI_Settings_Manager::get_providers(),
        'models' => AI_Settings_Manager::get_models( AI_Settings_Manager::get_active_provider() ),
    ], 200);
}
```

#### GET `/cforge/v1/ai/models/{provider}`

**Purpose:** Get available models for a provider.

**Parameters:**
- `provider` (path): Provider name (openai, anthropic, google)

**Response:**
```json
{
    "models": {
        "gpt-4": "GPT-4",
        "gpt-4-turbo": "GPT-4 Turbo",
        "gpt-3.5-turbo": "GPT-3.5 Turbo"
    }
}
```

#### POST `/cforge/v1/ai/settings`

**Purpose:** Save AI settings.

**Request Body:**
```json
{
    "provider": "openai",
    "model": "gpt-4",
    "api_key": "sk-..."
}
```

**Response:**
```json
{
    "success": true,
    "message": "Settings saved successfully"
}
```

**Validation:**
- Verify provider is valid
- Verify model is valid for provider
- Validate API key format (basic check)
- Encrypt API key before storing

#### POST `/cforge/v1/ai/test-connection`

**Purpose:** Test API connection.

**Request Body:**
```json
{
    "provider": "openai",
    "model": "gpt-4",
    "api_key": "sk-..."
}
```

**Response (Success):**
```json
{
    "success": true,
    "message": "Connection successful"
}
```

**Response (Error):**
```json
{
    "success": false,
    "message": "Invalid API key. Please verify your API key and try again.",
    "code": "invalid_api_key"
}
```

**Error Codes:**
- `invalid_api_key`: Authentication failed
- `network_error`: Network/connection issue
- `rate_limit`: Rate limit exceeded
- `invalid_model`: Model not available
- `unknown_error`: Generic error

#### GET `/cforge/v1/ai/api-key/{provider}`

**Purpose:** Get saved API key status (masked).

**Parameters:**
- `provider` (path): Provider name

**Response:**
```json
{
    "has_key": true,
    "masked_key": "sk-...****"
}
```

**Security:** Never return full API key, only masked version if exists.

---

### 2. AI Generation Endpoints

#### POST `/cforge/v1/ai/generate`

**Purpose:** Generate both title and content using AI in a single API call.

**Request Body:**
```json
{
    "content_type": "e-commerce",
    "custom_prompt": "Write about best practices",
    "editor_type": "block"
}
```

**Response:**
```json
{
    "success": true,
    "title": "Generated Post Title",
    "content": "Generated content here...",
    "provider": "openai",
    "model": "gpt-4"
}
```

**Implementation:**
```php
public function generate( $request ) {
    $params = $request->get_json_params();
    $content_type = isset( $params['content_type'] ) ? sanitize_key( $params['content_type'] ) : 'general';
    $custom_prompt = isset( $params['custom_prompt'] ) ? sanitize_textarea_field( $params['custom_prompt'] ) : '';
    $editor_type = isset( $params['editor_type'] ) ? sanitize_key( $params['editor_type'] ) : 'block';
    
    $provider = AI_Settings_Manager::get_active_provider();
    $model = AI_Settings_Manager::get_active_model();
    $api_key = AI_Settings_Manager::get_api_key( $provider );
    
    if ( ! $api_key ) {
        return new \WP_REST_Response([
            'success' => false,
            'message' => __( 'AI is not configured. Please configure AI settings first.', 'content-forge' ),
        ], 400);
    }
    
    $generator = new AI_Content_Generator( $provider, $model, $api_key, $editor_type );
    $result = $generator->generate( $content_type, $custom_prompt );
    
    if ( is_wp_error( $result ) ) {
        return new \WP_REST_Response([
            'success' => false,
            'message' => $result->get_error_message(),
            'code' => $result->get_error_code(),
        ], 400);
    }
    
    return new \WP_REST_Response([
        'success' => true,
        'title' => $result['title'],
        'content' => $result['content'],
        'provider' => $provider,
        'model' => $model,
    ], 200);
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Rate limit exceeded. Please try again later.",
    "code": "rate_limit"
}
```

---

### 3. Posts Endpoint Updates

#### POST `/cforge/v1/posts/bulk` (modified)

**New Parameters:**
- `use_ai` (boolean, optional): Use AI generation
- `content_type` (string, optional): Content type for AI
- `ai_prompt` (string, optional): Custom AI prompt

**Request Body Example:**
```json
{
    "post_type": "post",
    "post_status": "publish",
    "post_number": 1,
    "use_ai": true,
    "content_type": "e-commerce",
    "ai_prompt": "Write a product review"
}
```

---

## Data Structures & Storage

### WordPress Options

**Option Names:**
- `cforge_ai_provider`: Active provider (string)
- `cforge_ai_model`: Active model (string)
- `cforge_ai_openai_key`: OpenAI API key (encrypted string)
- `cforge_ai_anthropic_key`: Anthropic API key (encrypted string)
- `cforge_ai_google_key`: Google API key (encrypted string)

### Encryption

**Method:** Use WordPress `base64_encode()` + simple obfuscation, or WordPress built-in encryption if available.

**Implementation:**
```php
// Simple encryption (for basic obfuscation)
private static function encrypt_key( string $key ): string {
    // Use WordPress salts for encryption
    $salt = wp_salt();
    return base64_encode( $key . $salt );
}

private static function decrypt_key( string $encrypted_key ): string|false {
    $salt = wp_salt();
    $decoded = base64_decode( $encrypted_key );
    return str_replace( $salt, '', $decoded );
}
```

**Note:** For production, consider using `openssl_encrypt()` with WordPress salts.

### Default Values

```php
const DEFAULT_PROVIDER = 'openai';
const DEFAULT_MODEL_OPENAI = 'gpt-4';
const DEFAULT_MODEL_ANTHROPIC = 'claude-3-opus';
const DEFAULT_MODEL_GOOGLE = 'gemini-pro';
```

---

## Security Implementation

### 1. API Key Storage

- **Encryption:** All API keys encrypted at rest
- **Access Control:** Only users with `manage_options` capability
- **Nonce Verification:** All API requests require valid nonces
- **Sanitization:** All inputs sanitized before processing

### 2. API Request Security

- **HTTPS Only:** All external API calls use HTTPS
- **Key Validation:** Validate API key format before storing
- **Error Messages:** Never expose full API keys in error messages
- **Rate Limiting:** Respect provider rate limits

### 3. Input Validation

```php
// Provider validation
$valid_providers = ['openai', 'anthropic', 'google'];
if ( ! in_array( $provider, $valid_providers, true ) ) {
    return new \WP_Error( 'invalid_provider', 'Invalid provider' );
}

// Model validation
$valid_models = AI_Settings_Manager::get_models( $provider );
if ( ! isset( $valid_models[ $model ] ) ) {
    return new \WP_Error( 'invalid_model', 'Invalid model for provider' );
}

// API key format validation (basic)
if ( ! preg_match( '/^sk-[a-zA-Z0-9]{20,}$/', $api_key ) ) {
    return new \WP_Error( 'invalid_key_format', 'Invalid API key format' );
}
```

---

## Provider Implementations

### OpenAI Implementation

**File:** `includes/Generator/Providers/AI_Provider_OpenAI.php`

**API Endpoint:** `https://api.openai.com/v1/chat/completions`

**Request Format:**
```json
{
    "model": "gpt-4",
    "messages": [
        {
            "role": "system",
            "content": "You are a helpful content writer. Always respond with valid JSON containing 'title' and 'content' keys."
        },
        {
            "role": "user",
            "content": "Generate a blog post with title and content. Return as JSON: {\"title\": \"...\", \"content\": \"...\"}"
        }
    ],
    "temperature": 0.7,
    "max_tokens": 2000,
    "response_format": { "type": "json_object" }
}
```

**Response Format:**
```json
{
    "choices": [
        {
            "message": {
                "content": "{\"title\": \"Generated Post Title\", \"content\": \"Generated content here...\"}"
            }
        }
    ]
}
```

**Parse Response Implementation:**
```php
protected function parse_response( array $response ): array {
    if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
        return ['title' => '', 'content' => ''];
    }
    
    $content = $response['choices'][0]['message']['content'];
    $parsed = json_decode( $content, true );
    
    if ( json_last_error() === JSON_ERROR_NONE && isset( $parsed['title'] ) && isset( $parsed['content'] ) ) {
        return [
            'title' => $parsed['title'],
            'content' => $parsed['content'],
        ];
    }
    
    // Fallback: Try to extract title and content from text
    // Look for title pattern or split content
    return $this->parse_text_response( $content );
}
```

**Headers:**
```
Authorization: Bearer {api_key}
Content-Type: application/json
```

### Anthropic Implementation

**File:** `includes/Generator/Providers/AI_Provider_Anthropic.php`

**API Endpoint:** `https://api.anthropic.com/v1/messages`

**Request Format:**
```json
{
    "model": "claude-3-opus-20240229",
    "max_tokens": 2000,
    "messages": [
        {
            "role": "user",
            "content": "Generate a blog post with title and content. Return as JSON: {\"title\": \"...\", \"content\": \"...\"}"
        }
    ]
}
```

**Response Format:**
```json
{
    "content": [
        {
            "text": "{\"title\": \"Generated Post Title\", \"content\": \"Generated content here...\"}"
        }
    ]
}
```

**Parse Response Implementation:**
```php
protected function parse_response( array $response ): array {
    if ( ! isset( $response['content'][0]['text'] ) ) {
        return ['title' => '', 'content' => ''];
    }
    
    $content = $response['content'][0]['text'];
    $parsed = json_decode( $content, true );
    
    if ( json_last_error() === JSON_ERROR_NONE && isset( $parsed['title'] ) && isset( $parsed['content'] ) ) {
        return [
            'title' => $parsed['title'],
            'content' => $parsed['content'],
        ];
    }
    
    return $this->parse_text_response( $content );
}
```

**Headers:**
```
x-api-key: {api_key}
anthropic-version: 2023-06-01
Content-Type: application/json
```

### Google Implementation

**File:** `includes/Generator/Providers/AI_Provider_Google.php`

**API Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`

**Request Format:**
```json
{
    "contents": [
        {
            "parts": [
                {
                    "text": "Generate a blog post with title and content. Return as JSON: {\"title\": \"...\", \"content\": \"...\"}"
                }
            ]
        }
    ]
}
```

**Response Format:**
```json
{
    "candidates": [
        {
            "content": {
                "parts": [
                    {
                        "text": "{\"title\": \"Generated Post Title\", \"content\": \"Generated content here...\"}"
                    }
                ]
            }
        }
    ]
}
```

**Parse Response Implementation:**
```php
protected function parse_response( array $response ): array {
    if ( ! isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
        return ['title' => '', 'content' => ''];
    }
    
    $content = $response['candidates'][0]['content']['parts'][0]['text'];
    $parsed = json_decode( $content, true );
    
    if ( json_last_error() === JSON_ERROR_NONE && isset( $parsed['title'] ) && isset( $parsed['content'] ) ) {
        return [
            'title' => $parsed['title'],
            'content' => $parsed['content'],
        ];
    }
    
    return $this->parse_text_response( $content );
}
```

**Headers:**
```
x-goog-api-key: {api_key}
Content-Type: application/json
```

---

## Error Handling

### Error Types

1. **Authentication Errors:**
   - Invalid API key
   - Expired API key
   - Missing API key

2. **Network Errors:**
   - Connection timeout
   - DNS resolution failure
   - SSL certificate issues

3. **Rate Limiting:**
   - Too many requests
   - Quota exceeded

4. **API Errors:**
   - Invalid model
   - Invalid parameters
   - Service unavailable

### Error Response Format

```php
return new \WP_Error(
    'error_code',
    'User-friendly error message',
    [
        'status' => 400,
        'provider' => $provider,
        'model' => $model,
    ]
);
```

### Frontend Error Handling

```jsx
try {
    const response = await apiFetch({
        path: 'cforge/v1/ai/generate',
        method: 'POST',
        data: payload,
    });
} catch (error) {
    let message = __('An error occurred', 'content-forge');
    
    if (error?.code === 'invalid_api_key') {
        message = __('Invalid API key. Please verify your API key and try again.', 'content-forge');
    } else if (error?.code === 'rate_limit') {
        message = __('Rate limit exceeded. Please try again later.', 'content-forge');
    } else if (error?.message) {
        message = error.message;
    }
    
    setNotice({
        message,
        status: 'error',
    });
}
```

---

## Hooks and Filters

### Available Filters

#### 1. `cforge_ai_providers`

**Purpose:** Filter available AI providers.

**Location:** `AI_Settings_Manager::get_providers()`

**Parameters:**
- `$providers` (array): Array of provider slugs => labels

**Example:**
```php
add_filter( 'cforge_ai_providers', function( $providers ) {
    // Add custom provider
    $providers['custom_provider'] = 'Custom AI Provider';
    
    // Remove a provider
    unset( $providers['google'] );
    
    return $providers;
} );
```

---

#### 2. `cforge_ai_models`

**Purpose:** Filter available models for a specific provider.

**Location:** `AI_Settings_Manager::get_models()`

**Parameters:**
- `$models` (array): Array of model slugs => labels
- `$provider` (string): Provider slug

**Example:**
```php
add_filter( 'cforge_ai_models', function( $models, $provider ) {
    if ( 'openai' === $provider ) {
        // Add custom model
        $models['gpt-4-custom'] = 'GPT-4 Custom';
        
        // Remove a model
        unset( $models['gpt-3.5-turbo'] );
    }
    
    return $models;
}, 10, 2 );
```

---

#### 3. `cforge_content_types`

**Purpose:** Filter available content types.

**Location:** `Content_Type_Data::get_types()`

**Parameters:**
- `$types` (array): Array of content type slugs => data arrays

**Example:**
```php
add_filter( 'cforge_content_types', function( $types ) {
    // Add custom content type
    $types['real-estate'] = [
        'label' => 'Real Estate',
        'context' => 'Property listings, real estate guides, and market analysis',
        'keywords' => ['property', 'listing', 'real estate', 'home', 'buy'],
        'examples' => ['Property listing', 'Market analysis', 'Buying guide'],
    ];
    
    // Modify existing content type
    $types['e-commerce']['keywords'][] = 'online store';
    
    return $types;
} );
```

---

#### 4. `cforge_ai_generation_prompt`

**Purpose:** Filter the AI generation prompt before sending to provider.

**Location:** `AI_Content_Generator::build_prompt()`

**Parameters:**
- `$prompt` (string): The generated prompt
- `$content_type` (string): Content type slug
- `$custom_prompt` (string): User-provided custom prompt
- `$editor_type` (string): Editor type (block/classic)

**Example:**
```php
add_filter( 'cforge_ai_generation_prompt', function( $prompt, $content_type, $custom_prompt, $editor_type ) {
    // Add custom instructions
    if ( 'e-commerce' === $content_type ) {
        $prompt .= "\n\nImportant: Include product specifications and pricing information.";
    }
    
    return $prompt;
}, 10, 4 );
```

---

#### 5. `cforge_ai_generated_content`

**Purpose:** Filter AI-generated title and content before saving.

**Location:** `Post::generate()` (after AI generation)

**Parameters:**
- `$result` (array): Array with 'title' and 'content' keys
- `$content_type` (string): Content type slug
- `$ai_prompt` (string): Custom AI prompt
- `$post_type` (string): WordPress post type

**Example:**
```php
add_filter( 'cforge_ai_generated_content', function( $result, $content_type, $ai_prompt, $post_type ) {
    // Modify generated content
    $result['title'] = strtoupper( $result['title'] );
    $result['content'] = '<div class="ai-generated">' . $result['content'] . '</div>';
    
    return $result;
}, 10, 4 );
```

---

#### 6. `cforge_ai_provider_request_payload`

**Purpose:** Filter the request payload before sending to provider API.

**Location:** `AI_Provider_Base::build_request_payload()`

**Parameters:**
- `$payload` (array): Request payload array
- `$provider` (string): Provider slug
- `$model` (string): Model slug

**Example:**
```php
add_filter( 'cforge_ai_provider_request_payload', function( $payload, $provider, $model ) {
    if ( 'openai' === $provider ) {
        // Adjust temperature for specific model
        if ( 'gpt-4' === $model ) {
            $payload['temperature'] = 0.9;
        }
    }
    
    return $payload;
}, 10, 3 );
```

---

#### 7. `cforge_ai_provider_response`

**Purpose:** Filter the raw provider API response before parsing.

**Location:** `AI_Provider_Base::parse_response()`

**Parameters:**
- `$response` (array): Raw API response
- `$provider` (string): Provider slug

**Example:**
```php
add_filter( 'cforge_ai_provider_response', function( $response, $provider ) {
    // Log response for debugging
    error_log( 'AI Provider Response: ' . print_r( $response, true ) );
    
    return $response;
}, 10, 2 );
```

---

### Available Actions

#### 1. `cforge_ai_before_generation`

**Purpose:** Fired before AI content generation starts.

**Parameters:**
- `$content_type` (string): Content type slug
- `$custom_prompt` (string): Custom prompt
- `$provider` (string): Provider slug
- `$model` (string): Model slug

**Example:**
```php
add_action( 'cforge_ai_before_generation', function( $content_type, $custom_prompt, $provider, $model ) {
    // Log generation attempt
    error_log( "Starting AI generation: {$content_type} with {$provider}/{$model}" );
}, 10, 4 );
```

---

#### 2. `cforge_ai_after_generation`

**Purpose:** Fired after AI content generation completes successfully.

**Parameters:**
- `$result` (array): Generated result with 'title' and 'content'
- `$content_type` (string): Content type slug
- `$provider` (string): Provider slug

**Example:**
```php
add_action( 'cforge_ai_after_generation', function( $result, $content_type, $provider ) {
    // Track usage statistics
    update_option( 'cforge_ai_usage_count', get_option( 'cforge_ai_usage_count', 0 ) + 1 );
}, 10, 3 );
```

---

#### 3. `cforge_ai_generation_error`

**Purpose:** Fired when AI generation fails.

**Parameters:**
- `$error` (WP_Error): Error object
- `$content_type` (string): Content type slug
- `$provider` (string): Provider slug

**Example:**
```php
add_action( 'cforge_ai_generation_error', function( $error, $content_type, $provider ) {
    // Log errors for monitoring
    error_log( "AI Generation Error: {$error->get_error_message()} ({$provider})" );
}, 10, 3 );
```

---

## Integration Points

### 1. Admin Menu Integration

**File:** `includes/Admin.php`

**Changes:**
```php
add_submenu_page(
    $parent_slug,
    __( 'Settings', 'content-forge' ),
    __( 'Settings', 'content-forge' ),
    $capability,
    'cforge-settings',
    [ __CLASS__, 'render_settings_page' ]
);
```

### 2. Loader Integration

**File:** `includes/Loader.php`

**Changes:**
```php
// Register AI Settings Manager
require_once CFORGE_INCLUDES_PATH . 'Settings/AI_Settings_Manager.php';

// Register AI Content Generator
require_once CFORGE_INCLUDES_PATH . 'Generator/AI_Content_Generator.php';

// Register Provider Adapters
require_once CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Base.php';
require_once CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_OpenAI.php';
require_once CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Anthropic.php';
require_once CFORGE_INCLUDES_PATH . 'Generator/Providers/AI_Provider_Google.php';

// Register REST API
require_once CFORGE_INCLUDES_PATH . 'Api/AI.php';
```

### 3. Content Type Integration

**File:** `includes/Content/Content_Type_Data.php`

**Usage in AI Generator:**
```php
$context = Content_Type_Data::get_type_context( $content_type );
$keywords = Content_Type_Data::get_type_keywords( $content_type );

$prompt = sprintf(
    'Write a %s article about %s. Focus on: %s',
    $context,
    $custom_prompt ?: 'the topic',
    implode( ', ', $keywords )
);
```

---

## File Structure

```
content-forge/
├── includes/
│   ├── Settings/
│   │   └── AI_Settings_Manager.php
│   ├── Generator/
│   │   ├── AI_Content_Generator.php
│   │   └── Providers/
│   │       ├── AI_Provider_Base.php
│   │       ├── AI_Provider_OpenAI.php
│   │       ├── AI_Provider_Anthropic.php
│   │       └── AI_Provider_Google.php
│   ├── Content/
│   │   └── Content_Type_Data.php
│   └── Api/
│       └── AI.php
├── src/
│   └── js/
│       ├── components/
│       │   ├── AISettings.jsx
│       │   └── AIGenerateTab.jsx
│       └── pages-posts.jsx (modified)
└── assets/
    ├── js/
    │   └── settings.js (compiled)
    └── css/
        └── settings.css (compiled)
```

---

## Testing Strategy

### Unit Tests

1. **AI_Settings_Manager Tests:**
   - Test provider/model retrieval
   - Test API key encryption/decryption
   - Test settings save/load
   - Test configuration status check

2. **AI_Content_Generator Tests:**
   - Test prompt building
   - Test response formatting
   - Test error handling
   - Test provider switching

3. **Provider Adapter Tests:**
   - Test API request building
   - Test response parsing
   - Test error handling
   - Test connection testing

### Integration Tests

1. **Settings Flow:**
   - Test settings page load
   - Test provider/model switching
   - Test API key saving
   - Test connection testing

2. **Generation Flow:**
   - Test AI content generation
   - Test fallback to regular generation
   - Test editor format conversion
   - Test error handling

### Manual Testing Checklist

- [ ] Configure OpenAI provider
- [ ] Switch to Anthropic provider
- [ ] Switch to Google provider
- [ ] Test connection with valid key
- [ ] Test connection with invalid key
- [ ] Generate content with AI
- [ ] Verify Block Editor format
- [ ] Verify Classic Editor format
- [ ] Test content type selection
- [ ] Test custom prompts
- [ ] Test error handling
- [ ] Test fallback behavior

---

## Implementation Phases

### Phase 1: Core Infrastructure
1. Create `AI_Settings_Manager` class
2. Create base provider class
3. Implement OpenAI provider
4. Create REST API endpoints for settings
5. Create Settings UI component

### Phase 2: Content Generation
1. Create `AI_Content_Generator` class
2. Integrate with Post Generator
3. Implement content type support
4. Create AI Generate tab component
5. Update Posts REST API

### Phase 3: Additional Providers
1. Implement Anthropic provider
2. Implement Google provider
3. Test provider switching
4. Update UI for all providers

### Phase 4: Polish & Testing
1. Error handling refinement
2. UI/UX improvements
3. Comprehensive testing
4. Documentation

---

## Notes

- All API keys are encrypted at rest
- Provider/model combinations validated before use
- Fallback to regular generation if AI fails
- Editor type detection maintained
- Content type enhances prompts but doesn't restrict generation
- Settings persist across plugin updates
- Multisite compatible (per-site settings)

---

## Future Enhancements

- Caching AI responses
- Batch generation optimization
- Usage statistics tracking
- Cost estimation
- Advanced prompt templates
- Provider performance comparison
- Custom provider support via hooks
