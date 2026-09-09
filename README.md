# AI Provider for Ask Sage

> [!WARNING]
> **This is a prototype.** It is not production software and is not affiliated with or endorsed by
> Ask Sage. Every request path has been exercised against *mocked* HTTP responses only — nothing has
> been validated against a live Ask Sage tenant. Response shapes for the native `/server/query`
> endpoint (notably token `usage`) are inferred from public documentation and may be wrong. Review,
> test against a real tenant, and confirm trademark usage with Ask Sage before deploying or
> distributing.

Ask Sage provider for the [WordPress AI Client](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/). Lets regulated WordPress sites route AI requests through their own **Ask Sage** account (FedRAMP High / IL-authorized), with dataset grounding, personas and citations.

Structured to match the official provider plugins and [Fueled's Ollama provider](https://github.com/Fueled/ai-provider-for-ollama).

## Install

```bash
composer require wpvip/ai-provider-for-ask-sage
```

Or drop the directory into `wp-content/plugins/` and activate — a fallback PSR-4 autoloader is included, so `composer install` is optional.

```php
// wp-config.php — prefer env vars on WordPress VIP so the key is not stored in the database.
define( 'ASK_SAGE_BASE_URL', 'https://api.<your-tenant>.ai' ); // GovCloud / IL endpoint; HTTPS only
define( 'ASK_SAGE_API_KEY', getenv( 'ASK_SAGE_API_KEY' ) );    // or use Settings > Connectors
```

## Usage

No Ask Sage-specific code is required:

```php
use WordPress\AiClient\AiClient;

$text = AiClient::prompt( 'Summarize the agency travel policy.' )->generateText();
```

With grounding:

```php
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;

$config = new ModelConfig();
$config->setSystemInstruction( 'You are a policy assistant.' );
$config->setCustomOptions( array(
    'dataset' => array( 'agency_policy_docs' ),
    'persona' => 7,
    'live'    => 0,
) );

$result = AiClient::generateTextResult( 'Is FedRAMP High sufficient?', $config );
$result->toText();
$result->getAdditionalData()['references'] ?? array();
```

Enforce a mandated corpus site-wide:

```php
add_filter( 'ai_provider_for_ask_sage_query_params', function ( $params ) {
    $params['dataset'] = array( 'agency_policy_docs' );
    $params['live']    = 0;
    return $params;
} );
```

## Architecture

| Class | Extends | Role |
|---|---|---|
| `Provider\AskSageProvider` | `AbstractApiProvider` | Provider registration |
| `Models\AskSageTextGenerationModel` | `AbstractApiBasedModel` | Routes each request to a surface |
| `Models\AskSageNativeTextGenerationModel` | `AbstractApiBasedModel` | `/server/query` (grounding) |
| `Models\AskSageOpenAiCompatibleTextGenerationModel` | `AbstractOpenAiCompatibleTextGenerationModel` | `/server/openai/v1/` (full options) |
| `Metadata\AskSageModelMetadataDirectory` | `AbstractApiBasedModelMetadataDirectory` | Model discovery |
| `Auth\AccessTokenAuthentication` | `ApiKeyRequestAuthentication` | `x-access-tokens` header |

### Two surfaces, routed per request

Ask Sage exposes two APIs with complementary strengths, so the provider uses both:

| | `/server/query` (native) | `/server/openai/v1/` (compatible) |
|---|---|---|
| Grounding (`dataset`, `persona`, `live`) | yes | no |
| Citations (`references`) | yes | no |
| `maxTokens`, `topP`, penalties | no | yes |
| Function calling | varies by model | yes |
| Multi-turn | flattened into one message | native `messages` array |
| Auth | `x-access-tokens` | `Authorization: Bearer` |

A request is routed to the **native** surface when any grounding option is set, and to the
**OpenAI-compatible** surface otherwise. Force one with the `asksage.endpoint` custom option
(`native` or `openai`).

When a grounded request also sets an OpenAI-only option, grounding wins and a debug notice is
raised under `WP_DEBUG` rather than failing silently.

Ask Sage authenticates with an `x-access-tokens` header rather than `Authorization: Bearer`, so a
custom `RequestAuthenticationInterface` implementation is supplied. Requests still flow through the
SDK's HTTP transporter.

## Option support

Advertised (honored by at least one surface): `inputModalities`, `outputModalities`,
`systemInstruction`, `temperature`, `customOptions`, `maxTokens`, `topP`,
`frequencyPenalty`, `presencePenalty`, `functionDeclarations`.

Grounding options travel through `customOptions` (`dataset`, `persona`, `live`,
`limit_references`). Callers that set OpenAI-only sampling options together with
grounding are routed to `/server/query`; a debug notice is raised under `WP_DEBUG`.

Deliberately not advertised: `topK`, `stopSequences`, `logprobs`, `webSearch`, and
output file/schema options. Callers that set these correctly fall through to another
provider.

Not implemented on the native endpoint: streaming, and function calling (`/server/query`
accepts a `tools` array but its format varies by model).


## License

GPL-2.0-or-later
