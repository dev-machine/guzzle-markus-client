<?php

namespace Devmachine\Tests\Guzzle\Markus;

use Devmachine\Guzzle\Markus\MarkusClient;
use Devmachine\Guzzle\Markus\MarkusDescription;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Subscriber\Mock;

class MarkusClientTest extends \PHPUnit_Framework_TestCase
{
    /** @var MarkusClient */
    private $client;

    /** @var Mock */
    private $mock;

    /** @var History */
    private $history;

    public function setUp()
    {
        $description = new MarkusDescription('http://forumcinemas.lv/xml');
        $this->mock = new Mock();
        $this->history = new History();
        $this->client = new MarkusClient(new Client(), $description);
        $this->client->getHttpClient()->getEmitter()->attach($this->mock);
        $this->client->getHttpClient()->getEmitter()->attach($this->history);
    }

    public function testAreas()
    {
        $result = $this->getClient('areas')->areas();

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);

        $this->assertEquals(1, $result['items'][0]['id']);
        $this->assertEquals('Markus Cinema One', $result['items'][0]['name']);

        $this->assertEquals(2, $result['items'][1]['id']);
        $this->assertEquals('Markus Cinema Two', $result['items'][1]['name']);
    }

    public function testLanguages()
    {
        $result = $this->getClient('languages')->languages();

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(3, $result['items']);

        $this->assertEquals(2, $result['items'][1]['id']);
        $this->assertEquals('Russian', $result['items'][1]['name']);
        $this->assertEquals('Krievu', $result['items'][1]['local_name']);
        $this->assertEquals('Krievu valodā', $result['items'][1]['original_name']);
        $this->assertEquals('ru', $result['items'][1]['code']);
        $this->assertEquals('rus', $result['items'][1]['three_letter_code']);
    }

    /**
     * Impossible to test with Mock subscriber. Fix with RingPHP mock handler.
     */
    public function xtestSchedule()
    {
        $this->mock->addResponse(function (TransactionInterface $transaction) {
            $query = $transaction->getRequest()->getQuery();

            if ($query->hasKey('area')) {
                return $this->createResponse('schedule_'.$query->get('area'));
            }

            return $this->createResponse('schedule');
        });

        // Returns result with 2 items.
        $result = $this->client->schedule(['area' => 1000]);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);
        $this->assertEquals('2014-05-01', $result['items'][0]);
        $this->assertEquals('2014-05-10', $result['items'][1]);

        // Returns result with 7 items.
        $result = $this->client->schedule();

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(7, $result['items']);
    }

    public function testArticleCategories()
    {
        $result = $this->getClient('article_categories')->articleCategories();

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(3, $result['items']);

        $this->assertEquals(1005, $result['items'][0]['id']);
        $this->assertEquals('Filmu ziņas', $result['items'][0]['name']);
        $this->assertEquals(10, $result['items'][0]['article_count']);
    }

    public function testArticles()
    {
        $result = $this->getClient('articles')->articles();

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(3, $result['items']);

        $this->assertEquals('2014-03-26', $result['items'][2]['published']);
        $this->assertEquals('UUS EESTI MÄNGUFILM "RISTTUULES" SINU KINOS', $result['items'][2]['title']);
        $this->assertEquals('Alates tänasest ootame kõiki vaatama uut eesti mängufilmi RISTTUULES. Martti Helde lavastatud mängufilm räägib eestlasi tabanud kuritööst linastub alates 26.03 Tallinnas ja Tartus ning alates 27.03 Narvas.', $result['items'][2]['abstract']);
        $this->assertStringStartsWith('<p style="text-align: justify;">See on film, mis taastab visuaalselt igaveseks', $result['items'][2]['content']);
        $this->assertEquals(299564, $result['items'][2]['event']);
        $this->assertEquals('http://www.forumcinemas.ee/News/MovieNews/2014-03-26/1889/UUS-EESTI-MANGUFILM-RISTTUULES-SINU-KINOS/', $result['items'][2]['url']);
        $this->assertEquals('http://media.forumcinemas.ee/1000/news/1889/risttuules_poster_valge.png', $result['items'][2]['image_url']);
        $this->assertEquals('http://media.forumcinemas.ee/1000/news/1889/THUMB_risttuules_poster_valge.png', $result['items'][2]['thumbnail_url']);

        $this->assertCount(2, $result['items'][2]['categories']);
        $this->assertEquals(1005, $result['items'][2]['categories'][0]['id']);
        $this->assertEquals('Filmimaailm', $result['items'][2]['categories'][0]['name']);
        $this->assertEquals(1002, $result['items'][2]['categories'][1]['id']);
        $this->assertEquals('Kinoklubi', $result['items'][2]['categories'][1]['name']);
    }

    public function testArticlesWithArguments()
    {
        $this->getClient('articles')->articles([
            'area' => 1,
            'event' => 2,
            'category' => 3,
            'dummy' => 'any_value',
        ]);

        // Test query parameters where actually sent.
        $this->assertEquals(['area', 'eventID', 'categoryID'], $this->history->getLastRequest()->getQuery()->getKeys());
    }

    public function testEventsWithDefaultArguments()
    {
        $this->getClient('events')->events();

        $this->assertEquals([
            'includeVideos' => 'false',
            'includeLinks' => 'false',
            'includeGallery' => 'false',
            'includePictures' => 'false',
            'listType' => 'NowInTheatres',
        ], $this->history->getLastRequest()->getQuery()->toArray());
    }

    public function testUpcomingEvents()
    {
        $this->getClient('events')->events(['coming_soon' => true]);

        $this->assertTrue($this->history->getLastRequest()->getQuery()->hasKey('listType'));
        $this->assertEquals('ComingSoon', $this->history->getLastRequest()->getQuery()->get('listType'));
    }

    public function testEvents()
    {
        $result = $this->getClient('events')->events();

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(3, $result['items']);

        $this->assertEquals(301312, $result['items'][0]['id']);
        $this->assertEquals('3 dienas, lai nogalinātu', $result['items'][0]['title']);
        $this->assertEquals('3 Days to Kill', $result['items'][0]['original_title']);
        $this->assertEquals(2014, $result['items'][0]['year']);
        $this->assertEquals(113, $result['items'][0]['length']);
        $this->assertEquals('2014-04-18', $result['items'][0]['release_date']);
        $this->assertEquals('12+', $result['items'][0]['rating']['name']);
        $this->assertEquals('Līdz 12 g.v. -  neiesakām', $result['items'][0]['rating']['description']);
        $this->assertEquals('http://forumcinemaslv.blob.core.windows.net/images/rating_large_12+.png', $result['items'][0]['rating']['image_url']);
        $this->assertEquals('Relativity Media', $result['items'][0]['production']);
        $this->assertEquals('BestFilm.eu OÜ', $result['items'][0]['distributor']['local_name']);
        $this->assertEquals('BestFilm.eu OÜ', $result['items'][0]['distributor']['global_name']);
        $this->assertEquals('Movie', $result['items'][0]['type']);
        $this->assertEquals(['Drāma', 'Detektīvfilma', 'Asa sižeta filma'], $result['items'][0]['genres']);
        $this->assertStringStartsWith('Ītanam Ranneram jau sen nav nevienam jāpierāda, ka ir viens no labākajiem CIP', $result['items'][0]['abstract']);
        $this->assertStringStartsWith('Liks Besons piedāvā kriminālo trilleri', $result['items'][0]['synopsis']);
        $this->assertEquals('http://www.forumcinemas.lv/Event/301312/', $result['items'][0]['url']);

        $this->assertEquals([
            ['title' => '', 'url' => 'm3XIuNdF9XY', 'thumbnail_url' => '', 'type' => 'EventTrailer', 'format' => 'YouTubeVideo'],
        ], $result['items'][0]['videos']);

        $this->assertEquals([
            ['title' => 'IMDB', 'url' => 'http://www.imdb.com/title/tt2172934/', 'type' => 'General'],
            ['title' => 'Oficiālā mājas lapa', 'url' => 'http://3daystokill.tumblr.com/', 'type' => 'EventOfficialHomepage'],
            ['title' => 'Facebook', 'url' => 'https://www.facebook.com/3daystokillmovie', 'type' => 'General'],
        ], $result['items'][0]['links']);

        $this->assertEquals([
            ['title' => '', 'url' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/gallery/3DaystoKill_010.JPG', 'thumbnail_url' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/gallery/THUMB_3DaystoKill_010.JPG'],
            ['title' => '', 'url' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/gallery/3DaystoKill_011.JPG', 'thumbnail_url' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/gallery/THUMB_3DaystoKill_011.JPG'],
        ], $result['items'][0]['gallery']);

        // Images should be merged with pictures.
        $this->assertEquals([
            'micro_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/portrait_micro/20140418_3daystokill.jpg',
            'small_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/portrait_small/20140418_3daystokill.jpg',
            'large_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/portrait_large/20140418_3daystokill.jpg',
            'large_landscape' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/landscape_large/3daystk_670.jpg',
            'fullhd_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/portrait_fullhd/20140418_3daystokill.jpg',
            'hd_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/portrait_hd/20140418_3daystokill.jpg',
            'extralarge_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/portrait_xlarge/20140418_3daystokill.jpg',
            'medium_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/portrait_medium/20140418_3daystokill.jpg',
            'poster' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7619/poster/20140418_3daystokill.jpg',
        ], $result['items'][0]['images']);

        $this->assertCount(6, $result['items'][0]['actors']);
        $this->assertCount(1, $result['items'][0]['directors']);
        $this->assertEquals(['first_name' => 'Jake', 'last_name' => 'McDorman'], $result['items'][0]['actors'][2]);
        $this->assertEquals(['first_name' => 'Clint', 'last_name' => 'Eastwood'], $result['items'][0]['directors'][0]);

        $this->assertArrayNotHasKey('actors', $result['items'][1]);
        $this->assertArrayNotHasKey('directors', $result['items'][1]);
    }

    public function testShowsWithDefaultArguments()
    {
        $this->getClient('shows')->shows();

        $this->assertEquals(['nrOfDays' => 1], $this->history->getLastRequest()->getQuery()->toArray());
    }

    public function testShowsWithArguments()
    {
        $this->getClient('shows')->shows(['date' => '2014-04-05', 'days_from_date' => 12, 'area' => 1000, 'event' => 5]);

        $this->assertEquals([
            'dt' => '05.04.2014',
            'nrOfDays' => 12,
            'area' => 1000,
            'eventID' => 5,
        ], $this->history->getLastRequest()->getQuery()->toArray());
    }

    public function testShows()
    {
        $result = $this->getClient('shows')->shows();

        $this->assertArrayHasKey('published', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(4, $result['items']);

        $this->assertEquals('2014-04-27', $result['published']);

        // Main show data.
        $this->assertEquals(190912, $result['items'][0]['id']);
        $this->assertEquals('2014-04-27', $result['items'][0]['date']);
        $this->assertEquals('2014-04-27T10:15:00', $result['items'][0]['sales_end_time']);
        $this->assertEquals('2014-04-27T07:15:00Z', $result['items'][0]['sales_end_time_utc']);
        $this->assertEquals('2014-04-27T10:30:00', $result['items'][0]['start_time']);
        $this->assertEquals('2014-04-27T07:30:00Z', $result['items'][0]['start_time_utc']);
        $this->assertEquals('2014-04-27T12:26:00', $result['items'][0]['end_time']);
        $this->assertEquals('2014-04-27T09:26:00Z', $result['items'][0]['end_time_utc']);
        $this->assertEquals('http://www.forumcinemas.lv/Websales/Show/190912/', $result['items'][0]['url']);

        // Internal event.
        $this->assertEquals([
            'id' => 301296,
            'title' => 'Rio 2',
            'original_title' => 'Rio 2',
            'year' => '2014',
            'length' => 101,
            'release_date' => '2014-04-18',
            'genres' => ['Piedzīvojumu filma', 'Komēdija', 'Animācija'],
            'type' => 'Movie',
            'rating' => [
                'name' => 'U',
                'description' => 'Bez ierobežojuma',
                'image_url' => 'http://forumcinemaslv.blob.core.windows.net/images/rating_large_U.png',
            ],
            'images' => [
                'micro_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7602/portrait_micro/rio2_poster.jpg',
                'small_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7602/portrait_small/rio2_poster.jpg',
                'large_portrait' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7602/portrait_large/rio2_poster.jpg',
                'large_landscape' => 'http://forumcinemaslv.blob.core.windows.net/1012/Event_7602/landscape_large/rio2_670.jpg',
            ],
            'url' => 'http://www.forumcinemas.lv/Event/301296/',
        ], $result['items'][0]['event']);

        // Sections.
        $this->assertEquals(['id' => 1032, 'name' => 'Kino Citadele'], $result['items'][0]['theatre']);
        $this->assertEquals(['id' => 1197, 'name' => 'Auditorija 2', 'full_name' => 'Kino Citadele, Auditorija 2'], $result['items'][0]['auditorium']);
        $this->assertEquals(['method' => '3D', 'description' => '3D, Latviešu valodā'], $result['items'][0]['presentation']);
    }

    /**
     * Set mock response from file.
     *
     * @param string $fixture
     *
     * @return \Devmachine\Guzzle\Markus\MarkusClient
     */
    private function getClient($fixture)
    {
        $this->mock->addResponse($this->createResponse($fixture));

        return $this->client;
    }

    private function createResponse($fixture)
    {
        $responseXml = __DIR__.'/fixtures/'.$fixture.'.xml';

        return new Response(200, [], Stream::factory(file_get_contents($responseXml)));
    }
}
