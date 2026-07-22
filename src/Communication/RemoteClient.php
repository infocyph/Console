<?php

declare(strict_types=1);

namespace Infocyph\Console\Communication;

use Infocyph\TalkingBytes\Core\Result\CommunicationResult;
use Infocyph\TalkingBytes\Http\HttpClient;
use Infocyph\TalkingBytes\Http\HttpResponse;

final readonly class RemoteClient
{
    public function __construct(private HttpClient $client) {}

    public function download(string $url): string
    {
        $result = $this->client->get($url);
        if (!$result->successful || !$result->response instanceof HttpResponse) {
            throw new \RuntimeException($result->error ?? 'Remote download failed.');
        }

        return $result->response->body;
    }

    public function get(string $url): CommunicationResult
    {
        return $this->client->get($url);
    }
}
