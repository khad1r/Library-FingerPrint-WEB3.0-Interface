<?php

class AttendanceLog
{
    private $ATTENDANCE_MACHINE_IP;
    private $ATTENDANCE_MACHINE_PORT;
    private $ATTENDANCE_MACHINE_WEB3_PORTAL_URL;
    private $CURL;
    private $CURL_BODY;
    private $CURL_METHOD = "POST";
    private $CURL_RESPONSE;
    private $CURL_ERROR;

    /**
     * AttendanceLog constructor.
     *
     * @param string $ip The IP address of the attendance machine
     * @param string $port The port number to connect to the attendance machine
     */
    public function __construct(string $ip, string $port)
    {
        $this->ATTENDANCE_MACHINE_IP = $ip;
        $this->ATTENDANCE_MACHINE_PORT = $port;
        $this->ATTENDANCE_MACHINE_WEB3_PORTAL_URL = "http://$this->ATTENDANCE_MACHINE_IP:$this->ATTENDANCE_MACHINE_PORT/form/Download";
    }

    /**
     * Prepares the cURL request to download attendance logs for a specific user and date range.
     *
     * @param string $startDate The start date of the attendance log in yyyy-mm-dd format
     * @param string $endDate The end date of the attendance log in yyyy-mm-dd format
     * @param mixed $uid The user ID(s) of the user(s) whose attendance logs are to be downloaded
     * @return $this The AttendanceLog object instance
     */
    public function prepare(string $startDate, string $endDate, mixed $uid)
    {
        $this->CURL = curl_init();

        $data = [
            'sdate' => $startDate,
            'edate' => $endDate,
            'uid' => $uid,
        ];
        $this->CURL_BODY = $this->_urlEncode($data);

        curl_setopt_array($this->CURL, [
            CURLOPT_PORT => $this->ATTENDANCE_MACHINE_PORT,
            CURLOPT_URL => $this->ATTENDANCE_MACHINE_WEB3_PORTAL_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $this->CURL_METHOD,
            CURLOPT_POSTFIELDS => $this->CURL_BODY,
            CURLOPT_HTTPHEADER => [
                "Accept: */*",
            ],
        ]);
        return $this;
    }

    /**
     * Executes the cURL request and stores the response in the CURL_RESPONSE property of the object.
     * Sets the CURL_ERROR property if there was an error in the cURL request.
     *
     * @return $this The AttendanceLog object instance
     */
    public function execute()
    {
        $this->CURL_RESPONSE = curl_exec($this->CURL);
        $err = curl_error($this->CURL);
        $this->CURL_ERROR = ($err) ? "cURL Error #:$err" : false;
        curl_close($this->CURL);
        return $this;
    }

    /** 
     * This function returns the formatted result of the cURL request, which is an array of attendance logs.
     * If there was an error with the cURL request, it returns the error message instead.
     *
     * @return array|string The formatted result of the cURL request or the error message
     */
    public function getResult()
    {
        return ($this->CURL_ERROR) ? $this->CURL_ERROR : $this->_formatResult($this->CURL_RESPONSE);
    }

    private function _urlEncode(mixed $data)
    {
        /** 
         * This is a private function that encodes the data array into a URL-encoded string for use in the cURL request body.
         *
         * @param mixed $data The data to be encoded
         *
         * @return string The URL-encoded string
         */
        $body = http_build_query($data, '', '&', PHP_QUERY_RFC3986);

        $body = str_replace('uid%5B', 'uid=', $body);
        $body = str_replace('%5D', '', $body);
        return $body;
    }

    private function _formatResult(string $data)
    {
        /**
         * This is a private function that takes in the raw response data from the cURL request and formats it into an array
         * of attendance logs sorted by name and date/time.
         *
         * @param string $data The raw response data from the cURL request
         *
         * @return array The formatted array of attendance logs
         */
        $lines = explode("\n", $data);
        $result = array();

        foreach ($lines as $line) {
            if (empty($line)) continue;
            $values = explode("\t", $line);
            $result[] = array(
                "uid" => $values[0],
                "name" => $values[1],
                "Date Time" => $values[2],
                "Status" => $values[3],
                "Verification" => $values[4]
            );
        }
        usort($result, function ($a, $b) {
            if ($a['name'] == $b['name']) {
                return strtotime($a['Date Time']) - strtotime($b['Date Time']);
            }
            return $a['name'] < $b['name'] ? -1 : 1;
        });
        return $result;
    }
}
