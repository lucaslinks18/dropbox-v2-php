<?php
    /**
     * Copyright (c) 2016 Alorel, https://github.com/Alorel
     * Licenced under MIT: https://github.com/Alorel/dropbox-v2-php/blob/master/LICENSE
     */

    namespace Alorel\Dropbox\Operations\Files;

    use Alorel\Dropbox\Operation\Files\Delete;
    use Alorel\Dropbox\Operation\Files\Search;
    use Alorel\Dropbox\Operation\Files\Upload;
    use Alorel\Dropbox\Options\Builder\SearchOptions as SO;
    use Alorel\Dropbox\Parameters\SearchMode;
    use Alorel\Dropbox\Test\DBTestCase;
    use Alorel\Dropbox\Test\NameGenerator;
    use Alorel\Dropbox\Test\TestUtil;
    use GuzzleHttp\Exception\ClientException;
    use GuzzleHttp\Promise\PromiseInterface;

    /**
     * @sleepTime  5
     * @retryCount 10
     */
    class SearchTest extends DBTestCase {
        use NameGenerator;

        private static $dir;

        /** @var  Search */
        private static $s;

        const NUM_FILES = 4;

        const PREFIX = 'foo';

        private static $deletedFname;

        static function setUpBeforeClass() {
            //Create a file name just so the cleanup can trigger
            self::genFileName();

            // Set up reusable objects
            self::$dir = '/' . self::generatorPrefix();
            self::$s = new Search();

            /** @var PromiseInterface[] $promises */
            $promises = [];
            $up = new Upload(true);

            try {
                for ($i = 1; $i <= self::NUM_FILES; $i++) {
                    $promises[] = $up->raw(self::$dir . '/' . self::PREFIX . '-' . $i . '.txt', '.');
                }

                self::$deletedFname = self::PREFIX . '-' . $i . '.txt';

                $promises[] = $up->raw(self::$dir . '/' . self::$deletedFname, '.');

                foreach ($promises as $p) {
                    $p->wait();
                }

                (new Delete())->raw(self::$dir . '/' . self::$deletedFname);
            } catch (ClientException $e) {
                TestUtil::decodeClientException($e);
                die(1);
            }
        }

        private static function getResults(SO $opts = null) {
            try {
                return json_decode(self::$s->raw('foo', self::$dir, $opts)->getBody()->getContents(), true);
            } catch (ClientException $e) {
                TestUtil::decodeClientException($e);
                die(1);
            }
        }

        function testSearchAll() {
            try {
                $r = self::getResults();

                $this->assertEquals(self::NUM_FILES, count($r['matches']));
                $this->assertEquals(self::NUM_FILES, $r['start']);
                $this->assertFalse($r['more']);
            } catch (ClientException $e) {
                TestUtil::decodeClientException($e);
                die(1);
            }
        }

        function testMaxResultsAndStart() {
            try {
                $r = self::getResults((new SO())->setMaxResults(2)->setStart(1));

                $this->assertEquals(2, count($r['matches']));
                $this->assertEquals(3, $r['start']);
                $this->assertTrue($r['more']);
            } catch (ClientException $e) {
                TestUtil::decodeClientException($e);
                die(1);
            }
        }

        function testDeleted() {
            try {
                $r = self::getResults((new SO())->setSearchMode(SearchMode::deletedFilename()));

                $this->assertEquals(1, count($r['matches']));
                $this->assertEquals(1, $r['start']);
                $this->assertFalse($r['more']);

                $match = $r['matches'][0];
                $this->assertEquals('filename', $match['match_type']['.tag']);

                $match = $match['metadata'];
                $pathDisplay = self::$dir . '/' . self::$deletedFname;

                $this->assertEquals('deleted', $match['.tag']);
                $this->assertEquals($pathDisplay, $match['path_display']);
                $this->assertEquals(strtolower($pathDisplay), $match['path_lower']);
            } catch (ClientException $e) {
                TestUtil::decodeClientException($e);
                die(1);
            }
        }
    }
