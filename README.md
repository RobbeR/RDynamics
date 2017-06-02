# RDynamics
Dynamics 365 Online CRM Web API - Lightweight PHP Connector

Initializing:

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


Querying contacts:

    $contactsResponse = $RDynamics->contacts->select('?$select=fullname');
    if($contactsResponse->isSuccess()) {
        // $contactsResponse->getData(); - Return data as array
    }
    else {
        // $contactsResponse->getErrorMessage(); - Return CRM Web API error message as string
    }

Querying contacts (when the number of contacts is more then 5000, you need paging):

    $i = 1;
    do {
        if((isset($contactsResponse) && $contactsResponse)) {
            $endpoint = $contactsResponse->getNextLink();
            if(!$endpoint) { // no next link defined, exiting
                break;
            }
        }
        else { // first loop
            $endpoint = '?$select=gendercode,fullname';
        }

        $contactsResponse = $RDynamics->contacts->select($endpoint);
        if($contactsResponse->isSuccess()) {
            // $contactsResponse->getData(); - as array
            ++$i;
        }
        else {
           // $contactsResponse->getErrorMessage(); - or ->getError() to get the full error object (with error_code and more)
        }
    } while($contactsResponse->getNextLink());

Inserting contact:

    $contactsResponse = $RDynamics->contacts->insert(array(
        "emailaddress1"     => "some_test_email"
    ));

    if($contactsResponse->isSuccess()) {
        // $contactsResponse->getGuidCreated(); - Get the GUID of the created entity
    }
    else {
        // $contactsResponse->getErrorMessage(); - Get the error message as string
    }

Updating contact

    $contactsResponse = $RDynamics->contacts->update('00000000-0000-0000-0000-000000000000', array(
        "emailaddress1"     => "some_test_email"
    ));

    if($contactsResponse->isSuccess()) {
        // $contactsResponse->getData(); - Get the response data
        // $contactsResponse->getHeaders(); - Get the response headers
    }
    else {
        // $contactsResponse->getErrorMessage(); - Get the error message as string
    }

Deleting contact:

    $contactsResponse = $RDynamics->contacts->delete('00000000-0000-0000-0000-000000000000');
    if($contactsResponse->isSuccess()) {
        // $contactsResponse->getData(); - Get the response data
        // $contactsResponse->getHeaders(); - Get the response headers
    }
    else {
        // $contactsResponse->getErrorMessage(); - Get the error message as string
    }

Running batch methods (max. 1000 request per batch):

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

    PATCH https://YOUT_CRM_INSTANCE.crm4.dynamics.com/api/data/v8.1/contacts($customerID) HTTP/1.1
    Content-Type: application/json;type=entry

    {"ftpsiteurl":"ftp://..."}

    EOT;
            ++$i;
        }
        $payload .= "--" . $batchID . "--\n\n";
        $batchResponse = $RDynamics->performBatchRequest($payload, $batchID);

        // $contactsResponse->getData(); - Get the response data
        // $contactsResponse->getHeaders(); - Get the response headers
    }
    else {
        // $contactsResponse->getErrorMessage(); - Get the error message as string
    }


