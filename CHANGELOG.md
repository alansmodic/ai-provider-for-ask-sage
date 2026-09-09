# Changelog

All notable changes to this project are documented in this file.

## [1.1.0]

### Added
- OpenAI-compatible model built on the SDK's `AbstractOpenAiCompatibleTextGenerationModel`, targeting
  Ask Sage's `/server/openai/v1/chat/completions` surface.
- Per-request routing: grounded requests use the native `/server/query` endpoint, everything else
  uses the OpenAI-compatible endpoint.
- Support for `maxTokens`, `topP`, `frequencyPenalty`, `presencePenalty` and `functionDeclarations`.
- Native multi-turn conversations (role-preserving `messages` array) on the OpenAI-compatible surface.
- Token usage reporting on both surfaces.
- `asksage.endpoint` custom option to force a surface (`native` or `openai`).
- Debug notice when a grounded request also sets options only the OpenAI surface honors.

## [1.0.0]

### Added
- Ask Sage provider registration for the WordPress AI Client.
- Text generation via Ask Sage's native `/server/query` endpoint.
- Model discovery via `/server/get-models`.
- Dataset grounding, personas, live-retrieval control and reference limits via `ModelConfig` custom options.
- `x-access-tokens` request authentication implemented against the SDK's `RequestAuthenticationInterface`.
- `ai_provider_for_ask_sage_query_params` filter for enforcing site-wide grounding.
