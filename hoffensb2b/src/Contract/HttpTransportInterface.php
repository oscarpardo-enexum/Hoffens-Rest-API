<?php

namespace Hoffens\B2B\Contract;

use Hoffens\B2B\Http\HttpRequest;
use Hoffens\B2B\Http\HttpResponse;

interface HttpTransportInterface
{
    /** @return HttpResponse */
    public function send(HttpRequest $request);

    /**
     * Envía solicitudes concurrentes y conserva las claves de entrada.
     *
     * @param HttpRequest[] $requests
     * @return HttpResponse[]
     */
    public function sendMany(array $requests);
}
