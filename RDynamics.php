<?php

    class RDynamicsResponse {

        private $endpoint = '';
        private $rawResponse = '';
        private $data = false;
        private $responseHeaders = array();
        private $originMethod = '';

        public function __construct($responseBody, $respHeaders, $endpoint, $originMethod, $rawResponse = '') {
            $this->rawResponse = $rawResponse;

            if($originMethod != "batch") {
                $this->data = json_decode($responseBody, true);
            }
            else {
                $this->data = $responseBody;
            }
            
            $this->endpoint = $endpoint;
            $this->originMethod = $originMethod;

            $array = explode("\r\n",$respHeaders);
            foreach($array as $h) {
                $r = explode(": ", $h);
                if(count($r) > 1) {
                    $this->responseHeaders[$r[0]] = $r[1];
                }
            }
        }

        /**
        * Get raw response
        *
        * @return string
        */
        public function getRawResponse() {
            return $this->rawResponse;
        }

        /**
        * Get original endpoint
        *
        * @return string
        */
        public function getEndpoint() {
            return $this->endpoint;
        }

        // Error handling 

            /**
            * Returns true if request didn't contain errors. False anyway.
            *
            * @return bool
            */
            public function isSuccess() {
                if($this->originMethod == "batch") {
                    return true;
                }
                
                if(isset($this->data["error"])) {
                    return false;
                }
                else {
                    return true;
                }
            }
            

            /**
            * Returns true if request contain errors. False anyway.
            *
            * @return bool
            */
            public function isFail() {
                return !$this->isSuccess();
            }

            /**
            * Get the error array if there was an error, null anyway.
            *
            * @return array
            */
            public function getError() {
                if($this->isSuccess()) {
                    return null;
                }

                return $this->data["error"];
            }

            /**
            * Get the error message if there was an error, null anyway.
            *
            * @return string
            */
            public function getErrorMessage() {
                if($this->isSuccess()) {
                    return null;
                }

                return $this->data["error"]["message"];
            }

        // data manipulation

            /**
            * Get the response data of the request as array. If there was an array, returns false.
            *
            * @return array
            */
            public function getData() {
                if($this->originMethod == "batch") {
                    return $this->data;
                }

                if($this->originMethod == "select" && preg_match('/\([a-zA-Z0-9\-]{36}\)$/', $this->endpoint)) {
                    return $this->data;
                }

                if(!$this->isSuccess()) {
                    return false;
                }

                return $this->data["value"];
            }

            /**
            * Get the request nextlink (if exists), false anyway
            *
            * @return string
            */
            public function getNextLink() {
                if(!$this->isSuccess()) {
                    return false;
                }

                if(!is_array($this->data) || !isset($this->data["@odata.nextLink"]) || !$this->data["@odata.nextLink"]) {
                    return false;
                }

                return $this->data["@odata.nextLink"];
            }

            /**
            * Get the ID of the newly created entity (only when the request type is insert (POST)), false anyway.
            *
            * @return string
            */
            public function getGuidCreated() {
                $result = false;
                if (isset($this->responseHeaders["OData-EntityId"])) {
                    $result = $this->responseHeaders["OData-EntityId"];
                }

                preg_match('/\(.*\)/', $result, $matches);

                if (count($matches) > 0) {
                    $result = $matches[0];
                    $result = str_replace(array("(",")"), "", $result);
                }

                return $result;
            }

            /**
            * Get the response headers as array.
            *
            * @return array
            */
            public function getHeaders() {
                return $this->responseHeaders;
            }
    }

    class RDynamicsWorker {

        private $entity = null;
        private $config = false;
        private $apiVersion = '8.2';

        private function fetchToken() {
            $params = array(
                'grant_type'    => 'password',
                'username'      => $this->config['user'],
                'password'      => $this->config['pass'],
                'scope'         => 'openid profile email',
                'resource'      => $this->config['crmApiEndPoint'],
                'client_id'     => $this->config['clientID'],
                'client_secret' => $this->config['clientSecret'],
            );

            $curl = curl_init();
            $curlopts = [
                CURLOPT_URL             => $this->config['tokenEndPoint'],
                CURLOPT_HEADER          => false,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_FOLLOWLOCATION  => true,
                CURLOPT_CONNECTTIMEOUT  => 3,
                CURLOPT_TIMEOUT         => 12,
                CURLOPT_MAXREDIRS       => 12,
                CURLOPT_SSL_VERIFYPEER  => 0
            ];
            $curlopts[CURLOPT_POST] = true;
            $curlopts[CURLOPT_POSTFIELDS] = $params;
            curl_setopt_array($curl, $curlopts);

            $response = curl_exec($curl);
            $response = json_decode($response, true);

            if(isset($response["error"])) { // error happened
                return array(
                    'success'       => false,
                    'error'         => $response["error"],
                    'description'   => $response["error_description"],
                    'access_token'  => false,
                    'id_token'      => false,
                    'refresh_token' => false
                );
            }

            return array(
                'success'       => true,
                'error'         => false,
                'description'   => false,
                'access_token'  => $response["access_token"],
                'id_token'      => $response["id_token"],
                'refresh_token' => $response["refresh_token"]
            );
        }

        /*
        * Perform a cURL request to the CRM Web API
        *
        * @param $endpoint - The API endpoint without the API URL
        * @param $method - The method of the request. Could be 'POST', 'PATCH', 'GET', 'DELETE'
        * @param $payload - On Insert/Update requests (POST/PATCH) the array of the fields to insert/update
        * @param $customHeaders - Extra headers from users. Default headers: Authorization, Content-type and Accept
        * @param $originMethod - Could be 'insert', 'update', 'delete', 'select' 
        */
        private function performRequest($endpoint, $method, $payload = false, $customHeaders, $originMethod) {
            try {
                $endpoint = str_replace(" ", "%20", $endpoint);
                $endpoint = str_replace("''", "%27", $endpoint);

                if(!preg_match('/^http(s)?\:\/\//', $endpoint)) {
                    if(!preg_match('/^\//', $endpoint)) {
                        $endpoint = '/' . $endpoint;
                    }

                    $request = $this->config["crmApiEndPoint"] . "api/data/v". $this->apiVersion . $endpoint;
                }
                else {
                    $request = $endpoint;
                }

                $curl = curl_init($request);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

                $token = $this->fetchToken();

                if(!$token["success"]) {
                    return new RDynamicsResponse(json_encode(array(
                        'error'     => array(
                            'message'   => '<strong>TOKEN ERROR</strong> (' . $token["error"] . '): ' . $token["description"]
                        )
                    )), array(), $this->config['tokenEndPoint'], "fetch_token");
                }

                $requestHeaders = array(
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token["access_token"]
                );

                if($originMethod != "batch") {
                    $requestHeaders[] = "Content-Type: application/json";
                }

                if($customHeaders && is_array($customHeaders)) {
                    foreach($customHeaders as $customHeader) {
                        if(!preg_match('/^Authorization/i', $customHeader)) {
                            $requestHeaders[] = $customHeader;
                        }
                    }
                }
                
                curl_setopt($curl, CURLOPT_HTTPHEADER, $requestHeaders);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($curl, CURLOPT_VERBOSE, 1);
                curl_setopt($curl, CURLOPT_HEADER, 1);
                
                if($payload && in_array($originMethod, array('insert', 'update', 'batch'))) { // In case of insert and update methods
                    if(is_array($payload)) {
                        $payload = json_encode($payload);
                    }

                    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                }

                $response = curl_exec($curl);
                $rawResponse = $response;
                // get response headers
                $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $responseHeaders = substr($response, 0, $headerSize);
                $responseBody = substr($response, $headerSize);

                return new RDynamicsResponse($responseBody, $responseHeaders, $endpoint, $originMethod, $rawResponse);
            }
            catch(\Exception $e) {
                return false;
            }
        }

        public function __construct($entity, $config) {
            $this->config = $config;
            $this->entity = $entity;
        }

        public function performBatchRequest($payload, $batchID) {
            return $this->performRequest('/$batch', 'POST', $payload, array(
                'Content-Type: multipart/mixed;boundary=' . $batchID
            ), 'batch');
        }

        /*
        * Querying entities
        *
        * @param $endpoint - The API endpoint without the API URL
        * @param $extraHeaders - Extra headers from users. Default headers: Authorization, Content-type and Accept
        */
        public function select($endpoint = '', $extraHeaders = false) {
            if(!preg_match('/^http(s)?\:\/\//', $endpoint)) {
                $endpoint = '/' . $this->entity . $endpoint;
            }
            
            return $this->performRequest($endpoint, 'GET', false, $extraHeaders, "select");
        }

        /*
        * Insering entities
        *
        * @param $payload - On Insert/Update requests (POST/PATCH) the array of the fields to insert/update
        * @param $extraHeaders - Extra headers from users. Default headers: Authorization, Content-type and Accept
        */
        public function insert($payload, $extraHeaders = false) {
            return $this->performRequest('/' . $this->entity, 'POST', $payload, $extraHeaders, "insert");
        }

        /*
        * Updating entities
        *
        * @param $GUID - The GUID of the entity you want to update
        * @param $payload - On Insert/Update requests (POST/PATCH) the array of the fields to insert/update
        */
        public function update($GUID, $payload, $extraHeaders = false) {
            return $this->performRequest('/' . $this->entity . '(' . $GUID . ')', 'PATCH', $payload, $extraHeaders, "update");
        }

        /*
        * Deleting entities
        *
        * @param $GUID - The GUID of the entity you want to delete
        */
        public function delete($GUID, $extraHeaders = false) {
            return $this->performRequest('/' . $this->entity . '(' . $GUID . ')', 'DELETE', false, $extraHeaders, "delete");
        }

    }

    class RDynamics
    {
        private $config = array(
            'authEndPoint'          => '',
            'tokenEndPoint'         => '',
            'crmApiEndPoint'        => '',
            'clientID'              => '',
            'clientSecret'          => '',
            'user'                  => '',
            'pass'                  => ''
        );

        public function __construct($config) {
            $this->config = $config;
        }

        public function performBatchRequest($payload, $batchID) {
            $worker = new RDynamicsWorker('contacts', $this->config);
            return $worker->performBatchRequest($payload, $batchID);
        }

        public function __get($prop) {
            $worker = new RDynamicsWorker($prop, $this->config);
            return $worker;
        }
    }
    
