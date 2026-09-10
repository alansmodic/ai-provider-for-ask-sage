# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

### Added
- PHPUnit 9 suite with mocked HTTP coverage for credentials, authentication,
  surface routing, native query payloads, and model discovery.

### Fixed
- Ask Sage reports request failures (invalid token, unknown model, etc.) with
  an HTTP 200 status and the real outcome embedded in the response body as
  `status`/`response`, on both the native and OpenAI-compatible surfaces.
  This went unnoticed by the status-code-based checks in both this provider
  and the underlying SDK, so a real Ask Sage-side error surfaced as a
  confusing "missing choices/message key" exception instead of the actual
  error. Both surfaces now check for Ask Sage's embedded status and raise a
  clear `ResponseException` with Ask Sage's own message when it reports one.

## [1.1.1]

### Changed
- PHPCS ruleset now includes WordPress-Extra, PrefixAllGlobals, and the documented VIP array syntax for sniff properties.
- PHPStan no longer points at a WordPress core SDK path that does not exist in this repository.
- Ask Sage base URLs are HTTPS-only. A misconfigured GovCloud URL no longer falls back to the commercial endpoint.
- OpenAI-compatible requests authenticate with a Bearer token only; native requests still use `x-access-tokens`.
- Filter output for `/server/query` is sanitized before it is sent.
- Model discovery failures are reported under `WP_DEBUG` instead of being swallowed.

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
