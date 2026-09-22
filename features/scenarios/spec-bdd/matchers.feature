Feature: Matchers
  As a developer
  I want a rich set of matchers for assertions
  So that I can express expectations clearly

  Scenario: Identity matcher
    Given a spec with example:
      """
      expect(42)->toBe(42);
      expect('hello')->toBe('hello');
      expect(true)->toBe(true);
      """
    When I run the spec
    Then all examples should pass

  Scenario: Equality matcher
    Given a spec with example:
      """
      expect('5')->toBeLike(5);
      expect(0)->toBeLike(false);
      """
    When I run the spec
    Then all examples should pass

  Scenario: Boolean matchers
    Given a spec with example:
      """
      expect(true)->toBeTrue();
      expect(false)->toBeFalse();
      expect(null)->toBeNull();
      expect([])->toBeEmpty();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Type matchers
    Given a spec with example:
      """
      expect('hello')->toBeOfType('string');
      expect(42)->toBeOfType('int');
      expect(3.14)->toBeOfType('float');
      expect(fn() => null)->toBeCallable();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Instance matcher
    Given a spec with example:
      """
      expect(new \stdClass())->toBeAnInstanceOf(\stdClass::class);
      expect(new \RuntimeException('x'))->toBeAnInstanceOf(\Exception::class);
      """
    When I run the spec
    Then all examples should pass

  Scenario: String matchers
    Given a spec with example:
      """
      expect('Hello, World!')->toContain('World');
      expect('Hello, World!')->toStartWith('Hello');
      expect('Hello, World!')->toEndWith('World!');
      expect('abc123')->toMatch('/\d{3}/');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Collection matchers
    Given a spec with example:
      """
      expect([1, 2, 3])->toHaveCount(3);
      expect([1, 2, 3])->toContain(2);
      expect(['name' => 'Alice'])->toHaveKey('name');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Comparison matchers
    Given a spec with example:
      """
      expect(10)->toBeGreaterThan(5);
      expect(3)->toBeLessThan(7);
      """
    When I run the spec
    Then all examples should pass

  Scenario: Object property matcher
    Given a spec with example:
      """
      $obj = new \stdClass();
      $obj->name = 'Alice';
      expect($obj)->toHaveProperty('name');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Exception matcher
    Given a spec with example:
      """
      expect(fn() => throw new \RuntimeException('boom'))->toThrow(\RuntimeException::class);
      expect(fn() => throw new \RuntimeException('boom'))->toThrow(\RuntimeException::class, 'boom');
      """
    When I run the spec
    Then all examples should pass

  Scenario: The exception matcher runs its callable where it is written
    Given a spec with example:
      """
      $path = tempnam(sys_get_temp_dir(), 'phpspec');
      file_put_contents($path, '{broken');
      try {
          expect(function () use ($path) {
              $raw = is_file($path) ? (string) file_get_contents($path) : '';

              return $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
          })->toThrow(\JsonException::class);
      } finally {
          unlink($path);
      }
      """
    When I run the spec
    Then all examples should pass

  Scenario: Negated matchers
    Given a spec with example:
      """
      expect(5)->not()->toBe(3);
      expect('hello')->not()->toBeEmpty();
      expect([1, 2])->not()->toContain(99);
      expect(42)->not()->toBeNull();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Custom matchers
    Given a spec with example:
      """
      addMatcher('toBeEven', fn($n) => $n % 2 === 0, 'Expected %s to be even');
      expect(4)->toBeEven();
      expect(3)->not()->toBeEven();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Chained expectations
    Given a spec with example:
      """
      expect('hello')->toBeOfType('string')->toStartWith('h')->toEndWith('o');
      """
    When I run the spec
    Then all examples should pass
