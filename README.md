# RDynamics
Dynamics CRM Web API - Lightweight PHP Connector

Examples:

    $RDynamics = new RDynamics(array(
        "base_url"              => "https://YOUR_CRM_INSTANCE.crm4.dynamics.com",
        "authEndPoint"          => "https://login.windows.net/common/oauth2/authorize",
        'tokenEndPoint'         => 'https://login.windows.net/common/oauth2/token',
        'crmApiEndPoint'        => 'https://YOUR_CRM_INSTANCE.api.crm4.dynamics.com/',
        "clientID"              => "***", 
        "clientSecret"          => "***", 
        'user'                  => '***',
        'pass'                  => '*'
    ));

    // SELECT
        $contactsResponse = $RDynamics->contacts->select('?$select=fullname');
        if($contactsResponse->isSuccess()) {
            die(json_encode($contactsResponse->getData()));
        }
        else {
            die($contactsResponse->getErrorMessage());
        }

    // SELECT (with paging)
        $i = 1;
        do {
            if((isset($contactsResponse) && $contactsResponse)) {
                $endpoint = $contactsResponse->getNextLink();
                if(!$endpoint) {
                    echo "END...";
                    break;
                }
            }
            else { // first loop
                $endpoint = '?$select=gendercode,fullname';
            }

            $contactsResponse = $RDynamics->contacts->select($endpoint);
            if($contactsResponse->isSuccess()) {
                echo "<br />-----------------PAGE " . $i . "-----------------<br />\n";
                echo count($contactsResponse->getData()) . "contact<br />\n";
                echo "<br />-----------------END OF PAGE " . $i . "-----------------<br />\n";
                ++$i;
            }
            else {
                die($contactsResponse->getErrorMessage());
            }
        } while($contactsResponse->getNextLink());
        die("END OF PAGING");

    // INSERT
        $contactsResponse = $RDynamics->contacts->insert(array(
            "emailaddress1"     => "some_test_email"
        ));

        if($contactsResponse->isSuccess()) {
            die(var_dump($contactsResponse->getGuidCreated()));
        }
        else {
            die($contactsResponse->getErrorMessage());
        }

    // UPDATE
        $contactsResponse = $RDynamics->contacts->update('00000000-0000-0000-0000-000000000000', array(
            "emailaddress1"     => "some_test_email"
        ));

        if($contactsResponse->isSuccess()) {
            die(json_encode(array(
                "data"      => $contactsResponse->getData(),
                "headers"   => $contactsResponse->getHeaders()
            )));
        }
        else {
            die($contactsResponse->getErrorMessage());
        }

    // DELETE
        $contactsResponse = $RDynamics->contacts->delete('00000000-0000-0000-0000-000000000000');

        if($contactsResponse->isSuccess()) {
            die(json_encode(array(
                "data"      => $contactsResponse->getData(),
                "headers"   => $contactsResponse->getHeaders()
            )));
        }
        else {
            die($contactsResponse->getErrorMessage());
        }

    // Batch run
        $contactsResponse = $RDynamics->contacts->select('?$top=10');
        if($contactsResponse->isSuccess()) {
            $customers = $contactsResponse->getData();
            $batchID = "batch_" . uniqid();
            $payload = '';
            $i = 1;

            foreach($customers as $customer) {
                $customerID = $customer["contactid"];
                $payload .= <<<EOT
--$batchID
Content-Type: application/http
Content-Transfer-Encoding:binary
Content-ID:$i

PATCH https://armsys-test.crm4.dynamics.com/api/data/v8.1/contacts($customerID) HTTP/1.1
Content-Type: application/json;type=entry

{"ftpsiteurl":"ftp://..."}

EOT;
                ++$i;
            }
            $payload .= "--" . $batchID . "--\n\n";
            $batchResponse = $RDynamics->performBatchRequest($payload, $batchID);

            die(json_encode(array(
                "data"      => $batchResponse->getData(),
                "headers"   => $batchResponse->getHeaders()
            )));
        }
        else {
            die($contactsResponse->getErrorMessage());
        }


