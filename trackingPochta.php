<?php

/**
 * @author Odiva.ru
 * Date: 12.11.15
 * request for unlimited access: https://tracking.pochta.ru/request
 * @link https://tracking.pochta.ru/specification
 */
ini_set('default_socket_timeout', 15);

class PochtaApi
{
    /** @link https://tracking.pochta.ru/access-settings */
    private $login = 'login';
    private $pass = 'pass';
    private $singleHost = 'https://tracking.russianpost.ru/rtm34?wsdl';
    private $ticketHost = 'https://tracking.russianpost.ru/fc?wsdl';
    private $ticketDataFile = '/data/ticket.txt';
    private $errorLogFile = '/data/error_log.txt';
    private $needTrace = true;

    const CLIENT_TYPE_SINGLE = 1;
    const CLIENT_TYPE_TICKET = 2;

    private function _objectToArray($o)
    {
        return json_decode(json_encode($o), true);
    }

    private function _call($method, $params = array(), $clientType = self::CLIENT_TYPE_SINGLE)
    {
        $params = $params ? $params : array();
        $authParams = array('login' => $this->login, 'password' => $this->pass);
        if ($clientType == self::CLIENT_TYPE_TICKET) {
            list ($host, $ver) = array($this->ticketHost, SOAP_1_1);
            $params = array_merge($params, $authParams);
        } else {
            list ($host, $ver) = array($this->singleHost, SOAP_1_2);
            $params['AuthorizationHeader'] = $authParams;
        }
        $client = new SoapClient($host, array('soap_version' => $ver, 'encoding' => 'UTF-8', 'trace' => $this->needTrace, 'connection_timeout' => 5));

        try {
            $response = $client->$method($params);
            if ($response->error) {
                throw new SoapFault("Message", $response->error->ErrorName);
            }
            return $this->_objectToArray($response);
        } catch (SoapFault $ex) {

            $this->_exceptionLog($ex);
            return false;
        }
    }

    private function _exceptionLog($ex)
    {
        $this->_file_force_contents(__DIR__ . $this->errorLogFile, date("Y-m-d H:i:s") . '; ' . $ex->getMessage() . '; in Line: ' . $ex->getLine() . "\r\n");
    }

    private function _file_force_contents($dir, $contents, $flags = FILE_APPEND)
    {
        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = '';
        foreach ($parts as $part) {
            if (!is_dir($dir .= "/$part")) mkdir($dir);
        }

        return file_put_contents("$dir/$file", $contents, $flags);
    }

    public function formatTrack($track)
    {
        $track = trim($track);
        try {
            if (!preg_match('/^[0-9]{14}|[A-Z]{2}[0-9]{9}[A-Z]{2}$/', $track)) {
                throw new SoapFault("Message", 'Некорректный формат почтового идентификатора: ' . $track);
            }

            return $track;
        } catch (SoapFault $ex) {
            $this->_exceptionLog($ex);

            return false;
        }
    }

    public function getTicket($arTracks)
    {
        $params = array();
        foreach ($arTracks as $key => $track) {
            if ($track = $this->formatTrack($track)) {
                $params['request']['Item'][$key]['Barcode'] = $track;
            }
        }

        $response = $this->_call('getTicket', $params, self::CLIENT_TYPE_TICKET);
        $ticketId = $response['value'];
        $this->writeTicketId($ticketId);

        return $ticketId;
    }

    public function clearTicketId()
    {
        $this->writeTicketId('');
    }

    public function writeTicketId($ticketId)
    {
        $flags = 0;
        return $this->_file_force_contents(__DIR__ . $this->ticketDataFile, $ticketId, $flags);
    }

    public function readTicketId()
    {
        return file_get_contents(__DIR__ . $this->ticketDataFile);
    }

    public function getOperationHistory($track)
    {
        if ($track = $this->formatTrack($track)) {
            $requestParams['OperationHistoryRequest']['Barcode'] = $track;
            $requestParams['OperationHistoryRequest']['MessageType'] = '0';

            return $this->_call('getOperationHistory', $requestParams, self::CLIENT_TYPE_SINGLE);
        }

        return false;
    }

    public function getResponseByTicket($ticket)
    {
        $params['ticket'] = $ticket ? $ticket : $this->readTicketId();

        return $this->_call('getResponseByTicket', $params, self::CLIENT_TYPE_TICKET);
    }
}
?>