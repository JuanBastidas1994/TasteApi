<?php

class HttpClient {
    private $curl;
    private $defaultHeaders;

    public function __construct($defaultHeaders = []) {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        $this->defaultHeaders = $defaultHeaders;
    }

    public function get($url, $headers = []) {
        return $this->request('GET', $url, null, $headers);
    }

    public function post($url, $data = [], $headers = [], $isJson = true) {
        return $this->request('POST', $url, $data, $headers, $isJson);
    }

    public function put($url, $data = [], $headers = [], $isJson = true) {
        return $this->request('PUT', $url, $data, $headers, $isJson);
    }

    public function delete($url, $headers = []) {
        return $this->request('DELETE', $url, null, $headers);
    }

    private function request($method, $url, $data = null, $headers = [], $isJson = false) {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data) {
            if ($isJson) {
                $data = json_encode($data);
                file_put_contents('logs/HTTPCLIENT.log', PHP_EOL . $data, FILE_APPEND);
                $headers[] = 'Content-Type: application/json';
            } else {
                $data = http_build_query($data);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
        }

        $combinedHeaders = array_merge($this->defaultHeaders, $headers);
        if (!empty($combinedHeaders)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $combinedHeaders);
        }

        $response = curl_exec($this->curl);
        
        if (curl_errno($this->curl)) {
            throw new Exception('Request Error: ' . curl_error($this->curl));
        }

        return $response;
    }

    public function __destruct() {
        curl_close($this->curl);
    }
}