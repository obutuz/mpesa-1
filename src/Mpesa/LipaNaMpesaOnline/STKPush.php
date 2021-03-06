<?php

namespace Kabangi\Mpesa\LipaNaMpesaOnline;

use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Kabangi\Mpesa\Engine\Core;
use Kabangi\Mpesa\Repositories\EndpointsRepository;

class STKPush{

    protected $pushEndpoint;

    protected $engine;

    protected $validationRules = [
        'BusinessShortCode:BusinessShortCode' => 'required()({label} is required) | number',
        'Password:Password' => 'required()({label} is required)',
        'Timestamp:Timestamp' => 'required()({label} is required)',
        'TransactionType:TransactionType' => 'required()({label} is required)',
        'Amount:Amount' => 'required()({label} is required) | number()({label} should be a numeric value)',
        'PartyA:Party A' => 'required()({label} is required)',
        'PartyB:PartyB' => 'required()({label} is required)',
        'PhoneNumber:PhoneNumber' => 'required()({label} is required)',
        'CallBackURL:CallBackURL' => 'required()({label} is required) | website',
        'AccountReference:AccountReference' => 'required()({label} is required)',
        'TransactionDesc:TransactionDesc' => 'required()({label} is required)'
    ];

    /**
     * STK constructor.
     *
     * @param Core $engine
     */
    public function __construct(Core $engine)
    {
        $this->engine       = $engine;
        $this->pushEndpoint = EndpointsRepository::build(MPESA_STK_PUSH);
        $this->engine->addValidationRules($this->validationRules);
    }
    

    /**
     * Initiate STK push request
     * 
     * @param Array $params
     * 
    */
    public function submit($params = []){
        // Make sure all the indexes are in Uppercases as shown in docs
        $userParams = [];
        foreach ($params as $key => $value) {
            $userParams[ucwords($key)] = $value;
        }

        $time      = Carbon::now()->format('YmdHis');
        $shortCode = $this->engine->config->get('mpesa.short_code');
        $passkey   = $this->engine->config->get('mpesa.lnmo.passkey');
        $password  = \base64_encode($shortCode . $passkey . $time);

        // Computed and params from config file.
        $configParams = [
            'BusinessShortCode' => $shortCode,
            'CallBackURL'       => $this->engine->config->get('mpesa.lnmo.callback'),
            'TransactionType'   => $this->engine->config->get('mpesa.lnmo.default_transaction_type'),
            'Password'          => $password,
            'PartyB'            => $shortCode,
            'Timestamp'         => $time,
        ];

        // This gives precedence to params coming from user allowing them to override config params
        $body = array_merge($configParams,$userParams);
        if(empty($body['PartyA']) && !empty($body['PhoneNumber'])){
            $body['PartyA'] = $body['PhoneNumber'];
        }
        
        // Validate $body based on the daraja docs.
        $validationResponse = $this->engine->validateParams($body);
        if($validationResponse !== true){
            return $validationResponse;
        }

        try {
            return $this->engine->makePostRequest([
                'endpoint' => $this->pushEndpoint,
                'body' => $body
            ]);
        } catch (RequestException $exception) {
            return \json_decode($exception->getResponse()->getBody());
        }
    }
}
