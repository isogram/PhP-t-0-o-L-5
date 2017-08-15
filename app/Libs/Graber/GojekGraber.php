<?php

namespace App\Libs\Graber;

use DB;
use GuzzleHttp\Client as HttpClient;

class GojekGraber
{

    protected $mongodb;

    const PER_PAGE = 50;
    const GOJEK_MERCHANT_API = 'https://api.gojek.co.id/gojek/merchant/find';

    function __construct()
    {
        if (php_sapi_name() != 'cli') {
            throw new \Exception("MUST EXECUTED FROM CLI!");
            
        }

        $this->mongodb = DB::connection('mongodb');
    }

    /**
     * Insert all data to mongo for location checking
     * 
     * @return void
     */
    public function insertData()
    {
        $query = "
            select
            mr_sub_district.id sub_district_id,
            mr_sub_district.name sub_district_name,
            mr_district.name district_name,
            mr_city.name city_name,
            concat(mr_sub_district.lat, ',' , mr_sub_district.lng) latlng,
            0 as checked
            from mr_sub_district
            join mr_district on mr_district.id = mr_sub_district.district_id
            join mr_city on mr_city.id = mr_district.city_id
            join mr_province on mr_province.id = mr_city.province_id
            where 1=1
            and mr_province.id IN (6, 5, 1, 3, 9, 10, 11);
        ";

        $data = DB::select($query);

        echo sprintf("TOTAL DATA: %s \n", count($data));
        sleep(3);

        foreach ($data as $k => $d) {
            $arrData = get_object_vars($d);
            $insert = $this->mongodb->collection('locations_to_check')->insert($arrData);

            if ($insert) {
                echo sprintf("%s SUCCESS - %s \n", $k, $arrData['sub_district_name']);
            } else {
                echo sprintf("%s FAILED - %s", $k, $arrData['sub_district_name']);
            }
        }
    }

    public function check()
    {
        // steps
        // 1. get unprocessed data (checked 0)
        // 2. set current location to checking (checked 1)
        // 3. process data
        // 4. set current location to checked (checked 2)

        $data = $this->getData(0);

        foreach ($data as $key => $value) {

            echo str_pad(" $value[sub_district_name], $value[district_name], $value[city_name] ", 100, "=", STR_PAD_BOTH) . "\n";
            $time1 = microtime(true);
            
            // set location to processing (checking)
            $this->mongodb->collection('locations_to_check')->where('sub_district_id', $value['sub_district_id'])->update(['checked' => 1]);

            // S: process fetch gojek data
            $this->getGojekData($value['latlng']);

            // set location to processed (checking)
            $this->mongodb->collection('locations_to_check')->where('sub_district_id', $value['sub_district_id'])->update(['checked' => 2]);
            $time2 = microtime(true);

            echo 'Total execution time: ' . ($time2 - $time1) . "\n";
        }
    }

    protected function getData($status = 0)
    {
        $location = $this->mongodb->collection('locations_to_check')->where('checked', $status)->take(self::PER_PAGE)->get();

        return $location;
    }

    protected function getGojekData($latlng)
    {

        //location=-6.23564360,106.84881280&page=0&limit=1000
        $loop = true;
        $page = 0;
        $firstId = 0;
        while ($loop === true) {

            $queryString = [
                'location'  => $latlng,
                'limit'     => 30,
                'page'      => $page,
            ];

            $url = self::GOJEK_MERCHANT_API . '?' . http_build_query($queryString);

            echo "Processing URL: $url\n";

            $client = new HttpClient();
            $response = $client->get($url);

            $arrResponse = json_decode($response->getBody(), true);

            if ($arrResponse) {

                foreach ($arrResponse as $k => $item) {

                    // set current id
                    $currentId = $item['id'];

                    // check to existing collection
                    $isExist = $this->mongodb->collection('merchant_gojek')->where('id', $currentId)->count();
                    
                    // if no exist insert
                    if (!$isExist) {
                        $merchant = $this->mongodb->collection('merchant_gojek')->insert($item);
                    }

                    // set loop to false if data matched with currentId
                    if (($firstId === $currentId) || $page == 500) {
                        $loop = false;
                    }

                    // set firstId
                    if ($page == 0 && $k == 0) {
                        $firstId = $item['id'];
                    }

                }

                echo "PAGE : $page OK\n";

            } else {
                echo "PAGE : $page OK\n";
                
                $loop = false;
            }

            $page++;
        }

    }
}