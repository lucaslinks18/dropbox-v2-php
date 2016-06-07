<?php
    /**
     * Copyright (c) 2016 Alorel, https://github.com/Alorel
     * Licenced under MIT: https://github.com/Alorel/dropbox-v2-php/blob/master/LICENSE
     */

    namespace Alorel\Dropbox\Operations\Files;

    use Alorel\Dropbox\Operation\Files\ListFolder\GetLatestCursor;
    use Alorel\Dropbox\Operation\Files\ListFolder\ListFolder;
    use Alorel\Dropbox\Operation\Files\ListFolder\ListFolderContinue;
    use Alorel\Dropbox\Operation\Files\Upload;
    use Alorel\Dropbox\Test\NameGenerator;
    use Alorel\Dropbox\Test\TestUtil;
    use GuzzleHttp\Exception\ClientException;

    class ListFolderTest extends \PHPUnit_Framework_TestCase {

        use NameGenerator;

        private static $dir;

        static function setUpBeforeClass() {
            self::$dir = '/' . self::generatorPrefix();
        }

        /** @large */
        function testListFolder() {
            //Upload our data

            $up = new Upload(true);
            $txt = self::genFileName('txt');
            $img = self::genFileName('jpg');

            try {
                $pr1 = $up->raw($txt, '.');
                $pr2 = $up->raw($img, fopen(TestUtil::INC_DIR . 'get-thumb.jpg', 'r'));

                $pr1->wait();
                $pr2->wait();

                $lf = json_decode((new ListFolder())->raw(self::$dir)->getBody()->getContents(), true);

                $this->assertFalse($lf['has_more']);
                $this->assertTrue(is_array($lf['entries']));
                $this->assertTrue(is_string($lf['cursor']));
                $this->assertEquals(2, count($lf['entries']));

                // /continue
                $prevCursor = $lf['cursor'];
                $lf = json_decode((new ListFolderContinue())->raw($prevCursor)->getBody()->getContents(), true);

                $this->assertFalse($lf['has_more']);
                $this->assertTrue(is_array($lf['entries']));
                $this->assertTrue(is_string($lf['cursor']));
                $this->assertEmpty($lf['entries']);
                $this->assertNotEquals($lf['cursor'], $prevCursor);
            } catch (ClientException $e) {
                d(json_decode($e->getResponse()->getBody(), true));
                die(1);
            }
        }

        function testGetLatestCursor() {
            try {
                $r = json_decode((new GetLatestCursor())->raw()->getBody()->getContents(), true);
                $this->assertEquals(['cursor'], array_keys($r));
                $this->assertTrue(is_string($r['cursor']));
            } catch (ClientException $e) {
                d(json_decode($e->getResponse()->getBody(), true));
                die(1);
            }
        }
    }
