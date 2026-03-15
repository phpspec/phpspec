Feature: Mocking
  As a developer
  I want to create test doubles for collaborators
  So that I can test objects in isolation

  Scenario: Creating a mock from an interface
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger)->toBeAnInstanceOf(App\Logger::class);
      """
    When I run the spec
    Then all examples should pass

  Scenario: Verifying a method was called
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('hello'))->toBeCalled();
      $logger->log('hello');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Verifying a method was called with specific arguments
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('hello'))->toBeCalledWith('hello');
      $logger->log('hello');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Verifying call count
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;

      interface Counter {
          public function increment(): void;
      }
      """
    And a spec with example:
      """
      $counter = mock(App\Counter::class);
      expect($counter->increment())->toBeCalledTimes(3);
      $counter->increment();
      $counter->increment();
      $counter->increment();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Stubbing a return value
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(1))->toReturn('Alice');
      expect($repo->find(1))->toBe('Alice');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Stubbing with a callback for dynamic returns
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(1))->toReturnUsing(fn($id) => $id === 1 ? 'Alice' : null);
      expect($repo->find(1))->toBe('Alice');
      expect($repo->find(999))->toBeNull();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Argument matchers
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('msg'))->toBeCalledWith(any());
      $logger->log('hello world');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Negated mock expectations
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('anything'))->not()->toBeCalled();
      """
    When I run the spec
    Then all examples should pass

  Scenario: instanceOf argument matcher
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a class "src/App/Event.php":
      """
      <?php
      namespace App;

      class Event {
          public function __construct(public string $name) {}
      }
      """
    And an interface "src/App/EventBus.php":
      """
      <?php
      namespace App;

      interface EventBus {
          public function dispatch(Event $event): void;
      }
      """
    And a spec with example:
      """
      $bus = mock(App\EventBus::class);
      expect($bus->dispatch(new App\Event('test')))->toBeCalledWith(anInstanceOf(App\Event::class));
      $bus->dispatch(new App\Event('created'));
      """
    When I run the spec
    Then all examples should pass

  Scenario: cetera argument matcher matches trailing args
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $level, string $message, string $context = ''): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('info', 'msg'))->toBeCalledWith('info', cetera());
      $logger->log('info', 'hello world', 'extra');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Argument-based stub dispatch with different args
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(1))->toReturn('Alice');
      allow($repo->find(2))->toReturn('Bob');
      expect($repo->find(1))->toBe('Alice');
      expect($repo->find(2))->toBe('Bob');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Catch-all stub with specific override
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find())->toReturn('default');
      allow($repo->find(42))->toReturn('special');
      expect($repo->find(42))->toBe('special');
      expect($repo->find(99))->toBe('default');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Fluent call count with once
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;

      interface Counter {
          public function increment(): void;
      }
      """
    And a spec with example:
      """
      $counter = mock(App\Counter::class);
      expect($counter->increment())->toBeCalled()->once();
      $counter->increment();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Fluent call count with exactly N times
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;

      interface Counter {
          public function increment(): void;
      }
      """
    And a spec with example:
      """
      $counter = mock(App\Counter::class);
      expect($counter->increment())->toBeCalled()->exactly(3)->times();
      $counter->increment();
      $counter->increment();
      $counter->increment();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Using argument matchers in stub setup
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;
      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(any()))->toReturn('fallback');
      allow($repo->find(42))->toReturn('special');
      expect($repo->find(42))->toBe('special');
      expect($repo->find(99))->toBe('fallback');
      """
    When I run the spec
    Then all examples should pass

  Scenario: let() values are fresh per example
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;
      interface Counter {
          public function increment(): void;
      }
      """
    And a spec file "spec/App/LetResetSpec.spec.php":
      """
      <?php
      describe('let reset', function() {
          let('counter', fn(App\Counter $counter) => $counter);

          it('first example calls increment', function() {
              expect($this->counter->increment())->toBeCalled()->once();
              $this->counter->increment();
          });

          it('second example gets fresh mock', function() {
              expect($this->counter->increment())->not()->toBeCalled();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Type-hinted mock injection in closures
    Given an interface "src/App/Mailer.php":
      """
      <?php
      namespace App;

      interface Mailer {
          public function send(string $to, string $body): void;
      }
      """
    And a spec file "spec/App/MailerSpec.spec.php":
      """
      <?php
      use App\Mailer;

      describe('Mailer injection', function () {
          it('injects mocks by type hint', function (Mailer $mailer) {
              expect($mailer)->toBeAnInstanceOf(Mailer::class);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
