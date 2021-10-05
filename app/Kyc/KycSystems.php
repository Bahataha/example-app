<?php

namespace App\Kyc;

use Illuminate\Http\JsonResponse;

class KycSystems
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $token = '';

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function setToken($token)
    {
        $this->request->setHeaders([
            'Authorization' => $token,
        ]);
    }

    public function verify(array $params, $iin)
    {
        return $this->request
            ->setPath('api/verify?iin=' . $iin)
            ->setParams($params)
            ->process();
    }

    
}