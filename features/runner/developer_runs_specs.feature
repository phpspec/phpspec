Feature: Developer runs the specs
  As a Developer
  I want to run the specs
  In order to get feedback on a state of my application

  Scenario: Running a spec with a class that doesn't exist causes failure
    Given I have started describing the "Runner/SpecExample1/Markdown" class
    When I run phpspec
    Then I should see "class Runner\SpecExample1\Markdown does not exist"

  Scenario: Running a spec with a correctly implemented class causes successs
    Given I have an example that contains:
      """
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      """
    And the object being specified contains:
      """
          public function toHtml($text)
          {
              return sprintf('<p>%s</p>', $text);
          }
      """
    When I run phpspec
    Then the example should pass
