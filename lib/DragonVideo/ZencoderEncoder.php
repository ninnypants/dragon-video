<?php
namespace DragonVideo;
use \Services_Zencoder_Exception;

class ZencoderEncoder
{

    protected $messages = array();

    protected $options = array(
        'api_key' => '',
        'watermark_url' => '',
    );

    protected $codecs = array(
        'webm' => 'vp8',
        'mp4'  => 'h264',
        'ogv'  => 'theora',
    );

    public function __construct($zencoder_class = 'Services_Zencoder')
    {
        $this->NOTIFICATION_URL = get_option('siteurl') .'/zencoder/'. get_option('zencoder_token');
        $this->options = get_option('zencoder_options', $this->options);
        $this->zencoder = new $zencoder_class($this->options['api_key']);
    }

    /**
     * Initialize WordPress hooks
     *
     * @param string $filename WordPress Plugin Filename
     * @return void
     **/
    public function pluginInit($filename)
    {
        register_activation_hook($filename, array(&$this, 'activate'));

        add_action('admin_menu', array(&$this, 'admin_menu'));

        add_action('dragon_video_encode', array(&$this, 'make_encodings'), 10, 5);

        add_filter('rewrite_rules_array', array(&$this, 'insert_rewrite_rules'));
        add_filter('query_vars',          array(&$this, 'insert_query_vars'));
        add_filter('init', 'flush_rewrite_rules');
        add_action('parse_query', array(&$this, 'do_page_redirect'));
    }

    /**
     * Plugin installation method
     */
    public function activate()
    {
        add_option('zencoder_options', $this->options, null, 'no');
        add_option('zencoder_token', md5(uniqid('', true)), null, 'no');
    }

    public function admin_menu()
    {
        add_submenu_page('dragonvideo', 'Zencoder', 'Zencoder Options', 'manage_options', 'zencoder', array(&$this, 'options_page'));
    }

    public function insert_rewrite_rules($rules)
    {
        $new_rules = array(
            'zencoder/([a-zA-Z0-9]{32})/?$' => 'index.php?za=zencoder&zk=$matches[1]',
        );

        return $new_rules + $rules;
    }

    public function insert_query_vars($vars)
    {
        array_unshift($vars, 'za', 'zk');
        return $vars;
    }

    public function do_page_redirect($wp_query)
    {
        if (isset($wp_query->query_vars['za'])) {
            switch ($wp_query->query_vars['za']) {
                case 'zencoder':
                    $this->handle_incoming_video($wp_query->query_vars['zk']);
                    break;
            }
            exit;
        }
    }

    protected function handle_incoming_video($token)
    {
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $savedtoken = get_option('zencoder_token');

            if ($token == $savedtoken) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                $notification = $this->zencoder->notifications->parseIncoming();
                foreach ( $notification->job->outputs as $output ) {
                    $label = explode('-', $output->label);
                    $attachment_id = $label[0];
                    $format = $label[1];
                    $size = $label[2];

                    $metadata = wp_get_attachment_metadata($attachment_id);

                    $file = get_attached_file($attachment_id);
                    $info = pathinfo($file);
                    $dir = $info['dirname'];

                    $destfilename = "{$dir}/{$metadata['sizes'][$size]['file'][$format]}";

                    $tmp = download_url($output->url);
                    rename($tmp, $destfilename);
                    echo "Saved $output->label to $destfilename\n";

                    if ( empty($metadata['sizes'][$size]['poster']) ) {
                        $info = pathinfo($metadata['sizes'][$size]['file'][$format]);
                        $name = $info['filename'];
                        $posters = array();
                        $number = 0;
                        foreach ( $output->thumbnails[0]->images as $thumbnail ) {
                            $url = $thumbnail->url;
                            $thumb = download_url($url);
                            $ext = strtolower($thumbnail->format);

                            $filename = "{$name}-{$number}.{$ext}";
                            $destfilename = "{$dir}/{$filename}";
                            rename($thumb, $destfilename);
                            $posters[] = $filename;
                            $number++;
                            echo "Saved poster $destfilename\n";
                        }

                        $metadata['sizes'][$size]['poster'] = $posters[0];
                        $metadata['sizes'][$size]['posters'] = $posters;
                        wp_update_attachment_metadata($attachment_id, $metadata);
                    }
                }
            }
        } else {
            echo '<strong>ERROR:</strong> no direct access';
        }
    }

    function make_encodings($file, $attachment_id, $sizes)
    {
        $file = str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $file);
        // New Encoding Job
        $job = array(
            'input' => $file,
            'output' => array(),
        );
        foreach ( $sizes as $size => $meta ) {
            foreach ( DragonVideo::encode_formats() as $format ) {
                $label = "{$attachment_id}-{$format}-{$size}";
                $output = array(
                    'label' => $label,
                    'video_codec' => $this->codecs[$format],
                    'width' => $meta['width'],
                    'notifications' => array(
                        $this->NOTIFICATION_URL,
                    ),
                    'thumbnails' => array(
                        'number' => 2,
                        'label' => $label,
                    ),
                );
                if ( !empty($this->options['watermark_url']) ) {
                    $output = array_merge($output, array(
                        'watermark' => array(
                            'url' => $this->options['watermark_url'],
                            'width' => '50%',
                        ),
                    ));
                }
                $job['output'][] = $output;
            }
        }

        try {
            $this->zencoder->jobs->create($job);
            return true;
        } catch (Services_Zencoder_Exception $e) {
            return false;
        }
    }

    /**
     * Display options page
     */
    public function options_page()
    {
        // if user clicked "Save Changes" save them
        if ( isset($_POST['Submit']) ) {
            foreach ( $this->options as $option => $value ) {
                if ( array_key_exists($option, $_POST) ) {
                    $this->options[$option] = $_POST[$option];
                }
            }
            update_option('zencoder_options', $this->options);

            $this->messages['updated'][] = 'Options updated!';
        }

        foreach ( $this->messages as $namespace => $messages ) {
            foreach ( $messages as $message ) { ?>
                <div class="<?php echo $namespace; ?>">
                    <p>
                        <strong><?php echo $message; ?></strong>
                    </p>
                </div>
                <?php
            }
        } ?>
<div class="wrap">
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2>Zencoder Settings</h2>
    <form method="post" action="">
        <div id="watermark_text" class="watermark_type">
            <table class="form-table" style="clear:none; width:auto;">
                <tr>
                    <th>API Key</th>
                    <td>
                        <input type="text" size="40" name="api_key" value="<?php echo $this->options['api_key']; ?>" /><br />
                        <span class="description">API key for zencoder.com</span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Watermark URL:</th>
                    <td>
                        <input type="text" size="40" name="watermark_url" value="<?php echo $this->options['watermark_url']; ?>" /><br />
                        <span class="description">URL to image to apply as watermark</span>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="Save Changes" />
        </p>

    </form>
</div>
<?php
    }
}
