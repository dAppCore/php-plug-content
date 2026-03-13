# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is `lthn/php-plug-content`, a PHP library providing content platform integrations for the Plug framework. It wraps the APIs of Dev.to, Hashnode, Medium, and WordPress into a uniform interface using contracts from `lthn/php` (the core Plug library).

## Build / Install

```bash
composer install
```

No test suite, linter, or build step exists in this repo currently.

## Architecture

**Namespace:** `Core\Plug\Content\` (PSR-4 mapped to `src/`)

Each platform is a subdirectory (`Devto/`, `Hashnode/`, `Medium/`, `Wordpress/`) containing classes that implement contracts from the core `Core\Plug` package:

| Class | Contract | Purpose |
|-------|----------|---------|
| `Auth` | `Authenticable` | Credential validation and auth URL generation |
| `Post` | `Postable` | Publishing and updating content |
| `Read` | `Readable` | Fetching posts and user profiles |
| `Delete` | `Deletable` | Removing/unpublishing content |
| `Publications` | `Listable` | Listing publications (Hashnode, Medium only) |
| `Media` | `MediaUploadable` | File uploads (WordPress only) |

**Shared traits** (from `Core\Plug\Concern`):
- `BuildsResponse` — wraps results into `Core\Plug\Response` objects via `fromHttp()` and `error()`
- `UsesHttp` — provides `http()` for HTTP client access (Laravel HTTP facade style)
- `ManagesTokens` — provides `accessToken()` for token storage/retrieval

**Key patterns:**
- All public API methods return `Core\Plug\Response`
- Response data is normalized via closure-based transformers passed to `fromHttp($response, fn ($data) => [...])`
- Fluent builder pattern for configuration (e.g., `->forSite()->withCredentials()`)
- Auth classes expose `identifier()` and `name()` static methods for platform registration

## Platform-Specific Notes

- **WordPress** uses Basic Auth (application passwords) and REST API; requires `siteUrl` on all operations
- **Hashnode** uses GraphQL (`gql.hashnode.com`) with PAT auth via `Authorization` header (no Bearer prefix)
- **Medium** uses OAuth 2.0 with REST API; limited read capabilities (no post retrieval or listing)
- **Dev.to** uses API key auth via `api-key` header; delete is implemented as unpublish (API limitation)
