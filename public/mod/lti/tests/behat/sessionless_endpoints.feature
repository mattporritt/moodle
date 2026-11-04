@mod @mod_lti
Feature: LTI endpoints called by other servers do not start a session
  In order to keep tool-facing endpoints stateless
  As a site owner
  I need those endpoints to send no cookie and no PHP debug output

  Scenario Outline: A sessionless endpoint sets no cookie
    Then the LTI endpoint "<path>" should respond with status "<status>" and "<contenttype>" and set no cookie

    Examples:
      | path                              | status | contenttype      |
      | mod/lti/certs.php                 | 200    | application/json |
      | mod/lti/openid-configuration.php  | 200    | application/json |
      | mod/lti/services.php              | 405    | application/json |
      | mod/lti/token.php                 | 400    | text/html        |
      | mod/lti/service.php               | 200    | text/html        |
      | mod/lti/openid-registration.php   | 401    | text/html        |
