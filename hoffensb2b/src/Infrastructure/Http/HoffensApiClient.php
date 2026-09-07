<?php

namespace Hoffens\B2B\Infrastructure\Http;

use Hoffens\B2B\Contract\HttpTransportInterface;
use Hoffens\B2B\Exception\ApiException;
use Hoffens\B2B\Http\HttpRequest;

final class HoffensApiClient
{
    private $transport;

    public function __construct(HttpTransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function get($path)
    {
        return $this->request(new HttpRequest('GET', $path));
    }

    public function getMany(array $paths)
    {
        $result = $this->getManyWithMetrics($paths);
        return $result['data'];
    }

    public function getManyWithMetrics(array $paths)
    {
        $requests = array();
        foreach ($paths as $key => $path) {
            $requests[$key] = new HttpRequest('GET', $path);
        }

        $responses = $this->transport->sendMany($requests);
        $decoded = array();
        $metrics = array();
        foreach ($responses as $key => $response) {
            $decoded[$key] = $this->decode($response);
            $metrics[$key] = $response->metrics();
        }
        return array('data' => $decoded, 'metrics' => $metrics);
    }

    public function post($path, array $payload, $idempotencyKey)
    {
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100
            || preg_match('/[\r\n]/', $idempotencyKey)) {
            throw new ApiException('Idempotency-Key debe contener entre 1 y 100 caracteres.');
        }

        $json = json_encode($payload);
        if ($json === false) {
            throw new ApiException('No fue posible serializar la solicitud como JSON.');
        }

        return $this->request(new HttpRequest('POST', $path, array(
            'Content-Type' => 'application/json',
            'Idempotency-Key' => $idempotencyKey,
        ), $json));
    }

    private function request(HttpRequest $request)
    {
        return $this->decode($this->transport->send($request));
    }

    private function decode($response)
    {
        $content = json_decode($response->body(), true);

        if (!$response->isSuccessful()) {
            $detail = is_array($content) && isset($content['detail'])
                ? $content['detail']
                : 'La API Hoffens respondió con un error.';
            throw new ApiException($detail, $response->statusCode());
        }

        if (!is_array($content)) {
            throw new ApiException('La API Hoffens devolvió JSON inválido.', $response->statusCode());
        }

        return $content;
    }
}
