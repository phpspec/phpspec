Feature: Browser testing
  As a developer
  I want to assert on HTTP responses
  So that I can test web endpoints expressively

  Background:
    Given any previous HTTP server is stopped

  Scenario: Status 200 with toBeOk
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(200, '');
      expect($response)->toBeOk();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Status 400 with toBeBad
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(400, '');
      expect($response)->toBeBad();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Exact status code with toHaveStatus
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(201, '');
      expect($response)->toHaveStatus(201);
      expect($response)->not()->toHaveStatus(200);
      """
    When I run the spec
    Then all examples should pass

  Scenario: JSON path with toHavePath
    Given a spec with example:
      """
      $body = json_encode(['data' => ['user' => ['name' => 'Chuck', 'age' => 30]]]);
      $response = new \PhpSpec\Browser\Response(200, $body);
      expect($response)->toHavePath('data.user.name', 'Chuck');
      expect($response)->toHavePath('data.user.age', 30);
      expect($response)->not()->toHavePath('data.user.name', 'Bruce');
      expect($response)->not()->toHavePath('missing.path', 'value');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Header existence with toHaveHeader
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(200, '', ['Content-Type' => 'application/json']);
      expect($response)->toHaveHeader('Content-Type');
      expect($response)->not()->toHaveHeader('X-Missing');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Header value matching with toHaveHeader
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(200, '', ['Content-Type' => 'application/json']);
      expect($response)->toHaveHeader('Content-Type', 'application/json');
      expect($response)->not()->toHaveHeader('Content-Type', 'text/html');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Redirect with toRedirectTo
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(302, '', ['Location' => '/dashboard']);
      expect($response)->toRedirectTo('/dashboard');
      expect($response)->not()->toRedirectTo('/login');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Redirect fails for non-3xx status
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(200, '', ['Location' => '/page']);
      expect($response)->not()->toRedirectTo('/page');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Lazy JSON decoding
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(200, json_encode(['items' => [1, 2, 3]]));
      expect($response->json)->toHaveKey('items');
      expect($response->json['items'])->toHaveCount(3);
      expect($response->json['items'])->toContain(2);
      """
    When I run the spec
    Then all examples should pass

  Scenario: Non-JSON body returns empty json array
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(200, 'plain text');
      expect($response->json)->toBe([]);
      expect($response->body)->toContain('plain');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Response properties with existing matchers
    Given a spec with example:
      """
      $body = json_encode(['name' => 'Chuck', 'role' => 'admin']);
      $response = new \PhpSpec\Browser\Response(200, $body, ['Content-Type' => 'application/json']);
      expect($response->status)->toBe(200);
      expect($response->body)->toContain('Chuck');
      expect($response->headers)->toHaveKey('Content-Type');
      expect($response->json['name'])->toBe('Chuck');
      expect($response->json)->toHaveCount(2);
      """
    When I run the spec
    Then all examples should pass

  Scenario: Negated response matchers
    Given a spec with example:
      """
      $response = new \PhpSpec\Browser\Response(404, '');
      expect($response)->not()->toBeOk();
      expect($response)->not()->toBeBad();
      expect($response)->not()->toHaveStatus(200);
      expect($response)->not()->toRedirectTo('/anywhere');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Matchers reject non-response objects
    Given a spec with example:
      """
      expect(42)->not()->toBeOk();
      expect('hello')->not()->toHaveStatus(200);
      expect(new \stdClass())->not()->toBeBad();
      expect([])->not()->toHavePath('key', 'value');
      expect(null)->not()->toHaveHeader('X-Any');
      expect(true)->not()->toRedirectTo('/url');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Chained response matchers
    Given a spec with example:
      """
      $body = json_encode(['status' => 'ok']);
      $response = new \PhpSpec\Browser\Response(200, $body, ['Content-Type' => 'application/json']);
      expect($response)->toBeOk()->toHaveStatus(200)->toHaveHeader('Content-Type')->toHavePath('status', 'ok');
      """
    When I run the spec
    Then all examples should pass

  Scenario: All 3xx redirect codes
    Given a spec with example:
      """
      foreach ([301, 302, 303, 307, 308] as $code) {
          $response = new \PhpSpec\Browser\Response($code, '', ['Location' => '/target']);
          expect($response)->toRedirectTo('/target');
      }
      """
    When I run the spec
    Then all examples should pass

  # -- Live HTTP server scenarios ----------------------------------------

  Scenario: visit() makes a GET request to the server
    Given a local HTTP server with router:
      """
      <?php
      header('Content-Type: application/json');
      echo json_encode(['message' => 'hello from server']);
      """
    And a spec with example:
      """
      $response = visit('/');
      expect($response)->toBeOk();
      expect($response)->toHavePath('message', 'hello from server');
      """
    When I run the spec
    Then all examples should pass

  Scenario: get() returns JSON and headers
    Given a local HTTP server with router:
      """
      <?php
      header('Content-Type: application/json');
      header('X-Custom: test-value');
      echo json_encode(['users' => [['name' => 'Chuck'], ['name' => 'Bruce']]]);
      """
    And a spec with example:
      """
      $response = get('/api/users');
      expect($response)->toBeOk();
      expect($response)->toHaveHeader('X-Custom', 'test-value');
      expect($response)->toHavePath('users', [['name' => 'Chuck'], ['name' => 'Bruce']]);
      expect($response->json['users'])->toHaveCount(2);
      """
    When I run the spec
    Then all examples should pass

  Scenario: post() sends JSON body
    Given a local HTTP server with router:
      """
      <?php
      $input = json_decode(file_get_contents('php://input'), true);
      header('Content-Type: application/json');
      http_response_code(201);
      echo json_encode(['created' => $input['name'] ?? 'unknown']);
      """
    And a spec with example:
      """
      $response = post('/api/users', ['json' => ['name' => 'Chuck']]);
      expect($response)->toHaveStatus(201);
      expect($response)->toHavePath('created', 'Chuck');
      """
    When I run the spec
    Then all examples should pass

  Scenario: put() sends a replacement payload
    Given a local HTTP server with router:
      """
      <?php
      $method = $_SERVER['REQUEST_METHOD'];
      $input = json_decode(file_get_contents('php://input'), true);
      header('Content-Type: application/json');
      echo json_encode(['method' => $method, 'name' => $input['name'] ?? null]);
      """
    And a spec with example:
      """
      $response = put('/api/users/1', ['json' => ['name' => 'Updated']]);
      expect($response)->toBeOk();
      expect($response)->toHavePath('method', 'PUT');
      expect($response)->toHavePath('name', 'Updated');
      """
    When I run the spec
    Then all examples should pass

  Scenario: patch() sends a partial update
    Given a local HTTP server with router:
      """
      <?php
      $method = $_SERVER['REQUEST_METHOD'];
      $input = json_decode(file_get_contents('php://input'), true);
      header('Content-Type: application/json');
      echo json_encode(['method' => $method, 'field' => $input['field'] ?? null]);
      """
    And a spec with example:
      """
      $response = patch('/api/users/1', ['json' => ['field' => 'patched']]);
      expect($response)->toBeOk();
      expect($response)->toHavePath('method', 'PATCH');
      expect($response)->toHavePath('field', 'patched');
      """
    When I run the spec
    Then all examples should pass

  Scenario: delete() sends a DELETE request
    Given a local HTTP server with router:
      """
      <?php
      $method = $_SERVER['REQUEST_METHOD'];
      header('Content-Type: application/json');
      http_response_code(204);
      """
    And a spec with example:
      """
      $response = delete('/api/users/1');
      expect($response)->toHaveStatus(204);
      """
    When I run the spec
    Then all examples should pass

  Scenario: Server returns 404 for unknown route
    Given a local HTTP server with router:
      """
      <?php
      header('Content-Type: application/json');
      http_response_code(404);
      echo json_encode(['error' => 'not found']);
      """
    And a spec with example:
      """
      $response = get('/unknown');
      expect($response)->toHaveStatus(404);
      expect($response)->not()->toBeOk();
      expect($response)->toHavePath('error', 'not found');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Server redirect followed by toRedirectTo
    Given a local HTTP server with router:
      """
      <?php
      header('Location: /dashboard', true, 302);
      """
    And a spec with example:
      """
      $response = get('/old-page');
      expect($response)->toRedirectTo('/dashboard');
      expect($response)->toHaveStatus(302);
      """
    When I run the spec
    Then all examples should pass
