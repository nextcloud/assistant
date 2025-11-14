# Pull Request: Add streaming support for Chat Bot responses

## 🎯 Overview

This PR proposes adding Server-Sent Events (SSE) streaming support for Chat Bot responses, enabling real-time token-by-token display as the LLM generates content. This addresses the user experience issues discussed in #398 and the original request in #41.

## ⚠️ Status: Proposal for Review

**This PR is a proposal for maintainer review and has not been tested yet.** I'm submitting this to get feedback on:
- The overall architectural approach
- Whether bypassing TaskProcessing is acceptable for this use case
- Any Nextcloud platform considerations I may have missed
- Testing requirements before this could be merged

## 🔗 Related Issues

- Closes #398 - Chat Bot streaming endpoint implementation
- Related to #41 - Original streaming request (marked as not feasible)

## 📋 What's Changed

### Backend
- **New `StreamingService`** (`lib/Service/StreamingService.php`):
  - Checks streaming configuration via app config
  - Makes direct HTTP requests to OpenAI-compatible provider APIs
  - Parses SSE responses and yields chunks as they arrive

- **New streaming endpoint** in `ChattyLLMController`:
  - `streamGenerate()` method for SSE streaming
  - Validates session ownership and authentication
  - Saves complete message to database when done

- **Route configuration**: Added `/chat/stream` GET endpoint

### Frontend
- **EventSource integration** in `ChattyLLMInputForm.vue`:
  - New `streamGenerationTask()` method using EventSource API
  - Real-time message updates as chunks arrive
  - Automatic fallback to polling if streaming fails

- **Graceful fallback**: Modified `runGenerationTask()` to try streaming first, then fall back to existing polling mechanism if unavailable

### Documentation
- **Complete setup guide** (`docs/STREAMING.md`):
  - Configuration for OpenAI, LocalAI, Ollama, Azure OpenAI
  - Architecture overview and API documentation
  - Troubleshooting guide

## ✨ Key Features

- ✅ **Real-time streaming**: Tokens appear as generated (like ChatGPT/Claude web interfaces)
- ✅ **Backward compatible**: Fully coexists with existing polling system
- ✅ **Automatic fallback**: Gracefully degrades to polling if streaming unavailable
- ✅ **Provider agnostic**: Works with any OpenAI-compatible streaming API
- ✅ **Reduced latency**: Eliminates 0-2 second polling delay
- ✅ **Lower server load**: One SSE connection instead of polling every 2 seconds

## 🔧 Configuration

Streaming requires admin configuration via OCC commands:

```bash
# Set API endpoint
php occ config:app:set assistant streaming_api_url --value="https://api.openai.com/v1/chat/completions"

# Set API key
php occ config:app:set assistant streaming_api_key --value="sk-..."

# Set model (optional, defaults to gpt-3.5-turbo)
php occ config:app:set assistant streaming_model --value="gpt-4"
```

## 🏗️ Architecture

The implementation bypasses the TaskProcessing framework for streaming:

```
User Message → Frontend → /chat/stream endpoint → StreamingService
→ Direct LLM API call (streaming enabled) → SSE chunks → Frontend updates
→ Complete message saved to DB
```

**Fallback path** (if streaming unavailable):
```
User Message → Frontend → /chat/generate → TaskProcessing (existing)
→ Poll /check_generation → Complete response
```

## 🤔 Architectural Questions

### 1. Bypassing TaskProcessing
This implementation bypasses TaskProcessing because:
- TaskProcessing is fundamentally synchronous (blocking `process()` method)
- No support for partial/chunked outputs
- `$reportProgress` callback exists but is unused

**Question**: Is this acceptable, or would you prefer exploring TaskProcessing modifications?

### 2. Provider Integration
Currently implemented in the Assistant app directly.

**Question**: Should streaming be:
- A) In Assistant app (current approach)
- B) In individual provider apps (e.g., integration_openai)
- C) Both, with an interface/contract?

### 3. Configuration
Currently uses app config values.

**Question**: Should there be:
- An admin UI for configuration?
- Auto-detection of provider streaming capabilities?
- Per-user opt-in/opt-out?

## 📝 Testing Needed

Before merging, this needs testing with:
- [ ] OpenAI API (GPT-3.5, GPT-4)
- [ ] LocalAI (self-hosted)
- [ ] Ollama (local models)
- [ ] Azure OpenAI
- [ ] Various network conditions (slow connections, timeouts)
- [ ] Fallback behavior when streaming fails
- [ ] Session switching during streaming
- [ ] Multiple concurrent streaming sessions
- [ ] Agency features compatibility
- [ ] Audio chat compatibility

## 🔍 Code Review Focus Areas

1. **Security**:
   - Session validation in streaming endpoint
   - API key storage and handling
   - SSE connection security

2. **Error Handling**:
   - Network failures
   - Provider errors
   - Partial response handling

3. **Performance**:
   - Memory usage during long responses
   - Connection management
   - Database write timing

4. **Compatibility**:
   - Impact on existing features
   - TaskProcessing coexistence
   - Frontend error scenarios

## 📖 Implementation Details

See `docs/STREAMING.md` for:
- Complete setup instructions for various providers
- Architecture diagrams
- API documentation
- Troubleshooting guide
- Security considerations

## 🙏 Request for Feedback

I'd love to hear from @julien-nc, @marcelklehr, and the community:

1. Does this architectural approach make sense for Nextcloud?
2. Are there platform considerations I'm missing?
3. What testing would be required before merging?
4. Should this coordinate with provider apps?
5. Any concerns about bypassing TaskProcessing?

**I'm happy to make any changes or explore alternative approaches based on your feedback!**

---

**Note**: This PR includes the proposal document from commit efbb0f8 as context for the feasibility discussion.
