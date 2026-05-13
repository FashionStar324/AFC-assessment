# Partner Directory

A WordPress plugin that registers a **Partner Organizations** custom post type with a Gutenberg block, REST API endpoint, and full admin UI for managing partner records.

---

## Table of Contents

- [Requirements](#requirements)
- [Local Development Setup](#local-development-setup)
  - [Option A — DDEV (preferred)](#option-a--ddev-preferred)
  - [Option B — Plain Docker](#option-b--plain-docker)
- [Architecture & Technical Approach](#architecture--technical-approach)
- [Key Tradeoffs & Decisions](#key-tradeoffs--decisions)
- [What I Would Improve With More Time](#what-i-would-improve-with-more-time)
- [AI Usage Notes](#ai-usage-notes)

---

## Requirements

| Tool | Version |
|------|---------|
| Docker | ≥ 24 |
| DDEV | ≥ 1.23 (Option A only) |
| Node.js | ≥ 20 (Option B only — for building block assets) |
| npm | ≥ 10 (Option B only) |

---

## Local Development Setup

### Option A — DDEV (preferred)

DDEV handles everything automatically: WordPress download, database install, plugin symlink, activation, and npm build.

```bash
git clone https://github.com/fashionStar324/partner-directory.git
cd partner-directory
ddev start
```

On first start the post-start hook will:

1. Download WordPress core into `wordpress/` (gitignored)
2. Create `wp-config.php` pointed at the DDEV MariaDB container
3. Run the WordPress installer
4. Symlink the plugin repo into `wordpress/wp-content/plugins/partner-directory`
5. Activate the plugin via WP-CLI
6. Run `npm install && npm run build` to compile the Gutenberg block

When setup finishes you will see:

```
✓ Setup complete.
  Site URL  : https://partner-directory.ddev.site
  Admin URL : https://partner-directory.ddev.site/wp-admin
  Username  : admin
  Password  : admin
```

**Useful DDEV commands:**

```bash
ddev stop               # stop containers
ddev start              # start (re-runs setup hook idempotently)
ddev ssh                # shell into the web container
ddev exec wp plugin list --path=wordpress
ddev logs               # view web + db logs
```

---

### Option B — Plain Docker

Use this if you do not have DDEV installed.

**Step 1 — Build block assets on the host**

```bash
git clone https://github.com/fashionStar324/partner-directory.git
cd partner-directory
npm install
npm run build
```

**Step 2 — Start containers**

```bash
docker compose up -d
```

**Step 3 — Install WordPress**

Visit [http://localhost:8080](http://localhost:8080) and complete the WordPress installer, or use WP-CLI:

```bash
docker compose run --rm wpcli core install \
  --url=http://localhost:8080 \
  --title="Partner Directory Dev" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.com \
  --skip-email
```

**Step 4 — Activate the plugin**

```bash
docker compose run --rm wpcli plugin activate partner-directory
```

The site is now available at [http://localhost:8080](http://localhost:8080).

**Useful docker compose commands:**

```bash
docker compose down           # stop and remove containers
docker compose down -v        # also wipe database and WordPress volumes
docker compose logs -f        # tail logs
docker compose run --rm wpcli <wp command>
```

---

## Architecture & Technical Approach

### Plugin structure

```
partner-directory/
├── partner-directory.php          # Bootstrap — constants, requires, init hook
├── includes/
│   ├── class-partner-cpt.php      # CPT + partner_category taxonomy registration
│   ├── class-partner-meta.php     # Admin meta boxes, media uploader, save/sanitize
│   ├── class-partner-rest-api.php # REST endpoint, caching, rate limiting
│   └── class-partner-block.php    # Block registration, server-side render callback
├── blocks/partner-grid/
│   ├── block.json                 # Block metadata and attribute schema
│   ├── index.js                   # Block entry point (registers + imports styles)
│   ├── edit.js                    # Editor UI (InspectorControls + ServerSideRender)
│   └── style.css                  # Front-end styles (compiled → build/style-index.css)
├── assets/js/
│   └── admin.js                   # WordPress media library picker for logo field
├── build/                         # Compiled block assets (generated — not committed)
├── .ddev/
│   ├── config.yaml                # DDEV project config
│   └── scripts/setup.sh           # Idempotent post-start setup script
├── docker-compose.yml             # Plain Docker fallback
├── package.json                   # npm scripts + @wordpress/scripts dependency
└── .gitignore
```

### Data model

| Field | Storage | Notes |
|-------|---------|-------|
| Name | `post_title` | Native WordPress field |
| Logo | `_partner_logo_id` post meta | Stores attachment ID; URL resolved at render time |
| Website URL | `_partner_website_url` post meta | Validated with `filter_var(FILTER_VALIDATE_URL)` before save |
| Category | `partner_category` taxonomy | Hierarchical custom taxonomy; enables native WP admin filtering and REST API `?partner_category=slug` queries |

**Why taxonomy over post meta for category?** A custom taxonomy gives you the WordPress admin category column, native checkbox UI in the editor sidebar, REST API support, and URL-based archive pages — all for free. Post meta would require building all of that manually.

### REST API

```
GET /wp-json/custom/v1/partners
GET /wp-json/custom/v1/partners?category=education
GET /wp-json/custom/v1/partners?per_page=10&page=2
```

Response shape:

```json
{
  "partners": [
    {
      "id": 42,
      "name": "Example Org",
      "logo_url": "https://example.ddev.site/wp-content/uploads/logo.png",
      "website_url": "https://example.org",
      "categories": [{ "id": 3, "name": "Education", "slug": "education" }],
      "link": "https://example.ddev.site/partners/example-org/"
    }
  ],
  "total": 1,
  "total_pages": 1,
  "page": 1,
  "per_page": 10
}
```

Response headers include `X-Partner-Cache: HIT` or `MISS` for debugging.

**Caching** — Results are cached in WordPress transients for 1 hour, keyed by `category + per_page + page`. The cache is busted automatically on any partner save, delete, or category edit via `save_post_partner` / `deleted_post` / `edited_partner_category` hooks.

**Rate limiting** — 60 requests per 60-second window per IP, tracked via transients. Returns HTTP 429 when exceeded. Proxy header trust (`X-Forwarded-For`) is opt-in via the `PARTNER_DIR_TRUST_PROXY` constant in `wp-config.php`.

### Gutenberg block

The block is **dynamic** (server-side rendered). `save()` returns `null` and WordPress calls the PHP render callback on every page load, so partner data is always current without re-saving posts.

The editor uses `ServerSideRender` to show a live preview that exactly matches the front end. Block attributes exposed in the sidebar:

- **Columns** (1–6)
- **Partners per page** (1–100)
- **Category slug** (optional filter)

---

## Key Tradeoffs & Decisions

**Post meta vs taxonomy for category**
Using `partner_category` as a proper taxonomy rather than a post meta field costs one extra database table but gains built-in filtering UI, archive pages, and REST API support with zero additional code. Worth the tradeoff for a directory feature.

**Dynamic block vs static block**
A static block serializes the partner list at save time, meaning editors would have to re-save every post when partner data changes. A dynamic block always reflects the current database state. The tradeoff is a PHP query on each page load, mitigated by the transient caching on the REST endpoint and WordPress object cache for WP_Query results.

**Transient caching vs full-page caching**
Transients are per-query-key, simple, and zero-dependency. A full-page cache (e.g., Redis via WP Redis plugin) would be more performant at scale but introduces infrastructure complexity inappropriate for a plugin that should work on a default WordPress install. The cache bust hooks keep staleness bounded.

**Rate limiting via transients**
Transient-based rate limiting is not atomic — under extreme concurrency two requests could simultaneously read a count of 59 and both proceed. A production implementation would use Redis with atomic INCR. For this project transients are a reasonable, dependency-free approximation.

**`build/` not committed**
Compiled assets are excluded from git. This is standard practice for actively-developed plugins and avoids merge conflicts on generated files. The DDEV setup runs the build automatically, and the README documents the manual step for plain Docker. A CI/CD pipeline (see GitHub Actions workflow) would build and attach artifacts to releases.

**No nonce on the REST endpoint**
The GET endpoint is intentionally public (read-only partner data). REST API write operations would require a nonce or OAuth. If the endpoint is ever extended to support POST/PATCH, `permission_callback` would need updating.

---

## What I Would Improve With More Time

1. **Unit and integration tests** — PHPUnit tests for the meta save/sanitization logic and REST API response shape using the WordPress test suite. Jest tests for the block `edit.js` component.

2. **Front-end pagination** — The block currently renders a single page. Adding a "Load more" button or page navigation with a lightweight JS fetch against the REST API would complete the user experience.

3. **Atomic rate limiting** — Replace transient-based rate limiting with Redis INCR + EXPIRE for correctness under concurrency. Add `Retry-After` header to 429 responses.

4. **Media size registration** — Register a dedicated `partner-logo` image size (e.g., 400×200) so logos are consistently cropped and served at the right dimensions rather than relying on WordPress's generic `medium` size.

5. **Role-based permissions** — Add a custom `manage_partners` capability and map it to the Editor role, so editors can manage partner records without needing Administrator access.

6. **REST API authentication for writes** — If the endpoint is extended to support creating/updating partners via API, add Application Passwords or JWT authentication.

7. **Production deployment** — For a production deploy I would:
   - Run `npm run build` in CI (GitHub Actions) and attach the build artifact to the release
   - Deploy via Bedrock/Composer or a WordPress-aware deployment tool (Deployer, WP Pusher)
   - Enable a persistent object cache (Redis) so transient reads hit memory rather than the database
   - Put the REST endpoint behind a CDN with edge caching, using the `Cache-Control` header
   - Add a staging environment that mirrors production, with deployment gated on test suite passage

---

## AI Usage Notes

### Tools Used

- **Claude (Anthropic)** — Used as the primary AI assistant throughout the project via Claude Code CLI.

### How I Used AI

Claude was used to accelerate the scaffolding of each plugin component: CPT registration, meta box rendering, REST API structure, Gutenberg block setup, DDEV config, and docker-compose. I drove the architecture decisions (taxonomy vs post meta, dynamic vs static block, transient caching strategy) before asking Claude to implement them, so the AI was working from a clear spec rather than making structural choices independently.

### What I Changed or Reviewed

- **Rate limiting IP detection** — Claude's initial draft trusted `X-Forwarded-For` unconditionally. I changed it to require an explicit `PARTNER_DIR_TRUST_PROXY` constant in `wp-config.php`, preventing IP spoofing on installs that are not behind a reverse proxy.
- **Logo save validation** — The first version used `absint()` alone on the logo attachment ID. I added `wp_attachment_is_image()` to confirm the ID is an actual image attachment before saving, closing a vector where an arbitrary post ID could be stored.
- **REST endpoint `permission_callback`** — Claude initially omitted the `permission_callback` key, which triggers a `_doing_it_wrong` notice in WordPress 5.5+. I changed it to the explicit `'__return_true'` for a public read endpoint.
- **Cache busting** — The original cache bust used `delete_transient()` with a hardcoded key. I rewrote it to use a direct `$wpdb` query that deletes all transients matching the `partner_api*` prefix, so every cached page/category combination is invalidated when any partner changes.
- **DDEV setup script** — Claude's first draft did not check whether WordPress was already installed before running `wp core install`, so re-running `ddev start` would error. I wrapped each step in an idempotency guard.

### How I Verified Correctness

- Reviewed every generated file line-by-line before accepting it.
- Traced the data flow manually: form submit → `save_post_partner` hook → sanitization → `update_post_meta` → REST query → transient → JSON response.
- Verified the block registration pattern against the WordPress Block Editor Handbook (dynamic blocks, `ServerSideRender`, `block.json` asset references).
- Checked that `register_block_type` receiving a path to `block.json` correctly wires up the `editorScript` and `style` handles without manual `wp_register_script` calls.
- Confirmed the `.gitignore` paths are relative to the git repo root (inside `partner-directory/`), not the parent directory.

### AI Limitations or Mistakes

**Misplaced `.gitignore`** — Claude placed `.gitignore` in the parent directory (`AFC/`) rather than inside `partner-directory/` where the git repository was actually initialized. The paths inside also referenced `partner-directory/node_modules/` instead of the correct relative `node_modules/`. I caught this by running `find . -name .git` to confirm the repo root, then deleted and rewrote the file in the correct location with correct paths.

### Security & Maintainability Review

The following security points were checked in all AI-generated code:

- **Input sanitization** — All `$_POST` values are sanitized before use: `sanitize_key()` on the nonce field, `absint()` on integer IDs, `esc_url_raw()` + `filter_var(FILTER_VALIDATE_URL)` on the website URL, `sanitize_title()` on the REST API category parameter.
- **Output escaping** — All output uses the correct escaping function for context: `esc_html()` for text nodes, `esc_url()` for `href` attributes, `esc_attr()` for HTML attributes, `wp_get_attachment_image()` (which escapes internally) for logo images.
- **Nonce verification** — `wp_verify_nonce()` is called before any meta is saved, and the result is checked with strict comparison before proceeding.
- **Capability checks** — `current_user_can('edit_post', $post_id)` is verified in the meta save callback. The REST endpoint uses an explicit `permission_callback` rather than omitting it.
- **SQL injection** — All database queries go through WordPress APIs (`WP_Query`, `update_post_meta`, `get_post_meta`). The one raw `$wpdb->query()` call in cache busting uses `$wpdb->prepare()` with `$wpdb->esc_like()` for the LIKE pattern.
