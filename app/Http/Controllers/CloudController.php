<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Kyc\KycSystems;
use GuzzleHttp\Psr7;
use Illuminate\Support\Str;
use SimpleXMLElement;
use thiagoalessio\TesseractOCR\TesseractOCR;

class CloudController extends Controller
{
    private $kycService;

    private $vision;

    public function __construct(KycSystems $service, TesseractOCR $vision){
        $this->kycService = $service;
        $this->vision = $vision;
    }

    /**
     * @throws \Exception
     */
    public function index(Request $request)
    {
        if($request->has('image')){
            $photoDoc = $request->image;
            $image = str_replace('data:image/png;base64,', '', $photoDoc);
            $image = str_replace(' ', '+', $image);
            $fileName = STR::random(40) . '.png';
//            $destinationPath = Storage::disk('public')->put($fileName);
            Storage::disk('public')->put($fileName, base64_decode($image));
        }
        else{
            return response()->json([
                'status' => false,
                'message' => 'фотография не были переданы'
            ]);
        }

        $path = Storage::disk('public')->path('') . $fileName;
        try{
            $result = $this->vision->image($path)->lang('eng', 'kaz')->psm(11)->configFile('alto')->oem(2)->run();

            $xml = new SimpleXMLElement($result);
            $xml = json_encode($xml);
            $xml = json_decode($xml, true);

            $strings = [];
            foreach ($xml['Layout']['Page']['PrintSpace']['ComposedBlock'] as $item){
                if(count($item['TextBlock']['TextLine']['String']) == 1){
                    foreach ($item['TextBlock']['TextLine']['String'] as $attribute){
                        $strings[] = $attribute;
                    }
                }
                else{
                    foreach ($item['TextBlock']['TextLine']['String'] as $attribute){
                        $strings[] = $attribute['@attributes'];
                    }
                }
            }

            $patterns = [
                'point-iin' => '/^\d{12}/',
                'point-1' => sprintf('/^(%s)$/iu', implode('|', ['ҚАЗАҚСТАН', 'QAZAQSTAN', 'KA3AKCTAH', 'ҚАЗАKСТАН', 'KA3AҚCTAH'])),
                'point-2' => sprintf('/^(%s)$/iu', implode('|', ['РЕСПУБЛИКА'])),
                'point-3' => sprintf('/^(%s)$/iu', 'УДОСТОВЕРЕНИЕ'),
                'point-4' => sprintf('/^(%s)$/iu', 'Личности'),
            ];

            $points = [];
            foreach ($strings as $item) {
                foreach ($patterns as $point => $pattern) {
                    if (preg_match($pattern, $item['CONTENT'], $matches)) {
                        $points[$point] = [
                            'x' => $item['HPOS'],
                            'y' => $item['VPOS'],
                            'width' => $item['WIDTH'],
                            'height' => $item['HEIGHT'],
                            'content' => $item['CONTENT'],
                        ];
                    }
                }
            }

            $criteria = [
                ['point-1:point-2', 1.475],
                ['point-1:point-3', 1.365],
                ['point-2:point-1', 0.678],
                ['point-2:point-3', 0.925],
                ['point-3:point-1', 0.733],
                ['point-3:point-2', 1.081],
                ['point-4:point-3', 0.684],
            ];

            $criteriaResults = [];
            foreach ($criteria as $target) {
                $pair = explode( ':', $target[0]);
                if (! isset($points[$pair[0]]) || ! isset($points[$pair[1]])) {
                    continue;
                }
                $fact = $this->distance($points['point-iin'], $points[$pair[0]]) / $this->distance($points['point-iin'], $points[$pair[1]]);
                if ($fact / $target[1] > 0.94 && $fact / $target[1] < 1.06) {
                    $criteriaResults[$target[0]] = [
                        'fact' => $fact,
                        'model' => $target[1],
                    ];
                }
            }
        }
        catch (\Exception $e){
            Storage::disk('public')->delete($fileName);
            return response()->json(['message' => 'not enough dsdfots', 'status' => 'error'], 205);
        }


        if (count($criteriaResults) >= 3){
            return response()->json(['message' => 'ok', 'status' => 'ok']);
        }
        else{
            Storage::disk('public')->delete($fileName);
            return response()->json(['message' => 'not enough dots', 'status' => 'error'], 205);
        }

        return $criteriaResults;

        preg_match('/\d{12}/m', $result, $matches);

        $iin = $results['point-3']['content'];
        if(isset($iin)){
            if(!$this->testIIN($iin)){
                return response()->json([
                    "status" => false,
                    "message" => "Не правильный ИИН"
                ]);
            }
        }
        else{
            return response()->json([
                "status" => false,
                "message" => "ИИН не найден"
            ]);
        }

        return $this->kycService->verify(['image' => $path], $iin);
    }

    private function testIIN($iin){
        $strIIN = (string) $iin;
        $mass = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
        $mass2 = [3, 4, 5, 6, 7, 8, 9, 10, 11, 1, 2];
        $control = 0;
        for($i=0; $i<11; $i++){
            $control += $strIIN[$i] * $mass[$i];
        }
        $control = $control % 11;
        if($control == 10){
            $control = 0;
            for($i=0; $i<11; $i++){
                $control += $strIIN[$i] * $mass2[$i];
            }
            $control = $control % 11;
        }

        return $control == $strIIN[11] ? 1 : 0;
    }

    private function distance($point1, $point2){
        return sqrt(pow($point1['x'] - $point2['x'], 2) + pow($point1['y'] - $point2['y'], 2));
    }
}
