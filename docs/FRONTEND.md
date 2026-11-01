# Kanlungan — Frontend Rules

This document is the source of truth for frontend work. Claude, read this
before generating any Blade, CSS, or JS. Teammates, read this before
opening a PR.

## Stack constraints

- Laravel 10, PHP 8.1+
- Blade templates (classic `@extends` / `@section`, NOT `<x-layout>` components)
- Vanilla CSS in `public/css/`
- Vanilla JS in `public/js/`
- **No Tailwind.** Tailwind classes render as nothing in this project.
- **No React, Vue, Alpine, Livewire, jQuery.** Cropper.js 1.6.2 is the sole
  approved third-party UI library, loaded only on the profile page for avatar cropping.
- **No build step.** Do not use Vite. Link CSS/JS manually from Blade.
- **No npm packages that require a build.**

Cropper.js is loaded from pinned cdnjs URLs:

- CSS: `https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css`
- JS: `https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js`

For deployments that cannot use a CDN, download those same two files and place
them under `public/vendor/cropperjs/`, then update the profile page asset URLs
to `vendor/cropperjs/cropper.min.css` and `vendor/cropperjs/cropper.min.js`.

## Design tokens

Defined in `public/css/base.css`. Always use these, never hardcode colors.

```css
:root {
    --surface-bg: #f5f3ee;
    --surface-card: #ffffff;
    --surface-hover: #eceae3;
    --border-default: #d8d4cc;
    --accent: #1a6b4a;
    --accent-dark: #0f4a33;
    --accent-soft: #e6f0ea;
    --text-primary: #1e1e1e;
    --text-muted: #6a6a6a;
    --danger: #b3261e;
    --warning: #b26a00;
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
}
```

## Visual language

- Calm, soft, campus-safe. Not clinical, not corporate, not playful.
- Cards: white surface, 12–16px radius, subtle 1px border, no heavy shadow.
- Spacing: generous. 16px card padding, 12px between items.
- Font: system UI / Segoe UI / Tahoma. No Google Fonts.
- Icons: inline SVG only. No emoji. No icon fonts. No external libs.
- Mobile-first: design at 375px width, scale up.

## Anti-patterns
Never use:

- Tailwind utility classes
- Purple or blue gradients
- Pill buttons everywhere (use them only for filter chips; post categories use non-pill tags)
- Centered hero text
- Emoji as icons
- Default browser scrollbars (style them or hide them)
- Rounded-everything (only cards and chips get radius)
- Comic Sans
- Anything that looks like a hospital form or a therapy app

## Reusable components
Create these once, reuse everywhere.

Blade components in `resources/views/components/`:

- `post-card.blade.php` — feed post card
- `comment-card.blade.php` — comment item
- `category-chip.blade.php` — All / Ask / Share / Help / Vent
- `course-chip.blade.php` — BSIT / BSMLS / BSN / Other
- `report-sheet.blade.php` — bottom sheet with 8 reasons
- `alert-item.blade.php` — notification list item
- `points-bar.blade.php` — points + badge progress
- `empty-state.blade.php` — no posts / no alerts / no bookmarks
- `toast.blade.php` — slow mode, success, error
- `anon-toggle.blade.php` — anonymity switch with alias preview
Layouts in `resources/views/layouts/`:

- `guest.blade.php` — login, register
- `community.blade.php` — authenticated, floating bottom nav (Home / Search / Post / Activity / Me); Activity contains notification and community-verdict tabs

## Data shape contract
Even with dummy data, controllers must pass arrays shaped like this.
When we swap to Eloquent, the Blade files don't change.

### Feed post
php

```
[
    'id' => 1,
    'author_alias' => 'Anonymous Falcon #4821',
    'is_anonymous' => true,
    'category' => 'Ask',
    'course' => 'BSIT',
    'subject' => 'ITPM',
    'body' => 'How to become SME?',
    'likes' => 12,
    'comments' => 4,
    'time_ago' => '5m',
    'is_bookmarked' => false,
    'is_helpful' => false,
]
```

### Comment
php

```
[
    'id' => 1,
    'post_id' => 1,
    'author_alias' => 'Anonymous Owl #1093',
    'is_anonymous' => true,
    'body' => 'Try checking the handbook.',
    'is_helpful' => true,
    'is_accepted' => false,
    'time_ago' => '1h',
]
```

### Notification
php

```
[
    'id' => 1,
    'type' => 'comment',
    'title' => 'New comment on your post',
    'preview' => 'Try checking the...',
    'time_ago' => '2m',
    'unread' => true,
]
```

## Route map (static for now)
php

```
Route::view('/', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::view('/feed', 'feed.index')->name('feed');
Route::view('/post/create', 'feed.create')->name('post.create');
Route::view('/post/{id}', 'feed.show')->name('post.show');
Route::view('/bookmarks', 'bookmarks.index')->name('bookmarks');
Route::view('/alerts', 'alerts.index')->name('alerts');
Route::view('/me', 'profile.index')->name('profile');
Route::view('/moderation', 'moderation.index')->name('moderation');
Route::view('/moderation/history', 'moderation.history')->name('moderation.history');
```

Replace with controllers when auth and DB are wired.

Active community reports are reviewed and voted on at `/reports`, not in the main feed.

## Commit conventions
Use Conventional Commits. Examples:

text

```
feat(ui): add feed screen from wireframe
feat(ui): add report bottom sheet component
style(css): define design tokens in base.css
chore(ui): scaffold empty layouts for guest and app
```

## What NOT to do

- Don't install Tailwind, Vite, or npm packages.
- Don't rename existing files or folders without flagging it in an issue.
- Don't commit `.env`, `vendor/`, or any real student data.
- Don't build a feature that isn't in a GitHub Issue.
