# Browser Testing

PhpSpec includes a lightweight HTTP client for testing web endpoints. Use `visit()`, `get()`, `post()`, `put()`, `patch()`, or `delete()` to make requests and assert on the response.

## Configuration

Add `base_url` to your `phpspec.json`:

```json
{
    "base_url": "http://localhost:8080"
}
```

## Quick Example

```php
<?php

describe('User API', function () {
    it('returns a user by id', function () {
        $response = get('/api/users/1');

        expect($response)->toBeOk();
        expect($response)->toHaveStatus(200);
        expect($response->json['name'])->toBe('Chuck Norris');
        expect($response)->toHavePath('data.user.name', 'Chuck Norris');
    });

    it('creates a user', function () {
        $response = post('/api/users', json: ['name' => 'Chuck']);

        expect($response)->toHaveStatus(201);
        expect($response)->toHavePath('name', 'Chuck');
    });

    it('updates a user', function () {
        $response = put('/api/users/1', json: ['name' => 'Updated']);

        expect($response)->toBeOk();
    });

    it('patches a user', function () {
        $response = patch('/api/users/1', json: ['name' => 'Patched']);

        expect($response)->toBeOk();
    });

    it('deletes a user', function () {
        $response = delete('/api/users/1');

        expect($response)->toHaveStatus(204);
    });
});
```

## Making Requests

| Function | Description |
|---|---|
| `visit(string $path)` | GET request (alias for `get()`) |
| `get(string $path)` | GET request |
| `post(string $path, array $options = [])` | POST request |
| `put(string $path, array $options = [])` | PUT request |
| `patch(string $path, array $options = [])` | PATCH request |
| `delete(string $path, array $options = [])` | DELETE request |

### Request Options

The `$options` array supports:

| Key | Type | Description |
|---|---|---|
| `json` | `array` | JSON-encoded as the request body; sets `Content-Type: application/json` |
| `body` | `string` | Raw request body |
| `headers` | `array` | Additional HTTP headers (`name => value`) |

### Response Object

All functions return a `Response` with the following properties:

| Property | Type | Description |
|---|---|---|
| `$response->status` | `int` | HTTP status code |
| `$response->body` | `string` | Raw response body |
| `$response->json` | `array` | Body decoded as JSON (lazy, `json_decode(..., true)`) |
| `$response->headers` | `array` | Response headers (`name => value`) |

## Response Matchers

### `toBeOk()`

Asserts the response status is `200`.

```php
expect($response)->toBeOk();
```

### `toBeBad()`

Asserts the response status is `400`.

```php
expect($response)->toBeBad();
```

### `toHaveStatus(int $code)`

Asserts the response status matches the given code.

```php
expect($response)->toHaveStatus(201);
expect($response)->toHaveStatus(404);
```

### `toHavePath(string $path, mixed $expected)`

Asserts a value at a dot-notation path in the JSON body.

```php
expect($response)->toHavePath('data.user.name', 'Chuck Norris');
expect($response)->toHavePath('meta.total', 42);
```

### `toHaveHeader(string $name, ?string $value = null)`

Asserts the response has a header. Optionally checks its value.

```php
expect($response)->toHaveHeader('Content-Type');
expect($response)->toHaveHeader('Content-Type', 'application/json');
expect($response)->not()->toHaveHeader('X-Debug');
```

### `toRedirectTo(string $url)`

Asserts the response is a 3xx redirect with a matching `Location` header.

```php
expect($response)->toRedirectTo('/dashboard');
expect($response)->toRedirectTo('https://example.com/login');
```

## Existing Matchers That Work on Response Properties

You don't need special matchers for everything — use the ones you already know:

```php
// Body
expect($response->body)->toContain('hello');
expect($response->body)->toMatch('/id":\s*\d+/');

// JSON
expect($response->json)->toHaveKey('name');
expect($response->json)->toContain('Chuck Norris');
expect($response->json['items'])->toHaveCount(3);

// Headers
expect($response->headers)->toHaveKey('Content-Type');
expect($response->headers)->toContain('application/json');

// Direct value access
expect($response->json['name'])->toBe('Chuck Norris');
expect($response->status)->toBeGreaterThan(199);
```

## PSR-7 Support

The response matchers (`toBeOk`, `toBeBad`, `toHaveStatus`, `toHavePath`) also work with any PSR-7 `ResponseInterface`. No hard dependency is required — install `psr/http-message` and the matchers detect it automatically:

```bash
composer require psr/http-message
```

This means Symfony and Laravel responses work out of the box via their PSR-7 bridges:

```php
// Symfony: use symfony/psr-http-message-bridge
$psr7Response = $psrFactory->createResponse($symfonyResponse);
expect($psr7Response)->toBeOk();
expect($psr7Response)->toHavePath('data.name', 'Chuck');

// Laravel: use nyholm/psr7
$psr7Response = app('Psr\Http\Message\ResponseInterface');
expect($psr7Response)->toHaveStatus(201);
```
