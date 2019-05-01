<?php namespace AliasProject\AuthorityLabs;

use Log;

class AuthorityLabs
{
    private $api_url = 'http://api.authoritylabs.com/keywords/';
    private $api_key;
    private $callback;

    public function __construct()
    {
        $this->api_key = config('authoritylabs.api_key');
        $this->callback = config('authoritylabs.callback');
    }

    /**
    * Collect Report
    *
    * @param array $keywords    List of keywords to POST
    * @param bool $priority     Immediate or Delayed queue
    * @param string $engine     Engine to return
    * @param string $locale     Locale for report
    * @param bool $mobile       Mobile or Desktop
    * @param array $geo_codes   Array of geo codes to run with report
    *
    */
    public function collectReport($keywords, $priority=false, $engine='google', $locale="en-us", $mobile=false, $geo_codes=[])
    {
        try {
            $request = null;
            //$priority = (count($keywords) > 500) ? false : $priority; // Increase to 5000

            foreach ($keywords as $keyword) {
                if ($geo_codes) {
                    foreach ($geo_codes[$keyword] as $geo_code) {
                        $request = $this->keywordPost(strtolower($keyword), $this->api_key, $priority, $engine, $mobile, $locale, "false", "false", $geo_code, $this->callback);
                    }
                } else {
                    $request = $this->keywordPost(strtolower($keyword), $this->api_key, $priority, $engine, $mobile, $locale, "false", "false", "", $this->callback);
                }

                if ($request !== 200) {
                    Log::error('Error collecting ' . $engine . ' PCR Report on '.date('Y-m-d').' with keyword: '.$keyword);
                    Log::error(json_encode($request));
                }
            }
        } catch (Exception $e) {
            Log::error(json_encode($e));
        }
    }

    public function getReport($keyword, $date, $engine)
    {
        return $this->keywordGet($keyword, $this->api_key, $date, 'json', $engine);
    }

    public function parseReport($url, $serp)
    {
        $serp = json_decode($serp);

        $url = str_ireplace('http://','',$url);
        $arr_rankings = array();

        if (is_object($serp)) {

            $serp = get_object_vars($serp);

            foreach ($serp as $key => $val) {
                $match = $val->href;

                if (stristr($match, '.' . $url)) {
                    $arr_rankings[$key] = $val->href;
                }


                if (stristr($match, '/' . $url)) {
                    $arr_rankings[$key] = $val->href;
                }
            }
        }

        return $arr_rankings;
    }

    /**
    * POST a keyword to the queue
    *
    * @param string $keyword   		Keyword to query against - this keyword must be passed in encoded as UTF-8
    * @param string $auth_token		AuthorityLabs Partner API Key
    * @param string $priority		OPTIONAL Defines whether or not to use priority queue. Passing "true" to this will use priority queue
    * @param string $engine			OPTIONAL Search engine to query - see supported engines list at http://authoritylabs.com/api/reference/#engines
    * @param string $locale			OPTIONAL Language/Country code - see supported language/country list at http://authoritylabs.com/api/reference/#countries
    * @param string $pages_from		OPTIONAL Default is false and only works with Google. Defines whether or not to use pages from location results
    * @param string $lang_only		OPTIONAL Default is false and only works with Google. Defines whether or not to use pages in specified language only
    * @param string $geo 			OPTIONAL Google only. Defines a specific geographic location to get SERP data for. Typically a city or postal code.
    * @param string $callback		OPTIONAL Default is taken from the callback URL that is set in your AuthorityLabs Partner API account. This parameter will override that URL
    *
    */
    public function keywordPost($keyword, $auth_token, $priority="false", $engine="google", $mobile=false, $locale="en-US", $pages_from="false", $lang_only="false", $geo="", $callback=null)
    {
        $path = '';

        $post_variables = array(
            'keyword' => mb_convert_encoding(trim($keyword), 'UTF-8', 'auto'),
            'auth_token' => $auth_token,
            'engine' => $engine,
            'mobile' => $mobile,
            'locale' => $locale,
            'pages_from' => $pages_from,
            'lang_only' => $lang_only,
            'geo' => $geo
        );

        if ($callback != null) {
            $post_variables['callback'] = $callback;
        }

        if ($priority == "true") {
            $path = 'priority';
        }

        return $this->request($post_variables, 'POST', $path);
    }

    /**
    * Request using PHP CURL functions
    * Requires curl library installed and configured for PHP
    * Returns response from the AuthorityLabs Partner API
    *
    * @param array $request_vars	Data for making the request to API
    * @param string $method			Specifies POST or GET method
    * @param string $path			OPTIONAL Path for the API request - specifies priority or get URL when applicable
    *
    */
    private function request($request_vars = [], $method, $path="")
    {
        $qs = '';
        $response = '';

        foreach ($request_vars AS $key=>$value) {
            $qs .= "$key=". urlencode($value) . '&';
        }

        $qs = substr($qs, 0, -1);

        //construct full api url
        $url = $this->api_url . $path;
        if (strtoupper($method) == 'GET') {
            $url .= $qs;
        }

        //initialize a new curl object
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        switch (strtoupper($method)) {
            case "GET":
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                break;
            case "POST":
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $qs);
                break;
        }

        if (FALSE === ($response = curl_exec($ch))) {
            return "Curl failed with error " . curl_error($ch);
        }

        //if POST, return response code from API
        if (strtoupper($method) == 'POST')
            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return $response;
    }
}
