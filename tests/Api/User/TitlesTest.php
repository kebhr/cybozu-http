<?php

namespace CybozuHttp\Tests\Api\User;

use PHPUnit\Framework\TestCase;
use UserTestHelper;

use League\Csv\Reader;
use CybozuHttp\Api\UserApi;

/**
 * @author ochi51 <ochiai07@gmail.com>
 */
class TitlesTest extends TestCase
{
    /**
     * @var UserApi
     */
    private UserApi $api;

    protected function setup(): void
    {
        $this->api = UserTestHelper::getUserApi();
    }

    public function testCsv(): void
    {
        $filename = __DIR__ . '/../../_data/titles.csv';
        $id = $this->api->titles()->postByCsv($filename);
        while (1) {
            $result = $this->api->csv()->result($id);
            if (!$result['done']) {
                continue;
            }
            if ($result['success']) {
                $this->assertTrue(true);
            } else {
                self::fail($result['errorCode']);
            }
            break;
        }

        $content = $this->api->titles()->getByCsv();
        $csv = Reader::createFromString($content);
        $records = $csv->getRecords();
        $flg1 = $flg2 = false;
        foreach ($records as $row) {
            if ('example-title1' === reset($row)) {
                $flg1 = true;
            }
            if ('example-title2' === reset($row)) {
                $flg2 = true;
            }
        }
        $this->assertTrue($flg1 and $flg2);

        $filename = __DIR__ . '/../../_data/delete-titles.csv';
        $id = $this->api->titles()->postByCsv($filename);
        while (1) {
            $result = $this->api->csv()->result($id);
            if (!$result['done']) {
                continue;
            }
            if ($result['success']) {
                $this->assertTrue(true);
            } else {
                self::fail($result['errorCode']);
            }
            break;
        }
    }

}
