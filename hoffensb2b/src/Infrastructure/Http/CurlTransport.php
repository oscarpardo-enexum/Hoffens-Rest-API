<?php

namespace Hoffens\B2B\Infrastructure\Http;

use Hoffens\B2B\Configuration\IntegrationConfig;
use Hoffens\B2B\Contract\HttpTransportInterface;
use Hoffens\B2B\Exception\ApiException;
use Hoffens\B2B\Http\HttpRequest;
use Hoffens\B2B\Http\HttpResponse;

final class CurlTransport implements HttpTransportInterface
{
    private $config;

    public function __construct(IntegrationConfig $config)
    {
        $this->config = $config;
    }

    public function send(HttpRequest $request)
    {
        return $this->sendWithRetries($request, $this->config->retries());
    }

    private function sendWithRetries(HttpRequest $request, $maxRetries)
    {
        $lastException = null;
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->sendOnce($request);
                if (!$this->isTransient($response->statusCode()) || $attempt === $maxRetries) {
                    return $response;
                }
            } catch (ApiException $exception) {
                $lastException = $exception;
                if ($attempt === $maxRetries) {
                    throw $exception;
                }
            }
            usleep((int) (100000 * pow(2, $attempt)) + mt_rand(0, 50000));
        }
        throw $lastException ?: new ApiException('Falló la solicitud REST.');
    }

    private function sendOnce(HttpRequest $request)
    {
        $curl = $this->createHandle($request);

        $body = curl_exec($curl);
        if ($body === false) {
            $message = curl_error($curl);
            curl_close($curl);
            throw new ApiException('Error de transporte REST: ' . $message);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $metrics = $this->metricsForHandle($curl, $body);
        curl_close($curl);

        return new HttpResponse($statusCode, array(), $body, $metrics);
    }

    public function sendMany(array $requests)
    {
        if (count($requests) === 0) {
            return array();
        }

        $multi = curl_multi_init();
        $handles = array();
        foreach ($requests as $key => $request) {
            if (!$request instanceof HttpRequest) {
                throw new ApiException('Todas las solicitudes deben ser HttpRequest.');
            }
            $handle = $this->createHandle($request);
            $handles[$key] = $handle;
            curl_multi_add_handle($multi, $handle);
        }

        $running = null;
        $transferErrors = array();
        do {
            $status = curl_multi_exec($multi, $running);
            while ($info = curl_multi_info_read($multi)) {
                if ($info['result'] !== CURLE_OK) {
                    foreach ($handles as $key => $candidate) {
                        if ($candidate === $info['handle']) {
                            $transferErrors[$key] = function_exists('curl_strerror')
                                ? curl_strerror($info['result'])
                                : 'cURL error ' . $info['result'];
                            break;
                        }
                    }
                }
            }
            if ($running) {
                if (curl_multi_select($multi, 1.0) === -1) {
                    usleep(10000);
                }
            }
        } while ($running && $status === CURLM_OK);

        if ($status !== CURLM_OK) {
            $this->closeMany($multi, $handles);
            throw new ApiException('Falló la ejecución concurrente de cURL.');
        }

        $responses = array();
        foreach ($handles as $key => $handle) {
            $error = isset($transferErrors[$key]) ? $transferErrors[$key] : curl_error($handle);
            if ($error !== '') {
                $this->closeMany($multi, $handles);
                throw new ApiException('Error de transporte REST concurrente: ' . $error);
            }
            $responses[$key] = new HttpResponse(
                (int) curl_getinfo($handle, CURLINFO_HTTP_CODE),
                array(),
                curl_multi_getcontent($handle),
                $this->metricsForHandle($handle, curl_multi_getcontent($handle))
            );
        }
        $this->closeMany($multi, $handles);

        // Solo los fallos transitorios se reintentan; el camino exitoso no agrega latencia.
        foreach ($responses as $key => $response) {
            if ($this->config->retries() > 0 && $this->isTransient($response->statusCode())) {
                $responses[$key] = $this->sendWithRetries($requests[$key], $this->config->retries() - 1);
            }
        }
        return $responses;
    }

    private function createHandle(HttpRequest $request)
    {
        if (!$this->config->hasToken()) {
            throw new ApiException('El token REST aún no está configurado.');
        }
        $curl = curl_init($this->config->baseUrl() . $request->path());
        if ($curl === false) {
            throw new ApiException('No fue posible inicializar cURL.');
        }
        $headers = array(
            'Accept: application/json',
            'Accept-Encoding: gzip',
            'Authorization: Bearer ' . $this->config->token(),
        );
        foreach ($request->headers() as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        curl_setopt_array($curl, array(
            CURLOPT_CUSTOMREQUEST => $request->method(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout(),
            CURLOPT_TIMEOUT => $this->config->timeout(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        if ($request->body() !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $request->body());
        }
        return $curl;
    }

    private function closeMany($multi, array $handles)
    {
        foreach ($handles as $handle) {
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
    }

    private function isTransient($statusCode)
    {
        return in_array((int) $statusCode, array(429, 502, 503, 504), true);
    }

    private function metricsForHandle($handle, $body)
    {
        return array(
            'totalMs' => (int) round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000),
            'startTransferMs' => (int) round(curl_getinfo($handle, CURLINFO_STARTTRANSFER_TIME) * 1000),
            'downloadBytes' => (int) curl_getinfo($handle, CURLINFO_SIZE_DOWNLOAD),
            'decodedBytes' => strlen((string) $body),
        );
    }
}
