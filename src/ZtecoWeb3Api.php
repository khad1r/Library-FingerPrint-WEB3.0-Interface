<?php

namespace khad1r\webfingerprint;

class ZtecoWeb3Api
{
    private $url;
    private $username;
    private $password;
    private $curl;
    private $sessionId;
    private $curlResponse;
    private $curlOpt;
    public $curlError;
    private const VALIDLOGINRESP = "<HTML><HEAD><TITLE></TITLE><METAhttp-equiv=Content-Typecontent='text/html;'><METAhttp-equiv=Cache-controlcontent=no-cache><METAhttp-equiv=Pragmacontent=no-cache><LINKhref='../css/Secutime.css'type=text/cssrel=stylesheet><METAcontent='MSHTML6.00.2900.3562'name=GENERATOR></HEAD><FRAMESETborder=0frameSpacing=0rows=37,*frameBorder=NOcols=*><FRAMEname=headersrc='/csl/header'noResizescrolling=no><FRAMESETborder=0frameSpacing=0rows=*frameBorder=NOcols=180,*><FRAMEname=menusrc='/csl/menu'noResizescrolling=auto><FRAMEname=contentsrc='/csl/desktop'></FRAMESET></FRAMESET></HTML>";
    /**
     * ZtecoWeb3 constructor.
     *
     * @param string $url The URL of the attendance machine
     * @param string $username The username for the attendance machine
     * @param string $password The password for the attendance machine
     * @throws Exception
     */
    public function __construct(string $url, string $username, string $password)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        if (!$this->getSessionId()) {
            throw new \Exception("Unable to connect to the attendance machine at $url");
        }
        if (!$this->login()) {
            throw new \Exception("Unable to login to the attendance machine at $url");
        }
    }

    /**
     * Get default cURL options.
     *
     * @return array
     */
    private function contructCurlOpt(array $options = [])
    {
        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => $options[CURLOPT_TIMEOUT] ?? 30,
            CURLOPT_CUSTOMREQUEST => $options[CURLOPT_CUSTOMREQUEST] ?? 'GET',
            CURLOPT_HTTPHEADER => $options[CURLOPT_HTTPHEADER] ?? [
                "content-type: text/plain",
                "cookie: SessionID={$this->sessionId}"
            ]
        ] + $options;
    }
    /**
     * Prepares the cURL request with the given options.
     *
     * @param array $options Additional cURL options
     * @return $this
     */
    private function prepareCurl(array $options = [])
    {
        $this->curl = curl_init();
        $this->curlOpt = $this->contructCurlOpt($options);
        curl_setopt_array($this->curl, $this->curlOpt);
        return $this;
    }

    /**
     * Executes the cURL request and captures the response.
     *
     * @return $this
     */
    private function executeCurl()
    {
        $this->curlResponse = curl_exec($this->curl);
        $err = curl_error($this->curl);
        $this->curlError = ($err) ? "cURL Error: $err" : false;
        curl_close($this->curl);
        return $this;
    }


    /**
     * Encodes data for URL-encoded requests.
     *
     * @param mixed  $data The data to be encoded
     * @return string The URL-encoded string
     */
    private function urlEncode(mixed $data)
    {
        return preg_replace(
            '/uid%5B\d+%5D=/',
            'uid=',
            http_build_query($data, '', '&', PHP_QUERY_RFC3986)
        );
    }

    /**
     * Validates the date format.
     *
     * @param string $date Date in yyyy-mm-dd format
     * @return bool
     */
    private function isValidDate(string $date)
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    /**
     * Formats the raw result into an array of logs.
     *
     * @param string $data Raw data
     * @return array
     */
    private function formatResult(string $data)
    {
        $lines = explode("\n", $data);
        $result = [];

        foreach ($lines as $line) {
            if (empty($line)) continue;
            $values = explode("\t", $line);
            $result[] = [
                "id" => $values[0],
                "name" => $values[1],
                "dateTime" => $values[2],
                "status" => $values[3],
                "verification" => $values[4],
            ];
        }

        usort($result, function ($a, $b) {
            return $a['name'] === $b['name']
                ? strtotime($a['dateTime']) - strtotime($b['dateTime'])
                : strcmp($a['name'], $b['name']);
        });

        return $result;
    }
    /**
     * Formats HTML response into an array of user data.
     *
     * @param string $html HTML response from the cURL request
     * @return array Array of user data
     */
    private function formatHTMLResult(string $html)
    {
        // Initialize DOMDocument and load HTML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Suppress errors for malformed HTML
        $dom->loadHTML($html);
        libxml_clear_errors();

        // Initialize DOMXPath to query HTML
        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query('//div[@id="cc"]//table//tr');
        $data = [];
        // Process each row and extract data
        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);
            $checkbox = $xpath->query('.//input[@type="checkbox" and @name="uid"]', $row)->item(0);
            $data[trim($cells[2]->textContent) ?? null] = [
                'uid' => $checkbox ? intval($checkbox->getAttribute('value')) : '',
                'Departement' => trim($cells[1]->textContent) ?? '',
                'ID Number' => trim($cells[2]->textContent) ?? '',
                'Name' => trim($cells[3]->textContent) ?? '',
                'Card' => trim($cells[4]->textContent) ?? '',
                'Group' => trim($cells[5]->textContent) ?? '',
                'Privilege' => trim($cells[6]->textContent) ?? '',
            ];
        }

        return $data;
    }

    /**
     * Gets the session ID from the server.
     *
     * @return $this
     */
    private function getSessionId()
    {
        // Prepare and execute the cURL request
        $this->prepareCurl([
            CURLOPT_URL => $this->url,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HTTPHEADER => []
        ])->executeCurl();

        $header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $header = substr($this->curlResponse, 0, $header_size);
        // Check and extract SessionID from the 'Set-Cookie' header
        if ($header && preg_match('/SessionID=([^;]+)/', $header, $sessionIdMatch)) {
            $this->sessionId = $sessionIdMatch[1];
        }
        return !$this->curlError;
    }

    /**
     * Logs into the attendance machine.
     *
     * @return bool
     */
    private function login()
    {
        $data = [
            'username' => $this->username,
            'userpwd' => $this->password,
        ];
        $curlBody = $this->urlEncode($data);
        $this->prepareCurl([
            CURLOPT_URL => "{$this->url}/csl/check",
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_TIMEOUT => 3,
            CURLOPT_POSTFIELDS => $curlBody,
        ])->executeCurl();
        return (!$this->curlError && (self::VALIDLOGINRESP == preg_replace('/\s+/', '', $this->curlResponse)));
    }
    /**
     * Creates a new user with the given ID, name, and pin.
     *
     * @param int $id User ID (must be a number up to 9999)
     * @param string $name User name
     * @param int $pin User pin (must be a number with up to 6 digits)
     * @return bool True on success, false on failure
     */
    public function create(int $id, string $name, int $pin)
    {
        // Validate ID and pin
        if (!preg_match('/^\d{1,9}$/', $id)) {
            throw new \InvalidArgumentException("ID must be a number up to 999999999.");
        }
        if (!preg_match('/^.{1,8}$/', $name)) {
            throw new \InvalidArgumentException("Name must be at most 8 characters long.");
        }
        if (!preg_match('/^\d{1,6}$/', $pin)) {
            throw new \InvalidArgumentException("Pin must be a number with up to 6 digits.");
        }

        // Prepare the data and cURL options
        $data = [
            'upin' => 'NULL',
            'upin2' => $id,
            'uname' => $name,
            'uprivilege' => 0,
            'upwd' => $pin,
            'ucard' => 0,
        ];
        $this->prepareCurl([
            CURLOPT_URL => "{$this->url}/csl/user?action=save&id=add",
            CURLOPT_POSTFIELDS => $this->urlEncode($data),
        ])->executeCurl();

        // Return success status
        return !$this->curlError;
    }

    /**
     * Deletes a user by their UID.
     *
     * @param int|string $uid User UID to delete
     * @return bool True on success, false on failure
     */
    public function delete(int|string $uid)
    {
        // Prepare the data and cURL options
        $data = ['uid' => $uid];
        $this->prepareCurl([
            CURLOPT_URL => "{$this->url}/csl/user?action=del",
            CURLOPT_POSTFIELDS => $this->urlEncode($data),
        ])->executeCurl();

        // Return success status
        return !$this->curlError;
    }
    /**
     * Retrieves user data with a limit on the number of users.
     *
     * @param int $count Number of users to retrieve (default is 100)
     * @return array|bool User data array on success, false on failure
     */
    public function getUsers(int $count = 100)
    {
        // Prepare cURL options for GET request
        $this->prepareCurl([
            CURLOPT_URL => "{$this->url}/csl/user?last=$count",
        ])->executeCurl();

        // Return formatted result or false on error
        return !$this->curlError ? $this->formatHTMLResult($this->curlResponse) : false;
    }
    /**
     * Retrieves user data by user ID.
     *
     * @param int|string $id User ID to retrieve
     * @return array|bool User data array on success, false on failure
     */
    public function getByID(int|string $id)
    {
        // Prepare cURL options for GET request
        $this->prepareCurl([
            CURLOPT_URL => "{$this->url}/csl/user?uid=$id",
        ])->executeCurl();

        // Return formatted result or false on error
        return !$this->curlError
            ? $this->formatHTMLResult($this->curlResponse)[$id] ?? false
            : false;
    }

    /**
     * Gets the attendance log for a specific date range and user.
     *
     * @param string $startDate Start date (yyyy-mm-dd)
     * @param string $endDate End date (yyyy-mm-dd)
     * @param mixed $uid User ID(s)
     * @return array|false
     * @throws InvalidArgumentException
     */
    public function getAttendance(string $startDate, string $endDate, $uid)
    {
        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            throw new \InvalidArgumentException("Invalid date format. Use yyyy-mm-dd.");
        }

        $data = [
            'sdate' => $startDate,
            'edate' => $endDate,
            'uid' => $uid,
        ];
        $curlBody = $this->urlEncode($data);
        $this->prepareCurl([
            CURLOPT_URL => "{$this->url}/form/Download",
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $curlBody,
        ])->executeCurl();
        return $this->curlError ? false : $this->formatResult($this->curlResponse);
    }

    /**
     * Format data from getAttendance with grouped by Date.
     *
     * @param array $data from output of getAttendance
     * @return array Formatted data 
     */
    public function formatAttendanceByDate(array $data)
    {
        $grouped = [];

        foreach ($data as $entry) {
            $date = substr($entry['dateTime'], 0, 10); // Extract date (YYYY-MM-DD)
            $time = substr($entry['dateTime'], 11, 8);  // Extract time (HH:MM:SS)
            $id = $entry['id'];

            // Add entry to grouped data
            $grouped[$date][$id][$time] = $entry;
        }

        return $grouped;
    }

    /**
     * Format data from getAttendance with grouped by ID.
     *
     * @param array $data from output of getAttendance
     * @return array Formatted data 
     */
    public function formatAttendanceByID(array $data)
    {
        $grouped = [];

        foreach ($data as $entry) {
            $date = substr($entry['dateTime'], 0, 10); // Extract date (YYYY-MM-DD)
            $time = substr($entry['dateTime'], 11, 8);  // Extract time (HH:MM:SS)
            $id = $entry['id'];

            // Add entry to grouped data
            $grouped[$id][$date][$time] = $entry;
        }

        return $grouped;
    }
}
