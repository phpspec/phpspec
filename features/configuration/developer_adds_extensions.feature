Feature: Developer enables extensions
  As a Developer
  I want to enable and configure extensions
  In order to customize or add features to phpspec behavior

  Scenario: Adding parametrized extensions with correct config
    Given the class file "src/Configuration/Extension2.php" contains:
      """
      <?php

      namespace Configuration;

      class Extension2 implements \PhpSpec\Extension
      {
          public function load(\PhpSpec\ServiceContainer $container, array $params)
          {
              throw new \Exception(get_class().' enabled'. print_r($params, true));
          }
      }

      """
    And the config file contains:
      """
      extensions:
          Configuration\Extension2: ~
          Configuration\Extension2: [testParam]
      """
    When I run phpspec
    Then I should see "Extension2 enabled"
    And I should see "testParam"

  Scenario: Adding parametrized extensions with incorrect config
    Given the class file "src/Configuration/Extension3.php" contains:
      """
      <?php

      namespace Configuration;

      class Extension3 implements \PhpSpec\Extension
      {
          public function load(\PhpSpec\ServiceContainer $container, array $params)
          {
              throw new \Exception(get_class().' enabled'. print_r($params, true));
          }
      }

      """
    And the config file contains:
      """
      extensions:
          Configuration\Extension3: test
      """
    When I run phpspec
    Then I should see "Extension configuration must be an array or null"

  Scenario: Adding a non existent class as extension
    Given the class file "src/Configuration/Extension4.php" contains:
      """
      <?php

      namespace Configuration;

      class NOPE implements \PhpSpec\Extension
      {
          public function load(\PhpSpec\ServiceContainer $container, array $params)
          {
              throw new \Exception(get_class().' enabled'. print_r($params, true));
          }
      }

      """
    And the config file contains:
      """
      extensions:
          Configuration\Extension4: test
          Configuration\Extension4: [nope]
          Configuration\Extension4: ~
      """
    When I run phpspec
    Then I should see "Extension class `Configuration\Extension4` does not exist"

  Scenario: Adding parametrized extensions without parameters
    Given the class file "src/Configuration/Extension4.php" contains:
      """
      <?php

      namespace Configuration;

      class Extension4 implements \PhpSpec\Extension
      {
          public function load(\PhpSpec\ServiceContainer $container, array $params)
          {
              throw new \Exception(get_class().' enabled'. print_r($params, true));
          }
      }

      """
    And the config file contains:
      """
      extensions:
          Configuration\Extension4: ~
      """
    When I run phpspec
    Then I should see "Extension4 enabled"
