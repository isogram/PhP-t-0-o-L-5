<?php

namespace App\Libs\Graber;

use DB;
use GuzzleHttp\Client as HttpClient;

class GojekNotifier
{
    protected $mongodb;

    const WEBHOOKS_URL = "https://maker.ifttt.com/trigger/gojek_graber_notification/with/key/c6q2JWFN09r70X6YWULHek";
    
    function __construct()
    {
        if (php_sapi_name() != 'cli') {
            throw new \Exception("MUST EXECUTED FROM CLI!");
            
        }

        $this->mongodb = DB::connection('mongodb');
    }

    public function sendNotification()
    {
        // Currently processing SUB_DISTRICT_NAME, DISTRICT_NAME, CITY_NAME
        // Statistics : PROCESSED of TOTAL_DATA locations
        // Gojek stats: XXXX data has been fetched

        $names = $this->getName();

        if ($names) {

            $stats = $this->getStats();

            $gojekStats = $this->getGojekStats();


            $data = [
                'value1' => json_encode($names),
                'value2' => sprintf("%s of %s locations", $stats['processed'], $stats['total']),
                'value3' => sprintf("%s data has been fetched", $gojekStats),
            ];

            $client = new HttpClient;
            $response = $client->post(self::WEBHOOKS_URL, [
                'json' => $data
            ]);

            $arrResponse = json_decode($response->getBody(), true);

            print_r($arrResponse);

        }

    }

    protected function getName()
    {
        $current = $this->mongodb->collection('locations_to_check')->where('checked', 1)->get();

        $names = [];

        foreach ($current as $item) {
            $names[] = sprintf("%s, %s, %s", $item['sub_district_name'], $item['district_name'], $item['city_name']);
        }

        return $names;
    }

    protected function getStats()
    {
        $stats = [
            'processed' => $this->mongodb->collection('locations_to_check')->where('checked', 2)->count(),
            'total' => $this->mongodb->collection('locations_to_check')->count(),
        ];

        return $stats;
    }

    protected function getGojekStats()
    {
        $data = $this->mongodb->collection('merchant_gojek')->count();

        return $data;
    }
}