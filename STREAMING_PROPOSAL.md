# Proposal: Add Streaming Support for Chat Bot Responses

## Summary

Add Server-Sent Events (SSE) streaming support to the Chat Bot to display LLM responses in real-time as tokens are generated, rather than waiting for the complete response. This would significantly improve user experience, especially for longer responses.

## Current Limitations

### User Experience Issues

1. **Long wait times with no feedback**: Users must wait for the entire response to be generated before seeing any output
2. **Polling overhead**: Frontend polls every 2 seconds, adding average 1-second latency to response delivery
3. **Timeout risks**: Long responses can trigger timeouts before completion (as noted in #41)
4. **Unnecessary server load**: Constant polling creates repeated HTTP requests while tasks are running

### Current Architecture

The Chat Bot currently uses a polling-based approach:

```
User sends message → TaskProcessing schedules task → Frontend polls every 2s →
Task completes → Result saved to DB → Next poll returns complete message
```

**File references:**
- Polling implementation: `src/components/ChattyLLM/ChattyLLMInputForm.vue:716-760`
- Task checking: `lib/Controller/ChattyLLMController.php:680-724`
- 2-second polling interval defined at `src/components/ChattyLLM/ChattyLLMInputForm.vue:753`

## Why Streaming is Now Feasible

### Addressing Previous Concerns

In #41, streaming was marked as "technically not realistic" due to concerns about model compatibility and Nextcloud platform limitations. However, I believe these concerns can be addressed:

#### 1. Model Compatibility

**Concern**: "We won't be able to use many models anymore"

**Reality**: Virtually all modern LLM providers support streaming:
- ✅ OpenAI API (GPT-3.5, GPT-4, GPT-4o) - `stream: true` parameter
- ✅ Anthropic Claude - Server-Sent Events streaming
- ✅ Ollama (local models) - Streaming endpoint
- ✅ LocalAI - OpenAI-compatible streaming API
- ✅ Azure OpenAI - Streaming support
- ✅ Google Gemini - Streaming responses
- ✅ Hugging Face Inference API - Streaming generators
- ✅ Together AI, Groq, Perplexity - All support streaming

Providers that don't support streaming can gracefully fallback to the current polling approach.

#### 2. Nextcloud Platform Limitations

**Current constraint**: The TaskProcessing framework is indeed incompatible with streaming:
- `ISynchronousProvider::process()` is a blocking method that returns complete output
- Task status is binary: "running" (HTTP 417) or "complete" (HTTP 200)
- No support for partial/chunked output delivery
- `$reportProgress` callback exists but is never utilized by any provider

**Solution**: Bypass TaskProcessing for streaming-capable providers by creating a dedicated streaming endpoint that communicates directly with LLM APIs.

## Proposed Implementation

### Architecture Overview

Create a **parallel streaming path** that coexists with the current TaskProcessing approach:

```
┌──────────────────────────────────────────────────────────────┐
│                    Chat Message Request                       │
└───────────────────────┬──────────────────────────────────────┘
                        │
                        ▼
              ┌─────────────────────┐
              │ Provider Capability │
              │     Detection       │
              └─────────────────────┘
                        │
          ┌─────────────┴─────────────┐
          ▼                           ▼
┌──────────────────┐        ┌──────────────────┐
│ Streaming Path   │        │ Legacy Path      │
│ (New)            │        │ (Current)        │
├──────────────────┤        ├──────────────────┤
│ Direct API call  │        │ TaskProcessing   │
│ Server-Sent      │        │ Poll every 2s    │
│ Events (SSE)     │        │ Complete output  │
│ Real-time chunks │        │                  │
└──────────────────┘        └──────────────────┘
          │                           │
          └─────────────┬─────────────┘
                        ▼
              ┌──────────────────┐
              │ Message saved    │
              │ to database      │
              └──────────────────┘
```

### Implementation Steps

#### 1. Backend: New Streaming Endpoint

**File**: `lib/Controller/ChattyLLMController.php`

Add a new method:

```php
/**
 * Stream LLM response in real-time using Server-Sent Events
 *
 * @param int $sessionId
 * @param int $messageId User's message ID
 * @return Http\StreamResponse
 */
#[NoAdminRequired]
public function streamGenerate(int $sessionId, int $messageId): Response {
    // 1. Validate session ownership
    // 2. Get conversation history
    // 3. Detect provider streaming capability
    // 4. If streaming supported:
    //    - Set headers: Content-Type: text/event-stream
    //    - Call provider API with streaming enabled
    //    - Yield chunks as Server-Sent Events
    //    - Save complete message to DB when done
    // 5. If streaming not supported:
    //    - Return error, frontend falls back to polling
}
```

#### 2. Provider Integration Layer

**New file**: `lib/Service/StreamingService.php`

```php
class StreamingService {
    /**
     * Check if configured provider supports streaming
     */
    public function providerSupportsStreaming(): bool;

    /**
     * Stream chat completion from provider
     * Yields string chunks as they arrive
     */
    public function streamChatCompletion(array $messages): \Generator;

    /**
     * Get provider-specific streaming configuration
     */
    private function getProviderConfig(): array;
}
```

This service would:
- Read provider settings (already configured in Assistant settings)
- Make direct HTTP requests to provider APIs with streaming enabled
- Parse streaming response format (SSE or JSON streaming)
- Yield chunks as they arrive
- Handle errors and reconnection

#### 3. Frontend: EventSource Integration

**File**: `src/components/ChattyLLM/ChattyLLMInputForm.vue`

Replace polling with EventSource:

```javascript
// New method (replaces pollGenerationTask)
async streamMessageGeneration(sessionId, messageId) {
    const url = generateUrl('/apps/assistant/api/v1/chat/stream')
    const params = new URLSearchParams({ sessionId, messageId })

    const eventSource = new EventSource(`${url}?${params}`)
    let fullMessage = ''

    eventSource.onmessage = (event) => {
        if (event.data === '[DONE]') {
            eventSource.close()
            this.loadingMessage = false
            return
        }

        // Append chunk to display
        fullMessage += event.data
        this.updateStreamingMessage(fullMessage)
    }

    eventSource.onerror = (error) => {
        eventSource.close()
        // Fallback to polling if streaming fails
        this.pollGenerationTask(taskId, sessionId)
    }
}

// New method: Update message display in real-time
updateStreamingMessage(content) {
    // Find or create placeholder message and update its content
    // This provides real-time visual feedback as tokens arrive
}
```

#### 4. Route Configuration

**File**: `appinfo/routes.php`

Add new route:

```php
[
    'name' => 'chattyLLM#streamGenerate',
    'url' => '/api/v1/chat/stream',
    'verb' => 'GET',
],
```

#### 5. Graceful Fallback

The implementation should:
- ✅ Detect provider streaming capability at runtime
- ✅ Use streaming when available
- ✅ Automatically fallback to polling for non-streaming providers
- ✅ Maintain full backward compatibility
- ✅ No configuration required from users

### Configuration

**No user action required.** The system should:
1. Auto-detect if the configured provider supports streaming
2. Use streaming endpoint if available
3. Fall back to current polling if not

This could be exposed as an optional toggle in admin settings:
```
☑ Enable streaming responses (recommended for supported providers)
```

## Benefits

### User Experience
- **Immediate feedback**: Users see responses as they're generated, like ChatGPT/Claude web interfaces
- **Perceived performance**: Even if total time is the same, streaming feels faster
- **Better for long responses**: Progress is visible instead of a loading spinner
- **Reduced timeouts**: Streaming keeps connection alive, preventing timeout issues

### Technical Benefits
- **Reduced server load**: One SSE connection vs. polling requests every 2 seconds
- **Lower latency**: No 0-2 second polling delay
- **More efficient**: Less HTTP overhead, fewer database queries
- **Modern standard**: SSE is well-supported in all modern browsers
- **Progressive enhancement**: Works alongside existing system

### Competitive Parity
All major AI chat interfaces use streaming:
- ChatGPT web interface
- Claude web interface
- Google Gemini
- Microsoft Copilot

Users expect this behavior from AI assistants.

## Implementation Effort Estimate

Based on codebase analysis:

| Task | Estimated Time |
|------|----------------|
| Backend streaming endpoint | 2-3 days |
| StreamingService with provider detection | 2-3 days |
| Frontend EventSource integration | 1-2 days |
| Fallback mechanism | 1 day |
| Testing (multiple providers) | 2-3 days |
| Documentation | 1 day |
| **Total** | **~2 weeks** |

## Backward Compatibility

✅ **Fully backward compatible**:
- Existing polling mechanism remains untouched
- Non-streaming providers continue to work
- Gradual rollout possible (enable per-provider)
- No database schema changes required
- No breaking API changes

## Security Considerations

- Same authentication/authorization as current chat endpoints
- Session ownership validation
- Rate limiting (inherit from existing chat endpoints)
- Input sanitization (already handled)
- SSE is one-way (server→client), no additional attack surface

## Open Questions for Maintainers

1. **Architecture approval**: Is bypassing TaskProcessing acceptable for this use case? Or would you prefer exploring `$reportProgress` callback implementation in TaskProcessing framework?

2. **Provider integration**: Should streaming be implemented in:
   - Option A: Assistant app directly (proposed above)
   - Option B: Individual provider apps (e.g., integration_openai)
   - Option C: Both with an interface/contract

3. **Feature flag**: Should this be:
   - Auto-enabled when provider supports it
   - Opt-in via admin setting
   - Opt-in per user

4. **Scope**: Should we also add streaming for:
   - Title generation?
   - Other TaskProcessing task types?

5. **Testing**: What providers should be tested in CI/CD?

## Alternative Considered: WebSockets

WebSockets would also enable streaming but:
- ❌ More complex (bi-directional when we only need server→client)
- ❌ Requires more infrastructure (persistent connections, state management)
- ❌ SSE is simpler and sufficient for this use case
- ✅ SSE auto-reconnects and is easier to debug

## Request for Feedback

I'd love to hear thoughts from @julien-nc, @marcelklehr, and the Nextcloud community:

1. Does this architectural approach make sense?
2. Are there Nextcloud platform considerations I'm missing?
3. Would you accept a PR implementing this? If so, any specific requirements?
4. Should this be coordinated with provider apps (e.g., integration_openai)?

## Related Issues

- #41 - Original streaming request (closed as not feasible)
- #150 - Related discussion mentioned in #41

## References

- Current polling implementation: `src/components/ChattyLLM/ChattyLLMInputForm.vue:716-760`
- Task processing controller: `lib/Controller/ChattyLLMController.php:680-724`
- TaskProcessing listener: `lib/Listener/ChattyLLMTaskListener.php:68`
- OpenAI Streaming API: https://platform.openai.com/docs/api-reference/streaming
- MDN Server-Sent Events: https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events

---

**I'm willing to implement this feature if there's interest from the maintainers.** Please let me know your thoughts!
