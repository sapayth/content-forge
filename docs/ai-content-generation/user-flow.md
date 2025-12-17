# AI Content Generation - User Flow

**Document Type:** Product Requirements - User Flow  
**Feature:** AI Settings Configuration  
**Target Audience:** End Users (WordPress Administrators)  
**Last Updated:** December 2024

---

## Overview

This document describes the user flow for configuring AI content generation settings in Content Forge. Users can select from multiple AI providers (OpenAI, Anthropic, Google), choose appropriate models, and store API keys for seamless switching between providers.

### Pages/Posts Tab Structure

The Pages/Posts → Add New page features three tabs for different content generation methods:

1. **Auto Generate Tab** - Generates random/fake content automatically
   - User specifies number of posts/pages
   - System generates titles and content randomly
   - Options for featured images and excerpts

2. **Manual Tab** - User manually enters content
   - User provides specific titles and content
   - Full control over post/page content
   - Supports comma-separated titles for bulk creation

3. **AI Generate Tab** - Generates content using AI (new in v1.2.0)
   - User selects content type (Blog, E-commerce, etc.)
   - Optional custom prompt for AI
   - Uses configured AI provider/model from Settings
   - Generates realistic, context-aware content

The AI Generate tab is always visible but shows a configuration prompt if AI settings are not configured.

---

## User Journey: Initial Setup

### Scenario 1: First-Time Configuration

**Starting Point:** User navigates to Content Forge → Settings → AI Settings

**Initial State:**
- First provider (OpenAI) is pre-selected
- First model for that provider is pre-selected
- API Key field is empty
- AI generation feature is disabled throughout the plugin
- Disabled state shows message: "Configure AI model to use AI content generation"

**User Actions:**
1. User sees the AI Settings page with default selections
2. User clicks on the API Key field
3. User enters their API key for the selected provider
4. User clicks "Test Connection" button
   - **Success:** Connection test passes, user proceeds
   - **Failure:** Error notice appears explaining the issue (invalid key, network error, etc.)
5. If test passes, user clicks "Save Changes"
6. Success notice appears confirming settings are saved
7. AI generation feature is now enabled throughout the plugin

**End State:**
- Provider and model remain selected
- API key is saved and stored
- AI Generate tab becomes functional in Pages/Posts → Add New page
- User can now use AI content generation via the AI Generate tab

---

## User Journey: Switching Providers

### Scenario 2: Changing from OpenAI to Anthropic

**Starting Point:** User has OpenAI configured with API key saved

**Initial State:**
- OpenAI is selected
- OpenAI model (e.g., GPT-4) is selected
- OpenAI API key is displayed in the field
- Settings are saved and working

**User Actions:**
1. User selects "Anthropic" as the provider
2. Model dropdown automatically updates to show Anthropic models (e.g., Claude 3 Opus, Claude 3 Sonnet)
3. First Anthropic model is auto-selected
4. API Key field clears (OpenAI key is hidden)
5. If user previously saved an Anthropic API key, it automatically loads into the field
6. If no Anthropic key was saved, field remains empty
7. User can:
   - Enter a new Anthropic API key
   - Test the connection with the loaded/existing key
   - Save changes to switch to Anthropic

**End State:**
- Anthropic is now the active provider
- Anthropic model is selected
- Anthropic API key is saved
- OpenAI key remains stored but inactive
- AI generation now uses Anthropic

---

## User Journey: Testing Connection

### Scenario 3: Validating API Key

**Starting Point:** User has entered or loaded an API key

**User Actions:**
1. User clicks "Test Connection" button
2. System validates the API key with the selected provider/model
3. **Success Path:**
   - Success notice appears: "Connection successful"
   - User can proceed to save settings
4. **Failure Path:**
   - Error notice appears with specific message:
     - "Invalid API key" (for authentication errors)
     - "Connection failed. Please check your network." (for network errors)
     - "Rate limit exceeded" (for rate limit errors)
     - Generic error message for other failures
   - User can correct the API key and test again

**Note:** Test connection works with the current field values, regardless of saved settings. This allows users to test before saving.

---

## User Journey: Managing Multiple Providers

### Scenario 4: Storing Keys for Multiple Providers

**Starting Point:** User has OpenAI configured and wants to also configure Google

**User Actions:**
1. User has OpenAI selected with saved API key
2. User switches to "Google" provider
3. Google models appear in dropdown
4. API Key field is empty (no Google key saved yet)
5. User enters Google API key
6. User tests connection (optional but recommended)
7. User saves changes
8. Google API key is now stored
9. User switches back to OpenAI
10. OpenAI API key automatically loads into the field
11. User can switch between providers seamlessly

**Key Behavior:**
- All provider API keys are stored separately
- Only the currently selected provider's key is displayed
- Switching providers automatically loads the saved key for that provider (if exists)
- No confirmation dialogs when switching
- Active provider is the one currently selected

---

## User Journey: Model Selection

### Scenario 5: Changing Models Within a Provider

**Starting Point:** User has OpenAI GPT-4 selected and wants to switch to GPT-3.5

**User Actions:**
1. User has OpenAI selected with GPT-4 model
2. User opens the Model dropdown
3. User sees available OpenAI models (GPT-4, GPT-3.5-turbo, etc.)
4. User selects "GPT-3.5-turbo"
5. Model selection updates immediately
6. User can test connection with new model (optional)
7. User saves changes
8. New model is now active

**Key Behavior:**
- Model dropdown is dynamic and updates when provider changes
- Model selection persists when switching providers and back
- No need to re-enter API key when changing models (same provider)

---

## User Journey: Error Handling

### Scenario 6: Handling Various Error States

**Error State 1: Invalid API Key**
- User enters API key and clicks "Test Connection"
- Error notice: "Invalid API key. Please verify your API key and try again."
- User corrects the key and tests again

**Error State 2: Network Error**
- User clicks "Test Connection"
- Error notice: "Connection failed. Please check your internet connection and try again."
- User can retry after checking network

**Error State 3: Rate Limit Exceeded**
- User clicks "Test Connection" or tries to generate content
- Error notice: "Rate limit exceeded. Please try again later."
- User waits and retries

**Error State 4: Missing API Key / AI Not Configured**
- User navigates to Pages/Posts → Add New
- User clicks on "AI Generate" tab
- Tab shows message: "Configure AI model to use AI content generation"
- Message includes link or button to navigate to Settings → AI Settings
- User cannot proceed with AI generation until configured
- Other tabs (Auto Generate, Manual) remain fully functional

**Error State 5: Provider-Specific Errors**
- Error notices are provider-agnostic where possible
- Generic error format: "An error occurred: [specific error message]"
- All errors use WordPress notice component for consistency

---

## User Journey: Content Generation with AI

### Scenario 7: Using AI After Configuration

**Starting Point:** User has successfully configured AI settings

**User Actions:**
1. User navigates to Content Forge → Pages/Posts
2. User clicks "Add New"
3. User sees three tabs:
   - **Auto Generate** - Auto generate post/page name and contents (existing)
   - **Manual** - Manually input post/page name and contents (existing)
   - **AI Generate** - Generate content using AI (new tab)
4. User clicks on the "AI Generate" tab
5. **If AI is not configured:**
   - AI Generate tab shows message: "Configure AI model to use AI content generation"
   - User is directed to Settings → AI Settings to configure
6. **If AI is configured:**
   - User sees AI generation form with:
     - Content Type selector (e.g., Blog, E-commerce, Portfolio, Business, etc.)
     - Optional custom prompt field
     - Post type, status, and other standard options
   - User selects content type (e.g., Blog, E-commerce)
   - User optionally enters a custom prompt
   - User configures other post settings (type, status, etc.)
   - User clicks generate
   - System uses currently active provider/model/API key from settings
   - Content is generated and formatted for the active editor
   - If generation fails, error notice appears with retry option

**Key Behavior:**
- AI Generate tab is always visible (third tab option)
- Tab shows configuration prompt if AI is not set up
- Tab shows AI generation form if AI is configured
- AI generation uses the currently active provider/model from settings
- No need to reconfigure for each generation
- User can switch providers in settings and immediately use new provider
- All errors show clear, actionable messages
- Content type selection enhances AI prompts for better results

---

## State Management Summary

### Settings Storage
- **Provider:** Currently selected provider (e.g., "openai", "anthropic", "google")
- **Model:** Currently selected model for active provider (e.g., "gpt-4", "claude-3-opus")
- **API Keys:** Stored separately per provider:
  - `cforge_ai_openai_key` (encrypted)
  - `cforge_ai_anthropic_key` (encrypted)
  - `cforge_ai_google_key` (encrypted)

### Active Configuration
- Active provider = Currently selected provider in UI
- Active model = Currently selected model in UI
- Active API key = API key for active provider (loaded from storage)

### UI State Rules
- Provider selection → Updates model dropdown → Clears/shows appropriate API key
- Model selection → No impact on API key field
- API key entry → Stored when saved, loaded when provider is selected
- Test connection → Uses current field values (provider + model + API key)
- Save changes → Stores all current selections and API key

---

## User Experience Principles

1. **Seamless Switching:** Users can switch between providers without friction
2. **Persistent Storage:** All provider keys are saved, not lost when switching
3. **Immediate Feedback:** Test connection provides instant validation
4. **Clear Errors:** All error messages are specific and actionable
5. **Minimal Friction:** No unnecessary confirmations or warnings
6. **Progressive Disclosure:** Start simple, add complexity only when needed
7. **Consistent Patterns:** Follow WordPress admin UI patterns and components

---

## Edge Cases & Considerations

### Edge Case 1: Provider Not Available
- If a provider's API becomes unavailable, show error notice
- Allow user to switch to another provider

### Edge Case 2: Model Deprecated
- If selected model is deprecated, show warning
- Suggest alternative models
- Allow user to select new model

### Edge Case 3: Multiple Tabs/Windows
- Settings changes in one tab should reflect in others
- Consider using WordPress heartbeat API for real-time updates

### Edge Case 4: Plugin Deactivation/Reactivation
- All stored API keys should persist
- Last active provider/model should be restored

### Edge Case 5: Invalid Model for Provider
- Model dropdown should only show valid models for selected provider
- Prevent invalid combinations at UI level

---

## Success Criteria

The user flow is successful when:
- ✅ Users can configure their first AI provider in under 2 minutes
- ✅ Users can switch between providers without confusion
- ✅ Users understand which provider is currently active
- ✅ Error messages are clear and actionable
- ✅ All provider API keys are preserved when switching
- ✅ Test connection provides reliable validation
- ✅ AI generation works immediately after configuration

---

## Future Enhancements (Out of Scope)

The following features are not included in the initial implementation but may be considered for future releases:
- Usage statistics and cost tracking
- Connection status indicators
- API key show/hide toggle
- Help links and documentation
- Advanced provider-specific settings
- Batch API key validation
- Provider performance comparison

---

## Notes

- All error notices use WordPress standard notice components
- API keys are encrypted at rest
- Provider/model combinations are validated before saving
- Test connection is optional but recommended before first use
- Settings are saved per WordPress site (multisite compatible)
