Feature: BDD workflow
  As a developer
  I want a full spec-first development cycle
  So that I can let my specs drive the design

  Scenario: Red-green cycle — method generation then implementation
    Given a PSR-4 project with "spec" and "src" directories
    And a class "src/App/Greeter.php":
      """
      <?php
      namespace App;

      class Greeter {}
      """
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php
      use App\Greeter;

      describe(Greeter::class, function () {
          it('greets by name', function () {
              $greeter = new Greeter();
              expect($greeter->greet('World'))->toBe('Hello, World!');
          });
      });
      """
    When I run phpspec run and answer "y" to generation prompts
    Then the class "src/App/Greeter.php" should contain "function greet"
    When I implement the greet method to return "Hello, World!"
    And I run phpspec run
    Then all examples should pass

  Scenario: Story-to-spec cycle — feature reveals missing class
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/greeting.feature":
      """
      Feature: Greeting
        Scenario: Say hello
          Given I have a greeting service
          When I greet "World"
          Then I should see "Hello, World!"
      """
    And a step file "features/steps/greeting.steps.php":
      """
      <?php
      given("I have a greeting service", function () {
          $this->greeter = new \App\Greeter();
      });

      when("I greet {string}", function (string $name) {
          $this->result = $this->greeter->greet($name);
      });

      then("I should see {string}", function (string $expected) {
          expect($this->result)->toBe($expected);
      });
      """
    When I run phpspec run "features/" and answer "y" to generation prompts
    Then a spec and class for "App\Greeter" should be generated
    When I implement the Greeter class
    And I run phpspec run "features/"
    Then all steps should pass

  Scenario: London School — mock collaborators, test in isolation
    Given a PSR-4 project with "spec" and "src" directories
    And an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?array;
      }
      """
    And a class "src/App/UserService.php":
      """
      <?php
      namespace App;

      class UserService {
          public function __construct(private UserRepository $repo) {}

          public function getDisplayName(int $id): string {
              $user = $this->repo->find($id);
              return $user ? $user['name'] : 'Unknown';
          }
      }
      """
    And a spec file "spec/App/UserService.spec.php":
      """
      <?php
      use App\UserService;
      use App\UserRepository;

      describe(UserService::class, function () {
          it('returns the user display name', function (UserRepository $repo) {
              allow($repo->find(1))->toReturn(['name' => 'Alice']);
              $service = new UserService($repo);
              expect($service->getDisplayName(1))->toBe('Alice');
          });

          it('returns Unknown for missing user', function (UserRepository $repo) {
              allow($repo->find(999))->toReturn(null);
              $service = new UserService($repo);
              expect($service->getDisplayName(999))->toBe('Unknown');
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
