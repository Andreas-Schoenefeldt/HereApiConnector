<?php

namespace Schoenef\HereApiConnector\Service;

use GuzzleHttp\Client;
use Schoenef\HereApiConnector\Util\Countries;

class HereApiConnector {
    const KEY_TIMEOUT = 'timeout'; // timeout in seconds
    const KEY_APP_ID = 'app_id';
    const KEY_APP_CODE = 'app_code';
    const KEY_API_KEY = 'apiKey';
    const KEY_LANG = 'lang';
    const KEY_COUNTRY = 'country';

    private $config;

    private $autocompleteClient;
    private $geocoderClient;

    private $lang;
    private $country;
    private $app_id;
    private $app_code;
    private $apiKey;

    public function __construct(array $connectorConfig){
        $this->config = $connectorConfig;

        $timeout = isset($this->config[self::KEY_TIMEOUT]) ? $this->config[self::KEY_TIMEOUT] : 10;
        $isNewApiAuth = isset($this->config[self::KEY_API_KEY]);

        $this->geocoderClient = new Client([
            // Base URI is used with relative requests
            'base_uri' => $isNewApiAuth ? 'https://geocoder.ls.hereapi.com/6.2/' : 'https://geocoder.api.here.com/6.2/',
            // You can set any number of default request options.
            'timeout'  => $timeout,
        ]);

        $this->autocompleteClient = new Client([
            // Base URI is used with relative requests
            'base_uri' => $isNewApiAuth ? 'https://autocomplete.geocoder.ls.hereapi.com/6.2/' : 'https://autocomplete.geocoder.api.here.com/6.2/',
            // You can set any number of default request options.
            'timeout'  => $timeout,
        ]);

        $this->lang = isset($this->config[self::KEY_LANG]) ? $this->config[self::KEY_LANG] : '';
        $this->country = isset($this->config[self::KEY_COUNTRY]) ? $this->config[self::KEY_COUNTRY] : '';
        $this->app_id = isset($this->config[self::KEY_APP_ID]) ? $this->config[self::KEY_APP_ID] : '';
        $this->apiKey = isset($this->config[self::KEY_API_KEY]) ? $this->config[self::KEY_API_KEY] : '';
        $this->app_code = isset($this->config[self::KEY_APP_CODE]) ? $this->config[self::KEY_APP_CODE] : '';
    }


    /**
     * this function will convert the HereApi result to geojson http://geojson.org/
     *
     * @param $query
     * @param array $options allows to define additional parameters to the call
     * @param array $filter the filter allows to reduce the results to certain types
     * @return array|bool
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchLocation ($query, $options = [], $filter = []) {

        $options = $this->getStandardOptions($options);

        if ($this->country) {
            $options[self::KEY_COUNTRY] = Countries::guessIso3($this->country);;
        }

        $options['query'] = $query;

        $response = $this->autocompleteClient->request('GET', 'suggest.json',['query' => $options]);
        if ($response->getStatusCode() == '200') {



            return $this->filterResult(json_decode($response->getBody()->getContents(), true)['suggestions'],$filter);
        }

        return false;
    }

    /**
     * pulls additional geo informtaion for a result
     *
     * @param array $result
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getLocationDetails (array $result) {
        $options = $this->getStandardOptions();

        $options['locationid'] = $result['properties']['id'];
        $options['gen'] = 9; // hardcode to generation 9
        $options['jsonattributes'] = 1; // to have a unified api - first letter is forced to be lowercase

        $response = $this->geocoderClient->request('GET', 'geocode.json',['query' => $options]);

        if ($response->getStatusCode() == '200') {

            $extendedResult = json_decode($response->getBody()->getContents(), true)['response']['view'][0]['result'][0];

            if ($extendedResult) {

                $geo = $extendedResult['location']['displayPosition'];

                // enrich the information with the lon/lat array
                $result['geometry']['coordinates'] = [$geo['longitude'], $geo['latitude']];

                // we take the much more beautifull label as well
                $result['properties']['label'] = $extendedResult['location']['address']['label'];
            }

        }

        return $result;
    }


    public function filterResult ($hitsArray, $filter = []) {

        if (count($filter)) {
            foreach ($filter as $key => $allowedValues) {
                $filteredResult = [];
                foreach ($hitsArray as $entry) {

                    $entry = $this->convertToGeoJSON($entry);

                    if (array_key_exists($key, $entry['properties']) && in_array($entry['properties'][$key], $allowedValues)) {
                        $filteredResult[] = $entry;
                    }
                }

                $hitsArray = $filteredResult;
            }
        } else {
            // we still need to convert this into geoJson ;)
            foreach ($hitsArray as $index => $entry) {
                $hitsArray[$index] = $this->convertToGeoJSON($entry);
            }
        }

        return $hitsArray;
    }

    public function convertToGeoJSON ($entry) {
        $geoEntry = [
            "type" => "Feature",
            "geometry" => [
                "type" => "Point",
                "coordinates" => []
            ],
            "properties" => []
        ];

        foreach ($entry as $attr => $val) {
            switch ($attr) {
                default:
                    $geoEntry['properties'][$attr] = $val;
                    break;
                case 'locationId':
                    $geoEntry['properties']['id'] = $val;
                    break;
                case 'address':
                    foreach ($val as $addressKey => $addressVal) {
                        $geoEntry['properties'][$addressKey] = $addressVal;

                        if ($addressKey === 'city') {
                            $geoEntry['properties']['name'] = $addressVal;
                        }
                    }
                    break;
            }
        }

        return $geoEntry;
    }

    protected function getStandardOptions ($options = []) {

        if ($this->apiKey) {
            $options['apiKey'] = $this->apiKey;
        } else {
            $options['app_id'] = $this->app_id;
            $options['app_code'] = $this->app_code;
        }

        if ($this->lang) {
            $options['language'] = $this->lang;
        }

        return $options;
    }

}