<?php

class WP_Queues
{
    /**
      * Give your WP_Queues a unique identifier
      * helps isolate filters and options
      */
    public $unique = 'my_schedules';

    /**
      * WP_Queues at run time register actions from
      * a certain range. Define this range by giving 
      * the start number and end number
      *
      * The point from this is not to have 2 events
      * conflict with each other, while we assign a
      * random number (from your range) to their
      * hook and meta.
      */
    public $rand_min = 1;
    
    /**
      * WP_Queues at run time register actions from
      * a certain range. Define this range by giving 
      * the start number and end number
      *
      * The point from this is not to have 2 events
      * conflict with each other, while we assign a
      * random number (from your range) to their
      * hook and meta.
      */
    public $rand_max = 100;
    
    /**
      * You can still specify the number of seconds
      * after which the event will be executed, but
      * for a default value we make it run 5 seconds
      * after fired.
      */
    public $after_seconds = 5;

    /**
      * By default, wp crons are meant to be
      * run in a repeated way with a certain
      * interval. But, wp_queues will only run
      * on first job and then stops. Defaults
      * to 60 seconds in case the event occurs
      * an error at first run
      */
    public $cron_interval = 60;

    /**
      * Regular expression patterns to identify
      * an event from its tag name, and call
      * its own callback at run
      */
    public $patterns = array();

    /**
      * class init
      *
      * You must hook this method to an early hook
      * like plugins_loaded or init
      */
    public function init()
    {
        add_filter('cron_schedules', array($this, 'pushCronInterval'));

        foreach ( range($this->rand_min, $this->rand_max) as $i ) {
            add_action("wp_queues_{$this->unique}_schedules_{$i}", array($this, 'events'));
        }
    }

    /**
      * Schedule an event
      *
      * @param $identifier a name for the event
      * @param $seconds optional, run event after x seconds, default $this->after_seconds
      */
    public function schedule($identifier, $seconds=null)
    {
        $i = rand($this->rand_min, $this->rand_max);
        $tag = "wp_queues_{$this->unique}_schedules_{$i}";

        if ( !intval($seconds) ) {
            $seconds = $this->after_seconds;
        }

        if( !wp_next_scheduled( $tag ) ) {
            wp_schedule_event( time() + $seconds, 'wp_queues_interval', $tag );  
        }

        $opt = "wp_queues_{$this->unique}_schedules_{$i}";
        $schedules = (array) get_site_option($opt, null);
        $schedules[] = $identifier;
        $schedules = array_filter($schedules);
        $schedules = array_unique($schedules);
        // save
        update_site_option($opt, $schedules);

        return $this;
    }

    /**
      * Add custom interval to cron schedules
      */
    function pushCronInterval($vals)
    {
        return array_merge(array(
            'wp_queues_interval' => array(
                'interval' => $this->cron_interval,
                'display' => __('WP_Queues interval', 'wp-queues')
            )
        ), $vals);
    }

    /**
      * Hooked into the cron job to fetch for
      * events based on the action hook name
      */
    public function events()
    {
        $tag = current_filter();
        $id = (int) str_replace("wp_queues_{$this->unique}_schedules_", '', $tag);

        if ( !$id )
            return;

        $opt = "wp_queues_{$this->unique}_schedules_{$id}";
        $schedules = (array) get_site_option($opt, null);

        if ( !$schedules ) {
            delete_site_option($opt);
        } else {
            foreach ( $schedules as $id ) {
                // core process
                $this->run($id);
            }
        }

        // delete
        delete_site_option($opt);

        // clear out
        wp_unschedule_event( wp_next_scheduled( $tag ), $tag );
    }

    /**
      * Get a list of regex patterns
      * 
      * Filter with wp_queues_event_patterns
      * or set class var $patterns with regex
      */ 
    public function patterns()
    {
        $this->patterns = apply_filters('wp_queues_event_patterns', $this->patterns);

        return $this->patterns;
    }

    /**
      * Run an event based on its name
      *
      * uses regex to match the event name with
      * existing callbacks
      *
      * @uses patterns to get avail regex patterns
      */
    public function run($id)
    {
        $patterns = $this->patterns();

        if ( !$patterns )
            return;

        foreach ( $patterns as $p=>$c ) {
            preg_match($p, $id, $args);
            if ( $args ) {
                if ( isset($args[1]) ) {
                    $args = array_slice($args, 1);
                }
                if ( is_callable($c) ) {
                    call_user_func_array($c, $args);
                }
            }
        }
    }

    /**
      * Clear all pending cron jobs upon deactivation
      * 
      * This process is a must.
      */
    public function deactivation()
    {
        foreach ( range($this->rand_min, $this->rand_max) as $i ) {
            $tag = "wp_queues_{$this->unique}_schedules_{$i}";
            wp_clear_scheduled_hook($tag);
        }
    }

    /**
      * Clean up orphaned options from database 
      *
      * @return number of deleted options
      */
    public function cleanup()
    {
        global $wpdb;

        return $wpdb->query("DELETE FROM {$wpdb->options} WHERE `option_name` LIKE 'wp_queues_{$this->unique}_%'");
    }
}