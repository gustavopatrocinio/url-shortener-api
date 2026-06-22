# URL Shortener API

A small Laravel API that shortens URLs, tracks clicks, and exposes basic analytics per link. Users manage their links behind token auth; redirects are public.

Built as a portfolio/showcase project — focused on clear structure and sensible defaults rather than scaling to millions of requests.

## Stack

- PHP 8.3
- Laravel 13
- Laravel Sanctum (API tokens)
- SQLite by default (swap to MySQL/Postgres in production if you want)
- Database-backed cache and queue (no Redis required locally)

## Requirements

- PHP 8.3+
- Composer
- SQLite extension enabled

## Getting started

```bash
git clone git@github.com:gustavopatrocinio/url-shortener-api.git
cd url-shortener-api

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

Start the app and the queue worker. Clicks are recorded asynchronously — without the worker, redirects still work but stats won't update.

```bash
# terminal 1
php artisan serve

# terminal 2
php artisan queue:listen
```

Or use the bundled dev script (serve + queue + logs + vite):

```bash
composer dev
```

Run tests:

```bash
composer test
```

## How it works

**Short links** live at `GET /{slug}` on the web route — not under `/api`. That keeps URLs clean (`http://localhost:8000/abc1234`).

**Authenticated routes** sit under `/api` and expect a Bearer token from register/login.

When someone hits a short URL, the app looks up the slug (cached in the database), returns a 302 to the original URL, and dispatches a job to record the click. The redirect doesn't wait for the database write.

### Status codes on redirect

| Code | When |
|------|------|
| 302 | Link found and active |
| 404 | Slug doesn't exist, link is inactive, or soft-deleted |
| 410 | Link has passed its `expires_at` |

## API

All JSON responses under `/api`. Send `Content-Type: application/json`.

### Auth

**Register**

```http
POST /api/register
```

```json
{
  "name": "Gustavo",
  "email": "you@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

Returns `user` and `token`. Use the token on protected routes:

```
Authorization: Bearer 1|your-token-here
```

**Login** — `POST /api/login` with `email` and `password`.

**Logout** — `POST /api/logout` (requires token). Revokes the current token.

### Links

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/links` | List your links (paginated) |
| POST | `/api/links` | Create a link |
| GET | `/api/links/{id}` | Show one link |
| PUT/PATCH | `/api/links/{id}` | Update |
| DELETE | `/api/links/{id}` | Soft delete |
| GET | `/api/links/{id}/stats` | Click analytics |

**Create a link**

```http
POST /api/links
Authorization: Bearer {token}
```

```json
{
  "original_url": "https://example.com/some/long/path",
  "title": "Optional label",
  "slug": "my-custom-slug",
  "expires_at": "2026-12-31T23:59:59Z",
  "is_active": true
}
```

- `slug` is optional. Omit it and the API generates a random 7-character code.
- Custom slugs must be 3–20 chars: letters, numbers, `_`, `-`.

**Idempotency** — pass `Idempotency-Key` on `POST /api/links` if your client might retry. Same key + same body returns the original response without creating a duplicate. Reusing the key with a different body returns `409`.

**Stats** — `GET /api/links/{id}/stats?days=7`

```json
{
  "link_id": 1,
  "slug": "abc1234",
  "total_clicks": 42,
  "clicks_by_day": [
    { "day": "2026-06-20", "clicks": 5 },
    { "day": "2026-06-21", "clicks": 12 }
  ],
  "period_days": 7
}
```

`days` defaults to 7, max 365.

### Public redirect

```http
GET /{slug}
```

No auth. Browser or `curl -I http://localhost:8000/{slug}`.

## Data model

```
users
  └── links (soft delete)
        └── clicks
```

- `links.clicks_count` is updated by the click job — stats read this for totals instead of counting rows every time.
- Deleting a link soft-deletes it (redirect stops working) but click history stays in the database.

## Design notes

A few choices worth mentioning if you're reading this for an interview or code review:

**Soft delete on links** — deleting a link shouldn't wipe analytics that already happened.

**Click recording in a queue** — the hot path is the redirect. Recording IP and user agent can wait a few hundred milliseconds.

**Database cache instead of Redis** — keeps local setup to `php artisan serve` and a SQLite file. For real traffic I'd move cache and queues to Redis.

**Slug generation** — random slugs use `random_bytes`, not `Str::random()`. Custom slugs are supported because that's how actual shorteners work.

**Idempotency** — on link creation (client retries) and click jobs (queue retries). Each real page visit still gets its own click.

## Project layout

```
app/
  Http/Controllers/   Auth, Link CRUD, Redirect
  Jobs/               RecordClick
  Policies/           LinkPolicy (owner-only access)
  Services/           SlugGenerator, LinkCache, IdempotencyService
routes/
  api.php             Auth + links
  web.php             Redirect route
tests/Feature/        UrlShortenerTest, IdempotencyTest
```

## License

MIT
