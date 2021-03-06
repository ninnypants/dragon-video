<?php
use DragonVideo\VideoJsPlayer;

/**
 *
 */
class VideoJsPlayerTest extends WP_UnitTestCase
{

    public function setUp()
    {
        parent::setUp();
        $this->videojsplayer = new VideoJsPlayer();
    }

    /**
     * @covers DragonVideo\VideoJsPlayer::__construct
     */
    public function test_filter_setup()
    {
        $this->assertEquals(10, has_action('wp_enqueue_scripts', array(&$this->videojsplayer, 'enqueue_scripts')));
        $this->assertEquals(10, has_filter('dragon_video_player', array($this->videojsplayer, 'show_video')));
    }

    /**
     * @covers DragonVideo\VideoJsPlayer::enqueue_scripts
     */
    public function test_enqueue_scripts()
    {
        $this->videojsplayer->enqueue_scripts();

        $ver = get_bloginfo( 'version' );

        $expected  = "<script type='text/javascript' src='http://example.org/wp-content/plugins/dragon-video/video-js/video.js?ver=$ver'></script>\n";
        $this->assertEquals($expected, get_echo('wp_print_scripts'));

        $expected  = "<link rel='stylesheet' id='videojs-css'  href='http://example.org/wp-content/plugins/dragon-video/video-js/video-js.min.css?ver=$ver' type='text/css' media='all' />\n";
        $this->assertEquals($expected, get_echo('wp_print_styles'));
    }

    /**
     * @covers DragonVideo\VideoJsPlayer::show_video
     */
    public function test_show_video()
    {
        $video = array(
            'width'  => 720,
            'height' => 480,
            'mp4'    => 'test.mp4',
            'webm'   => 'test.webm',
            'ogv'    => 'test.ogv',
            'poster' => 'poster.jpg',
        );
        $expected = file_get_contents(TEST_FIXTURE_DIR.'/videojs.html');
        $actual = $this->videojsplayer->show_video('', $video);
        $this->assertEquals($expected, "$actual\n");
    }
}
