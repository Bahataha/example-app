<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Kyc\KycSystems;

class VeriLiveController extends Controller
{
    private $kycService;

    public function __construct(KycSystems $service){
        $this->kycService = $service;
    }

    public function index(Request $request){
        $imageName = str_random(10).'.'.'png';
        \File::put(public_path('verilive/') . $imageName, base64_decode(json_decode(json_encode($request->json()->all()))->bestframe));

        return response()->json([
            'status' => true, 
            'message' => 'ok'
        ]);
    }
}
