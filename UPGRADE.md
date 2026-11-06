# Upgrade guide: API authentication

This guide is for anyone who maintains a client of the TukerIn API, such as the mobile app. It describes every change from [bayutb123/TukerIn#9](https://github.com/bayutb123/TukerIn/pull/9) that a client can notice, and what to do about each one.

**In short:** every endpoint except register and login now needs the user's `api_token` in an `Authorization` header. Requests also can't act on another user's data any more. A client that doesn't send the header gets `401` on almost every call.

- [Checklist](#checklist)
- [Rollout order](#rollout-order)
- [1. Send the token on every request](#1-send-the-token-on-every-request)
- [2. `api_token` is removed from user objects](#2-api_token-is-removed-from-user-objects)
- [3. Requests can only act on your own data](#3-requests-can-only-act-on-your-own-data)
- [4. Error responses changed](#4-error-responses-changed)
- [5. Endpoint changes](#5-endpoint-changes)
- [What did not change](#what-did-not-change)
- [Known limitations](#known-limitations)
- [QA checklist](#qa-checklist)

## Checklist

- [ ] Read `user.api_token` from the **register** and **login** responses, and store it.
- [ ] Send `Authorization: Bearer {api_token}` on every request except register and login.
- [ ] On `401`, clear the stored session and send the user to the login screen.
- [ ] Handle `403`: always send the logged-in user's own ID in `user_id`, `sender_id`, `{userId}`, etc.
- [ ] Stop reading `api_token` from any other response (`author`, `receiver`, `user`, …). It is no longer there.
- [ ] Parse error bodies as JSON. Errors that used to come back as HTML (302 or 500) are now JSON.
- [ ] When attaching images with `POST /message/image/upload`, send them as `image[]`.

## Rollout order

The old API ignores the `Authorization` header, so a client can start sending it before the server changes. Deploy in this order and no user loses access:

1. **Release an app version that sends the token** (step 1 below). It keeps working against the current API.
2. **Wait until most users have updated.** If the app has a minimum-version or force-update mechanism, raise the minimum to that version.
3. **Deploy the API** from PR #9.

Old app versions that don't send the header stop working at step 3: every call except register and login returns `401`.

> No database migration is needed. The `users.api_token` column already exists and every user registered through the API already has a token.

## 1. Send the token on every request

### Getting the token

The register and login responses already returned the token. Their shape is unchanged:

```json
POST /api/user/login

{
  "message": "User logged in successfully",
  "user": {
    "id": 1,
    "name": "Budi",
    "email": "budi@example.com",
    "api_token": "Xq3…80 characters…"
  }
}
```

- `POST /api/user/register` returns the same `user.api_token` (status `201`).
- A user who somehow has no token (for example, an account created directly in the database) is issued one when they log in.
- The token does not change between logins, and it does not expire.

**Users who are already logged in** don't need to log in again if the app stored the `user` object from the login response, because that object already contains `api_token`. If the app stored only some fields (for example `id` and `name`), it has to ask those users to log in again after updating.

### Sending the token

Add the header to every request except `POST /api/user/register` and `POST /api/user/login`:

```
Authorization: Bearer {api_token}
```

```bash
curl http://localhost:8000/api/post/all/1 \
  -H "Authorization: Bearer $API_TOKEN"
```

The simplest way is one place in the HTTP client that adds the header to every request, such as an interceptor or a default header, rather than adding it to each call.

> The server also accepts the token as an `api_token` query or form parameter (`?api_token=…`). Don't use this: URLs end up in logs and proxies. Use the header.

### Handling 401

Any protected endpoint returns this when the header is missing, or the token doesn't match a user:

```json
HTTP 401
{ "message": "Unauthenticated." }
```

Treat it as "logged out": clear the stored user and token, and show the login screen.

### Which endpoints need the token

| Needs the token | Endpoints |
|---|---|
| No | `POST /user/register`, `POST /user/login` |
| Yes | Every other endpoint under `/api`, including read-only ones such as `GET /post/category/all`, `GET /post/all/{userId}` and `GET /post/get/{id}`. |

## 2. `api_token` is removed from user objects

**Before:** every serialized user included its `api_token`. Any logged-in user could read other users' tokens from the feed (`posts[].author.api_token`), search results, reviews, chats, and so on. Now that the token authenticates requests, that would let anyone take over any account.

**After:** `api_token` is returned **only** in the register and login responses, and only for the user who is logging in. It is missing from every other user object:

| Endpoint | Field that no longer has `api_token` |
|---|---|
| `GET /user/get/{id}` | `user` |
| `POST /user/update` | `user` |
| `GET /post/all/{userId}`, `GET /my/post/all/{userId}`, `GET /post/search/…`, `GET /post/saved/{userId}`, `GET /post/active/{peerId}` | `posts[].author` |
| `GET /post/review/{postId}` | `reviews[].user`, `reviews[].post_owner` |
| `GET /message/chats/{userId}` | `data[].receiver` |

If the app ever read the current user's token from one of these responses, for example by refreshing the stored user from `GET /user/get/{id}`, keep the token from login instead. Otherwise the refresh overwrites the stored token with nothing.

## 3. Requests can only act on your own data

Previously the API trusted whatever IDs the client sent, so any client could, for example, edit another user's profile or read another user's chats. Now the API compares those IDs with the user that owns the token, and returns `403` when they don't match:

```json
HTTP 403
{ "message": "You can only act on your own account." }
```

For chat endpoints the message is `"You are not a participant of this chat."`.

### Rules per endpoint

"Logged-in user" means the owner of the token in the `Authorization` header.

| Endpoint | Rule (otherwise `403`) |
|---|---|
| `POST /user/update` | `id` must be the logged-in user. |
| `POST /post/create` | `user_id` must be the logged-in user. |
| `POST /post/uploadImage` | The post (`post_id`) must belong to the logged-in user. |
| `PUT /post/update` | The post (`post_id`) must belong to the logged-in user. |
| `DELETE /post/delete/{id}` | The post must belong to the logged-in user. |
| `POST /post/review` | `user_id` (the reviewer) must be the logged-in user. |
| `POST /post/save` | `user_id` must be the logged-in user. |
| `GET /post/saved/{userId}` | `{userId}` must be the logged-in user. |
| `DELETE /post/saved/delete/{userId}/{postId}` | `{userId}` must be the logged-in user. |
| `GET /message/chats/{userId}` | `{userId}` must be the logged-in user. |
| `POST /message/create` | `user_id` must be the logged-in user. |
| `GET /message/messages/{chatId}` | The logged-in user must be one of the chat's two users. |
| `POST /message/send` | `sender_id` must be the logged-in user, and they must be one of the chat's two users. |
| `POST /message/image/upload` | The message must have been sent by the logged-in user. |
| `PUT /message/read/{chatId}/{userId}` | `{userId}` must be the logged-in user. |
| `POST /post/update/publish/status` | `peer_id` must be the logged-in user. Only the post's owner or its current peer may set a status, except `2` (`TRANSACTION_PENDING`), which anyone may set to start a trade. Added after PR #9; see the [README](README.md#change-publish-status). |

These endpoints only need a valid token. They don't check whose data it is:

- `GET /user/get/{id}`, `GET /user/rating/{id}`: profiles and ratings are public to logged-in users.
- `GET /post/all/{userId}`, `GET /my/post/all/{userId}`, `GET /post/get/{id}`, `GET /post/search/…`, `GET /post/search/suggestion/…`, `GET /post/active/{peerId}`, `GET /post/review/{postId}`, `GET /post/category/…`.

A well-behaved client already sends the logged-in user's own ID everywhere, and won't see any `403`. If one shows up during testing, it points to a bug in the app, such as a stale or wrong user ID.

## 4. Error responses changed

Errors are now always JSON for `/api/*`, whether or not the client sends `Accept: application/json`. Several requests that used to crash now return a proper error.

Each "Before" value below was observed on `main` and each "After" value on the PR branch, with the same data:

| Request | Before | After |
|---|---|---|
| Any protected endpoint without a token | `200` (no auth) | `401` `{"message":"Unauthenticated."}` |
| Validation error **without** `Accept: application/json` (e.g. empty login) | `302` redirect to an HTML page | `422` `{"message":"…","errors":{…}}` |
| Validation error **with** `Accept: application/json` | `422` JSON | `422` JSON (unchanged) |
| Unknown route, e.g. `GET /api/nope` | `404` HTML page | `404` JSON |
| `GET /message/messages/{chatId}` for a chat that doesn't exist | `200` with `"data": []` | `404` `{"message":"Chat not found"}` |
| `DELETE /post/delete/{id}` for a post that doesn't exist | `500` HTML | `400` `{"message":"Post not found","post":null}` |
| `POST /post/review` for a post that doesn't exist | `500` HTML | `404` `{"message":"Post not found"}` |
| `POST /post/uploadImage` for a post that doesn't exist | `201`, images saved for a post ID that doesn't exist | `404` `{"message":"Post not found"}` |
| `POST /user/update` without `photo_path` | `500` HTML | `200`, profile updated |
| `POST /post/review` without `review` | `500` HTML | `201`, review created |
| `POST /message/image/upload` | `500` (endpoint was broken) | `201`, see [below](#post-messageimageupload) |

If the app showed a generic error for any "Before" `500` above, those calls now succeed or return a normal error message that can be shown to the user.

> On a production server (`APP_DEBUG=false`) error bodies contain just `message` (and `errors` for 422). With `APP_DEBUG=true`, `exception`, `file` and `trace` are added too, so never enable debug on production.

## 5. Endpoint changes

### `PUT /message/read/{chatId}/{userId}`

The route was declared as `/message/read/{userId}/{chatId}`, but the controller always read the first segment as the chat ID and the second as the user ID. Laravel passes path segments by position, not by name. The route is now declared as `/message/read/{chatId}/{userId}`, so the names match what it does.

**There is no behaviour change for callers.** Any client that worked before already sent the chat ID first, and keeps working. A client that sent the user ID first never worked: it marked messages in the wrong chat, or nothing at all. Now it typically gets a `403`, because the second segment isn't the logged-in user.

```bash
curl -X PUT http://localhost:8000/api/message/read/12/1 \
  -H "Authorization: Bearer $API_TOKEN"
# chat 12, logged-in user 1: marks every message in chat 12 sent to user 1 as read
```

### `POST /message/image/upload`

**Before:** the route existed, but the controller method it pointed to didn't, so it always returned `500`.

**After:** it attaches images to a message that was already sent. Only that message's sender can use it.

| Field | Rules |
|---|---|
| `message_id` | required, an existing message sent by the logged-in user |
| `image[]` | required, **must be an array**: jpeg, png, jpg, gif or svg, max 2 MB each |

```bash
curl -X POST http://localhost:8000/api/message/image/upload \
  -H "Authorization: Bearer $API_TOKEN" \
  -F message_id=34 \
  -F "image[]=@photo1.jpg" -F "image[]=@photo2.jpg"
```

```json
HTTP 201
{
  "message": "Image uploaded successfully",
  "data": [
    { "message_id": 34, "image": "341762412345_0.jpg", "id": 7, "created_at": "…", "updated_at": "…" }
  ]
}
```

Sending a single file as `image` instead of `image[]` returns `422` `"The image field must be an array."`. Uploaded images appear in the message's `attachments` from `GET /message/messages/{chatId}`, and are served from `{APP_URL}/images/message/{image}`.

Sending images directly with `POST /message/send` still works as before.

## What did not change

- **URLs and methods**: every endpoint keeps its path and HTTP method.
- **Request fields**: no field was added, removed or renamed. The only exception is that `POST /message/image/upload`, which never worked, now requires `image[]` as an array.
- **Success response shapes**: apart from `api_token` disappearing from user objects ([section 2](#2-api_token-is-removed-from-user-objects)), successful responses are unchanged.
- **Register and login**: same requests, same responses.
- **Rate limit**: still 60 requests per minute.

## Known limitations

These are not part of PR #9, but are worth knowing:

- **Tokens never expire or rotate**, and there is no logout endpoint that invalidates a token. "Logging out" in the app means deleting the stored token on the device. A leaked token stays valid until it is changed in the database.
- **Tokens are stored in plain text** in `users.api_token`. Anyone with read access to the database can use them.
- **Tokens issued before this change were exposed.** Before PR #9, every user's token was visible to other users in API responses. Nothing could be done with them while the API didn't check tokens, but they become usable once it does. To be safe, consider giving every user a new token when deploying, for example with `UPDATE users SET api_token = NULL` (or a new random value per user). Users then get a new token at their next login, but must log in again first.

## QA checklist

Test these on the updated app against a server running PR #9:

- [ ] Register a new account, then use the app without logging in again: feed, post detail, create post.
- [ ] Log out, log in, use the app.
- [ ] An already logged-in user updates the app: still logged in, everything works (or, if the app didn't store the token, they are sent to login).
- [ ] Remove or corrupt the stored token: the app shows the login screen instead of crashing or looping.
- [ ] Create, edit and delete your own post; upload extra images to it.
- [ ] Save and unsave a post; list saved posts.
- [ ] Review a post; open reviews and ratings.
- [ ] Start a chat, send messages with and without images, open the chat from the other account, mark messages as read.
- [ ] Update your profile, with and without a photo.
- [ ] Watch the server or app logs during testing: there should be no `401` or `403` responses.
