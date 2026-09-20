# Assets Folder

## /icons/
`icons.svg` — SVG sprite sheet with all project icons.

### How to use in PHP files:
First include the sprite once, right after <body> opens (done in header.php):
```html
<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/FoodRescueHub/assets/icons/icons.svg'; ?>
```

Then use any icon anywhere:
```html
<svg width="20" height="20" aria-hidden="true">
  <use href="/FoodRescueHub/assets/icons/icons.svg#icon-food"></use>
</svg>
```

### Available icon IDs:
- `#icon-food`      — basket / food
- `#icon-donor`     — gift box
- `#icon-ngo`       — handshake / charity
- `#icon-volunteer` — delivery truck
- `#icon-admin`     — shield
- `#icon-check`     — checkmark / delivered
- `#icon-clock`     — expiry / time
- `#icon-pin`       — location pin
- `#icon-alert`     — urgent warning
- `#icon-stats`     — bar chart / reports

## /images/
Place your project images here (screenshots, logo, food photos for listings).
Recommended naming: `logo.png`, `hero-bg.jpg`, `default-food.jpg`
