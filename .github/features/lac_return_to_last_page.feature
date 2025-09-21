# features/lac_return_to_last_page.feature

Feature: Return to Last Page after Consent
  As a guest user,
  I want to be returned to the page I was trying to access after confirming my age,
  So that my browsing experience is seamless and not disruptive.

  Background:
    Given a Piwigo gallery is set up
    And the Legal Age Consent plugin is active
    And a specific photo page exists at "/index.php?/photo/123"
    And a specific album page exists at "/index.php?/category/45"

  Scenario: Guest is returned to their specific photo page after consent
    Given I am a guest user who has not yet given age consent
    When I try to navigate to the photo page at "/index.php?/photo/123"
    Then I should be redirected to the age confirmation page
    When I confirm my age on that page
    Then I should be redirected back to the photo page at "/index.php?/photo/123"
    And I should see the content of that photo

  Scenario: Guest is returned to the main gallery if they visit the consent page directly
    Given I am a guest user who has not yet given age consent
    When I navigate directly to the age confirmation page
    And I confirm my age on that page
    Then I should be redirected to the main gallery page

  Scenario: The system remembers the latest intended destination
    Given I am a guest user who has not yet given age consent
    When I try to navigate to the album page at "/index.php?/category/45"
    Then I should be redirected to the age confirmation page
    # The user navigates away or hesitates, then tries to access something else
    When I then try to navigate to the photo page at "/index.php?/photo/123"
    Then I should be redirected to the age confirmation page again
    When I confirm my age on that page
    # The user should be sent to the most recent page they tried to access
    Then I should be redirected back to the photo page at "/index.php?/photo/123"