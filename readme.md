# WP Queues
Run queues and background jobs and tasks in WordPress

## Installation

Installation is simple, you include `wp-queues.php` class file in your project.

```php
if ( !class_exists('\WP_Queues') ) {
	include ( '/path/to/wp-queues/wp-queues.php' );
}
```

After that, make an instace of `WP_Queues` class, run its `init` method and its deactivation hook:

```php
// make a global instance
global $My_Queues;

// instantiate the class
$My_Queues = new \WP_Queues;

// let WP_Queues register the core actions
add_action('plugins_loaded', array($My_Queues, 'init'));

// clear all pending cron jobs
register_deactivation_hook(__FILE__, array($My_Queues, 'deactivation'));
```

And you're done!

## Usage

Here's how WP_Queues works:

1. Allows you to push a custom event to be executed after x seconds
2. Requires you to give a name for this event (e.g `welcome_users_1,2`)
3. While events are executed, it will match your event name with the available regex patterns in order to find the correct callback to process your event.

Here's a sample case: I want to notify a user, who's id is 1.

```php
global $My_Queues;

$My_Queues->schedule('custom_notify_user_1');
```

And I want this function to be used to process the event (notification)

```php
function my_custom_notify_user($user_id) {
    $user = get_userdata($user_id);

    return wp_mail(
        $user->user_email,
        "Hello @{$user->user_nicename}!",
        "Just a greetings from the site admins.\n"
    );
}
```

Good, now the event will be fired after `5` seconds (default `$My_Queues->after_seconds`). How do I tell WP_Queues to use `my_custom_notify_user` to notify the user (process the event)?

You'll want to filter the class regex patterns with the `wp_queues_event_patterns` filter. the class variable `$patterns` is an array of regular expression patterns and their callbacks, so let's register my custom callback:

```php
add_filter('wp_queues_event_patterns', function($patterns){
    return array_merge(array(
        '/^custom_notify_user_([0-9]+)$/' => 'my_custom_notify_user'
    ), $patterns);
});
```

`/^custom_notify_user_([0-9]+)$/` will match our event name `custom_notify_user_1` indeed, and pass the user id `1` as first argument to `my_custom_notify_user` callback.


<em>
	**Todo:**<br/>
	<input type="checkbox" disabled="disabled" /> more documentation
</em>