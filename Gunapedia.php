<?php
/*

Gunapedia Registrar Module 

*/
class Registrar_Adapter_Gunapedia extends Registrar_AdapterAbstract
{
    public $config = array(
        'apikey' => null,
    );
    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }
        if(isset($options['apikey']) && !empty($options['apikey'])) {
            $this->config['apikey'] = $options['apikey'];
            unset($options['apikey']);
        } else {
            throw new Registrar_Exception('Domain registrar "Gunapedia" is not configured properly. Please update configuration parameter "Gunapedia Apikey" at "Configuration -> Domain registration".');
        }
    }
    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on Gunapedia via API',
            'form'  => array(
                'apikey' => array('password', array(
                    'label' => 'Gunapedia API key',
                    'description'=>'Gunapedia API key',
                    'renderPassword' => true,
                ),
                ),
        ),
		);
    }

    public function getTlds()
    {
        return array(
            '.com', '.net', '.org', '.co.uk', '.co.in', '.in', '.nl', '.biz', 'org.uk'
        );
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $result = $this->_request("domain/query/whois", ["domain={$domain->getName()}"], "POST")["data"];
        return ($result["status"] == "Available");
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyNs(Registrar_Domain $domain)
    {
        $payload = [
            "domain" => $domain->getName(),
            "ns1" => $domain->getNs1(),
            "ns2" => $domain->getNs2(),
            "ns3" => $domain->getNs3(),
            "ns4" => $domain->getNs4(),
            "ns5" => ""
        ];

        $this->_request('domain/update/nameserver', $payload, "PUT");

        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $payload = [
			"domain" => $domain->getName(),
			"nameFirst" => $c->getFirstName(),
			"nameLast" => $c->getLastName(),
			"address1" => $c->getAddress1(),
            "address2" => $c->getAddress2(),
			"city" => $c->getCity(),
			"state" => $c->getState(),
			"country" => $c->getCountry(),
			"zip" => $c->getZip(),
			"phone" => '+' . $c->getTelCc() . '.' . $c->getTel()
		];
        
        $this->_request("domain/update/contact", $payload, "PUT");

        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        return false;
    }

    /**
     * @param Registrar_Domain $domain
     * @return Registrar_Domain
     * @throws Registrar_Exception
     */
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $result = $this->_request("domain/data/info", ["domain={$domain->getName()}"], "POST")["data"];
        $contact = $result['contact'];

        $c = new Registrar_Domain_Contact();
        $c->setFirstName((string) ($contact['nameFirst']) ? $contact['nameFirst'] : "GunaPedia")
            ->setLastName((string) ($contact['nameLast']) ? $contact['nameLast'] : "Domains")
            ->setEmail((string) "privacy@icann.org")
            ->setCompany((string) $contact['nameFirst'] . ' ' . $contact['nameLast'])
            ->setTel((string) $contact["phone"])
            ->setAddress1((string) ($contact['address1']) ? $contact['address1'] : "Indonesia")
            ->setAddress2((string) $contact['address2'])
            ->setCity((string) ($contact['city']) ? $contact['city'] : "Jakarta")
            ->setCountry((string) $contact["country"])
            ->setState((string) ($contact['state']) ? $contact['state'] : "Jakarta")
            ->setZip((string) $contact["zip"]);
        // Add nameservers
        $domain->setNs1($result['nameserver1']);
        $domain->setNs2($result['nameserver2']);
        $domain->setNs3($result['nameserver3']);
        $domain->setNs4($result['nameserver4']);

        $domain->setExpirationTime(strtotime($result["expired"]));
        $domain->setRegistrationTime(strtotime($result["registred"]));
        $domain->setPrivacyEnabled($result['is_private']);
        //$domain->setEpp();
        $domain->setContactRegistrar($c);

        return $domain;
    }

    /**
     * @param Registrar_Domain $domain
     * @throws Registrar_Exception
     */
    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }
    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function registerDomain(Registrar_Domain $domain)
    {
        try {
            $result = $this->_request("purchase/domain", ["domain={$domain->getName()}"], "POST");
            if ($result["status"] != "success"){
                throw new Registrar_Exception("Error when register domain, Message: {$result["message"]}");
                return false;
            }
            return true;
        } catch (Exception $e){
            throw new Registrar_Exception("Error when register domain, Message: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception("Renew domain is not support for now, Renewal system is Auto Renew");
        return false;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function togglePrivacyProtection(Registrar_Domain $domain)
    {
        $payload = [
			"domain" => $domain->getName()
		];
        try {
            $result = $this->_request('domain/update/privacy', $payload, "PUT");
            return true; 
        } catch (Exception $e){
            throw new Registrar_Exception("Error when editing domain privacy");
            return false;
        }
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        return false;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function lock(Registrar_Domain $domain)
    {
        $payload = [
			"domain" => $domain->getName()
		];

        $result = $this->_request('domain/update/locked', $payload, "PUT");
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function unlock(Registrar_Domain $domain)
    {
        $payload = [
			"domain" => $domain->getName()
		];

        $result = $this->_request('domain/update/locked', $payload, "PUT");
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $payload = [
			"domain" => $domain->getName()
		];

        $result = $this->_request('domain/update/privacy', $payload, "PUT");
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $payload = [
			"domain" => $domain->getName()
		];

        $result = $this->_request('domain/update/privacy', $payload, "PUT");
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function getEpp(Registrar_Domain $domain)
    {
        $result = $this->_request("domain/data/info", ["domain={$domain->getName()}"], "POST")["data"];
        return $result["eppcode"];
    }
    /**
     * Runs an api command and returns parsed data.
     * @param string $cmd
     * @param array $body
     * @return array
     */
    private function _request($cmd, $body = [], $method)
    {
        # cek aktif g extensi curl nya
        if (!extension_loaded("curl")) {
            throw new Registrar_Exception("PHP extension curl must be loaded.");
        }
        
        try {
            $body['api_key'] = $this->config['apikey'];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_getApiUrl() . $cmd);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            // Set headers
            $headers = [];
            if ($method == "PUT") {
                $headers[] = "Content-Type: application/json";
                $body = json_encode($body);
            } else {
                $headers[] = "Content-Type: application/x-www-form-urlencoded";
                $body = $body[0];
            }
            $headers[] = "Key: {$this->config['apikey']}";

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            // Set body
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

            $result = curl_exec($ch);

            if ($result === false) {
                $e = new Registrar_Exception(sprintf('CurlException: "%s"', curl_error($ch)));
                $this->getLog()->err($e);
                curl_close($ch);
                throw $e;
            }

            try {
                $json = json_decode($result, true);
                $json["http_code"] = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
            } catch (Exception $e) {
                throw new Registrar_Exception($e->getMessage());
            }

            $this->getLog()->debug($cmd);
            $this->getLog()->debug(print_r($result, true));

            $responseonseCode = (string)$json["http_code"];

            if($responseonseCode[0] == 5) throw new Registrar_Exception("Fatal Error, Please contact support");
            if($responseonseCode == 401) throw new Registrar_Exception("Authenticaton Failed, please check your API Key");
            if($responseonseCode == 403) throw new Registrar_Exception("Authorization Failed, please check your payload");
            if($responseonseCode == 404) throw new Registrar_Exception("Not Found, Please check your Path");
            if($responseonseCode == 500) throw new Registrar_Exception("Action Error, Please contact support (2)");
            // if($responseonseCode[0] != 2) throw new Registrar_Exception("User Input Error, Please check your configuration");
                
            curl_close($ch);

            return $json;
        } catch (Exception $e) {
            throw new Registrar_Exception("Error when make cURL Request, Message: {$e->getMessage()}");
        }
    }

    public function isTestEnv()
    {
        return $this->_testMode;
    }
    /**
     * Api URL.
     * @return string
     */
    private function _getApiUrl()
    {
        return 'https://api.gunapedia.com/';
    }
}
