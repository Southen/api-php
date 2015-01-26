<?php
abstract class API {
    /**
     * Property: method
     * The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method = '';
    /**
     * Property: endpoint
     * The Model requested in the URI. eg: /files
     */
    protected $endpoint = '';
    /**
     * Property: verb
     * An optional additional descriptor about the endpoint, used for things that can
     * not be handled by the basic methods. eg: /files/process
     */
    protected $verb = '';
    /**
     * Property: args
     * Any additional URI components after the endpoint and verb have been removed, in our
     * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
     * or /<endpoint>/<arg0>
     */
    protected $args = array();
    /**
     * Property: file
     * Stores the input of the PUT or POST request
     */
    protected $file = null;
    /**
     * Property: type
     * Stores the content type of the input
     */
    protected $type = '';

    /**
     * Constructor: __construct
     * Allow for CORS, assemble and pre-process the data
     */
    public function __construct($request) {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: *");
        header("Content-Type: application/json");

        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);
        if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])) {
            $this->verb = array_shift($this->args);
        }

        $this->method = $_SERVER['REQUEST_METHOD'];
        if (($this->method == 'POST') && (array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER))) {
            if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
                $this->method = 'DELETE';
            }
            else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
                $this->method = 'PUT';
            }
            else {
                throw new Exception("Unexpected Header");
            }
        }

        switch($this->method) {
        case 'POST':    // Create
            $this->file = file_get_contents("php://input");
            if (strlen($this->file) > 0) {
                $this->type = $_SERVER['CONTENT_TYPE'];
            }
            $this->request = array_merge($this->_cleanInputs($_GET), $this->_cleanInputs($_POST));
            break;
        case 'GET':     // Read
            $this->request = $this->_cleanInputs($_GET);
            break;
        case 'PUT':     // Update
            $this->file = file_get_contents("php://input");
            if (strlen($this->file) > 0) {
                $this->type = $_SERVER['CONTENT_TYPE'];
            }
            $this->request = $this->_cleanInputs($_GET);
            break;
        case 'DELETE':  // Delete
            $this->request = $this->_cleanInputs($_POST);
            break;
        // PATCH
        case 'OPTIONS':
            header('Allow: GET,HEAD,POST,OPTIONS');
            $this->_response(null, 200);
            break;
        // HEAD
        // TRACE
        // CONNECT
        default:
            $this->_response('Invalid Method', 405);
            break;
        }
    }

    public function processAPI() {
        if ((int)method_exists($this, $this->endpoint) > 0) {
            try {
                return $this->_response($this->{$this->endpoint}($this->args));
            }
            catch (Exception $e) {
                //var_dump($e->getMessage()); exit;
                // http://tools.ietf.org/html/draft-pbryan-http-json-resource-01
                return $this->_response(
                    array(
                        'error' => ($e->getCode() ? $e->getCode() : 500),
                        'reason' => ($e->getMessage() ? $e->getMessage() : $this->_requestStatus($e->getCode()))
                    ),
                    ($e->getCode() ? $e->getCode() : 500)
                );
            }
        }
        return $this->_response("No Endpoint: $this->endpoint", 404);
    }

    protected function _response($data, $status = 200) {
        if (http_response_code() == 200) {
        //if ((!isset($GLOBALS['http_response_code'])) || ($GLOBALS['http_response_code'] == 200)) {
            header($_SERVER["SERVER_PROTOCOL"] . " " . $status . " " . $this->_requestStatus($status));
        }
        return json_encode($data, JSON_UNESCAPED_SLASHES/*|JSON_NUMERIC_CHECK*/|JSON_PRETTY_PRINT);
    }

    private function _cleanInputs($data) {
        $clean_input = array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->_cleanInputs($v);
            }
        }
        else {
            $clean_input = trim(strip_tags($data));
        }
        return $clean_input;
    }

    private function _requestStatus($code) {
        $status = array(
            200 => 'OK',
            201 => 'Created',                       // POST/PUT resulted in a new resource, MUST include Location header
            202 => 'Accepted',                      // request accepted for processing but not yet completed, might be disallowed later
            204 => 'No Content',                    // DELETE/PUT fulfilled, MUST NOT include message-body
            304 => 'Not Modified',                  // If-Modified-Since, MUST include Date header
            400 => 'Bad Request',                   // malformed syntax
            403 => 'Forbidden',                     // unauthorized
            404 => 'Not Found',                     // request URI does not exist
            405 => 'Method Not Allowed',            // HTTP method unavailable for URI, MUST include Allow header
            415 => 'Unsupported Media Type',        // unacceptable request payload format for resource and/or method
            426 => 'Upgrade Required',
            451 => 'Unavailable For Legal Reasons', // REDACTED
            500 => 'Internal Server Error',         // all other errors
            501 => 'Not Implemented'                // (currently) unsupported request method
        );
        return (isset($status[$code]) ? $status[$code] : $status[500]);
    }
}
?>
