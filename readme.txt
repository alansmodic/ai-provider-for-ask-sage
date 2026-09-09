=== AI Provider for Ask Sage ===
Contributors: wordpressvip
Tags: ai, ask-sage, llm, fedramp, govtech
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ask Sage provider for the WordPress AI Client. FedRAMP High / IL-authorized GenAI with dataset grounding and personas.

== Description ==

**PROTOTYPE — not production software.** Not affiliated with or endorsed by Ask Sage. All request
paths have been tested against mocked HTTP responses only, never a live Ask Sage tenant. Review and
validate before deploying.

Registers Ask Sage as a provider for the WordPress AI Client, so any AI-aware plugin, block or
Ability can use an organization's own Ask Sage account with no Ask Sage-specific code.

Like the official AI provider plugins (Anthropic, OpenAI, Google), this plugin registers a provider
with the PHP AI Client SDK. WordPress automatically generates the corresponding
**Settings > Connectors** card from the provider's metadata.

What makes an Ask Sage account worth connecting natively:

* **Dataset grounding** — restrict answers to an approved corpus.
* **Personas** — apply a configured response profile.
* **Live-retrieval control** — disable external retrieval entirely.
* **Citations** — grounding references are preserved on the result.

= Configuration =

Provide the Ask Sage API key by any of the following, checked in order:

1. Environment variable `ASK_SAGE_API_KEY`
2. PHP constant `ASK_SAGE_API_KEY`
3. Settings > Connectors > Ask Sage

Government tenants set their instance endpoint with the `ASK_SAGE_BASE_URL` constant. It defaults
to `https://api.asksage.ai`.

== Frequently Asked Questions ==

= Which Ask Sage endpoint is used? =

Both. Ask Sage exposes a native `/server/query` endpoint that carries grounding parameters, and an
OpenAI-compatible endpoint that supports the full set of standard sampling options, multi-turn
conversations and tool calling. Requests that ask for grounding (`dataset`, `persona`, `live` or
`limit_references`) use the native endpoint; everything else uses the OpenAI-compatible endpoint.

Set the `asksage.endpoint` custom option to `native` or `openai` to force one.

= Are any options still unsupported? =

`topK`, `stopSequences`, `logprobs` and the output file/schema options are not advertised. Grounded
requests also cannot use the OpenAI-only sampling options; when both are set, a debug notice is
raised and grounding takes precedence.

= Can I force all requests to use a specific grounding dataset? =

Yes. Use the `ai_provider_for_ask_sage_query_params` filter.

== Changelog ==

= 1.1.0 =
* Added OpenAI-compatible endpoint support with automatic per-request routing.
* Added max tokens, top-p, penalties, function calling and native multi-turn.
* Added token usage reporting.

= 1.0.0 =
* Initial release.
