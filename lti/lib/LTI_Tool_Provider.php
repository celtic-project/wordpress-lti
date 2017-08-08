<?php
/**
 * LTI_Tool_Provider - PHP class to include in an external tool to handle connections with an LTI 1 compliant tool consumer
 * Copyright (C) 2015  Stephen P Vickers
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * Contact: stephen@spvsoftwareproducts.com
 *
 * Version history:
 *   2.0.00  30-Jun-12  Initial release (replacing version 1.1.01 of BasicLTI_Tool_Provider)
 *   2.1.00   3-Jul-12  Added option to restrict use of consumer key based on tool consumer GUID value
 *                      Added field to record day of last access for each consumer key
 *   2.2.00  16-Oct-12  Added option to return parameters sent in last extension request
 *                      Released under GNU Lesser General Public License, version 3
 *   2.3.00   2-Jan-13  Removed autoEnable property from LTI_Tool_Provider class (including constructor parameter)
 *                      Added LTI_Tool_Provider->setParameterConstraint() method
 *                      Changed references to $_REQUEST to $_POST
 *                      Added LTI_Tool_Consumer->getIsAvailable() method
 *                      Deprecated LTI_Context (use LTI_Resource_Link instead), other references to Context deprecated in favour of Resource_Link
 *   2.3.01   2-Feb-13  Added error callback option to LTI_Tool_Provider class
 *                      Fixed typo in setParameterConstraint function
 *                      Updated to use latest release of OAuth dependent library
 *                      Added message property to LTI_Tool_Provider class to override default message returned on error
 *   2.3.02  18-Apr-13  Tightened up checking of roles - now case sensitive and checks fully qualified URN
 *                      Fixed bug with not updating a resource link before redirecting to a shared resource link
 *   2.3.03   5-Jun-13  Altered order of checks in authenticate
 *                      Fixed bug with LTI_Resource_Link->doOutcomesService when a resource link is shared with a different tool consumer
 *                      Separated LTI_User from LTI_Outcome object
 *                      Fixed bug with returned outcome values of zero
 *   2.3.04  13-Aug-13  Ensure nonce values are no longer than 32 characters
 *   2.3.05  29-Jul-14  Added support for ContentItemSelectionRequest message
 *                      Accepts messages with an lti_version of LTI-2p0
 *                      Added data connector for Oracle
 *   2.3.06   5-Aug-14  Fixed bug with OCI data connector
 *   2.4.00  10-Apr-15  Added class methods as alternatives to callbacks
 *                      Added methods for generating signed auto-submit forms for LTI messages
 *                      Added classes for Content-item objects
 *                      Added support for unofficial ConfigureLaunchRequest and DashboardRequest messages
 *   2.5.00  20-May-15  Added LTI_HTTP_Message class to handle the sending of HTTP requests
 *                      Added workflow for automatically assigning resource link ID on first launch of a content-item message created link
 *                      Enhanced checking of parameter values
 *                      Added mediaTypes and documentTargets properties to LTI_Tool_Provider class for ContentItemSelectionRequest messages
 *   2.5.01  11-Mar-16  Fixed bug with saving User before ResourceLink in LTI_Tool_Provider->authenticate()
 *                      Fixed bug with creating a MySQL data connector when a database connector is passed to getDataConnector()
 *                      Added check in OAuth.php that query string is set before extracting the GET parameters
 *                      Added check for incorrect version being passed in lti_version parameter
 */

/**
 * OAuth library file
 */
require_once('OAuth.php');

/**
 * Class to represent an LTI Tool Provider
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Tool_Provider {

/**
 * Default connection error message.
 */
  const CONNECTION_ERROR_MESSAGE = 'Sorry, there was an error connecting you to the application.';

/**
 * LTI version 1 for messages.
 *
 * @deprecated Use LTI_VERSION1 instead
 * @see LTI_Tool_Provider::LTI_VERSION1
 */
  const LTI_VERSION = 'LTI-1p0';
/**
 * LTI version 1 for messages.
 */
  const LTI_VERSION1 = 'LTI-1p0';
/**
 * LTI version 2 for messages.
 */
  const LTI_VERSION2 = 'LTI-2p0';
/**
 * Use ID value only.
 */
  const ID_SCOPE_ID_ONLY = 0;
/**
 * Prefix an ID with the consumer key.
 */
  const ID_SCOPE_GLOBAL = 1;
/**
 * Prefix the ID with the consumer key and context ID.
 */
  const ID_SCOPE_CONTEXT = 2;
/**
 * Prefix the ID with the consumer key and resource ID.
 */
  const ID_SCOPE_RESOURCE = 3;
/**
 * Character used to separate each element of an ID.
 */
  const ID_SCOPE_SEPARATOR = ':';

/**
 *  @var boolean True if the last request was successful.
 */
  public $isOK = TRUE;
/**
 *  @var LTI_Tool_Consumer Tool Consumer object.
 */
  public $consumer = NULL;
/**
 *  @var string Return URL provided by tool consumer.
 */
  public $return_url = NULL;
/**
 *  @var LTI_User User object.
 */
  public $user = NULL;
/**
 *  @var LTI_Resource_Link Resource link object.
 */
  public $resource_link = NULL;
/**
 *  @var LTI_Context Resource link object.
 *
 *  @deprecated Use resource_link instead
 *  @see LTI_Tool_Provider::$resource_link
 */
  public $context = NULL;
/**
 *  @var LTI_Data_Connector Data connector object.
 */
  public $data_connector = NULL;
/**
 *  @var string Default email domain.
 */
  public $defaultEmail = '';
/**
 *  @var int Scope to use for user IDs.
 */
  public $id_scope = self::ID_SCOPE_ID_ONLY;
/**
 *  @var boolean Whether shared resource link arrangements are permitted.
 */
  public $allowSharing = FALSE;
/**
 *  @var string Message for last request processed
 */
  public $message = self::CONNECTION_ERROR_MESSAGE;
/**
 *  @var string Error message for last request processed.
 */
  public $reason = NULL;
/**
 *  @var array Details for error message relating to last request processed.
 */
  public $details = array();

/**
 *  @var string URL to redirect user to on successful completion of the request.
 */
  protected $redirectURL = NULL;
/**
 *  @var string URL to redirect user to on successful completion of the request.
 */
  protected $mediaTypes = NULL;
/**
 *  @var string URL to redirect user to on successful completion of the request.
 */
  protected $documentTargets = NULL;
/**
 *  @var string HTML to be displayed on a successful completion of the request.
 */
  protected $output = NULL;
/**
 *  @var string HTML to be displayed on an unsuccessful completion of the request and no return URL is available.
 */
  protected $error_output = NULL;
/**
 *  @var boolean Whether debug messages explaining the cause of errors are to be returned to the tool consumer.
 */
  protected $debugMode = FALSE;

/**
 *  @var array Callback functions for handling requests.
 */
  private $callbackHandler = NULL;
/**
 *  @var array LTI parameter constraints for auto validation checks.
 */
  private $constraints = NULL;
/**
 *  @var array List of supported message types and associated callback type names
 */
  private $messageTypes = array('basic-lti-launch-request' => 'launch',
                                'ConfigureLaunchRequest' => 'configure',
                                'DashboardRequest' => 'dashboard',
                                'ContentItemSelectionRequest' => 'content-item');
/**
 *  @var array List of supported message types and associated class methods
 */
  private $methodNames = array('basic-lti-launch-request' => 'onLaunch',
                               'ConfigureLaunchRequest' => 'onConfigure',
                               'DashboardRequest' => 'onDashboard',
                               'ContentItemSelectionRequest' => 'onContentItem');
/**
 *  @var array Names of LTI parameters to be retained in the settings property.
 */
  private $lti_settings_names = array('ext_resource_link_content', 'ext_resource_link_content_signature',
                                      'lis_result_sourcedid', 'lis_outcome_service_url',
                                      'ext_ims_lis_basic_outcome_url', 'ext_ims_lis_resultvalue_sourcedids',
                                      'ext_ims_lis_memberships_id', 'ext_ims_lis_memberships_url',
                                      'ext_ims_lti_tool_setting', 'ext_ims_lti_tool_setting_id', 'ext_ims_lti_tool_setting_url');

/**
 * @var array Permitted LTI versions for messages.
 */
  private $LTI_VERSIONS = array(self::LTI_VERSION1, self::LTI_VERSION2);

/**
 * Class constructor
 *
 * @param mixed   $data_connector  Object containing a database connection object (optional, default is a blank prefix and MySQL)
 * @param mixed   $callbackHandler String containing name of callback function for launch request, or associative array of callback functions for each request type
 */
  function __construct($data_connector = '', $callbackHandler = NULL) {

// For backward compatibility the parameters may be in the opposite order, but the recommended practice is to just pass a data connector object and
// override the callback class methods instead of using callback method names.

    $reverse = FALSE;
    if (!is_string($data_connector) || (!is_null($callbackHandler) && !is_string($callbackHandler))) {
      if (is_object($callbackHandler)) {
        $reverse = TRUE;
      } else if (is_array($data_connector) && array_diff_key($data_connector ,array_keys(array_keys($data_connector)))) {
        $reverse = TRUE;
      } else if (!is_array($data_connector) && is_array($callbackHandler)) {
        $reverse = TRUE;
      }
    } else if (!is_null($callbackHandler) && empty($callbackHandler)) {
      $reverse = TRUE;
    }
    if ($reverse) {
      $temp = $callbackHandler;
      $callbackHandler = $data_connector;
      $data_connector = $temp;
    }
    $this->constraints = array();
    $this->context = &$this->resource_link;
    $this->callbackHandler = array();
    if (is_array($callbackHandler)) {
      $this->callbackHandler = $callbackHandler;
      if (isset($this->callbackHandler['connect']) && !isset($this->callbackHandler['launch'])) {  // for backward compatibility
        $this->callbackHandler['launch'] = $this->callbackHandler['connect'];
        unset($this->callbackHandler['connect']);
      }
    } else if (!empty($callbackHandler)) {
      $this->callbackHandler['launch'] = $callbackHandler;
    }
    $this->data_connector = LTI_Data_Connector::getDataConnector($data_connector);
    $this->isOK = !is_null($this->data_connector);
#
### Set debug mode
#
    $this->debugMode = isset($_POST['custom_debug']) && (strtolower($_POST['custom_debug']) == 'true');
#
### Set return URL if available
#
    if (isset($_POST['launch_presentation_return_url'])) {
      $this->return_url = $_POST['launch_presentation_return_url'];
    } else if (isset($_POST['content_item_return_url'])) {
      $this->return_url = $_POST['content_item_return_url'];
    }

  }

/**
 * Process an incoming request
 *
 * @deprecated Use handle_request instead
 * @see LTI_Tool_Provider::$handle_request
 *
 * @return mixed Returns TRUE or FALSE, a redirection URL or HTML
 */
  public function execute() {

    $this->handle_request();

  }

/**
 * Process an incoming request
 *
 * @return mixed Returns TRUE or FALSE, a redirection URL or HTML
 */
  public function handle_request() {

#
### Perform action
#
    if ($this->isOK) {
      if ($this->authenticate()) {
        $this->doCallback();
      }
    }
    $this->result();

  }

/**
 * Add a parameter constraint to be checked on launch
 *
 * @param string $name          Name of parameter to be checked
 * @param boolean $required     True if parameter is required (optional, default is TRUE)
 * @param int $max_length       Maximum permitted length of parameter value (optional, default is NULL)
 * @param array $message_types  Array of message types to which the constraint applies (default is all)
 */
  public function setParameterConstraint($name, $required = TRUE, $max_length = NULL, $message_types = NULL) {

    $name = trim($name);
    if (strlen($name) > 0) {
      $this->constraints[$name] = array('required' => $required, 'max_length' => $max_length, 'messages' => $message_types);
    }

  }

/**
 * Get an array of defined tool consumers
 *
 * @return array Array of LTI_Tool_Consumer objects
 */
  public function getConsumers() {

#
### Initialise data connector
#
    $this->data_connector = LTI_Data_Connector::getDataConnector($this->data_connector);

    return $this->data_connector->Tool_Consumer_list();

  }

/**
 * Get an array of fully qualified user roles
 *
 * @param string Comma-separated list of roles
 *
 * @return array Array of roles
 */
  public static function parseRoles($rolesString) {

    $rolesArray = explode(',', $rolesString);
    $roles = array();
    foreach ($rolesArray as $role) {
      $role = trim($role);
      if (!empty($role)) {
        if (substr($role, 0, 4) != 'urn:') {
          $role = 'urn:lti:role:ims/lis/' . $role;
        }
        $roles[] = $role;
      }
    }

    return $roles;

  }

/**
 * Generate a web page containing an auto-submitted form of parameters.
 *
 * @param string $url     URL to which the form should be submitted
 * @param array  $params  Array of form parameters
 * @param string $target  Name of target (optional)
 */
  public static function sendForm($url, $params, $target = '') {

    $page = <<< EOD
<html>
<head>
<title>IMS LTI message</title>
<script type="text/javascript">
//<![CDATA[
function doOnLoad() {
  document.forms[0].submit();
}

window.onload=doOnLoad;
//]]>
</script>
</head>
<body>
<form action="{$url}" method="post" target="" encType="application/x-www-form-urlencoded">

EOD;

    foreach($params as $key => $value ) {
      $key = htmlentities($key, ENT_COMPAT | ENT_HTML401, 'UTF-8');
      $value = htmlentities($value, ENT_COMPAT | ENT_HTML401, 'UTF-8');
      $page .= <<< EOD
  <input type="hidden" name="{$key}" value="{$value}" />

EOD;

    }

    $page .= <<< EOD
</form>
</body>
</html>
EOD;

    return $page;

  }

###
###  PROTECTED METHODS
###

/**
 * Process a valid launch request
 *
 * @return boolean True if no error
 */
  protected function onLaunch() {

    $this->doCallbackMethod();

  }

/**
 * Process a valid configure request
 *
 * @return boolean True if no error
 */
  protected function onConfigure() {

    $this->doCallbackMethod();

  }

/**
 * Process a valid dashboard request
 *
 * @return boolean True if no error
 */
  protected function onDashboard() {

    $this->doCallbackMethod();

  }

/**
 * Process a valid content-item request
 *
 * @return boolean True if no error
 */
  protected function onContentItem() {

    $this->doCallbackMethod();

  }

/**
 * Process a response to an invalid request
 *
 * @return boolean True if no further error processing required
 */
  protected function onError() {

    $this->doCallbackMethod('error');

  }

###
###  PRIVATE METHODS
###

/**
 * Call any callback function for the requested action.
 *
 * This function may set the redirectURL and output properties.
 *
 * @return boolean True if no error reported
 */
  private function doCallback() {

    $method = $this->methodNames[$_POST['lti_message_type']];
    $this->$method();

  }

/**
 * Call any callback function for the requested action.
 *
 * This function may set the redirectURL and output properties.
 *
 * @param string  $type             Callback type
 *
 * @return boolean True if no error reported
 */
  private function doCallbackMethod($type = NULL) {

    $callback = $type;
    if (is_null($callback)) {
      $callback = $this->messageTypes[$_POST['lti_message_type']];
    }
    if (isset($this->callbackHandler[$callback])) {
      $result = call_user_func($this->callbackHandler[$callback], $this);

#
### Callback function may return HTML, a redirect URL, or a boolean value
#
      if (is_string($result)) {
        if ((substr($result, 0, 7) == 'http://') || (substr($result, 0, 8) == 'https://')) {
          $this->redirectURL = $result;
        } else {
          if (is_null($this->output)) {
            $this->output = '';
          }
          $this->output .= $result;
        }
      } else if (is_bool($result)) {
        $this->isOK = $result;
      }
    } else if (is_null($type) && $this->isOK) {
      $this->isOK = FALSE;
      $this->reason = 'Message type not supported.';
    }

  }

/**
 * Perform the result of an action.
 *
 * This function may redirect the user to another URL rather than returning a value.
 *
 * @return string Output to be displayed (redirection, or display HTML or message)
 */
  private function result() {

    $ok = FALSE;
    if (!$this->isOK) {
      $ok = $this->onError();
    }
    if (!$ok) {
      if (!$this->isOK) {
#
### If not valid, return an error message to the tool consumer if a return URL is provided
#
        if (!empty($this->return_url)) {
          $error_url = $this->return_url;
          if (strpos($error_url, '?') === FALSE) {
            $error_url .= '?';
          } else {
            $error_url .= '&';
          }
          if ($this->debugMode && !is_null($this->reason)) {
            $error_url .= 'lti_errormsg=' . urlencode("Debug error: $this->reason");
          } else {
            $error_url .= 'lti_errormsg=' . urlencode($this->message);
            if (!is_null($this->reason)) {
              $error_url .= '&lti_errorlog=' . urlencode("Debug error: $this->reason");
            }
          }
          if (!is_null($this->consumer) && isset($_POST['lti_message_type']) && ($_POST['lti_message_type'] === 'ContentItemSelectionRequest')) {
            $form_params = array();
            if (isset($_POST['data'])) {
              $form_params['data'] = $_POST['data'];
            }
            $version = (isset($_POST['lti_version'])) ? $_POST['lti_version'] : LTI_Tool_Provider::LTI_VERSION1;
            $form_params = $this->consumer->signParameters($error_url, 'ContentItemSelection', $version, $form_params);
            $page = LTI_Tool_Provider::sendForm($error_url, $form_params);
            echo $page;
          } else {
            header("Location: {$error_url}");
          }
          exit;
        } else {
          if (!is_null($this->error_output)) {
            echo $this->error_output;
          } else if ($this->debugMode && !empty($this->reason)) {
            echo "Debug error: {$this->reason}";
          } else {
            echo "Error: {$this->message}";
          }
        }
      } else if (!is_null($this->redirectURL)) {
        header("Location: {$this->redirectURL}");
        exit;
      } else if (!is_null($this->output)) {
        echo $this->output;
      }
    }

  }

/**
 * Check the authenticity of the LTI launch request.
 *
 * The consumer, resource link and user objects will be initialised if the request is valid.
 *
 * @return boolean True if the request has been successfully validated.
 */
  private function authenticate() {

#
### Get the consumer
#
    $doSaveConsumer = FALSE;
// Check all required launch parameters
    $this->isOK = isset($_POST['lti_message_type']) && array_key_exists($_POST['lti_message_type'], $this->messageTypes);
    if (!$this->isOK) {
      $this->reason = 'Invalid or missing lti_message_type parameter.';
    }
    if ($this->isOK) {
      $this->isOK = isset($_POST['lti_version']) && in_array($_POST['lti_version'], $this->LTI_VERSIONS);
      if (!$this->isOK) {
        $this->reason = 'Invalid or missing lti_version parameter.';
      }
    }
    if ($this->isOK) {
      if (($_POST['lti_message_type'] == 'basic-lti-launch-request') || ($_POST['lti_message_type'] == 'DashboardRequest')) {
        $this->isOK = isset($_POST['resource_link_id']) && (strlen(trim($_POST['resource_link_id'])) > 0);
        if (!$this->isOK) {
          $this->reason = 'Missing resource link ID.';
        }
      } else if ($_POST['lti_message_type'] == 'ContentItemSelectionRequest') {
        if (isset($_POST['accept_media_types']) && (strlen(trim($_POST['accept_media_types'])) > 0)) {
          $mediaTypes = array_filter(explode(',', str_replace(' ', '', $_POST['accept_media_types'])), 'strlen');
          $mediaTypes = array_unique($mediaTypes);
          $this->isOK = count($mediaTypes) > 0;
          if (!$this->isOK) {
            $this->reason = 'No accept_media_types found.';
          } else {
            $this->mediaTypes = $mediaTypes;
          }
        } else {
          $this->isOK = FALSE;
        }
        if ($this->isOK && isset($_POST['accept_presentation_document_targets']) && (strlen(trim($_POST['accept_presentation_document_targets'])) > 0)) {
          $documentTargets = array_filter(explode(',', str_replace(' ', '', $_POST['accept_presentation_document_targets'])), 'strlen');
          $documentTargets = array_unique($documentTargets);
          $this->isOK = count($documentTargets) > 0;
          if (!$this->isOK) {
            $this->reason = 'Missing or empty accept_presentation_document_targets parameter.';
          } else {
            foreach ($documentTargets as $documentTarget) {
              $this->isOK = $this->checkValue($documentTarget, array('embed', 'frame', 'iframe', 'window', 'popup', 'overlay', 'none'),
                 'Invalid value in accept_presentation_document_targets parameter: %s.');
              if (!$this->isOK) {
                break;
              }
            }
            if ($this->isOK) {
              $this->documentTargets = $documentTargets;
            }
          }
        } else {
          $this->isOK = FALSE;
        }
        if ($this->isOK) {
          $this->isOK = isset($_POST['content_item_return_url']) && (strlen(trim($_POST['content_item_return_url'])) > 0);
          if (!$this->isOK) {
            $this->reason = 'Missing content_item_return_url parameter.';
          }
        }
      }
    }
// Check consumer key
    if ($this->isOK) {
      $this->isOK = isset($_POST['oauth_consumer_key']);
      if (!$this->isOK) {
        $this->reason = 'Missing consumer key.';
      }
    }
    if ($this->isOK) {
      $this->consumer = new LTI_Tool_Consumer($_POST['oauth_consumer_key'], $this->data_connector);
      $this->isOK = !is_null($this->consumer->created);
      if (!$this->isOK) {
        $this->reason = 'Invalid consumer key.';
      }
    }
    if ($this->isOK && isset($this->consumer->lti_version)) {
      $this->isOK = $this->consumer->lti_version == $_POST['lti_version'];
      if ($this->debugMode && !$this->isOK) {
        $this->reason = 'Incorrect lti_version parameter.';
      }
    }
    $now = time();
    if ($this->isOK) {
      $today = date('Y-m-d', $now);
      if (is_null($this->consumer->last_access)) {
        $doSaveConsumer = TRUE;
      } else {
        $last = date('Y-m-d', $this->consumer->last_access);
        $doSaveConsumer = $doSaveConsumer || ($last != $today);
      }
      $this->consumer->last_access = $now;
      try {
        $store = new LTI_OAuthDataStore($this);
        $server = new OAuthServer($store);
        $method = new OAuthSignatureMethod_HMAC_SHA1();
        $server->add_signature_method($method);
        $request = OAuthRequest::from_request();
        $res = $server->verify_request($request);
      } catch (Exception $e) {
        $this->isOK = FALSE;
        if (empty($this->reason)) {
          if ($this->debugMode) {
            $consumer = new OAuthConsumer($this->consumer->getKey(), $this->consumer->secret);
            $signature = $request->build_signature($method, $consumer, FALSE);
            $this->reason = $e->getMessage();
            if (empty($this->reason)) {
              $this->reason = 'OAuth exception';
            }
            $this->details[] = 'Timestamp: ' . time();
            $this->details[] = "Signature: {$signature}";
            $this->details[] = "Base string: {$request->base_string}]";
          } else {
            $this->reason = 'OAuth signature check failed - perhaps an incorrect secret or timestamp.';
          }
        }
      }
    }
    if ($this->isOK && $this->consumer->protected) {
      if (!is_null($this->consumer->consumer_guid)) {
        $this->isOK = isset($_POST['tool_consumer_instance_guid']) && !empty($_POST['tool_consumer_instance_guid']) &&
           ($this->consumer->consumer_guid == $_POST['tool_consumer_instance_guid']);
        if (!$this->isOK) {
          $this->reason = 'Request is from an invalid tool consumer.';
        }
      } else {
        $this->isOK = isset($_POST['tool_consumer_instance_guid']);
        if (!$this->isOK) {
          $this->reason = 'A tool consumer GUID must be included in the launch request.';
        }
      }
    }
    if ($this->isOK) {
      $this->isOK = $this->consumer->enabled;
      if (!$this->isOK) {
        $this->reason = 'Tool consumer has not been enabled by the tool provider.';
      }
    }
    if ($this->isOK) {
      $this->isOK = is_null($this->consumer->enable_from) || ($this->consumer->enable_from <= $now);
      if ($this->isOK) {
        $this->isOK = is_null($this->consumer->enable_until) || ($this->consumer->enable_until > $now);
        if (!$this->isOK) {
          $this->reason = 'Tool consumer access has expired.';
        }
      } else {
        $this->reason = 'Tool consumer access is not yet available.';
      }
    }

#
### Validate other message parameter values
#
    if ($this->isOK) {
      if ($_POST['lti_message_type'] != 'ContentItemSelectionRequest') {
        if (isset($_POST['launch_presentation_document_target'])) {
          $this->isOK = $this->checkValue($_POST['launch_presentation_document_target'], array('embed', 'frame', 'iframe', 'window', 'popup', 'overlay'),
             'Invalid value for launch_presentation_document_target parameter: %s.');
        }
      } else {
        if (isset($_POST['accept_unsigned'])) {
          $this->isOK = $this->checkValue($_POST['accept_unsigned'], array('true', 'false'), 'Invalid value for accept_unsigned parameter: %s.');
        }
        if ($this->isOK && isset($_POST['accept_multiple'])) {
          $this->isOK = $this->checkValue($_POST['accept_multiple'], array('true', 'false'), 'Invalid value for accept_multiple parameter: %s.');
        }
        if ($this->isOK && isset($_POST['accept_copy_advice'])) {
          $this->isOK = $this->checkValue($_POST['accept_copy_advice'], array('true', 'false'), 'Invalid value for accept_copy_advice parameter: %s.');
        }
        if ($this->isOK && isset($_POST['auto_create'])) {
          $this->isOK = $this->checkValue($_POST['auto_create'], array('true', 'false'), 'Invalid value for auto_create parameter: %s.');
        }
        if ($this->isOK && isset($_POST['can_confirm'])) {
          $this->isOK = $this->checkValue($_POST['can_confirm'], array('true', 'false'), 'Invalid value for can_confirm parameter: %s.');
        }
      }
    }

#
### Validate message parameter constraints
#
    if ($this->isOK) {
      $invalid_parameters = array();
      foreach ($this->constraints as $name => $constraint) {
        if (empty($constraint['messages']) || in_array($_POST['lti_message_type'], $constraint['messages'])) {
          $ok = TRUE;
          if ($constraint['required']) {
            if (!isset($_POST[$name]) || (strlen(trim($_POST[$name])) <= 0)) {
              $invalid_parameters[] = "{$name} (missing)";
              $ok = FALSE;
            }
          }
          if ($ok && !is_null($constraint['max_length']) && isset($_POST[$name])) {
            if (strlen(trim($_POST[$name])) > $constraint['max_length']) {
              $invalid_parameters[] = "{$name} (too long)";
            }
          }
        }
      }
      if (count($invalid_parameters) > 0) {
        $this->isOK = FALSE;
        if (empty($this->reason)) {
          $this->reason = 'Invalid parameter(s): ' . implode(', ', $invalid_parameters) . '.';
        }
      }
    }

    if ($this->isOK) {
#
### Set the request context/resource link
#
      if (isset($_POST['resource_link_id'])) {
        $content_item_id = '';
        if (isset($_POST['custom_content_item_id'])) {
          $content_item_id = $_POST['custom_content_item_id'];
        }
        $this->resource_link = new LTI_Resource_Link($this->consumer, trim($_POST['resource_link_id']), $content_item_id);
        if (isset($_POST['context_id'])) {
          $this->resource_link->lti_context_id = trim($_POST['context_id']);
        }
        $this->resource_link->lti_resource_id = trim($_POST['resource_link_id']);
        $title = '';
        if (isset($_POST['context_title'])) {
          $title = trim($_POST['context_title']);
        }
        if (isset($_POST['resource_link_title']) && (strlen(trim($_POST['resource_link_title'])) > 0)) {
          if (!empty($title)) {
            $title .= ': ';
          }
          $title .= trim($_POST['resource_link_title']);
        }
        if (empty($title)) {
          $title = "Course {$this->resource_link->getId()}";
        }
        $this->resource_link->title = $title;
// Save LTI parameters
        foreach ($this->lti_settings_names as $name) {
          if (isset($_POST[$name])) {
            $this->resource_link->setSetting($name, $_POST[$name]);
          } else {
            $this->resource_link->setSetting($name, NULL);
          }
        }
// Delete any existing custom parameters
        foreach ($this->resource_link->getSettings() as $name => $value) {
          if (strpos($name, 'custom_') === 0) {
            $this->resource_link->setSetting($name);
          }
        }
// Save custom parameters
        foreach ($_POST as $name => $value) {
          if (strpos($name, 'custom_') === 0) {
            $this->resource_link->setSetting($name, $value);
          }
        }
      }
#
### Set the user instance
#
      $user_id = '';
      if (isset($_POST['user_id'])) {
        $user_id = trim($_POST['user_id']);
      }
      $this->user = new LTI_User($this->resource_link, $user_id);
#
### Set the user name
#
      $firstname = (isset($_POST['lis_person_name_given'])) ? $_POST['lis_person_name_given'] : '';
      $lastname = (isset($_POST['lis_person_name_family'])) ? $_POST['lis_person_name_family'] : '';
      $fullname = (isset($_POST['lis_person_name_full'])) ? $_POST['lis_person_name_full'] : '';
      $this->user->setNames($firstname, $lastname, $fullname);
#
### Set the user email
#
      $email = (isset($_POST['lis_person_contact_email_primary'])) ? $_POST['lis_person_contact_email_primary'] : '';
      $this->user->setEmail($email, $this->defaultEmail);
#
### Set the user roles
#
      if (isset($_POST['roles'])) {
        $this->user->roles = LTI_Tool_Provider::parseRoles($_POST['roles']);
      }
#
### Initialise the consumer and check for changes
#
      $this->consumer->defaultEmail = $this->defaultEmail;
      if ($this->consumer->lti_version != $_POST['lti_version']) {
        $this->consumer->lti_version = $_POST['lti_version'];
        $doSaveConsumer = TRUE;
      }
      if (isset($_POST['tool_consumer_instance_name'])) {
        if ($this->consumer->consumer_name != $_POST['tool_consumer_instance_name']) {
          $this->consumer->consumer_name = $_POST['tool_consumer_instance_name'];
          $doSaveConsumer = TRUE;
        }
      }
      if (isset($_POST['tool_consumer_info_product_family_code'])) {
        $version = $_POST['tool_consumer_info_product_family_code'];
        if (isset($_POST['tool_consumer_info_version'])) {
          $version .= "-{$_POST['tool_consumer_info_version']}";
        }
// do not delete any existing consumer version if none is passed
        if ($this->consumer->consumer_version != $version) {
          $this->consumer->consumer_version = $version;
          $doSaveConsumer = TRUE;
        }
      } else if (isset($_POST['ext_lms']) && ($this->consumer->consumer_name != $_POST['ext_lms'])) {
        $this->consumer->consumer_version = $_POST['ext_lms'];
        $doSaveConsumer = TRUE;
      }
      if (isset($_POST['tool_consumer_instance_guid'])) {
        if (is_null($this->consumer->consumer_guid)) {
          $this->consumer->consumer_guid = $_POST['tool_consumer_instance_guid'];
          $doSaveConsumer = TRUE;
        } else if (!$this->consumer->protected) {
          if ($this->consumer->consumer_guid != $_POST['tool_consumer_instance_guid']) {
            $this->consumer->consumer_guid = $_POST['tool_consumer_instance_guid'];
            $doSaveConsumer = TRUE;
          }
        }
      }
      if (isset($_POST['launch_presentation_css_url'])) {
        if ($this->consumer->css_path != $_POST['launch_presentation_css_url']) {
          $this->consumer->css_path = $_POST['launch_presentation_css_url'];
          $doSaveConsumer = TRUE;
        }
      } else if (isset($_POST['ext_launch_presentation_css_url']) &&
         ($this->consumer->css_path != $_POST['ext_launch_presentation_css_url'])) {
        $this->consumer->css_path = $_POST['ext_launch_presentation_css_url'];
        $doSaveConsumer = TRUE;
      } else if (!empty($this->consumer->css_path)) {
        $this->consumer->css_path = NULL;
        $doSaveConsumer = TRUE;
      }
    }
#
### Persist changes to consumer
#
    if ($doSaveConsumer) {
      $this->consumer->save();
    }

    if ($this->isOK && isset($this->resource_link)) {
#
### Check if a share arrangement is in place for this resource link
#
      $this->isOK = $this->checkForShare();
#
### Persist changes to resource link
#
      $this->resource_link->save();
#
### Save the user instance
#
      if (isset($_POST['lis_result_sourcedid'])) {
        if ($this->user->lti_result_sourcedid != $_POST['lis_result_sourcedid']) {
          $this->user->lti_result_sourcedid = $_POST['lis_result_sourcedid'];
          $this->user->save();
        }
      } else if (!empty($this->user->lti_result_sourcedid)) {
        $this->user->delete();
      }
    }

    return $this->isOK;

  }

/**
 * Check if a share arrangement is in place.
 *
 * @return boolean True if no error is reported
 */
  private function checkForShare() {

    $ok = TRUE;
    $doSaveResourceLink = TRUE;

    $key = $this->resource_link->primary_consumer_key;
    $id = $this->resource_link->primary_resource_link_id;

    $shareRequest = isset($_POST['custom_share_key']) && !empty($_POST['custom_share_key']);
    if ($shareRequest) {
      if (!$this->allowSharing) {
        $ok = FALSE;
        $this->reason = 'Your sharing request has been refused because sharing is not being permitted.';
      } else {
// Check if this is a new share key
        $share_key = new LTI_Resource_Link_Share_Key($this->resource_link, $_POST['custom_share_key']);
        if (!is_null($share_key->primary_consumer_key) && !is_null($share_key->primary_resource_link_id)) {
// Update resource link with sharing primary resource link details
          $key = $share_key->primary_consumer_key;
          $id = $share_key->primary_resource_link_id;
          $ok = ($key != $this->consumer->getKey()) || ($id != $this->resource_link->getId());
          if ($ok) {
            $this->resource_link->primary_consumer_key = $key;
            $this->resource_link->primary_resource_link_id = $id;
            $this->resource_link->share_approved = $share_key->auto_approve;
            $ok = $this->resource_link->save();
            if ($ok) {
              $doSaveResourceLink = FALSE;
              $this->user->getResourceLink()->primary_consumer_key = $key;
              $this->user->getResourceLink()->primary_resource_link_id = $id;
              $this->user->getResourceLink()->share_approved = $share_key->auto_approve;
              $this->user->getResourceLink()->updated = time();
// Remove share key
              $share_key->delete();
            } else {
              $this->reason = 'An error occurred initialising your share arrangement.';
            }
          } else {
            $this->reason = 'It is not possible to share your resource link with yourself.';
          }
        }
        if ($ok) {
          $ok = !is_null($key);
          if (!$ok) {
            $this->reason = 'You have requested to share a resource link but none is available.';
          } else {
            $ok = (!is_null($this->user->getResourceLink()->share_approved) && $this->user->getResourceLink()->share_approved);
            if (!$ok) {
              $this->reason = 'Your share request is waiting to be approved.';
            }
          }
        }
      }
    } else {
// Check no share is in place
      $ok = is_null($key);
      if (!$ok) {
        $this->reason = 'You have not requested to share a resource link but an arrangement is currently in place.';
      }
    }

// Look up primary resource link
    if ($ok && !is_null($key)) {
      $consumer = new LTI_Tool_Consumer($key, $this->data_connector);
      $ok = !is_null($consumer->created);
      if ($ok) {
        $resource_link = new LTI_Resource_Link($consumer, $id);
        $ok = !is_null($resource_link->created);
      }
      if ($ok) {
        if ($doSaveResourceLink) {
          $this->resource_link->save();
        }
        $this->resource_link = $resource_link;
      } else {
        $this->reason = 'Unable to load resource link being shared.';
      }
    }

    return $ok;

  }

/**
 * Validate a parameter value from an array of permitted values.
 *
 * @return boolean True if value is valid
 */
  private function checkValue($value, $values, $reason) {

    $ok = in_array($value, $values);
    if (!$ok && !empty($reason)) {
      $this->reason = sprintf($reason, $value);
    }

    return $ok;

  }

}


/**
 * Class to represent a tool consumer
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Tool_Consumer {

/**
 * @var string Local name of tool consumer.
 */
  public $name = NULL;
/**
 * @var string Shared secret.
 */
  public $secret = NULL;
/**
 * @var string LTI version (as reported by last tool consumer connection).
 */
  public $lti_version = NULL;
/**
 * @var string Name of tool consumer (as reported by last tool consumer connection).
 */
  public $consumer_name = NULL;
/**
 * @var string Tool consumer version (as reported by last tool consumer connection).
 */
  public $consumer_version = NULL;
/**
 * @var string Tool consumer GUID (as reported by first tool consumer connection).
 */
  public $consumer_guid = NULL;
/**
 * @var string Optional CSS path (as reported by last tool consumer connection).
 */
  public $css_path = NULL;
/**
 * @var boolean Whether the tool consumer instance is protected by matching the consumer_guid value in incoming requests.
 */
  public $protected = FALSE;
/**
 * @var boolean Whether the tool consumer instance is enabled to accept incoming connection requests.
 */
  public $enabled = FALSE;
/**
 * @var object Date/time from which the the tool consumer instance is enabled to accept incoming connection requests.
 */
  public $enable_from = NULL;
/**
 * @var object Date/time until which the tool consumer instance is enabled to accept incoming connection requests.
 */
  public $enable_until = NULL;
/**
 * @var object Date of last connection from this tool consumer.
 */
  public $last_access = NULL;
/**
 * @var int Default scope to use when generating an Id value for a user.
 */
  public $id_scope = LTI_Tool_Provider::ID_SCOPE_ID_ONLY;
/**
 * @var string Default email address (or email domain) to use when no email address is provided for a user.
 */
  public $defaultEmail = '';
/**
 * @var object Date/time when the object was created.
 */
  public $created = NULL;
/**
 * @var object Date/time when the object was last updated.
 */
  public $updated = NULL;

/**
 * @var string Consumer key value.
 */
  private $key = NULL;
/**
 * @var mixed Data connector object or string.
 */
  private $data_connector = NULL;

/**
 * Class constructor.
 *
 * @param string  $key             Consumer key
 * @param mixed   $data_connector  String containing table name prefix, or database connection object, or array containing one or both values (optional, default is MySQL with an empty table name prefix)
 * @param boolean $autoEnable      true if the tool consumers is to be enabled automatically (optional, default is false)
 */
  public function __construct($key = NULL, $data_connector = '', $autoEnable = FALSE) {

    $this->data_connector = LTI_Data_Connector::getDataConnector($data_connector);
    if (!empty($key)) {
      $this->load($key, $autoEnable);
    } else {
      $this->secret = LTI_Data_Connector::getRandomString(32);
    }

  }

/**
 * Initialise the tool consumer.
 */
  public function initialise() {

    $this->key = NULL;
    $this->name = NULL;
    $this->secret = NULL;
    $this->lti_version = NULL;
    $this->consumer_name = NULL;
    $this->consumer_version = NULL;
    $this->consumer_guid = NULL;
    $this->css_path = NULL;
    $this->protected = FALSE;
    $this->enabled = FALSE;
    $this->enable_from = NULL;
    $this->enable_until = NULL;
    $this->last_access = NULL;
    $this->id_scope = LTI_Tool_Provider::ID_SCOPE_ID_ONLY;
    $this->defaultEmail = '';
    $this->created = NULL;
    $this->updated = NULL;

  }

/**
 * Save the tool consumer to the database.
 *
 * @return boolean True if the object was successfully saved
 */
  public function save() {

    return $this->data_connector->Tool_Consumer_save($this);

  }

/**
 * Delete the tool consumer from the database.
 *
 * @return boolean True if the object was successfully deleted
 */
  public function delete() {

    return $this->data_connector->Tool_Consumer_delete($this);

  }

/**
 * Get the tool consumer key.
 *
 * @return string Consumer key value
 */
  public function getKey() {

    return $this->key;

  }

/**
 * Get the data connector.
 *
 * @return mixed Data connector object or string
 */
  public function getDataConnector() {

    return $this->data_connector;

  }

/**
 * Is the consumer key available to accept launch requests?
 *
 * @return boolean True if the consumer key is enabled and within any date constraints
 */
  public function getIsAvailable() {

    $ok = $this->enabled;

    $now = time();
    if ($ok && !is_null($this->enable_from)) {
      $ok = $this->enable_from <= $now;
    }
    if ($ok && !is_null($this->enable_until)) {
      $ok = $this->enable_until > $now;
    }

    return $ok;

  }

/**
 * Add the OAuth signature to an LTI message.
 *
 * @param string  $url         URL for message request
 * @param string  $type        LTI message type
 * @param string  $version     LTI version
 * @param array   $params      Message parameters
 *
 * @return array Array of signed message parameters
 */
  public function signParameters($url, $type, $version, $params) {

    if (!empty($url)) {
// Check for query parameters which need to be included in the signature
      $query_params = array();
      $query_string = parse_url($url, PHP_URL_QUERY);
      if (!is_null($query_string)) {
        $query_items = explode('&', $query_string);
        foreach ($query_items as $item) {
          if (strpos($item, '=') !== FALSE) {
            list($name, $value) = explode('=', $item);
            $query_params[urldecode($name)] = urldecode($value);
          } else {
            $query_params[urldecode($item)] = '';
          }
        }
      }
      $params = $params + $query_params;
// Add standard parameters
      $params['lti_version'] = $version;
      $params['lti_message_type'] = $type;
      $params['oauth_callback'] = 'about:blank';
// Add OAuth signature
      $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
      $consumer = new OAuthConsumer($this->getKey(), $this->secret, NULL);
      $req = OAuthRequest::from_consumer_and_token($consumer, NULL, 'POST', $url, $params);
      $req->sign_request($hmac_method, $consumer, NULL);
      $params = $req->get_parameters();
// Remove parameters being passed on the query string
      foreach (array_keys($query_params) as $name) {
        unset($params[$name]);
      }
    }

    return $params;

  }

###
###  PRIVATE METHOD
###

/**
 * Load the tool consumer from the database.
 *
 * @param string  $key        The consumer key value
 * @param boolean $autoEnable True if the consumer should be enabled (optional, default if false)
 *
 * @return boolean True if the consumer was successfully loaded
 */
  private function load($key, $autoEnable = FALSE) {

    $this->initialise();
    $this->key = $key;
    $ok = $this->data_connector->Tool_Consumer_load($this);
    if (!$ok) {
      $this->enabled = $autoEnable;
    }

    return $ok;

  }

}


/**
 * Class to represent a tool consumer resource link
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Resource_Link {

/**
 * Read action.
 */
  const EXT_READ = 1;
/**
 * Write (create/update) action.
 */
  const EXT_WRITE = 2;
/**
 * Delete action.
 */
  const EXT_DELETE = 3;

/**
 * Decimal outcome type.
 */
  const EXT_TYPE_DECIMAL = 'decimal';
/**
 * Percentage outcome type.
 */
  const EXT_TYPE_PERCENTAGE = 'percentage';
/**
 * Ratio outcome type.
 */
  const EXT_TYPE_RATIO = 'ratio';
/**
 * Letter (A-F) outcome type.
 */
  const EXT_TYPE_LETTER_AF = 'letteraf';
/**
 * Letter (A-F) with optional +/- outcome type.
 */
  const EXT_TYPE_LETTER_AF_PLUS = 'letterafplus';
/**
 * Pass/fail outcome type.
 */
  const EXT_TYPE_PASS_FAIL = 'passfail';
/**
 * Free text outcome type.
 */
  const EXT_TYPE_TEXT = 'freetext';

/**
 * @var string Context ID as supplied in the last connection request.
 */
  public $lti_context_id = NULL;
/**
 * @var string Resource link ID as supplied in the last connection request.
 */
  public $lti_resource_id = NULL;
/**
 * @var string Context title.
 */
  public $title = NULL;
/**
 * @var array Setting values (LTI parameters, custom parameters and local parameters).
 */
  public $settings = NULL;
/**
 * @var array User group sets (NULL if the consumer does not support the groups enhancement)
 */
  public $group_sets = NULL;
/**
 * @var array User groups (NULL if the consumer does not support the groups enhancement)
 */
  public $groups = NULL;
/**
 * @var string Request for last service request.
 */
  public $ext_request = NULL;
/**
 * @var array Request headers for last service request.
 */
  public $ext_request_headers = NULL;
/**
 * @var string Response from last service request.
 */
  public $ext_response = NULL;
/**
 * @var array Response header from last service request.
 */
  public $ext_response_headers = NULL;
/**
 * @var string Consumer key value for resource link being shared (if any).
 */
  public $primary_consumer_key = NULL;
/**
 * @var string ID value for resource link being shared (if any).
 */
  public $primary_resource_link_id = NULL;
/**
 * @var boolean Whether the sharing request has been approved by the primary resource link.
 */
  public $share_approved = NULL;
/**
 * @var object Date/time when the object was created.
 */
  public $created = NULL;
/**
 * @var object Date/time when the object was last updated.
 */
  public $updated = NULL;

/**
 * @var LTI_Tool_Consumer Tool Consumer for this resource link.
 */
  private $consumer = NULL;
/**
 * @var string ID for this resource link.
 */
  private $id = NULL;
/**
 * @var string Previous ID for this resource link.
 */
  private $previous_id = NULL;
/**
 * @var boolean Whether the settings value have changed since last saved.
 */
  private $settings_changed = FALSE;
/**
 * @var string XML document for the last extension service request.
 */
  private $ext_doc = NULL;
/**
 * @var array XML node array for the last extension service request.
 */
  private $ext_nodes = NULL;

/**
 * Class constructor.
 *
 * @param string $consumer         Consumer key value
 * @param string $id               Resource link ID value
 * @param string $current_id       Current ID of resource link (optional, default is NULL)
 */
  public function __construct($consumer, $id, $current_id = NULL) {

    $this->consumer = $consumer;
    $this->id = $id;
    $this->previous_id = $this->id;
    if (!empty($id)) {
      $this->load();
      if (is_null($this->created) && !empty($current_id)) {
        $this->id = $current_id;
        $this->load();
        $this->id = $id;
        $this->previous_id = $current_id;
      }
    } else {
      $this->initialise();
    }

  }

/**
 * Initialise the resource link.
 */
  public function initialise() {

    $this->lti_context_id = NULL;
    $this->lti_resource_id = NULL;
    $this->title = '';
    $this->settings = array();
    $this->group_sets = NULL;
    $this->groups = NULL;
    $this->primary_consumer_key = NULL;
    $this->primary_resource_link_id = NULL;
    $this->share_approved = NULL;
    $this->created = NULL;
    $this->updated = NULL;

  }

/**
 * Save the resource link to the database.
 *
 * @return boolean True if the resource link was successfully saved.
 */
  public function save() {

    $ok = $this->consumer->getDataConnector()->Resource_Link_save($this);
    if ($ok) {
      $this->settings_changed = FALSE;
    }

    return $ok;

  }

/**
 * Delete the resource link from the database.
 *
 * @return boolean True if the resource link was successfully deleted.
 */
  public function delete() {

    return $this->consumer->getDataConnector()->Resource_Link_delete($this);

  }

/**
 * Get tool consumer.
 *
 * @return object LTI_Tool_Consumer object for this resource link.
 */
  public function getConsumer() {

    return $this->consumer;

  }

/**
 * Get tool consumer key.
 *
 * @return string Consumer key value for this resource link.
 */
  public function getKey() {

    return $this->consumer->getKey();

  }

/**
 * Get resource link ID.
 *
 * @param string $previous   TRUE if previous ID value is to be returned (optional, default is FALSE)
 *
 * @return string ID for this resource link.
 */
  public function getId($previous = FALSE) {

    if ($previous) {
      $id = $this->previous_id;
    } else {
      $id = $this->id;
    }

    return $id;

  }

/**
 * Get a setting value.
 *
 * @param string $name    Name of setting
 * @param string $default Value to return if the setting does not exist (optional, default is an empty string)
 *
 * @return string Setting value
 */
  public function getSetting($name, $default = '') {

    if (array_key_exists($name, $this->settings)) {
      $value = $this->settings[$name];
    } else {
      $value = $default;
    }

    return $value;

  }

/**
 * Set a setting value.
 *
 * @param string $name  Name of setting
 * @param string $value Value to set, use an empty value to delete a setting (optional, default is null)
 */
  public function setSetting($name, $value = NULL) {

    $old_value = $this->getSetting($name);
    if ($value != $old_value) {
      if (!empty($value)) {
        $this->settings[$name] = $value;
      } else {
        unset($this->settings[$name]);
      }
      $this->settings_changed = TRUE;
    }

  }

/**
 * Get an array of all setting values.
 *
 * @return array Associative array of setting values
 */
  public function getSettings() {

    return $this->settings;

  }

/**
 * Save setting values.
 *
 * @return boolean True if the settings were successfully saved
 */
  public function saveSettings() {

    if ($this->settings_changed) {
      $ok = $this->save();
    } else {
      $ok = TRUE;
    }

    return $ok;

  }

/**
 * Check if the Outcomes service is supported.
 *
 * @return boolean True if this resource link supports the Outcomes service (either the LTI 1.1 or extension service)
 */
  public function hasOutcomesService() {

    $url = $this->getSetting('ext_ims_lis_basic_outcome_url') . $this->getSetting('lis_outcome_service_url');

    return !empty($url);

  }

/**
 * Check if the Memberships service is supported.
 *
 * @return boolean True if this resource link supports the Memberships service
 */
  public function hasMembershipsService() {

    $url = $this->getSetting('ext_ims_lis_memberships_url');

    return !empty($url);

  }

/**
 * Check if the Setting service is supported.
 *
 * @return boolean True if this resource link supports the Setting service
 */
  public function hasSettingService() {

    $url = $this->getSetting('ext_ims_lti_tool_setting_url');

    return !empty($url);

  }

/**
 * Perform an Outcomes service request.
 *
 * @param int $action The action type constant
 * @param LTI_Outcome $lti_outcome Outcome object
 * @param LTI_User $user User object
 *
 * @return boolean True if the request was successfully processed
 */
  public function doOutcomesService($action, $lti_outcome, $user = NULL) {

    $response = FALSE;
    $this->ext_response = NULL;
#
### Lookup service details from the source resource link appropriate to the user (in case the destination is being shared)
#
    $source_resource_link = $this;
    $sourcedid = $lti_outcome->getSourcedid();
    if (!is_null($user)) {
      $source_resource_link = $user->getResourceLink();
      $sourcedid = $user->lti_result_sourcedid;
    }
#
### Use LTI 1.1 service in preference to extension service if it is available
#
    $urlLTI11 = $source_resource_link->getSetting('lis_outcome_service_url');
    $urlExt = $source_resource_link->getSetting('ext_ims_lis_basic_outcome_url');
    if ($urlExt || $urlLTI11) {
      switch ($action) {
        case self::EXT_READ:
          if ($urlLTI11 && ($lti_outcome->type == self::EXT_TYPE_DECIMAL)) {
            $do = 'readResult';
          } else if ($urlExt) {
            $urlLTI11 = NULL;
            $do = 'basic-lis-readresult';
          }
          break;
        case self::EXT_WRITE:
          if ($urlLTI11 && $this->checkValueType($lti_outcome, array(self::EXT_TYPE_DECIMAL))) {
            $do = 'replaceResult';
          } else if ($this->checkValueType($lti_outcome)) {
            $urlLTI11 = NULL;
            $do = 'basic-lis-updateresult';
          }
          break;
        case self::EXT_DELETE:
          if ($urlLTI11 && ($lti_outcome->type == self::EXT_TYPE_DECIMAL)) {
            $do = 'deleteResult';
          } else if ($urlExt) {
            $urlLTI11 = NULL;
            $do = 'basic-lis-deleteresult';
          }
          break;
      }
    }
    if (isset($do)) {
      $value = $lti_outcome->getValue();
      if (is_null($value)) {
        $value = '';
      }
      if ($urlLTI11) {
        $xml = '';
        if ($action == self::EXT_WRITE) {
          $xml = <<<EOF

        <result>
          <resultScore>
            <language>{$lti_outcome->language}</language>
            <textString>{$value}</textString>
          </resultScore>
        </result>
EOF;
        }
        $sourcedid = htmlentities($sourcedid);
        $xml = <<<EOF
      <resultRecord>
        <sourcedGUID>
          <sourcedId>{$sourcedid}</sourcedId>
        </sourcedGUID>{$xml}
      </resultRecord>
EOF;
        if ($this->doLTI11Service($do, $urlLTI11, $xml)) {
          switch ($action) {
            case self::EXT_READ:
              if (!isset($this->ext_nodes['imsx_POXBody']["{$do}Response"]['result']['resultScore']['textString'])) {
                break;
              } else {
                $lti_outcome->setValue($this->ext_nodes['imsx_POXBody']["{$do}Response"]['result']['resultScore']['textString']);
              }
            case self::EXT_WRITE:
            case self::EXT_DELETE:
              $response = TRUE;
              break;
          }
        }
      } else {
        $params = array();
        $params['sourcedid'] = $sourcedid;
        $params['result_resultscore_textstring'] = $value;
        if (!empty($lti_outcome->language)) {
          $params['result_resultscore_language'] = $lti_outcome->language;
        }
        if (!empty($lti_outcome->status)) {
          $params['result_statusofresult'] = $lti_outcome->status;
        }
        if (!empty($lti_outcome->date)) {
          $params['result_date'] = $lti_outcome->date;
        }
        if (!empty($lti_outcome->type)) {
          $params['result_resultvaluesourcedid'] = $lti_outcome->type;
        }
        if (!empty($lti_outcome->data_source)) {
          $params['result_datasource'] = $lti_outcome->data_source;
        }
        if ($this->doService($do, $urlExt, $params)) {
          switch ($action) {
            case self::EXT_READ:
              if (isset($this->ext_nodes['result']['resultscore']['textstring'])) {
                $response = $this->ext_nodes['result']['resultscore']['textstring'];
              }
              break;
            case self::EXT_WRITE:
            case self::EXT_DELETE:
              $response = TRUE;
              break;
          }
        }
      }
      if (is_array($response) && (count($response) <= 0)) {
        $response = '';
      }
    }

    return $response;

  }

/**
 * Perform a Memberships service request.
 *
 * The user table is updated with the new list of user objects.
 *
 * @param boolean $withGroups True is group information is to be requested as well
 *
 * @return mixed Array of LTI_User objects or False if the request was not successful
 */
  public function doMembershipsService($withGroups = FALSE) {
    $users = array();
    $old_users = $this->getUserResultSourcedIDs(TRUE, LTI_Tool_Provider::ID_SCOPE_RESOURCE);
    $this->ext_response = NULL;
    $url = $this->getSetting('ext_ims_lis_memberships_url');
    $params = array();
    $params['id'] = $this->getSetting('ext_ims_lis_memberships_id');
    $ok = FALSE;
    if ($withGroups) {
      $ok = $this->doService('basic-lis-readmembershipsforcontextwithgroups', $url, $params);
    }
    if ($ok) {
      $this->group_sets = array();
      $this->groups = array();
    } else {
      $ok = $this->doService('basic-lis-readmembershipsforcontext', $url, $params);
    }

    if ($ok) {
      if (!isset($this->ext_nodes['memberships']['member'])) {
        $members = array();
      } else if (!isset($this->ext_nodes['memberships']['member'][0])) {
        $members = array();
        $members[0] = $this->ext_nodes['memberships']['member'];
      } else {
        $members = $this->ext_nodes['memberships']['member'];
      }

      for ($i = 0; $i < count($members); $i++) {

        $user = new LTI_User($this, $members[$i]['user_id']);
#
### Set the user name
#
        $firstname = (isset($members[$i]['person_name_given'])) ? $members[$i]['person_name_given'] : '';
        $lastname = (isset($members[$i]['person_name_family'])) ? $members[$i]['person_name_family'] : '';
        $fullname = (isset($members[$i]['person_name_full'])) ? $members[$i]['person_name_full'] : '';
        $user->setNames($firstname, $lastname, $fullname);
#
### Set the user email
#
        $email = (isset($members[$i]['person_contact_email_primary'])) ? $members[$i]['person_contact_email_primary'] : '';
        $user->setEmail($email, $this->consumer->defaultEmail);
#
### Set the user roles
#
        if (isset($members[$i]['roles'])) {
          $user->roles = LTI_Tool_Provider::parseRoles($members[$i]['roles']);
        }
#
### Set the user groups
#
        if (!isset($members[$i]['groups']['group'])) {
          $groups = array();
        } else if (!isset($members[$i]['groups']['group'][0])) {
          $groups = array();
          $groups[0] = $members[$i]['groups']['group'];
        } else {
          $groups = $members[$i]['groups']['group'];
        }
        for ($j = 0; $j < count($groups); $j++) {
          $group = $groups[$j];
          if (isset($group['set'])) {
            $set_id = $group['set']['id'];
            if (!isset($this->group_sets[$set_id])) {
              $this->group_sets[$set_id] = array('title' => $group['set']['title'], 'groups' => array(),
                 'num_members' => 0, 'num_staff' => 0, 'num_learners' => 0);
            }
            $this->group_sets[$set_id]['num_members']++;
            if ($user->isStaff()) {
              $this->group_sets[$set_id]['num_staff']++;
            }
            if ($user->isLearner()) {
              $this->group_sets[$set_id]['num_learners']++;
            }
            if (!in_array($group['id'], $this->group_sets[$set_id]['groups'])) {
              $this->group_sets[$set_id]['groups'][] = $group['id'];
            }
            $this->groups[$group['id']] = array('title' => $group['title'], 'set' => $set_id);
          } else {
            $this->groups[$group['id']] = array('title' => $group['title']);
          }
          $user->groups[] = $group['id'];
        }
#
### If a result sourcedid is provided save the user
#
        if (isset($members[$i]['lis_result_sourcedid'])) {
          $user->lti_result_sourcedid = $members[$i]['lis_result_sourcedid'];
          $user->save();
        }
        $users[] = $user;
#
### Remove old user (if it exists)
#
        unset($old_users[$user->getId(LTI_Tool_Provider::ID_SCOPE_RESOURCE)]);
      }
#
### Delete any old users which were not in the latest list from the tool consumer
#
      foreach ($old_users as $id => $user) {
        $user->delete();
      }
    } else {
      $users = FALSE;
    }

    return $users;

  }

/**
 * Perform a Setting service request.
 *
 * @param int    $action The action type constant
 * @param string $value  The setting value (optional, default is null)
 *
 * @return mixed The setting value for a read action, true if a write or delete action was successful, otherwise false
 */
  public function doSettingService($action, $value = NULL) {

    $response = FALSE;
    $this->ext_response = NULL;
    switch ($action) {
      case self::EXT_READ:
        $do = 'basic-lti-loadsetting';
        break;
      case self::EXT_WRITE:
        $do = 'basic-lti-savesetting';
        break;
      case self::EXT_DELETE:
        $do = 'basic-lti-deletesetting';
        break;
    }
    if (isset($do)) {

      $url = $this->getSetting('ext_ims_lti_tool_setting_url');
      $params = array();
      $params['id'] = $this->getSetting('ext_ims_lti_tool_setting_id');
      if (is_null($value)) {
        $value = '';
      }
      $params['setting'] = $value;

      if ($this->doService($do, $url, $params)) {
        switch ($action) {
          case self::EXT_READ:
            if (isset($this->ext_nodes['setting']['value'])) {
              $response = $this->ext_nodes['setting']['value'];
              if (is_array($response)) {
                $response = '';
              }
            }
            break;
          case self::EXT_WRITE:
            $this->setSetting('ext_ims_lti_tool_setting', $value);
            $this->saveSettings();
            $response = TRUE;
            break;
          case self::EXT_DELETE:
            $response = TRUE;
            break;
        }
      }

    }

    return $response;

  }

/**
 * Obtain an array of LTI_User objects for users with a result sourcedId.
 *
 * The array may include users from other resource links which are sharing this resource link.
 * It may also be optionally indexed by the user ID of a specified scope.
 *
 * @param boolean $local_only True if only users from this resource link are to be returned, not users from shared resource links (optional, default is false)
 * @param int     $id_scope     Scope to use for ID values (optional, default is null for consumer default)
 *
 * @return array Array of LTI_User objects
 */
  public function getUserResultSourcedIDs($local_only = FALSE, $id_scope = NULL) {

    return $this->consumer->getDataConnector()->Resource_Link_getUserResultSourcedIDs($this, $local_only, $id_scope);

  }

/**
 * Get an array of LTI_Resource_Link_Share objects for each resource link which is sharing this context.
 *
 * @return array Array of LTI_Resource_Link_Share objects
 */
  public function getShares() {

    return $this->consumer->getDataConnector()->Resource_Link_getShares($this);

  }

###
###  PRIVATE METHODS
###

/**
 * Load the resource link from the database.
 *
 * @return boolean True if resource link was successfully loaded
 */
  private function load() {

    $this->initialise();
    return $this->consumer->getDataConnector()->Resource_Link_load($this);

  }

/**
 * Convert data type of value to a supported type if possible.
 *
 * @param LTI_Outcome $lti_outcome     Outcome object
 * @param string[]    $supported_types Array of outcome types to be supported (optional, default is null to use supported types reported in the last launch for this resource link)
 *
 * @return boolean True if the type/value are valid and supported
 */
  private function checkValueType($lti_outcome, $supported_types = NULL) {

    if (empty($supported_types)) {
      $supported_types = explode(',', str_replace(' ', '', strtolower($this->getSetting('ext_ims_lis_resultvalue_sourcedids', self::EXT_TYPE_DECIMAL))));
    }
    $type = $lti_outcome->type;
    $value = $lti_outcome->getValue();
// Check whether the type is supported or there is no value
    $ok = in_array($type, $supported_types) || (strlen($value) <= 0);
    if (!$ok) {
// Convert numeric values to decimal
      if ($type == self::EXT_TYPE_PERCENTAGE) {
        if (substr($value, -1) == '%') {
          $value = substr($value, 0, -1);
        }
        $ok = is_numeric($value) && ($value >= 0) && ($value <= 100);
        if ($ok) {
          $lti_outcome->setValue($value / 100);
          $lti_outcome->type = self::EXT_TYPE_DECIMAL;
        }
      } else if ($type == self::EXT_TYPE_RATIO) {
        $parts = explode('/', $value, 2);
        $ok = (count($parts) == 2) && is_numeric($parts[0]) && is_numeric($parts[1]) && ($parts[0] >= 0) && ($parts[1] > 0);
        if ($ok) {
          $lti_outcome->setValue($parts[0] / $parts[1]);
          $lti_outcome->type = self::EXT_TYPE_DECIMAL;
        }
// Convert letter_af to letter_af_plus or text
      } else if ($type == self::EXT_TYPE_LETTER_AF) {
        if (in_array(self::EXT_TYPE_LETTER_AF_PLUS, $supported_types)) {
          $ok = TRUE;
          $lti_outcome->type = self::EXT_TYPE_LETTER_AF_PLUS;
        } else if (in_array(self::EXT_TYPE_TEXT, $supported_types)) {
          $ok = TRUE;
          $lti_outcome->type = self::EXT_TYPE_TEXT;
        }
// Convert letter_af_plus to letter_af or text
      } else if ($type == self::EXT_TYPE_LETTER_AF_PLUS) {
        if (in_array(self::EXT_TYPE_LETTER_AF, $supported_types) && (strlen($value) == 1)) {
          $ok = TRUE;
          $lti_outcome->type = self::EXT_TYPE_LETTER_AF;
        } else if (in_array(self::EXT_TYPE_TEXT, $supported_types)) {
          $ok = TRUE;
          $lti_outcome->type = self::EXT_TYPE_TEXT;
        }
// Convert text to decimal
      } else if ($type == self::EXT_TYPE_TEXT) {
        $ok = is_numeric($value) && ($value >= 0) && ($value <=1);
        if ($ok) {
          $lti_outcome->type = self::EXT_TYPE_DECIMAL;
        } else if (substr($value, -1) == '%') {
          $value = substr($value, 0, -1);
          $ok = is_numeric($value) && ($value >= 0) && ($value <=100);
          if ($ok) {
            if (in_array(self::EXT_TYPE_PERCENTAGE, $supported_types)) {
              $lti_outcome->type = self::EXT_TYPE_PERCENTAGE;
            } else {
              $lti_outcome->setValue($value / 100);
              $lti_outcome->type = self::EXT_TYPE_DECIMAL;
            }
          }
        }
      }
    }

    return $ok;

  }

/**
 * Send a service request to the tool consumer.
 *
 * @param string $type   Message type value
 * @param string $url    URL to send request to
 * @param array  $params Associative array of parameter values to be passed
 *
 * @return boolean True if the request successfully obtained a response
 */
  private function doService($type, $url, $params) {

    $ok = FALSE;
    $this->ext_request = NULL;
    $this->ext_request_headers = '';
    $this->ext_response = NULL;
    $this->ext_response_headers = '';
    if (!empty($url)) {
      $params = $this->consumer->signParameters($url, $type, $this->consumer->lti_version, $params);
// Connect to tool consumer
      $http = new LTI_HTTP_Message($url, 'POST', $params);
// Parse XML response
      if ($http->send()) {
        $this->ext_response = $http->response;
        $this->ext_response_headers = $http->response_headers;
        try {
          $this->ext_doc = new DOMDocument();
          $this->ext_doc->loadXML($http->response);
          $this->ext_nodes = $this->domnode_to_array($this->ext_doc->documentElement);
          if (isset($this->ext_nodes['statusinfo']['codemajor']) && ($this->ext_nodes['statusinfo']['codemajor'] == 'Success')) {
            $ok = TRUE;
          }
        } catch (Exception $e) {
        }
      }
      $this->ext_request = $http->request;
      $this->ext_request_headers = $http->request_headers;
    }

    return $ok;

  }

/**
 * Send a service request to the tool consumer.
 *
 * @param string $type Message type value
 * @param string $url  URL to send request to
 * @param string $xml  XML of message request
 *
 * @return boolean True if the request successfully obtained a response
 */
  private function doLTI11Service($type, $url, $xml) {

    $ok = FALSE;
    $this->ext_request = NULL;
    $this->ext_request_headers = '';
    $this->ext_response = NULL;
    $this->ext_response_headers = '';
    if (!empty($url)) {
      $id = uniqid();
      $xmlRequest = <<< EOD
<?xml version = "1.0" encoding = "UTF-8"?>
<imsx_POXEnvelopeRequest xmlns = "http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
  <imsx_POXHeader>
    <imsx_POXRequestHeaderInfo>
      <imsx_version>V1.0</imsx_version>
      <imsx_messageIdentifier>{$id}</imsx_messageIdentifier>
    </imsx_POXRequestHeaderInfo>
  </imsx_POXHeader>
  <imsx_POXBody>
    <{$type}Request>
{$xml}
    </{$type}Request>
  </imsx_POXBody>
</imsx_POXEnvelopeRequest>
EOD;
// Calculate body hash
      $hash = base64_encode(sha1($xmlRequest, TRUE));
      $params = array('oauth_body_hash' => $hash);

// Add OAuth signature
      $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
      $consumer = new OAuthConsumer($this->consumer->getKey(), $this->consumer->secret, NULL);
      $req = OAuthRequest::from_consumer_and_token($consumer, NULL, 'POST', $url, $params);
      $req->sign_request($hmac_method, $consumer, NULL);
      $params = $req->get_parameters();
      $header = $req->to_header();
      $header .= "\nContent-Type: application/xml";
// Connect to tool consumer
      $http = new LTI_HTTP_Message($url, 'POST', $xmlRequest, $header);
// Parse XML response
      if ($http->send()) {
        $this->ext_response = $http->response;
        $this->ext_response_headers = $http->response_headers;
        try {
          $this->ext_doc = new DOMDocument();
          $this->ext_doc->loadXML($http->response);
          $this->ext_nodes = $this->domnode_to_array($this->ext_doc->documentElement);
          if (isset($this->ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_codeMajor']) &&
              ($this->ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_codeMajor'] == 'success')) {
            $ok = TRUE;
          }
        } catch (Exception $e) {
        }
      }
      $this->ext_request = $http->request;
      $this->ext_request_headers = $http->request_headers;
    }

    return $ok;

  }

/**
 * Convert DOM nodes to array.
 *
 * @param DOMElement $node XML element
 *
 * @return array Array of XML document elements
 */
  private function domnode_to_array($node) {

    $output = '';
    switch ($node->nodeType) {
      case XML_CDATA_SECTION_NODE:
      case XML_TEXT_NODE:
        $output = trim($node->textContent);
        break;
      case XML_ELEMENT_NODE:
        for ($i = 0; $i < $node->childNodes->length; $i++) {
          $child = $node->childNodes->item($i);
          $v = $this->domnode_to_array($child);
          if (isset($child->tagName)) {
            $t = $child->tagName;
            if (!isset($output[$t])) {
              $output[$t] = array();
            }
            $output[$t][] = $v;
          } else {
            $s = (string) $v;
            if (strlen($s) > 0) {
              $output = $s;
            }
          }
        }
        if (is_array($output)) {
          if ($node->attributes->length) {
            $a = array();
            foreach ($node->attributes as $attrName => $attrNode) {
              $a[$attrName] = (string) $attrNode->value;
            }
            $output['@attributes'] = $a;
          }
          foreach ($output as $t => $v) {
            if (is_array($v) && count($v)==1 && $t!='@attributes') {
              $output[$t] = $v[0];
            }
          }
        }
        break;
    }

    return $output;

  }

}

/**
 * Class to represent a tool consumer context
 *
 * @deprecated Use LTI_Resource_Link instead
 * @see LTI_Resource_Link
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Context extends LTI_Resource_Link {

/**
 * ID value for context being shared (if any).
 *
 * @deprecated Use primary_resource_link_id instead
 * @see LTI_Resource_Link::$primary_resource_link_id
 */
  public $primary_context_id = NULL;

/**
 * Class constructor.
 *
 * @param string $consumer Consumer key value
 * @param string $id       Resource link ID value
 */
  public function __construct($consumer, $id) {

    parent::__construct($consumer, $id);
    $this->primary_context_id = &$this->primary_resource_link_id;

  }

}


/**
 * Class to represent an outcome
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Outcome {

/**
 * @var string Language value.
 */
  public $language = NULL;
/**
 * @var string Outcome status value.
 */
  public $status = NULL;
/**
 * @var object Outcome date value.
 */
  public $date = NULL;
/**
 * @var string Outcome type value.
 */
  public $type = NULL;
/**
 * @var string Outcome data source value.
 */
  public $data_source = NULL;

/**
 * @var string Result sourcedid.
 *
 * @deprecated Use User object instead
 */
  private $sourcedid = NULL;
/**
 * @var string Outcome value.
 */
  private $value = NULL;

/**
 * Class constructor.
 *
 * @param string $sourcedid Result sourcedid value for the user/resource link (optional, default is to use associated User object)
 * @param string $value     Outcome value (optional, default is none)
 */
  public function __construct($sourcedid = NULL, $value = NULL) {

    $this->sourcedid = $sourcedid;
    $this->value = $value;
    $this->language = 'en-US';
    $this->date = gmdate('Y-m-d\TH:i:s\Z', time());
    $this->type = 'decimal';

  }

/**
 * Get the result sourcedid value.
 *
 * @deprecated Use User object instead
 *
 * @return string Result sourcedid value
 */
  public function getSourcedid() {

    return $this->sourcedid;

  }

/**
 * Get the outcome value.
 *
 * @return string Outcome value
 */
  public function getValue() {

    return $this->value;

  }

/**
 * Set the outcome value.
 *
 * @param string Outcome value
 */
  public function setValue($value) {

    $this->value = $value;

  }

}


/**
 * Class to represent a tool consumer nonce
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Consumer_Nonce {

/**
 * Maximum age nonce values will be retained for (in minutes).
 */
  const MAX_NONCE_AGE = 30;  // in minutes

/**
 * Date/time when the nonce value expires.
 */
  public  $expires = NULL;

/**
 * @var LTI_Tool_Consumer Tool Consumer to which this nonce applies.
 */
  private $consumer = NULL;
/**
 * @var string Nonce value.
 */
  private $value = NULL;

/**
 * Class constructor.
 *
 * @param LTI_Tool_Consumer $consumer Consumer object
 * @param string            $value    Nonce value (optional, default is null)
 */
  public function __construct($consumer, $value = NULL) {

    $this->consumer = $consumer;
    $this->value = $value;
    $this->expires = time() + (self::MAX_NONCE_AGE * 60);

  }

/**
 * Load a nonce value from the database.
 *
 * @return boolean True if the nonce value was successfully loaded
 */
  public function load() {

    return $this->consumer->getDataConnector()->Consumer_Nonce_load($this);

  }

/**
 * Save a nonce value in the database.
 *
 * @return boolean True if the nonce value was successfully saved
 */
  public function save() {

    return $this->consumer->getDataConnector()->Consumer_Nonce_save($this);

  }

/**
 * Get tool consumer.
 *
 * @return LTI_Tool_Consumer Consumer for this nonce
 */
  public function getConsumer() {

    return $this->consumer;

  }

/**
 * Get tool consumer key.
 *
 * @return string Consumer key value
 */
  public function getKey() {

    return $this->consumer->getKey();

  }

/**
 * Get outcome value.
 *
 * @return string Outcome value
 */
  public function getValue() {

    return $this->value;

  }

}


/**
 * Class to represent a tool consumer resource link share key
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Resource_Link_Share_Key {

/**
 * Maximum permitted life for a share key value.
 */
  const MAX_SHARE_KEY_LIFE = 168;  // in hours (1 week)
/**
 * Default life for a share key value.
 */
  const DEFAULT_SHARE_KEY_LIFE = 24;  // in hours
/**
 * Minimum length for a share key value.
 */
  const MIN_SHARE_KEY_LENGTH = 5;
/**
 * Maximum length for a share key value.
 */
  const MAX_SHARE_KEY_LENGTH = 32;

/**
 * @var string Consumer key for resource link being shared.
 */
  public $primary_consumer_key = NULL;
/**
 * @var string ID for resource link being shared.
 */
  public $primary_resource_link_id = NULL;
/**
 * @var int Length of share key.
 */
  public $length = NULL;
/**
 * @var int Life of share key.
 */
  public $life = NULL;  // in hours
/**
 * @var boolean Whether the sharing arrangement should be automatically approved when first used.
 */
  public $auto_approve = FALSE;
/**
 * @var object Date/time when the share key expires.
 */
  public $expires = NULL;

/**
 * @var string Share key value.
 */
  private $id = NULL;
/**
 * @var LTI_Data_Connector Data connector.
 */
  private $data_connector = NULL;

/**
 * Class constructor.
 *
 * @param LTI_Resource_Link $resource_link  Resource_Link object
 * @param string      $id      Value of share key (optional, default is null)
 */
  public function __construct($resource_link, $id = NULL) {

    $this->initialise();
    $this->data_connector = $resource_link->getConsumer()->getDataConnector();
    $this->id = $id;
    $this->primary_context_id = &$this->primary_resource_link_id;
    if (!empty($id)) {
      $this->load();
    } else {
      $this->primary_consumer_key = $resource_link->getKey();
      $this->primary_resource_link_id = $resource_link->getId();
    }

  }

/**
 * Initialise the resource link share key.
 */
  public function initialise() {

    $this->primary_consumer_key = NULL;
    $this->primary_resource_link_id = NULL;
    $this->length = NULL;
    $this->life = NULL;
    $this->auto_approve = FALSE;
    $this->expires = NULL;

  }

/**
 * Save the resource link share key to the database.
 *
 * @return boolean True if the share key was successfully saved
 */
  public function save() {

    if (empty($this->life)) {
      $this->life = self::DEFAULT_SHARE_KEY_LIFE;
    } else {
      $this->life = max(min($this->life, self::MAX_SHARE_KEY_LIFE), 0);
    }
    $this->expires = time() + ($this->life * 60 * 60);
    if (empty($this->id)) {
      if (empty($this->length) || !is_numeric($this->length)) {
        $this->length = self::MAX_SHARE_KEY_LENGTH;
      } else {
        $this->length = max(min($this->length, self::MAX_SHARE_KEY_LENGTH), self::MIN_SHARE_KEY_LENGTH);
      }
      $this->id = LTI_Data_Connector::getRandomString($this->length);
    }

    return $this->data_connector->Resource_Link_Share_Key_save($this);

  }

/**
 * Delete the resource link share key from the database.
 *
 * @return boolean True if the share key was successfully deleted
 */
  public function delete() {

    return $this->data_connector->Resource_Link_Share_Key_delete($this);

  }

/**
 * Get share key value.
 *
 * @return string Share key value
 */
  public function getId() {

    return $this->id;

  }

###
###  PRIVATE METHOD
###

/**
 * Load the resource link share key from the database.
 */
  private function load() {

    $this->initialise();
    $this->data_connector->Resource_Link_Share_Key_load($this);
    if (!is_null($this->id)) {
      $this->length = strlen($this->id);
    }
    if (!is_null($this->expires)) {
      $this->life = ($this->expires - time()) / 60 / 60;
    }

  }

}

/**
 * Class to represent a tool consumer context share key
 *
 * @deprecated Use LTI_Resource_Link_Share_Key instead
 * @see LTI_Resource_Link_Share_Key
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Context_Share_Key extends LTI_Resource_Link_Share_Key {

/**
 * ID for context being shared.
 *
 * @deprecated Use LTI_Resource_Link_Share_Key->primary_resource_link_id instead
 * @see LTI_Resource_Link_Share_Key::$primary_resource_link_id
 */
  public $primary_context_id = NULL;

/**
 * Class constructor.
 *
 * @param LTI_Resource_Link $resource_link  Resource_Link object
 * @param string      $id      Value of share key (optional, default is null)
 */
  public function __construct($resource_link, $id = NULL) {

    parent::__construct($resource_link, $id);
    $this->primary_context_id = &$this->primary_resource_link_id;

  }

}


/**
 * Class to represent a tool consumer resource link share
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Resource_Link_Share {

/**
 * @var string Consumer key value.
 */
  public $consumer_key = NULL;
/**
 * @var string Resource link ID value.
 */
  public $resource_link_id = NULL;
/**
 * @var string Title of sharing context.
 */
  public $title = NULL;
/**
 * @var boolean Whether sharing request is to be automatically approved on first use.
 */
  public $approved = NULL;

/**
 * Class constructor.
 */
  public function __construct() {
    $this->context_id = &$this->resource_link_id;
  }

}

/**
 * Class to represent a tool consumer context share
 *
 * @deprecated Use LTI_Resource_Link_Share instead
 * @see LTI_Resource_Link_Share
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Context_Share extends LTI_Resource_Link_Share {

/**
 * Context ID value.
 *
 * @deprecated Use LTI_Resource_Link_Share->resource_link_id instead
 * @see LTI_Resource_Link_Share::$resource_link_id
 */
  public $context_id = NULL;

/**
 * Class constructor.
 */
  public function __construct() {

    parent::__construct();
    $this->context_id = &$this->resource_link_id;

  }

}


/**
 * Class to represent a tool consumer user
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_User {

/**
 * @var string User's first name.
 */
  public $firstname = '';
/**
 * @var string User's last name (surname or family name).
 */
  public $lastname = '';
/**
 * @var string User's fullname.
 */
  public $fullname = '';
/**
 * @var string User's email address.
 */
  public $email = '';
/**
 * @var array Roles for user.
 */
  public $roles = array();
/**
 * @var array Groups for user.
 */
  public $groups = array();
/**
 * @var string User's result sourcedid.
 */
  public $lti_result_sourcedid = NULL;
/**
 * @var object Date/time the record was created.
 */
  public $created = NULL;
/**
 * @var object Date/time the record was last updated.
 */
  public $updated = NULL;

/**
 * @var LTI_Resource_Link Resource link object.
 */
  private $resource_link = NULL;
/**
 * @var LTI_Context Resource link object.
 */
  private $context = NULL;
/**
 * @var string User ID value.
 */
  private $id = NULL;

/**
 * Class constructor.
 *
 * @param LTI_Resource_Link $resource_link Resource_Link object
 * @param string      $id      User ID value
 */
  public function __construct($resource_link, $id) {

    $this->initialise();
    $this->resource_link = $resource_link;
    $this->context = &$this->resource_link;
    $this->id = $id;
    $this->load();

  }

/**
 * Initialise the user.
 */
  public function initialise() {

    $this->firstname = '';
    $this->lastname = '';
    $this->fullname = '';
    $this->email = '';
    $this->roles = array();
    $this->groups = array();
    $this->lti_result_sourcedid = NULL;
    $this->created = NULL;
    $this->updated = NULL;

  }

/**
 * Load the user from the database.
 *
 * @return boolean True if the user object was successfully loaded
 */
  public function load() {

    $this->initialise();
    if (!is_null($this->resource_link)) {
      $this->resource_link->getConsumer()->getDataConnector()->User_load($this);
    }

  }

/**
 * Save the user to the database.
 *
 * @return boolean True if the user object was successfully saved
 */
  public function save() {

    if (!empty($this->lti_result_sourcedid) && !is_null($this->resource_link)) {
      $ok = $this->resource_link->getConsumer()->getDataConnector()->User_save($this);
    } else {
      $ok = TRUE;
    }

    return $ok;

  }

/**
 * Delete the user from the database.
 *
 * @return boolean True if the user object was successfully deleted
 */
  public function delete() {

    if (!is_null($this->resource_link)) {
      $ok = $this->resource_link->getConsumer()->getDataConnector()->User_delete($this);
    } else {
      $ok = TRUE;
    }

    return $ok;

  }

/**
 * Get resource link.
 *
 * @return LTI_Resource_Link Resource link object
 */
  public function getResourceLink() {

    return $this->resource_link;

  }

/**
 * Get context.
 *
 * @deprecated Use getResourceLink() instead
 * @see LTI_User::getResourceLink()
 *
 * @return LTI_Resource_Link Context object
 */
  public function getContext() {

    return $this->resource_link;

  }

/**
 * Get the user ID (which may be a compound of the tool consumer and resource link IDs).
 *
 * @param int $id_scope Scope to use for user ID (optional, default is null for consumer default setting)
 *
 * @return string User ID value
 */
  public function getId($id_scope = NULL) {

    if (empty($id_scope)) {
      if (!is_null($this->resource_link)) {
        $id_scope = $this->resource_link->getConsumer()->id_scope;
      } else {
        $id_scope = LTI_Tool_Provider::ID_SCOPE_ID_ONLY;
      }
    }
    switch ($id_scope) {
      case LTI_Tool_Provider::ID_SCOPE_GLOBAL:
        $id = $this->resource_link->getKey() . LTI_Tool_Provider::ID_SCOPE_SEPARATOR . $this->id;
        break;
      case LTI_Tool_Provider::ID_SCOPE_CONTEXT:
        $id = $this->resource_link->getKey();
        if ($this->resource_link->lti_context_id) {
          $id .= LTI_Tool_Provider::ID_SCOPE_SEPARATOR . $this->resource_link->lti_context_id;
        }
        $id .= LTI_Tool_Provider::ID_SCOPE_SEPARATOR . $this->id;
        break;
      case LTI_Tool_Provider::ID_SCOPE_RESOURCE:
        $id = $this->resource_link->getKey();
        if ($this->resource_link->lti_resource_id) {
          $id .= LTI_Tool_Provider::ID_SCOPE_SEPARATOR . $this->resource_link->lti_resource_id;
        }
        $id .= LTI_Tool_Provider::ID_SCOPE_SEPARATOR . $this->id;
        break;
      default:
        $id = $this->id;
        break;
    }

    return $id;

  }

/**
 * Set the user's name.
 *
 * @param string $firstname User's first name.
 * @param string $lastname User's last name.
 * @param string $fullname User's full name.
 */
  public function setNames($firstname, $lastname, $fullname) {

    $names = array(0 => '', 1 => '');
    if (!empty($fullname)) {
      $this->fullname = trim($fullname);
      $names = preg_split("/[\s]+/", $this->fullname, 2);
    }
    if (!empty($firstname)) {
      $this->firstname = trim($firstname);
      $names[0] = $this->firstname;
    } else if (!empty($names[0])) {
      $this->firstname = $names[0];
    } else {
      $this->firstname = 'User';
    }
    if (!empty($lastname)) {
      $this->lastname = trim($lastname);
      $names[1] = $this->lastname;
    } else if (!empty($names[1])) {
      $this->lastname = $names[1];
    } else {
      $this->lastname = $this->id;
    }
    if (empty($this->fullname)) {
      $this->fullname = "{$this->firstname} {$this->lastname}";
    }

  }

/**
 * Set the user's email address.
 *
 * @param string $email        Email address value
 * @param string $defaultEmail Value to use if no email is provided (optional, default is none)
 */
  public function setEmail($email, $defaultEmail = NULL) {

    if (!empty($email)) {
      $this->email = $email;
    } else if (!empty($defaultEmail)) {
      $this->email = $defaultEmail;
      if (substr($this->email, 0, 1) == '@') {
        $this->email = $this->getId() . $this->email;
      }
    } else {
      $this->email = '';
    }

  }

/**
 * Check if the user is an administrator (at any of the system, institution or context levels).
 *
 * @return boolean True if the user has a role of administrator
 */
  public function isAdmin() {

    return $this->hasRole('Administrator') || $this->hasRole('urn:lti:sysrole:ims/lis/SysAdmin') ||
           $this->hasRole('urn:lti:sysrole:ims/lis/Administrator') || $this->hasRole('urn:lti:instrole:ims/lis/Administrator');

  }

/**
 * Check if the user is staff.
 *
 * @return boolean True if the user has a role of instructor, contentdeveloper or teachingassistant
 */
  public function isStaff() {

    return ($this->hasRole('Instructor') || $this->hasRole('ContentDeveloper') || $this->hasRole('TeachingAssistant'));

  }

/**
 * Check if the user is a learner.
 *
 * @return boolean True if the user has a role of learner
 */
  public function isLearner() {

    return $this->hasRole('Learner');

  }

###
###  PRIVATE METHODS
###

/**
 * Check whether the user has a specified role name.
 *
 * @param string $role Name of role
 *
 * @return boolean True if the user has the specified role
 */
  private function hasRole($role) {

    if (substr($role, 0, 4) != 'urn:') {
      $role = 'urn:lti:role:ims/lis/' . $role;
    }

    return in_array($role, $this->roles);

  }

}


/**
 * Class to represent a content-item image object
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Content_Item_Image {

/**
 * Class constructor.
 *
 * @param string $id      URL of image
 * @param int    $height  Height of image in pixels (optional)
 * @param int    $width   Width of image in pixels (optional)
 */
  function __construct($id, $height = NULL, $width = NULL) {

    $this->{'@id'} = $id;
    if (!is_null($height)) {
      $this->height = $height;
    }
    if (!is_null($width)) {
      $this->width = $width;
    }

  }

}


/**
 * Class to represent a content-item object
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Content_Item {

/**
 * Media type for LTI launch links.
 */
  const LTI_LINK_MEDIA_TYPE = 'application/vnd.ims.lti.v1.ltilink';

/**
 * Class constructor.
 *
 * @param string $type Class type of content-item
 * @param LTI_Content_Item_Placement $placementAdvice  Placement object for item (optional)
 * @param string $id   URL of content-item (optional)
 */
  function __construct($type, $placementAdvice = NULL, $id = NULL) {

    $this->{'@type'} = $type;
    if (is_object($placementAdvice) && (count(get_object_vars($placementAdvice)) > 0)) {
      $this->placementAdvice = $placementAdvice;
    }
    if (!empty($id)) {
      $this->{'@id'} = $id;
    }

  }

/**
 * Set a URL value for the content-item.
 *
 * @param string $url  URL value
 */
  public function setUrl($url) {

    if (!empty($url)) {
      $this->url = $url;
    } else {
      unset($this->url);
    }

  }

/**
 * Set a media type value for the content-item.
 *
 * @param string $mediaType  Media type value
 */
  public function setMediaType($mediaType) {

    if (!empty($mediaType)) {
      $this->mediaType = $mediaType;
    } else {
      unset($this->mediaType);
    }

  }

/**
 * Set a title value for the content-item.
 *
 * @param string $title  Title value
 */
  public function setTitle($title) {

    if (!empty($title)) {
      $this->title = $title;
    } else if (isset($this->title)) {
      unset($this->title);
    }

  }

/**
 * Set a link text value for the content-item.
 *
 * @param string $text  Link text value
 */
  public function setText($text) {

    if (!empty($text)) {
      $this->text = $text;
    } else if (isset($this->text)) {
      unset($this->text);
    }

  }

/**
 * Wrap the content items to form a complete application/vnd.ims.lti.v1.contentitems+json media type instance.
 *
 * @param mixed $items  An array of content items or a single item
 */
  public static function toJson($items) {

    $data = array();
    if (!is_array($items)) {
      $data[] = json_encode($items);
    } else {
      foreach ($items as $item) {
        $data[] = json_encode($item);
      }
    }
    $json = '{ "@context" : "http://purl.imsglobal.org/ctx/lti/v1/ContentItem", "@graph" : [' . implode(", ", $data) . '] }';

    return $json;

  }

}


/**
 * Class to represent a content-item placement object
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_Content_Item_Placement {

/**
 * Class constructor.
 *
 * @param int $displayWidth       Width of item location
 * @param int $displayHeight      Height of item location
 * @param string $documentTarget  Location to open content in
 * @param string $windowTarget    Name of window target
 */
  function __construct($displayWidth, $displayHeight, $documentTarget, $windowTarget) {

    if (!empty($displayWidth)) {
      $this->displayWidth = $displayWidth;
    }
    if (!empty($displayHeight)) {
      $this->displayHeight = $displayHeight;
    }
    if (!empty($documentTarget)) {
      $this->documentTarget = $documentTarget;
    }
    if (!empty($windowTarget)) {
      $this->windowTarget = $windowTarget;
    }

  }

}


/**
 * Class to represent an OAuth datastore
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_HTTP_Message {

/**
 * @var request Request body.
 */
  public $request = NULL;

/**
 * @var request_headers Request headers.
 */
  public $request_headers = '';

/**
 * @var response Response body.
 */
  public $response = NULL;

/**
 * @var response_headers Response headers.
 */
  public $response_headers = '';

/**
 * @var status Status of response (0 if undetermined).
 */
  public $status = 0;

/**
 * @var error Error message
 */
  public $error = '';

/**
 * @var url Request URL.
 */
  private $url = NULL;

/**
 * @var method Request method.
 */
  private $method = NULL;

/**
 * Class constructor.
 *
 * @param string $url     URL to send request to
 * @param string $method  Request method to use (optional, default is GET)
 * @param mixed  $params  Associative array of parameter values to be passed or message body (optional, default is none)
 * @param string $header  Values to include in the request header (optional, default is none)
 */
  function __construct($url, $method = 'GET', $params = NULL, $header = NULL) {

    $this->url = $url;
    $this->method = strtoupper($method);
    if (is_array($params)) {
      $this->request = http_build_query($params);
    } else {
      $this->request = $params;
    }
    if (!empty($header)) {
      $this->request_headers = explode("\n", $header);
    }

  }

/**
 * Send the request to the target URL.
 *
 * @return boolean TRUE if the request was successful
 */
  public function send() {

    $ok = FALSE;
// Try using curl if available
    if (function_exists('curl_init')) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->url);
      if (!empty($this->request_headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->request_headers);
      } else {
        curl_setopt($ch, CURLOPT_HEADER, 0);
      }
      if ($this->method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request);
      } else if ($this->method != 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        if (!is_null($this->request)) {
          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request);
        }
      }
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, TRUE);
      curl_setopt($ch, CURLOPT_SSLVERSION,3);
      $ch_resp = curl_exec($ch);
      $ok = $ch_resp !== FALSE;
      if ($ok) {
        $ch_resp = str_replace("\r\n", "\n", $ch_resp);
        $ch_resp_split = explode("\n\n", $ch_resp, 2);
        if ((count($ch_resp_split) > 1) && (substr($ch_resp_split[1], 0, 5) == 'HTTP/')) {
          $ch_resp_split = explode("\n\n", $ch_resp_split[1], 2);
        }
        $this->response_headers = $ch_resp_split[0];
        $resp = $ch_resp_split[1];
        $this->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ok = $this->status < 400;
        if (!$ok) {
          $this->error = curl_error($ch);
        }
      }
      $this->request_headers = str_replace("\r\n", "\n", curl_getinfo($ch, CURLINFO_HEADER_OUT));
      curl_close($ch);
      $this->response = $resp;
    } else {
// Try using fopen if curl was not available
      $opts = array('method' => $this->method,
                    'content' => $this->request
                   );
      if (!empty($this->request_headers)) {
        $opts['header'] = $this->request_headers;
      }
      try {
        $ctx = stream_context_create(array('http' => $opts));
        $fp = @fopen($this->url, 'rb', false, $ctx);
        if ($fp) {
          $resp = @stream_get_contents($fp);
          $ok = $resp !== FALSE;
        }
      } catch (Exception $e) {
        $ok = FALSE;
      }
    }

    return $ok;

  }

}


/**
 * Class to represent an OAuth datastore
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class LTI_OAuthDataStore extends OAuthDataStore {

/**
 * @var LTI_Tool_Provider Tool Provider object.
 */
  private $tool_provider = NULL;

/**
 * Class constructor.
 *
 * @param LTI_Tool_Provider $tool_provider Tool_Provider object
 */
  public function __construct($tool_provider) {

    $this->tool_provider = $tool_provider;

  }

/**
 * Create an OAuthConsumer object for the tool consumer.
 *
 * @param string $consumer_key Consumer key value
 *
 * @return OAuthConsumer OAuthConsumer object
 */
  function lookup_consumer($consumer_key) {

    return new OAuthConsumer($this->tool_provider->consumer->getKey(),
       $this->tool_provider->consumer->secret);

  }

/**
 * Create an OAuthToken object for the tool consumer.
 *
 * @param string $consumer   OAuthConsumer object
 * @param string $token_type Token type
 * @param string $token      Token value
 *
 * @return OAuthToken OAuthToken object
 */
  function lookup_token($consumer, $token_type, $token) {

    return new OAuthToken($consumer, '');

  }

/**
 * Lookup nonce value for the tool consumer.
 *
 * @param OAuthConsumer $consumer  OAuthConsumer object
 * @param string        $token     Token value
 * @param string        $value     Nonce value
 * @param string        $timestamp Date/time of request
 *
 * @return boolean True if the nonce value already exists
 */
  function lookup_nonce($consumer, $token, $value, $timestamp) {

    $nonce = new LTI_Consumer_Nonce($this->tool_provider->consumer, $value);
    $ok = !$nonce->load();
    if ($ok) {
      $ok = $nonce->save();
    }
    if (!$ok) {
      $this->tool_provider->reason = 'Invalid nonce.';
    }

    return !$ok;

  }

/**
 * Get new request token.
 *
 * @param OAuthConsumer $consumer  OAuthConsumer object
 * @param string        $callback  Callback URL
 *
 * @return string Null value
 */
  function new_request_token($consumer, $callback = NULL) {

    return NULL;

  }

/**
 * Get new access token.
 *
 * @param string        $token     Token value
 * @param OAuthConsumer $consumer  OAuthConsumer object
 * @param string        $verifier  Verification code
 *
 * @return string Null value
 */
  function new_access_token($token, $consumer, $verifier = NULL) {

    return NULL;

  }

}


/**
 * Abstract class to provide a connection to a persistent store for LTI objects
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @version 2.5.00
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
abstract class LTI_Data_Connector {

/**
 * Default name for database table used to store tool consumers.
 */
  const CONSUMER_TABLE_NAME = 'lti_consumer';
/**
 * Default name for database table used to store resource links.
 */
  const CONTEXT_TABLE_NAME = 'lti_context';
  const RESOURCE_LINK_TABLE_NAME = 'lti_context';
/**
 * Default name for database table used to store users.
 */
  const USER_TABLE_NAME = 'lti_user';
/**
 * Default name for database table used to store resource link share keys.
 */
  const RESOURCE_LINK_SHARE_KEY_TABLE_NAME = 'lti_share_key';
/**
 * Default name for database table used to store nonce values.
 */
  const NONCE_TABLE_NAME = 'lti_nonce';

/**
 * @var string SQL date format (default = 'Y-m-d')
 */
  protected $date_format = 'Y-m-d';
/**
 * @var string SQL time format (default = 'H:i:s')
 */
  protected $time_format = 'H:i:s';

/**
 * Load tool consumer object.
 *
 * @param mixed $consumer LTI_Tool_Consumer object
 *
 * @return boolean True if the tool consumer object was successfully loaded
 */
  abstract public function Tool_Consumer_load($consumer);
/**
 * Save tool consumer object.
 *
 * @param LTI_Tool_Consumer $consumer Consumer object
 *
 * @return boolean True if the tool consumer object was successfully saved
 */
  abstract public function Tool_Consumer_save($consumer);
/**
 * Delete tool consumer object.
 *
 * @param LTI_Tool_Consumer $consumer Consumer object
 *
 * @return boolean True if the tool consumer object was successfully deleted
 */
  abstract public function Tool_Consumer_delete($consumer);
/**
 * Load tool consumer objects.
 *
 * @return array Array of all defined LTI_Tool_Consumer objects
 */
  abstract public function Tool_Consumer_list();

/**
 * Load resource link object.
 *
 * @param LTI_Resource_Link $resource_link Resource_Link object
 *
 * @return boolean True if the resource link object was successfully loaded
 */
  abstract public function Resource_Link_load($resource_link);
/**
 * Save resource link object.
 *
 * @param LTI_Resource_Link $resource_link Resource_Link object
 *
 * @return boolean True if the resource link object was successfully saved
 */
  abstract public function Resource_Link_save($resource_link);
/**
 * Delete resource link object.
 *
 * @param LTI_Resource_Link $resource_link Resource_Link object
 *
 * @return boolean True if the Resource_Link object was successfully deleted
 */
  abstract public function Resource_Link_delete($resource_link);
/**
 * Get array of user objects.
 *
 * @param LTI_Resource_Link $resource_link      Resource link object
 * @param boolean     $local_only True if only users within the resource link are to be returned (excluding users sharing this resource link)
 * @param int         $id_scope     Scope value to use for user IDs
 *
 * @return array Array of LTI_User objects
 */
  abstract public function Resource_Link_getUserResultSourcedIDs($resource_link, $local_only, $id_scope);
/**
 * Get array of shares defined for this resource link.
 *
 * @param LTI_Resource_Link $resource_link Resource_Link object
 *
 * @return array Array of LTI_Resource_Link_Share objects
 */
  abstract public function Resource_Link_getShares($resource_link);

/**
 * Load nonce object.
 *
 * @param LTI_Consumer_Nonce $nonce Nonce object
 *
 * @return boolean True if the nonce object was successfully loaded
 */
  abstract public function Consumer_Nonce_load($nonce);
/**
 * Save nonce object.
 *
 * @param LTI_Consumer_Nonce $nonce Nonce object
 *
 * @return boolean True if the nonce object was successfully saved
 */
  abstract public function Consumer_Nonce_save($nonce);

/**
 * Load resource link share key object.
 *
 * @param LTI_Resource_Link_Share_Key $share_key Resource_Link share key object
 *
 * @return boolean True if the resource link share key object was successfully loaded
 */
  abstract public function Resource_Link_Share_Key_load($share_key);
/**
 * Save resource link share key object.
 *
 * @param LTI_Resource_Link_Share_Key $share_key Resource link share key object
 *
 * @return boolean True if the resource link share key object was successfully saved
 */
  abstract public function Resource_Link_Share_Key_save($share_key);
/**
 * Delete resource link share key object.
 *
 * @param LTI_Resource_Link_Share_Key $share_key Resource link share key object
 *
 * @return boolean True if the resource link share key object was successfully deleted
 */
  abstract public function Resource_Link_Share_Key_delete($share_key);

/**
 * Load user object.
 *
 * @param LTI_User $user User object
 *
 * @return boolean True if the user object was successfully loaded
 */
  abstract public function User_load($user);
/**
 * Save user object.
 *
 * @param LTI_User $user User object
 *
 * @return boolean True if the user object was successfully saved
 */
  abstract public function User_save($user);
/**
 * Delete user object.
 *
 * @param LTI_User $user User object
 *
 * @return boolean True if the user object was successfully deleted
 */
  abstract public function User_delete($user);

/**
 * Create data connector object.
 *
 * A type and table name prefix are required to make a database connection.  The default is to use MySQL with no prefix.
 *
 * If a data connector object is passed, then this is returned unchanged.
 *
 * If the $data_connector parameter is a string, this is used as the prefix.
 *
 * If the $data_connector parameter is an array, the first entry should be a prefix string and an optional second entry
 * being a string containing the database type or a database connection object (e.g. the value returned by a call to
 * mysqli_connect() or a PDO object).  A bespoke data connector class can be specified in the optional third parameter.
 *
 * @param mixed  $data_connector A data connector object, string or array
 * @param mixed  $db             A database connection object or string (optional)
 * @param string $type           The type of data connector (optional)
 *
 * @return LTI_Data_Connector Data connector object
 */
  static function getDataConnector($data_connector, $db = NULL, $type = NULL) {

    if (!is_null($data_connector)) {
      if (!is_object($data_connector) || !is_subclass_of($data_connector, get_class())) {
        $prefix = NULL;
        if (is_string($data_connector)) {
          $prefix = $data_connector;
        } else if (is_array($data_connector)) {
          for ($i = 0; $i < min(count($data_connector), 3); $i++) {
            if (is_string($data_connector[$i])) {
              if (is_null($prefix)) {
                $prefix = $data_connector[$i];
              } else if (is_null($type)) {
                $type = $data_connector[$i];
              }
            } else if (is_null($db)) {
              $db = $data_connector[$i];
            }
          }
        } else if (is_object($data_connector)) {
          $db = $data_connector;
        }
        if (is_null($prefix)) {
          $prefix = '';
        }
        if (!is_null($db)) {
          if (is_string($db)) {
            $type = $db;
            $db = NULL;
          } else if (is_null($type)) {
            if (is_object($db)) {
              $type = get_class($db);
            } else {
              $type = 'mysql';
            }
          }
        }
        if (is_null($type)) {
          $type = 'mysql';
        }
        $type = strtolower($type);
        if ($type == 'mysql') {
          $db = NULL;
        }
        $type = "LTI_Data_Connector_{$type}";
        require_once("{$type}.php");
        if (is_null($db)) {
          $data_connector = new $type($prefix);
        } else {
          $data_connector = new $type($db, $prefix);
        }
      }
    }

    return $data_connector;

  }

/**
 * Generate a random string.
 *
 * The generated string will only comprise letters (upper- and lower-case) and digits.
 *
 * @param int $length Length of string to be generated (optional, default is 8 characters)
 *
 * @return string Random string
 */
  static function getRandomString($length = 8) {

    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    $value = '';
    $charsLength = strlen($chars) - 1;

    for ($i = 1 ; $i <= $length; $i++) {
      $value .= $chars[rand(0, $charsLength)];
    }

    return $value;

  }

/**
 * Quote a string for use in a database query.
 *
 * Any single quotes in the value passed will be replaced with two single quotes.  If a null value is passed, a string
 * of 'NULL' is returned (which will never be enclosed in quotes irrespective of the value of the $addQuotes parameter.
 *
 * @param string $value     Value to be quoted
 * @param string $addQuotes If true the returned string will be enclosed in single quotes (optional, default is true)
 *
 * @return boolean True if the user object was successfully deleted
 */
  static function quoted($value, $addQuotes = TRUE) {

    if (is_null($value)) {
      $value = 'NULL';
    } else {
      $value = str_replace('\'', '\'\'', $value);
      if ($addQuotes) {
        $value = "'{$value}'";
      }
    }

    return $value;

  }

}

?>
