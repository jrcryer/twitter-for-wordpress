<?php

/*
Plugin Name: Simple Twitter for Wordpress
Version: 1.0
Plugin URI: http://www.twitter.com/jrcryer
Description: Displays your public Twitter messages for all to read.
Author: James Cryer
Author URI: http://www.twitter.com/jrcryer
*/
define('MAGPIE_CACHE_ON', 1); //2.7 Cache Bug
define('MAGPIE_CACHE_AGE', 180);
define('MAGPIE_INPUT_ENCODING', 'UTF-8');
define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

class TwitterFeed {

    public function __contruct() {}

    /**
     * Extracts the feed items from Twitter
     *
     * @param string $username
     * @param integer $feedItemsNum
     * @param bool $encode
     * @param bool $extractLinks
     * @param bool $extractUsers
     * @return array
     */
    public function getFeedItems($username, $feedItemsNum = 5, $encode = true, $extractLinks = true, $extractUsers = true) {
        $aMessage = $this->getTwitterMessages($username, $feedItemsNum);

        if(empty($aMessage)) {
            return array();
        }
        $aMessage = $this->parseMessages($aMessage, $encode, $extractLinks, $extractUsers);
        return $aMessage;
    }
    
    /**
     * Returns the messages from the supplied users limited by the number
     * of items to display
     *
     * @param string $username
     * @param int $num
     * @return array
     */
    protected function getTwitterMessages($username, $num = 5) {
        include_once(ABSPATH . WPINC . '/rss.php');
        $aMessage = fetch_rss('http://api.twitter.com/1/statuses/user_timeline'.$username.'.rss');

        if(empty($aMessage)) {
            return array();
        }
        $aMessage = array_slice($aMessage->items, 0, $num);
        return $aMessage;
    }

    /**
     * Encodes all message content with UTF-8 encoding
     *
     * @param array $aMessage
     * @return array
     */
    protected function parseMessages($aMessage, $encode, $extractLinks, $extractUsers) {
        $aParsedMsg = array();

        foreach($aMessage as $item) {
            $content = " ".substr(strstr($item['description'],': '), 2, strlen($item['description']))." ";

            if($encode) {
                $content = utf8_encode($content);
            }

            if($extractLinks) {
                $content = $this->extractHyperlinks($content);
            }

            if($extractUsers) {
                $content = $this->extractUsers($content);
            }
            $item['description'] = $content;
            $item['date-posted'] = $this->getMessageTimestamp($item['pubdate']);
            $aParsedMsg[] = $item;
        }
        return $aParsedMsg;
    }

    /**
     * Returns the message time stamp based on the publishDate
     *
     * @param int $publishDate
     * @return string
     */
    protected function getMessageTimestamp($publishDate) {

        $h_time      = null;
        $time        = strtotime($publishDate);
        if ( ( abs( time() - $time) ) < 86400 ) {
            $h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
        }else {
            $h_time = date(__('Y/m/d'), $time);
        }
        return sprintf( __('%s', 'twitter-for-wordpress'),' <span class="twitter-timestamp"><abbr title="' . date(__('Y/m/d H:i:s'), $time) . '">' . $h_time . '</abbr></span>' );
    }

    /**
     * Extract the links from the messages
     *
     * @param string $text
     * @return string
     */
    private function extractHyperlinks($text) {
        // Props to Allen Shaw & webmancers.com
        // match protocol://address/path/file.extension?some=variable&another=asf%
        //$text = preg_replace("/\b([a-zA-Z]+:\/\/[a-z][a-z0-9\_\.\-]*[a-z]{2,6}[a-zA-Z0-9\/\*\-\?\&\%]*)\b/i","<a href=\"$1\" class=\"twitter-link\">$1</a>", $text);
        $text = preg_replace('/\b([a-zA-Z]+:\/\/[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"$1\" class=\"twitter-link\">$1</a>", $text);
        // match www.something.domain/path/file.extension?some=variable&another=asf%
        //$text = preg_replace("/\b(www\.[a-z][a-z0-9\_\.\-]*[a-z]{2,6}[a-zA-Z0-9\/\*\-\?\&\%]*)\b/i","<a href=\"http://$1\" class=\"twitter-link\">$1</a>", $text);
        $text = preg_replace('/\b(?<!:\/\/)(www\.[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"http://$1\" class=\"twitter-link\">$1</a>", $text);

        // match name@address
        $text = preg_replace("/\b([a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]*\@[a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]{2,6})\b/i","<a href=\"mailto://$1\" class=\"twitter-link\">$1</a>", $text);
        //mach #trendingtopics. Props to Michael Voigt
        $text = preg_replace('/([\.|\,|\:|\�|\�|\>|\{|\(]?)#{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/#search?q=$2\" class=\"twitter-link\">#$2</a>$3 ", $text);
        return $text;
    }

    /**
     * Extract the user from the messages
     *
     * @param string $text
     * @return string
     */
    private function extractUsers($text) {
        $text = preg_replace('/([\.|\,|\:|\�|\�|\>|\{|\(]?)@{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/$2\" class=\"twitter-user\">@$2</a>$3 ", $text);
        return $text;
    }
}

class TwitterWidget extends WP_Widget {

    public function TwitterWidget() {
        parent::WP_Widget(false, $name = 'TwitterWidget');
    }

    public function form($instance) {
        $title          = esc_attr($instance['title']);
        $username       = esc_attr($instance['username']);
        $number         = esc_attr($instance['num']);
        $update         = esc_attr($instance['update']);
        $linked         = esc_attr($instance['linked']);
        $hyperlinks     = esc_attr($instance['hyperlinks']);
        $twitter_users  = esc_attr($instance['twitter_users']);
        $encode         = esc_attr($instance['encode_utf8']);

        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?>
                <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('Twitter username:'); ?>
                <input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" name="<?php echo $this->get_field_name('username'); ?>" type="text" value="<?php echo $username; ?>" />
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('num'); ?>"><?php _e('Number of tweets:'); ?>
                <input class="widefat" id="<?php echo $this->get_field_id('num'); ?>" name="<?php echo $this->get_field_name('num'); ?>" type="text" value="<?php echo $number; ?>" />
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('update'); ?>"><?php _e('Show date posted:'); ?>
                <input id="<?php echo $this->get_field_id('update'); ?>" name="<?php echo $this->get_field_name('update'); ?>" type="checkbox" <?php echo $update? 'checked="chcked"' : ''; ?> />
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('hyperlinks'); ?>"><?php _e('Discover hyperlinks:'); ?>
                <input id="<?php echo $this->get_field_id('hyperlinks'); ?>" name="<?php echo $this->get_field_name('hyperlinks'); ?>" type="checkbox" <?php echo $hyperlinks ? 'checked="checked"' : ''; ?> />
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('twitter_users'); ?>"><?php _e('Discover @replies:'); ?>
                <input id="<?php echo $this->get_field_id('twitter_users'); ?>" name="<?php echo $this->get_field_name('twitter_users'); ?>" type="checkbox" <?php echo $twitter_users ? 'checked="checked"' : ''; ?> />
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('encode_utf8'); ?>"><?php _e('UTF8 Encode:'); ?>
                <input id="<?php echo $this->get_field_id('encode_utf8'); ?>" name="<?php echo $this->get_field_name('encode_utf8'); ?>" type="checkbox" <?php echo $encode ? 'checked="checked"' : ''; ?> />
            </label>
        </p>
        <?php
    }

    /**
     * Update a widget values
     *
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
	$instance['title']         = strip_tags($new_instance['title']);
        $instance['username']      = strip_tags($new_instance['username']);
        $instance['num']           = strip_tags($new_instance['num']);
        $instance['update']        = strip_tags($new_instance['update']);
        $instance['linked']        = strip_tags($new_instance['linked']);
        $instance['hyperlinks']    = strip_tags($new_instance['hyperlinks']);
        $instance['twitter_users'] = strip_tags($new_instance['twitter_users']);
        $instance['encode_utf8']   = strip_tags($new_instance['encode_utf8']);
        return $instance;
    }

    /**
     * Process the widget and output the content
     * 
     * @param array $args
     * @param array $instance
     * @return string
     */
    public function widget($args, $instance) {
        extract($args);

        $username               = $instance['username'];
        $num                    = $instance['num'];
        $list                   = $instance['list'];
        $update                 = $instance['update'];
        $linked                 = $instance['linked'];
        $extractLinks           = $instance['hyperlinks'];
        $extractUsers           = $instance['twitter_users'];
        $encode                 = $instance['utf8_encode'];

        if(empty($username)) {
            return false;
        }
        $oFeed    = new TwitterFeed();
        $aMessage = $oFeed->getFeedItems($username, $num, $encode, $extractLinks, $extractUsers);
        $content  = empty($aMessage) ?
                    '<p class="notice">There are no public messages.</p>' :
                    $this->generateMessageOutput($aMessage, $update);
        
        echo sprintf(
            "%s%s%s%s%s",
            $before_widget,
                $before_title,
                    $this->getTitle($instance),
                $after_title,
                $content,
            $after_widget
        );
    }

    /**
     * Returns the HTML for the title of the widget
     * 
     * @param array $instance
     * @return string
     */
    protected function getTitle($instance) {
        $title    = apply_filters('widget_title', $instance['title']);
        $username = $instance['username'];
        return sprintf(
            '<a class="twitter-title" href="http://twitter.com/%1$s" class="twitter_title_link">%2$s</a>'.
            '<a class="twitter-icon" href="http://www.twitter.com/%1$s"><img src="http://twitter-badges.s3.amazonaws.com/t_small-b.png" alt="Follow jrcryer on Twitter"/></a>',
            $username, $title
        );       
    }

    /**
     * Generates HTML list of feed items
     *
     * @param arrray $aMessage
     * @param bool $update
     * @return string
     */
    protected function generateMessageOutput($aMessage, $update = true) {
        
        $output = '<ul class="twitter">';

        foreach ( $aMessage as $item ) {
            $content  = $item['description'];
            $link     = $item['link'];

            $output .= sprintf(
                '<li class="twitter-item">%s%s</li>',
                $content,
                $update ? sprintf('<a href="%s" class="twitter-link">%s</a>', $link, $item['date-posted']) : ''
            );
        }
        $output .= '</ul>';
        return $output;
    }
}
add_action('widgets_init', create_function('', 'return register_widget("TwitterWidget");'));
