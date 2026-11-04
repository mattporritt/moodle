@mod @mod_lti
Feature: LTI endpoints called by other servers do not start a session
  In order to keep tool-facing endpoints stateless
  As a site owner
  I need those endpoints to send no cookie and no PHP debug output

  Scenario Outline: A sessionless endpoint sets no cookie
    Then the LTI endpoint "<path>" should respond with "<contenttype>" and set no cookie

    Examples:
      | path                              | contenttype      |
      | mod/lti/certs.php                 | application/json |
      | mod/lti/openid-configuration.php  | application/json |
      | mod/lti/services.php              | application/json |
      | mod/lti/token.php                 | text/html        |
      | mod/lti/service.php               | text/html        |
      | mod/lti/openid-registration.php   | text/html        |
