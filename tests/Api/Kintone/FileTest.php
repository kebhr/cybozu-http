<?php

namespace CybozuHttp\Tests\Api\Kintone;

use CybozuHttp\Api\Kintone\File;
use CybozuHttp\Client;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use KintoneTestHelper;

use CybozuHttp\Api\KintoneApi;

/**
 * @author ochi51 <ochiai07@gmail.com>
 */
class FileTest extends TestCase
{
    /**
     * @var KintoneApi
     */
    private KintoneApi $api;

    /**
     * @var int
     */
    private int $spaceId;

    /**
     * @var int|null
     */
    private ?int $guestSpaceId;

    /**
     * @var int
     */
    private int $appId;

    /**
     * @var int|null
     */
    private ?int $guestAppId;

    protected function setup(): void
    {
        $this->api = KintoneTestHelper::getKintoneApi();
        $this->spaceId = KintoneTestHelper::createTestSpace();
        $space = $this->api->space()->get($this->spaceId);
        $this->appId = $this->api->preview()
            ->post('test app', $this->spaceId, $space['defaultThread'])['app'];

        $this->guestSpaceId = KintoneTestHelper::createTestSpace(true);
        $guestSpace = $this->api->space()->get($this->guestSpaceId, $this->guestSpaceId);
        $this->guestAppId = $this->api->preview()
            ->post('test app', $this->guestSpaceId, $guestSpace['defaultThread'], $this->guestSpaceId)['app'];
    }

    public function testFile(): void
    {
        $filename = __DIR__ . '/../../_data/sample.js';
        $key = $this->api->file()->post($filename);

        $this->api->preview()->putCustomize($this->appId, [[
            'type' => 'FILE',
            'file' => ['fileKey' => $key]
        ]]);
        $fileKey = $this->api->preview()
            ->getCustomize($this->appId)['desktop']['js'][0]['file']['fileKey'];

        $content = $this->api->file()->get($fileKey);
        $this->assertStringEqualsFile($filename, $content);

        $key = $this->api->file()->post($filename, $this->guestSpaceId, 'sample.js');

        $this->api->preview()->putCustomize($this->guestAppId, [[
            'type' => 'FILE',
            'file' => ['fileKey' => $key]
        ]], [], [], $this->guestSpaceId);
        $fileKey = $this->api->preview()
            ->getCustomize($this->guestAppId, $this->guestSpaceId)['desktop']['js'][0]['file']['fileKey'];

        $content = $this->api->file()->get($fileKey, $this->guestSpaceId);
        $this->assertStringEqualsFile($filename, $content);
    }

    public function testFileStream(): void
    {
        $filename = __DIR__ . '/../../_data/sample.js';
        $key = $this->api->file()->post($filename);

        $this->api->preview()->putCustomize($this->appId, [[
            'type' => 'FILE',
            'file' => ['fileKey' => $key]
        ]]);
        $fileKey = $this->api->preview()
            ->getCustomize($this->appId)['desktop']['js'][0]['file']['fileKey'];

        $response = $this->api->file()->getStreamResponse($fileKey);
        $this->assertStringEqualsFile($filename, $response->getBody()->read(1024));
    }

    public function testFileStreamError(): void
    {
        $exception = new RequestException('foobar', new Request('GET', '/'));
        /** @var Client|MockObject $client */
        $client = $this->createMock(Client::class);
        $client->method('get')->willThrowException($exception);
        $file = new File($client);
        try {
            $file->getStreamResponse('');
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals('foobar', $e->getMessage());
        }

        $body = json_encode(['message' => 'simple error']);
        $response = new Response(400, ['Content-Type' => 'application/json; charset=utf-8'], $body);
        $exception = new RequestException('simple error', new Request('GET', '/'), $response);
        /** @var Client|MockObject $client */
        $client = $this->createMock(Client::class);
        $client->method('get')->willThrowException($exception);
        $file = new File($client);
        try {
            $file->getStreamResponse('');
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals('simple error', $e->getMessage());
        }
    }

    public function testMultiFile(): void
    {
        $filename = __DIR__ . '/../../_data/sample.js';
        $fileNames = array_fill(0, 10, $filename);
        $keys = $this->api->file()->multiPost($fileNames);
        $files = [];
        foreach ($keys as $key) {
            $files[] = ['type' => 'FILE', 'file' => ['fileKey' => $key]];
        }
        $this->api->preview()->putCustomize($this->appId, $files);
        $js = $this->api->preview()->getCustomize($this->appId)['desktop']['js'];
        $fileKeys = [];
        foreach ($js as $file) {
            $fileKeys[] = $file['file']['fileKey'];
        }
        $results = $this->api->file()->multiGet($fileKeys);
        $content = file_get_contents($filename);
        foreach ($results as $body) {
            $this->assertEquals($content, $body);
        }
    }

    protected function tearDown(): void
    {
        $this->api->space()->delete($this->spaceId);
        $this->api->space()->delete($this->guestSpaceId, $this->guestSpaceId);
    }
}
