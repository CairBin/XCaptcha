<?php

namespace AltchaOrg\Altcha;

class Algorithm
{
    const SHA1 = 'SHA-1';
    const SHA256 = 'SHA-256';
    const SHA512 = 'SHA-512';
}

class ChallengeOptions
{
    public $algorithm;
    public $maxNumber;
    public $saltLength;
    public $hmacKey;
    public $salt;
    public $number;
    public $expires;
    public $params;

    public function __construct($options = [])
    {
        $this->algorithm = $options['algorithm'] ?? Altcha::DEFAULT_ALGORITHM;
        $this->maxNumber = $options['maxNumber'] ?? Altcha::DEFAULT_MAX_NUMBER;
        $this->saltLength = $options['saltLength'] ?? Altcha::DEFAULT_SALT_LENGTH;
        $this->hmacKey = $options['hmacKey'] ?? '';
        $this->salt = $options['salt'] ?? '';
        $this->number = $options['number'] ?? 0;
        $this->expires = $options['expires'] ?? null;
        $this->params = $options['params'] ?? [];
    }
}

class ServerSignaturePayload
{
    public $algorithm;
    public $verificationData;
    public $signature;
    public $verified;

    public function __construct($algorithm, $verificationData, $signature, $verified)
    {
        $this->algorithm = $algorithm;
        $this->verificationData = $verificationData;
        $this->signature = $signature;
        $this->verified = $verified;
    }
}

class Challenge
{
    public $algorithm;
    public $challenge;
    public $maxnumber;
    public $salt;
    public $signature;

    public function __construct($algorithm, $challenge, $maxNumber, $salt, $signature)
    {
        $this->algorithm = $algorithm;
        $this->challenge = $challenge;
        $this->maxnumber = $maxNumber;
        $this->salt = $salt;
        $this->signature = $signature;
    }
}

class ServerSignatureVerificationData
{
    public $classification;
    public $country;
    public $detectedLanguage;
    public $email;
    public $expire;
    public $fields;
    public $fieldsHash;
    public $ipAddress;
    public $reasons;
    public $score;
    public $time;
    public $verified;
}

class Altcha
{
    const DEFAULT_MAX_NUMBER = 1e6;
    const DEFAULT_SALT_LENGTH = 12;
    const DEFAULT_ALGORITHM = Algorithm::SHA256;

    private static function randomBytes($length)
    {
        return random_bytes($length);
    }

    private static function randomInt($max)
    {
        return random_int(0, $max);
    }

    private static function hash($algorithm, $data)
    {
        switch ($algorithm) {
            case Algorithm::SHA1:
                return sha1($data, true);
            case Algorithm::SHA256:
                return hash('sha256', $data, true);
            case Algorithm::SHA512:
                return hash('sha512', $data, true);
            default:
                throw new \InvalidArgumentException("Unsupported algorithm: $algorithm");
        }
    }

    public static function hashHex($algorithm, $data)
    {
        return bin2hex(self::hash($algorithm, $data));
    }

    private static function hmacHash($algorithm, $data, $key)
    {
        switch ($algorithm) {
            case Algorithm::SHA1:
                return hash_hmac('sha1', $data, $key, true);
            case Algorithm::SHA256:
                return hash_hmac('sha256', $data, $key, true);
            case Algorithm::SHA512:
                return hash_hmac('sha512', $data, $key, true);
            default:
                throw new \InvalidArgumentException("Unsupported algorithm: $algorithm");
        }
    }

    private static function hmacHex($algorithm, $data, $key)
    {
        return bin2hex(self::hmacHash($algorithm, $data, $key));
    }

    private static function decodePayload($payload)
    {
        $decoded = base64_decode($payload);

        if (!$decoded) {
            return null;
        }

        // try {
        //     $data = json_decode($decoded, true, 2, JSON_THROW_ON_ERROR);
        // } catch (\JsonException|\ValueError $e) {
        //     return null;
        // }

        // PHP72不兼容JSON_THROW_ON_ERROR 这是PHP73引入的，下面是兼容写法 
        $data = json_decode($decoded, true, 2);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (!is_array($data) || empty($data)) {
            return null;
        }

        return $data;
    }

    public static function createChallenge($options)
    {
        if (is_array($options)) {
            $options = new ChallengeOptions($options);
        }

        $algorithm = $options->algorithm ?: self::DEFAULT_ALGORITHM;
        $maxNumber = $options->maxNumber ?: self::DEFAULT_MAX_NUMBER;
        $saltLength = $options->saltLength ?: self::DEFAULT_SALT_LENGTH;

        $params = $options->params;
        if ($options->expires) {
            $params['expires'] = $options->expires->getTimestamp();
        }

        $salt = $options->salt ?: bin2hex(self::randomBytes($saltLength));
        if (!empty($params)) {
            $salt .= '?' . http_build_query($params);
        }

        $number = $options->number ?: self::randomInt($maxNumber);

        $challenge = self::hashHex($algorithm, $salt . $number);

        $signature = self::hmacHex($algorithm, $challenge, $options->hmacKey);

        return new Challenge($algorithm, $challenge, $maxNumber, $salt, $signature);
    }

    public static function verifySolution($payload, $hmacKey, $checkExpires = true)
    {
        if (is_string($payload)) {
            $payload = self::decodePayload($payload);
        }

        if ($payload === null
            || !isset($payload['algorithm'], $payload['challenge'], $payload['number'], $payload['salt'], $payload['signature'])
        ) {
            return false;
        }

        $payload = new Payload($payload['algorithm'], $payload['challenge'], $payload['number'], $payload['salt'], $payload['signature']);

        $params = self::extractParams($payload);
        if ($checkExpires && isset($params['expires'])) {
            $expireTime = (int)$params['expires'];
            if (time() > $expireTime) {
                return false;
            }
        }

        $challengeOptions = new ChallengeOptions([
            'algorithm' => $payload->algorithm,
            'hmacKey' => $hmacKey,
            'number' => $payload->number,
            'salt' => $payload->salt,
        ]);

        $expectedChallenge = self::createChallenge($challengeOptions);

        return $expectedChallenge->challenge === $payload->challenge &&
            $expectedChallenge->signature === $payload->signature;
    }

    private static function extractParams($payload)
    {
        $saltParts = explode('?', $payload->salt);
        if (count($saltParts) > 1) {
            parse_str($saltParts[1], $params);
            return $params;
        }
        return [];
    }

    public static function verifyFieldsHash($formData, $fields, $fieldsHash, $algorithm)
    {
        $lines = [];
        foreach ($fields as $field) {
            $lines[] = $formData[$field] ?? '';
        }
        $joinedData = implode("\n", $lines);
        $computedHash = self::hashHex($algorithm, $joinedData);
        return $computedHash === $fieldsHash;
    }

    public static function verifyServerSignature($payload, $hmacKey)
    {
        if (is_string($payload)) {
            $payload = self::decodePayload($payload);
        }

        if ($payload === null
            || !isset($payload['algorithm'], $payload['verificationData'], $payload['signature'], $payload['verified'])
        ) {
            return false;
        }

        $payload = new ServerSignaturePayload($payload['algorithm'], $payload['verificationData'], $payload['signature'], $payload['verified']);

        $hash = self::hash($payload->algorithm, $payload->verificationData);
        $expectedSignature = self::hmacHex($payload->algorithm, $hash, $hmacKey);

        parse_str($payload->verificationData, $params);

        $verificationData = new ServerSignatureVerificationData();
        $verificationData->classification = $params['classification'] ?? '';
        $verificationData->country = $params['country'] ?? '';
        $verificationData->detectedLanguage = $params['detectedLanguage'] ?? '';
        $verificationData->email = $params['email'] ?? '';
        $verificationData->expire = (int)($params['expire'] ?? 0);
        $verificationData->fields = explode(',', $params['fields'] ?? '');
        $verificationData->fieldsHash = $params['fieldsHash'] ?? '';
        $verificationData->reasons = explode(',', $params['reasons'] ?? '');
        $verificationData->score = (float)($params['score'] ?? 0);
        $verificationData->time = (int)($params['time'] ?? 0);
        $verificationData->verified = ($params['verified'] ?? 'false') === 'true';

        $now = time();
        $isVerified = $payload->verified && $verificationData->verified &&
            $verificationData->expire > $now &&
            $payload->signature === $expectedSignature;

        return [$isVerified, $verificationData];
    }

    public static function solveChallenge($challenge, $salt, $algorithm, $max = 1000000, $start = 0)
    {
        $startTime = microtime(true);

        for ($n = $start; $n <= $max; $n++) {
            $hash = self::hashHex($algorithm, $salt . $n);
            if ($hash === $challenge) {
                $took = microtime(true) - $startTime;
                return new Solution($n, $took);
            }
        }

        return null;
    }
}

class Solution
{
    public $number;
    public $took;

    public function __construct($number, $took)
    {
        $this->number = $number;
        $this->took = $took;
    }
}

class Payload
{
    public $algorithm;
    public $challenge;
    public $number;
    public $salt;
    public $signature;

    public function __construct($algorithm, $challenge, $number, $salt, $signature)
    {
        $this->algorithm = $algorithm;
        $this->challenge = $challenge;
        $this->number = $number;
        $this->salt = $salt;
        $this->signature = $signature;
    }
}
