# TukerIn

REST API for the TukerIn app: a marketplace where users post items to sell or trade, save posts, chat with each other, and review completed trades.

Built with Laravel 13 (PHP 8.3+).

- [Getting started](#getting-started)
- [Conventions](#conventions)
- [Users](#users)
- [Posts](#posts)
- [Reviews and ratings](#reviews-and-ratings)
- [Saved posts](#saved-posts)
- [Categories](#categories)
- [Chat](#chat)

## Getting started

Requirements: PHP 8.3 or newer, Composer, MySQL (or SQLite for local testing), and Node.js 20.19+ if you want to build the frontend assets.

```bash
composer install
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Set the database credentials in `.env`. Set `GEOAPIFY_API_KEY` too: creating a post calls the [Geoapify](https://www.geoapify.com/) reverse-geocoding API to work out the city from its coordinates, and fails without a key.

```bash
php artisan migrate
```

```bash
php artisan db:seed --class=PostCategorySeeder
```

```bash
php artisan serve
```

The API is then served at `http://localhost:8000/api`.

To run the tests:

```bash
vendor/bin/phpunit
```

## Conventions

- **Base URL:** every endpoint below is relative to `{APP_URL}/api`.
- **Request bodies:** send `application/x-www-form-urlencoded`, or `multipart/form-data` when uploading images. Send multiple images as `image[]`.
- **Errors** are always JSON. Validation errors come back as `422` with an `errors` object:

  ```json
  {
    "message": "The email field is required.",
    "errors": { "email": ["The email field is required."] }
  }
  ```

- **Authentication:** see [below](#authentication).
- **Rate limit:** 60 requests per minute per IP.
- **Images:** uploaded files are stored under `public/images`. Build the URL from the returned file name:
  - post images: `{APP_URL}/images/{image_name}`
  - chat attachments: `{APP_URL}/images/message/{image}`
- **Response shape:** most responses are JSON with a `message` string, plus the payload under a named key (`user`, `post`, `posts`, `data`, …).

### Authentication

Every endpoint except [register](#register) and [log in](#log-in) needs the user's `api_token`, which those two endpoints return. Send it as a bearer token:

```
Authorization: Bearer {api_token}
```

| Status | When |
|---|---|
| `401` `{"message": "Unauthenticated."}` | The token is missing or wrong. |
| `403` | The request acts on another user's data. For example, `user_id`, `sender_id` or the `{userId}` in the path is not the logged-in user, the post belongs to someone else, or the user is not part of the chat. |

The token is only included in the register and login responses. User objects returned anywhere else (`author`, `receiver`, …) never contain it.

Paginated endpoints return 10 items per page, accept a `?page=` query parameter, and include:

```json
"pagination": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 25 }
```

### Post publish status

`is_published` is an integer that controls where a post appears:

| Value | Shown in |
|---|---|
| `0` | Hidden. Set automatically once the post has been reviewed. |
| `1`, `2` | The public feed and search (`GET /post/all`, `GET /post/search`). |
| `2`, `3`, `4` | A peer's active posts (`GET /post/active/{peerId}`). |

Change it with [`POST /post/update/publish/status`](#change-publish-status).

## Users

### Register

`POST /user/register`

| Field | Rules |
|---|---|
| `name` | required, string, max 255 |
| `email` | required, email, max 255 |
| `password` | required, string, min 8 |
| `phone` | required, string, max 14 |

```bash
curl -X POST http://localhost:8000/api/user/register \
  -H "Accept: application/json" \
  -d name="Budi" -d email="budi@example.com" -d password="secret123" -d phone="081234567890"
```

**201**:

```json
{
  "message": "User created successfully",
  "user": { "id": 1, "name": "Budi", "email": "budi@example.com", "phone": "081234567890", "api_token": "…" }
}
```

**409**: `Email already exists`

Keep the `api_token`: it authenticates every other request.

### Log in

`POST /user/login`

| Field | Rules |
|---|---|
| `email` | required, email |
| `password` | required, string |

**200**: `{ "message": "User logged in successfully", "user": { …, "api_token": "…" } }`

A user who has no token yet is issued one on login.

**401**: `{ "message": "Invalid credentials", "user": null }`

### Update profile

`POST /user/update`

| Field | Rules |
|---|---|
| `id` | required, integer: must be the logged-in user |
| `name` | required, string, max 255 |
| `email` | required, email, max 255 |
| `photo_path` | optional, string, max 255: saved as `profile_photo_path` |

**200**: `{ "message": "User updated successfully", "user": { … } }`

**400**: `User not found`

### Get a user

`GET /user/get/{id}`

Returns the user plus `rating` (average from the `rating` table) and `trx_points`.

**200**: `{ "message": "User found", "user": { …, "rating": 4.5, "trx_points": 120 } }`

**400**: `User not found`

### Get a user's review rating

`GET /user/rating/{id}`

Summarises the reviews a user received as post owner. `points` also counts reviews the user wrote.

**200**:

```json
{ "message": "Rating found", "name": "Budi", "count": 3, "rating": 4.33, "points": 1500 }
```

## Posts

### Create a post

`POST /post/create` (`multipart/form-data`)

| Field | Rules |
|---|---|
| `user_id` | required, integer: the author, must be the logged-in user |
| `title` | required, string, max 255 |
| `content` | required, string |
| `price` | required, integer |
| `type` | required, string |
| `can_trade_in` | required, integer (`0` or `1`) |
| `latitude` | required, numeric |
| `longitude` | required, numeric |
| `image` or `image[]` | required: jpeg, png, jpg, gif, svg or webp |

`city` is filled in from the coordinates through Geoapify. New posts start with `status` `active` and `is_premium` `0`. Double quotes are stripped from `title`, `content` and `type`.

```bash
curl -X POST http://localhost:8000/api/post/create \
  -H "Authorization: Bearer $API_TOKEN" \
  -F user_id=1 -F title="Sepeda lipat" -F content="Masih mulus" -F price=1500000 \
  -F type="barang" -F can_trade_in=1 -F latitude=-6.2 -F longitude=106.8 \
  -F "image[]=@photo1.jpg" -F "image[]=@photo2.jpg"
```

**201**: `{ "message": "Post created successfully", "post": { … }, "first_image": { … } }`

### Upload more images to a post

`POST /post/uploadImage` (`multipart/form-data`). Only the post's owner can do this.

| Field | Rules |
|---|---|
| `post_id` | required, integer |
| `image` or `image[]` | required: jpeg, png, jpg, gif or svg, max 2 MB each |

**201**: `{ "message": "Image uploaded successfully", "image": [ { "post_id": 1, "image_name": "…" } ] }`

### Feed

`GET /post/all/{userId}?page=1`

Published posts from everyone except `{userId}`, newest first, with premium posts first within each page. Each post includes `thumnail` (first image) and `author`. Paginated.

### My posts

`GET /my/post/all/{userId}?page=1`

All of the user's own posts, newest first, whatever their publish status. Paginated.

### Get a post

`GET /post/get/{id}`

**200**: the post plus `address` (city), `images` (list of file names), `author_name`, `author_email` and `author_phone`.

**400**: `Post not found`

### Search

`GET /post/search/{query}/{userId}?page=1`

Published posts whose title contains `{query}`, excluding posts by `{userId}`. Paginated.

### Search suggestions

`GET /post/search/suggestion/{query}/{userId}`

Up to 10 distinct matching titles.

**200**: `{ "suggestions": [ { "title": "Sepeda lipat" } ] }`

### Update a post

`PUT /post/update`. Only the post's owner can do this.

| Field | Rules |
|---|---|
| `post_id` | required, integer |
| `title` | required, string, max 255 |
| `content` | required, string |
| `status` | required, integer |
| `is_premium` | required, integer |
| `latitude` | required, numeric |
| `longitude` | required, numeric |

**200**: `{ "message": "Post updated successfully", "post": { … } }`

**400**: `Post not found`

### Delete a post

`DELETE /post/delete/{id}`

Soft-deletes the post and its images. Only the post's owner can do this.

**200**: `{ "message": "Post deleted successfully", "post": { … } }`

### Change publish status

`POST /post/update/publish/status`

Sets `is_published` and the trade peer (`peer_id`) of a post. See [Post publish status](#post-publish-status).

| Field | Rules |
|---|---|
| `post_id` | required, integer |
| `peer_id` | required, integer: the user trading with the owner |
| `publish_status_id` | required, integer: the new `is_published` value |

**200**: `{ "message": "Post set active successfully", "post": { … } }`

**404**: `Post not found`

### A peer's active posts

`GET /post/active/{peerId}`

Posts where `{peerId}` is the trade peer and `is_published` is 2–4. Each post includes `thumnail` and `author`, and the author includes their average `rating`.

## Reviews and ratings

### Review a post

`POST /post/review`

| Field | Rules |
|---|---|
| `user_id` | required, integer: the reviewer, must be the logged-in user |
| `post_id` | required, integer |
| `rating` | required, integer |
| `review` | string |

The reviewer earns points worth 1% of the post's price. The post is then marked `reviewed` and hidden (`is_published` = `0`).

**201**: `{ "message": "Review created successfully", "review": { … } }`

**409**: `Post already reviewed`

### Reviews of a post

`GET /post/review/{postId}`

Each review includes `user`, `post` and `post_owner`.

**200**: `{ "message": "Reviews found", "reviews": [ … ] }`

## Saved posts

### Save a post

`POST /post/save`

| Field | Rules |
|---|---|
| `post_id` | required, integer |
| `user_id` | required, integer: must be the logged-in user |

**200**: `Post saved successfully`

**409**: `Post already saved, please check your saved post`

### List saved posts

`GET /post/saved/{userId}`. `{userId}` must be the logged-in user.

**200**: `{ "message": "Saved posts found", "posts": [ … ] }`. Each post includes `thumnail` and `author`.

### Remove a saved post

`DELETE /post/saved/delete/{userId}/{postId}`. `{userId}` must be the logged-in user.

**200**: `Post unsaved successfully`

**404**: `Post not found`

## Categories

### Top-level categories

`GET /post/category/all`

**200**: `{ "message": "Categories found", "categories": [ { "id": 1, "parent_id": null, "name": "…", "description": "…" } ] }`

### Sub-categories

`GET /post/category/{parentId}`

Categories whose `parent_id` is `{parentId}`.

Both endpoints return `200` with an empty list and the message `Categories not found` when there is nothing to show.

## Chat

### List a user's chats

`GET /message/chats/{userId}`. `{userId}` must be the logged-in user.

Chats where the user is the sender or the receiver, most recently active first. Each chat includes `receiver` and `last_message`.

**200**: `{ "message": "Chats retrieved successfully", "data": [ … ] }`

### Start a chat

`POST /message/create`

| Field | Rules |
|---|---|
| `user_id` | required, existing user ID: must be the logged-in user |
| `receiver_id` | required, existing user ID |
| `context` | optional: ID of the post the chat is about |

**201**: `{ "message": "Chat created successfully", "data": { … } }`

### Messages in a chat

`GET /message/messages/{chatId}`. Only the two users in the chat can read it.

Each message includes `attachments`, a list of `{ "image": "…" }`.

**200**: `{ "message": "Messages retrieved successfully", "data": [ … ] }`

### Send a message

`POST /message/send` (`multipart/form-data` when attaching images)

| Field | Rules |
|---|---|
| `chat_id` | required, existing chat ID: the sender must be part of it |
| `sender_id` | required, existing user ID: must be the logged-in user |
| `receiver_id` | required, existing user ID |
| `message` | string |
| `image[]` | optional: jpeg, png, jpg, gif or svg |

**201**: `{ "message": "Message sent successfully", "data": { … } }`

### Attach images to a message

`POST /message/image/upload` (`multipart/form-data`). Only the message's sender can do this.

| Field | Rules |
|---|---|
| `message_id` | required, existing message ID |
| `image[]` | required: jpeg, png, jpg, gif or svg, max 2 MB each |

Use this to add images to a message that was already sent. You can also send images directly with `POST /message/send`.

**201**: `{ "message": "Image uploaded successfully", "data": [ { "message_id": 1, "image": "…" } ] }`

### Mark messages as read

`PUT /message/read/{chatId}/{userId}`

Marks every message in the chat that was sent **to** `{userId}` as read. `{userId}` must be the logged-in user.

**200**: `Message read successfully`
