---
name: vk-news-blog
description: Set up (or replicate) a "news blog" that auto-pulls the latest posts from a VK group at BUILD TIME into a static JSON, with a no-backend, never-fails fallback. Use when the user wants to add a VK-fed news/blog section to a site, port this mechanism to another site/another VK group, or asks "сделай парсинг новостей из ВК", "блог как на noblefarm", "новости из вконтакте на сайт".
---

# vk-news-blog — VK → static news feed (build-time, no backend)

This skill installs a self-contained mechanism that fetches the latest posts from one
VK group and renders them as a news/blog section. It is **stack-agnostic**: the parser is
plain Node, the data is a static JSON file, the UI is a thin consumer you adapt to the
host project's design. Reference implementation: noblefarm.ru (React + Vite + GitHub
Actions + SCP).

## Core idea (do not violate)

1. **Fetch at BUILD time, never in the browser.** The VK token is a secret and must never
   reach client JS. A Node script runs during CI/build, writes a static JSON, and the
   JSON ships as a normal asset.
2. **Three-level fallback — the site never breaks.** Live JSON → seeded array baked into
   the component → empty-but-valid render. Missing token / VK error / rate limit ⇒ the
   script exits `0` and the build continues on stale-or-seed data. *Never* let the fetch
   fail the build.
3. **One group, build-time snapshot.** Not real-time. New posts appear on the next deploy.

If the host site has a real backend and wants live updates, say so — this mechanism is the
no-backend variant; a serverless function on a cron is the alternative (mention it, don't
build it unasked).

## What to ask the user before writing anything

1. **VK group** — URL or screen-name. You must resolve it to a numeric `owner_id`
   (negative for groups). See "Resolving owner_id" below.
2. **VK service token** — they create a Standalone VK app at https://vk.com/apps?act=manage
   → get a **service access token** (read-only wall access is enough). It goes into the
   CI secret store, NOT the repo. Never ask them to paste it into a file.
3. **Categories / hashtags** — how posts should be bucketed (or "no categories, flat
   feed"). This drives `detectCategory()`.
4. **Where the JSON lives** — the host project's static-assets dir (Vite/CRA `public/`,
   Next `public/`, plain site root, etc.).
5. **Deploy pipeline** — GitHub Actions? Other CI? This decides where the fetch step and
   the secret go. If there is no CI, the fallback is `npm run fetch-news` run locally
   before each manual deploy.
6. **Design** — the news cards must match the HOST site's design system, not this one.
   Read the host's existing card/section components and mirror them. Do NOT copy
   noblefarm's Tailwind classes blindly.

Use the host project's CLAUDE.md / design conventions for the UI. This skill owns the
**data pipeline**; the host owns the **look**.

## The four pieces

```
scripts/fetch-vk-posts.js   ← Node parser: VK API → public/vk-news.json
public/vk-news.json         ← generated artifact (gitignore it OR commit as seed)
<NewsComponent>             ← reads the JSON, falls back to a seeded array
CI step + secret            ← runs the parser before build, injects VK_SERVICE_TOKEN
```

### 1. Parser — `scripts/fetch-vk-posts.js`

Copy `reference/fetch-vk-posts.js` from this skill and change:
- `ownerId` → the new group's numeric id (negative).
- `detectCategory()` → the new hashtag→category map (or make it return one flat category).
- post count cap (default 6) and `count=20` window if they want more/fewer.
- title-extraction heuristics only if their posts are structured differently.

Key invariants to preserve when editing:
- Read token from `process.env.VK_SERVICE_TOKEN`; if absent → log + `process.exit(0)`.
- Use `wall.get` with `v=5.131`.
- Skip posts with no text and no photo (a card needs an image); pick the largest photo size.
- Wrap the whole run in try/catch that on error logs a warning and `process.exit(0)` —
  a failed fetch must NEVER fail the deploy.
- Write pretty JSON to the host's static dir.

Add an npm script: `"fetch-news": "node scripts/fetch-vk-posts.js"`.

### 2. JSON artifact — `public/vk-news.json`

It's the contract between parser and UI. Schema (per item):
```json
{ "id": "vk_post_123", "date": "15 мая 2026", "title": "...", "text": "...",
  "image": "https://...", "link": "https://vk.com/wall-123_456", "category": "Stado" }
```
Decide with the user: **gitignore it** (regenerated every deploy, cleaner) OR **commit a
seed copy** (so local `npm run dev` shows real cards without a token). For a brand-new
site, committing one generated snapshot as a seed is friendlier.

### 3. UI consumer — adapt `reference/NewsConsumer.tsx`

This is a PATTERN, not a drop-in. The reference shows the three-level fallback logic:
- A `SEED_DATA` array baked into the component (a few hand-written posts) renders instantly.
- `useEffect` → `fetch('/vk-news.json')`; on ok + non-empty array, map VK items into the
  component's shape and `setPosts`. On any failure, keep `SEED_DATA`.
- Optional: pin a fixed first post; category filter bar.

Rebuild the markup with the HOST site's card components and tokens. Keep only the
data-flow + fallback skeleton.

### 4. CI step + secret (GitHub Actions reference)

In the deploy workflow, BEFORE the build step:
```yaml
      - name: Fetch VK News
        run: npm run fetch-news
        env:
          VK_SERVICE_TOKEN: ${{ secrets.VK_SERVICE_TOKEN }}
      - name: Build
        run: npm run build
```
Then tell the user to add the repo secret:
`Settings → Secrets and variables → Actions → New repository secret → VK_SERVICE_TOKEN`.
For non-GitHub CI, the same two ideas: set the env var from the CI secret store, run
`fetch-news` before `build`.

## Resolving owner_id

A group's `owner_id` is `-1 × group_id`. To get it from a screen-name, call (with the
token) `https://api.vk.com/method/utils.resolveScreenName?screen_name=<name>&access_token=<t>&v=5.131`
→ `object_id` for type `group`; prefix with `-`. Or read it off the group page source.
Verify by hitting `wall.get` once and checking you get items back.

## Verify before claiming done

1. `VK_SERVICE_TOKEN=<token> npm run fetch-news` locally → inspect `vk-news.json` has real
   posts with images and sane titles/categories.
2. Run the dev server → news section shows the live posts; categories filter correctly.
3. Temporarily unset the token and re-run fetch → confirms it exits 0 and the UI falls
   back to seed data (the never-break guarantee).
4. Confirm the token appears ONLY in CI secrets and `process.env`, never in committed files
   or client bundle (`grep -r VK_SERVICE_TOKEN dist/` must be empty).

## Handing this to another chat / another repo

Copy the whole `vk-news-blog/` skill folder into the target repo's `.claude/skills/`.
The target chat invokes it, answers the 6 questions above, and the skill reconstructs the
pipeline against that repo's stack and design. The `reference/` files are starting points
to edit, not files to ship verbatim.
