@tool @tool_gradesort
Feature: Sorting a grade category
  In order to keep a gradebook readable as a course changes
  As a teacher
  I need to sort a grade category's contents without items escaping their category

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | numsections |
      | Course 1 | C1        | topics | 3           |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    # Created deliberately out of order: new grade items are appended to the end
    # of the gradebook, so creation order becomes gradebook order.
    And the following "activities" exist:
      | activity | course | name    | section | idnumber |
      | assign   | C1     | Charlie | 3       | a3       |
      | assign   | C1     | Bravo   | 2       | a2       |
      | assign   | C1     | Alpha   | 1       | a1       |

  @javascript
  Scenario: Sorting a category alphabetically reorders the gradebook
    Given I am on the "Course 1" "Course" page logged in as "teacher1"
    When I navigate to "Setup > Grade category sort" in the course gradebook
    And I set the following fields to these values:
      | Category to sort | Course 1     |
      | Sort by          | Alphabetically |
    And I press "Preview sort"
    Then I should see "Alpha"
    And I should see "Bravo"
    When I press "Apply sort"
    Then I should see "Reordered"
    And "Alpha" "text" should appear before "Bravo" "text"
    And "Bravo" "text" should appear before "Charlie" "text"
