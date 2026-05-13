<?php
declare(strict_types=1);
namespace Lognitor;

final class CurlTransport implements TransportInterface
{
    public function send(string $url, mixed $payload, array $headers): TransportResponse
    {
        $body = is_string($payload) ? $payload : Utils::safeJsonEncode($payload);
        $allHeaders = ['Content-Type: application/json'];
        foreach ($headers as $key => $value) {
            $allHeaders[] = "$key: $value";
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return new TransportResponse(0, []);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if (!is_string($response)) {
            return new TransportResponse(0, []);
        }

        $rawHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            $parts = explode(': ', $line, 2);
            if (count($parts) === 2) {
                $parsedHeaders[strtolower($parts[0])] = $parts[1];
            }
        }

        $decodedBody = null;
        try {
            $decodedBody = json_decode($responseBody, true);
        } catch (\Throwable $e) {
            // ignore
        }

        return new TransportResponse($httpCode, $parsedHeaders, $decodedBody);
    }
}
